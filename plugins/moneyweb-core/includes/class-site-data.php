<?php
/**
 * POST /moneyweb/v1/site-data
 *
 * - Builds combined Core+theme schema
 * - Validates payload against it
 * - Resolves or creates WordPress pages
 * - Sets _wp_page_template (except front-page)
 * - Sets show_on_front/page_on_front for is_front_page pages
 * - Sideloads image URLs to media library and stores attachment IDs
 * - Saves field values via update_field(), routing globals to the correct
 *   ACF field key based on `source` (core vs theme)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moneyweb_Site_Data {

    public static function handle( WP_REST_Request $request ) {
        $combined = Moneyweb_Schema::build_combined();
        if ( is_wp_error( $combined ) ) {
            return Moneyweb_Schema::wp_error_to_response( $combined );
        }

        $payload = $request->get_json_params();
        if ( ! is_array( $payload ) ) {
            return new WP_REST_Response( [
                'status'  => 'error',
                'code'    => 'invalid_json',
                'message' => 'Request body must be a JSON object',
            ], 400 );
        }

        $result = Moneyweb_Validator::validate( $payload, $combined );
        if ( ! empty( $result['errors'] ) ) {
            return new WP_REST_Response( [
                'status' => 'error',
                'code'   => 'validation_failed',
                'errors' => $result['errors'],
            ], 400 );
        }

        $warnings = $result['warnings'];
        $saved    = [
            'global' => 0,
            'pages'  => [],
        ];

        // Index globals by key so we can quickly route.
        $global_defs = [];
        foreach ( $combined['global'] as $f ) {
            if ( ! empty( $f['key'] ) ) {
                $global_defs[ $f['key'] ] = $f;
            }
        }

        // Save globals.
        $global_payload = isset( $payload['global'] ) && is_array( $payload['global'] )
            ? $payload['global']
            : [];
        foreach ( $global_payload as $key => $value ) {
            if ( ! isset( $global_defs[ $key ] ) ) {
                continue; // already in warnings
            }
            $f         = $global_defs[ $key ];
            $field_key = self::global_field_key( $f );
            $stored    = self::store_value( $field_key, $value, $f, 'option', $warnings );
            if ( $stored ) {
                $saved['global']++;
            }
        }

        // Save pages.
        $pages_payload = isset( $payload['pages'] ) && is_array( $payload['pages'] )
            ? $payload['pages']
            : [];
        foreach ( $pages_payload as $page_key => $values ) {
            if ( ! isset( $combined['pages'][ $page_key ] ) ) {
                continue; // already in warnings
            }
            $page_def = $combined['pages'][ $page_key ];
            $field_defs = [];
            if ( ! empty( $page_def['fields'] ) && is_array( $page_def['fields'] ) ) {
                foreach ( $page_def['fields'] as $f ) {
                    if ( ! empty( $f['key'] ) ) {
                        $field_defs[ $f['key'] ] = $f;
                    }
                }
            }

            $page_id = self::resolve_or_create_page( $page_key, $page_def );
            if ( is_wp_error( $page_id ) ) {
                $warnings[] = [
                    'code'    => 'page_create_failed',
                    'page'    => $page_key,
                    'message' => $page_id->get_error_message(),
                ];
                continue;
            }
            self::apply_page_settings( $page_id, $page_key, $page_def );

            $page_saved    = 0;
            $safe_page_key = Moneyweb_ACF_Builder::sanitize_key( $page_key );
            foreach ( (array) $values as $field_key_in => $value ) {
                if ( ! isset( $field_defs[ $field_key_in ] ) ) {
                    continue; // already in warnings
                }
                $f         = $field_defs[ $field_key_in ];
                $field_key = 'field_mw_' . $safe_page_key . '_' . Moneyweb_ACF_Builder::sanitize_key( $field_key_in );
                $stored    = self::store_value( $field_key, $value, $f, $page_id, $warnings );
                if ( $stored ) {
                    $page_saved++;
                }
            }
            $saved['pages'][ $page_key ] = $page_saved;
        }

        return new WP_REST_Response( [
            'status'   => 'ok',
            'saved'    => $saved,
            'warnings' => array_values( $warnings ),
        ], 200 );
    }

    /**
     * Returns the correct ACF field key for a global field, based on `source`.
     */
    private static function global_field_key( $f ) {
        $source = isset( $f['source'] ) ? (string) $f['source'] : 'theme';
        $key    = Moneyweb_ACF_Builder::sanitize_key( $f['key'] );
        if ( 'core' === $source ) {
            return 'field_mw_core_' . $key;
        }
        return 'field_mw_theme_global_' . $key;
    }

    /**
     * Resolve or create the WordPress page.
     */
    private static function resolve_or_create_page( $page_key, $page_def ) {
        $query = new WP_Query( [
            'post_type'              => 'page',
            'post_status'            => [ 'publish', 'draft', 'private', 'pending' ],
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'fields'                 => 'ids',
            'meta_query'             => [
                [
                    'key'   => MONEYWEB_CORE_PAGE_META_KEY,
                    'value' => $page_key,
                ],
            ],
        ] );
        if ( ! empty( $query->posts ) ) {
            return (int) $query->posts[0];
        }

        $slug = isset( $page_def['slug'] ) ? sanitize_title( $page_def['slug'] ) : '';
        if ( '' !== $slug ) {
            $existing = get_page_by_path( $slug, OBJECT, 'page' );
            if ( $existing instanceof WP_Post ) {
                return (int) $existing->ID;
            }
        }

        $title = isset( $page_def['title'] ) ? (string) $page_def['title'] : ucfirst( $page_key );
        $args  = [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => wp_strip_all_tags( $title ),
            'post_content' => '',
        ];
        if ( '' !== $slug ) {
            $args['post_name'] = $slug;
        }
        $new_id = wp_insert_post( $args, true );
        if ( is_wp_error( $new_id ) ) {
            return $new_id;
        }
        return (int) $new_id;
    }

    private static function apply_page_settings( $page_id, $page_key, $page_def ) {
        update_post_meta( $page_id, MONEYWEB_CORE_PAGE_META_KEY, $page_key );

        $is_front = ! empty( $page_def['is_front_page'] );
        if ( $is_front ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', (int) $page_id );
        } else {
            $template = isset( $page_def['template'] ) ? (string) $page_def['template'] : '';
            if ( '' !== $template ) {
                update_post_meta( $page_id, '_wp_page_template', $template );
            }
        }
    }

    /**
     * Sanitize + persist a single field value.
     */
    private static function store_value( $field_key, $value, $field_def, $target, &$warnings ) {
        if ( ! function_exists( 'update_field' ) ) {
            return false;
        }
        $type = isset( $field_def['type'] ) ? $field_def['type'] : 'text';

        switch ( $type ) {
            case 'text':
                $clean = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
                return (bool) update_field( $field_key, $clean, $target );

            case 'wysiwyg':
                $clean = is_scalar( $value ) ? wp_kses_post( (string) $value ) : '';
                return (bool) update_field( $field_key, $clean, $target );

            case 'number':
                if ( is_numeric( $value ) ) {
                    return (bool) update_field( $field_key, 0 + $value, $target );
                }
                return false;

            case 'true_false':
                return (bool) update_field( $field_key, (bool) $value ? 1 : 0, $target );

            case 'color':
                $clean = self::sanitize_color( $value );
                if ( '' === $clean ) {
                    return false;
                }
                return (bool) update_field( $field_key, $clean, $target );

            case 'image':
                if ( ! is_string( $value ) || '' === trim( $value ) ) {
                    return false;
                }
                $attachment_id = self::sideload_image( $value );
                if ( is_wp_error( $attachment_id ) ) {
                    $warnings[] = [
                        'code'    => 'image_sideload_failed',
                        'field'   => isset( $field_def['key'] ) ? $field_def['key'] : '',
                        'message' => $attachment_id->get_error_message(),
                    ];
                    return false;
                }
                return (bool) update_field( $field_key, (int) $attachment_id, $target );

            case 'repeater':
                if ( ! is_array( $value ) ) {
                    return false;
                }
                $sub_defs = [];
                if ( ! empty( $field_def['sub_fields'] ) && is_array( $field_def['sub_fields'] ) ) {
                    foreach ( $field_def['sub_fields'] as $sf ) {
                        if ( ! empty( $sf['key'] ) ) {
                            $sub_defs[ $sf['key'] ] = $sf;
                        }
                    }
                }
                $rows = [];
                foreach ( $value as $row ) {
                    if ( ! is_array( $row ) ) {
                        continue;
                    }
                    $clean_row = [];
                    foreach ( $row as $sub_key => $sub_val ) {
                        if ( ! isset( $sub_defs[ $sub_key ] ) ) {
                            continue;
                        }
                        $sub_def       = $sub_defs[ $sub_key ];
                        $sub_field_key = $field_key . '_' . Moneyweb_ACF_Builder::sanitize_key( $sub_key );
                        $clean_row[ $sub_field_key ] = self::clean_sub_value( $sub_val, $sub_def, $warnings );
                    }
                    if ( ! empty( $clean_row ) ) {
                        $rows[] = $clean_row;
                    }
                }
                return (bool) update_field( $field_key, $rows, $target );

            default:
                $warnings[] = [
                    'code'    => 'unsupported_type',
                    'field'   => isset( $field_def['key'] ) ? $field_def['key'] : '',
                    'message' => sprintf( 'Unsupported field type "%s" — skipped', (string) $type ),
                ];
                return false;
        }
    }

    private static function clean_sub_value( $value, $sub_def, &$warnings ) {
        $type = isset( $sub_def['type'] ) ? $sub_def['type'] : 'text';
        switch ( $type ) {
            case 'text':
                return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
            case 'wysiwyg':
                return is_scalar( $value ) ? wp_kses_post( (string) $value ) : '';
            case 'number':
                return is_numeric( $value ) ? 0 + $value : 0;
            case 'true_false':
                return (bool) $value ? 1 : 0;
            case 'color':
                return self::sanitize_color( $value );
            case 'image':
                if ( ! is_string( $value ) || '' === trim( $value ) ) {
                    return 0;
                }
                $id = self::sideload_image( $value );
                if ( is_wp_error( $id ) ) {
                    $warnings[] = [
                        'code'    => 'image_sideload_failed',
                        'field'   => isset( $sub_def['key'] ) ? $sub_def['key'] : '',
                        'message' => $id->get_error_message(),
                    ];
                    return 0;
                }
                return (int) $id;
            default:
                return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
        }
    }

    /**
     * Returns a clean #rrggbb / #rrggbbaa string or empty string.
     */
    private static function sanitize_color( $value ) {
        if ( ! is_string( $value ) ) {
            return '';
        }
        $value = trim( $value );
        if ( preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value ) ) {
            return strtolower( $value );
        }
        return '';
    }

    /**
     * Sideload an image URL to media library, return attachment ID.
     */
    private static function sideload_image( $url ) {
        $url = esc_url_raw( trim( $url ) );
        if ( '' === $url ) {
            return new WP_Error( 'invalid_url', 'Empty or invalid image URL' );
        }
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $attachment_id = media_sideload_image( $url, 0, null, 'id' );
        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }
        return (int) $attachment_id;
    }
}
