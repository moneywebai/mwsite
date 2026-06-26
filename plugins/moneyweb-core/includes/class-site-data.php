<?php
/**
 * POST /moneyweb/v1/site-data
 *
 * - Validates payload against manifest
 * - Resolves or creates WordPress pages
 * - Sets _wp_page_template (except front-page)
 * - Sets show_on_front/page_on_front for is_front_page pages
 * - Sideloads image URLs to media library and stores attachment IDs
 * - Saves field values via update_field()
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moneyweb_Site_Data {

    public static function handle( WP_REST_Request $request ) {
        $manifest = Moneyweb_Manifest::get();
        if ( is_wp_error( $manifest ) ) {
            $data = $manifest->get_error_data();
            $code = isset( $data['status'] ) ? (int) $data['status'] : 500;
            return new WP_REST_Response( [
                'status'  => 'error',
                'code'    => $manifest->get_error_code(),
                'message' => $manifest->get_error_message(),
            ], $code );
        }

        $payload = $request->get_json_params();
        if ( ! is_array( $payload ) ) {
            return new WP_REST_Response( [
                'status'  => 'error',
                'code'    => 'invalid_json',
                'message' => 'Request body must be a JSON object',
            ], 400 );
        }

        $result = Moneyweb_Validator::validate( $payload, $manifest );
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

        // Save globals.
        $manifest_global = self::index_by_key( $manifest['global'] );
        $global_payload  = isset( $payload['global'] ) && is_array( $payload['global'] )
            ? $payload['global']
            : [];
        foreach ( $global_payload as $key => $value ) {
            if ( ! isset( $manifest_global[ $key ] ) ) {
                continue; // already in warnings
            }
            $field_def = $manifest_global[ $key ];
            $field_key = 'field_mw_global_' . Moneyweb_ACF_Builder::sanitize_key( $key );
            $stored    = self::store_value( $field_key, $value, $field_def, 'option', $warnings );
            if ( $stored ) {
                $saved['global']++;
            }
        }

        // Save pages.
        $pages_payload = isset( $payload['pages'] ) && is_array( $payload['pages'] )
            ? $payload['pages']
            : [];
        foreach ( $pages_payload as $page_key => $values ) {
            if ( ! isset( $manifest['pages'][ $page_key ] ) ) {
                continue; // already in warnings
            }
            $page_def    = $manifest['pages'][ $page_key ];
            $manifest_fields = self::index_by_key( $page_def['fields'] ?? [] );

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

            $page_saved = 0;
            $safe_page_key = Moneyweb_ACF_Builder::sanitize_key( $page_key );
            foreach ( (array) $values as $field_key_in => $value ) {
                if ( ! isset( $manifest_fields[ $field_key_in ] ) ) {
                    continue; // already in warnings
                }
                $field_def  = $manifest_fields[ $field_key_in ];
                $field_key  = 'field_mw_' . $safe_page_key . '_' . Moneyweb_ACF_Builder::sanitize_key( $field_key_in );
                $stored     = self::store_value( $field_key, $value, $field_def, $page_id, $warnings );
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
     * Resolve or create the WordPress page.
     *
     * @return int|WP_Error  page ID or error
     */
    private static function resolve_or_create_page( $page_key, $page_def ) {
        // 1. By _moneyweb_page_key meta.
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

        // 2. By slug.
        $slug = isset( $page_def['slug'] ) ? sanitize_title( $page_def['slug'] ) : '';
        if ( '' !== $slug ) {
            $existing = get_page_by_path( $slug, OBJECT, 'page' );
            if ( $existing instanceof WP_Post ) {
                return (int) $existing->ID;
            }
        }

        // 3. Create.
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

    /**
     * Sets page template + meta key + front-page settings.
     */
    private static function apply_page_settings( $page_id, $page_key, $page_def ) {
        update_post_meta( $page_id, MONEYWEB_CORE_PAGE_META_KEY, $page_key );

        $is_front = ! empty( $page_def['is_front_page'] );
        if ( $is_front ) {
            // Do NOT set _wp_page_template for front page; let WP pick front-page.php automatically.
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
     *
     * @param string                 $field_key  ACF field key
     * @param mixed                  $value
     * @param array                  $field_def  Manifest definition
     * @param int|string             $target     Post ID, 'option', or other ACF target
     * @param array                  &$warnings
     * @return bool                  true if stored
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
                        $sub_def     = $sub_defs[ $sub_key ];
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
     * Sideload an image URL to media library, return attachment ID.
     *
     * @param string $url
     * @return int|WP_Error
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

    private static function index_by_key( $fields ) {
        $out = [];
        if ( ! is_array( $fields ) ) {
            return $out;
        }
        foreach ( $fields as $f ) {
            if ( is_array( $f ) && ! empty( $f['key'] ) ) {
                $out[ $f['key'] ] = $f;
            }
        }
        return $out;
    }
}
