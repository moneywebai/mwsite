<?php
/**
 * POST /moneyweb/v1/site-data
 *
 * Phase 1.1 / 1.1-fix:
 * - Validates payload against combined Core+theme schema
 * - Requires both schema_version (Core API) and theme_schema_version (theme)
 * - Resolves or creates WordPress pages
 * - Sets _wp_page_template (except front-page) and show_on_front/page_on_front
 * - Sideloads image URLs to media library and stores attachment IDs
 * - store_value() reports `updated` / `unchanged` / `failed` per field; an
 *   ACF "value already identical" outcome is `unchanged`, NOT `failed`.
 *
 * This endpoint is the full initial-payload writer for a new site. Partial
 * customer edits are out of scope until a dedicated update-mode is built.
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

        $validation = Moneyweb_Validator::validate( $payload, $combined );
        if ( ! empty( $validation['errors'] ) ) {
            return new WP_REST_Response( [
                'status' => 'error',
                'code'   => 'validation_failed',
                'errors' => $validation['errors'],
            ], 400 );
        }

        $warnings = $validation['warnings'];
        $result   = [
            'global' => [ 'updated' => 0, 'unchanged' => 0, 'failed' => 0 ],
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
            $status    = self::store_value( $field_key, $value, $f, 'option', $warnings );
            self::tally( $result['global'], $status );
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

            $page_result   = [ 'updated' => 0, 'unchanged' => 0, 'failed' => 0 ];
            $safe_page_key = Moneyweb_ACF_Builder::sanitize_key( $page_key );
            foreach ( (array) $values as $field_key_in => $value ) {
                if ( ! isset( $field_defs[ $field_key_in ] ) ) {
                    continue; // already in warnings
                }
                $f         = $field_defs[ $field_key_in ];
                $field_key = 'field_mw_' . $safe_page_key . '_' . Moneyweb_ACF_Builder::sanitize_key( $field_key_in );
                $status    = self::store_value( $field_key, $value, $f, $page_id, $warnings );
                self::tally( $page_result, $status );
            }
            $result['pages'][ $page_key ] = $page_result;
        }

        return new WP_REST_Response( [
            'status'   => 'ok',
            'result'   => $result,
            'warnings' => array_values( $warnings ),
        ], 200 );
    }

    private static function tally( &$bucket, $status ) {
        if ( isset( $bucket[ $status ] ) ) {
            $bucket[ $status ]++;
        } else {
            $bucket['failed']++;
        }
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
     * Resolve or create the WordPress page (idempotent via _moneyweb_page_key).
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
     *
     * Returns one of: 'updated', 'unchanged', 'failed'.
     * `failed` means the value could not be stored; `unchanged` means it was
     * already correct in ACF — both leave the site in a consistent state.
     */
    private static function store_value( $field_key, $value, $field_def, $target, &$warnings ) {
        if ( ! function_exists( 'update_field' ) ) {
            return 'failed';
        }
        $type       = isset( $field_def['type'] ) ? $field_def['type'] : 'text';
        $field_name = Moneyweb_ACF_Builder::sanitize_key( isset( $field_def['key'] ) ? $field_def['key'] : '' );

        // Step 1: prepare the value or fail fast.
        switch ( $type ) {
            case 'text':
                $new = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
                break;
            case 'wysiwyg':
                $new = is_scalar( $value ) ? wp_kses_post( (string) $value ) : '';
                break;
            case 'number':
                if ( ! is_numeric( $value ) ) {
                    return 'failed';
                }
                $new = 0 + $value;
                break;
            case 'true_false':
                $new = (bool) $value ? 1 : 0;
                break;
            case 'color':
                $new = self::sanitize_color( $value );
                if ( '' === $new ) {
                    return 'failed';
                }
                break;
            case 'image':
                if ( ! is_string( $value ) || '' === trim( $value ) ) {
                    return 'failed';
                }
                $current_attachment = '' !== $field_name ? (int) get_field( $field_name, $target ) : 0;
                $res = self::resolve_image_attachment( $value, $current_attachment );
                if ( is_wp_error( $res ) ) {
                    $warnings[] = [
                        'code'    => 'image_sideload_failed',
                        'field'   => isset( $field_def['key'] ) ? $field_def['key'] : '',
                        'message' => $res->get_error_message(),
                    ];
                    return 'failed';
                }
                $new = (int) $res;
                // Fast path: same attachment ID as currently stored → unchanged.
                if ( $current_attachment > 0 && $current_attachment === $new ) {
                    return 'unchanged';
                }
                break;
            case 'repeater':
                if ( ! is_array( $value ) ) {
                    return 'failed';
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
                $new = $rows;
                break;
            default:
                $warnings[] = [
                    'code'    => 'unsupported_type',
                    'field'   => isset( $field_def['key'] ) ? $field_def['key'] : '',
                    'message' => sprintf( 'Unsupported field type "%s" — skipped', (string) $type ),
                ];
                return 'failed';
        }

        // Step 2: pre-check unchanged.
        // Image already used resolve_image_attachment() above (fast-path returned 'unchanged').
        // For scalars, repeater and image-as-id, compare current vs intended before writing.
        if ( '' !== $field_name && 'image' !== $type ) {
            $current = get_field( $field_name, $target );
            if ( 'repeater' === $type ) {
                $sub_defs_list = isset( $field_def['sub_fields'] ) && is_array( $field_def['sub_fields'] )
                    ? $field_def['sub_fields']
                    : [];
                $sub_prefix = $field_key . '_';
                if ( self::repeater_equivalent( $current, $new, $sub_defs_list, $sub_prefix ) ) {
                    return 'unchanged';
                }
            } elseif ( self::values_equivalent_typed( $current, $new, $type ) ) {
                return 'unchanged';
            }
        }

        // Step 3: persist.
        $ok = update_field( $field_key, $new, $target );
        if ( $ok ) {
            return 'updated';
        }

        // update_field returned falsy — distinguish unchanged from failed by re-reading.
        if ( '' === $field_name ) {
            return 'failed';
        }
        $after = get_field( $field_name, $target );

        if ( 'image' === $type ) {
            if ( (int) $after === (int) $new ) {
                return 'unchanged';
            }
            return 'failed';
        }

        // Pre-check above already established that current ≠ intended,
        // so if after equals intended now, the write succeeded — even when
        // update_field() returned false (ACF return-value quirks).
        if ( 'repeater' === $type ) {
            $sub_defs_list = isset( $field_def['sub_fields'] ) && is_array( $field_def['sub_fields'] )
                ? $field_def['sub_fields']
                : [];
            $sub_prefix = $field_key . '_';
            if ( self::repeater_equivalent( $after, $new, $sub_defs_list, $sub_prefix ) ) {
                return 'updated';
            }
            return 'failed';
        }

        if ( self::values_equivalent_typed( $after, $new, $type ) ) {
            return 'updated';
        }
        return 'failed';
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
                $id = self::resolve_image_attachment( $value, 0 );
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
     * Idempotent image resolver.
     *
     * Returns the attachment ID that should represent $url. Does not download
     * if the URL is already represented by an existing attachment on this
     * subsite. Newly-downloaded attachments get `_moneyweb_source_url` post-meta.
     *
     * @param string $url             Image URL from payload.
     * @param int    $current_attachment_id  Currently stored attachment in the field, or 0.
     * @return int|WP_Error Attachment ID.
     */
    private static function resolve_image_attachment( $url, $current_attachment_id = 0 ) {
        $url = esc_url_raw( trim( (string) $url ) );
        if ( '' === $url ) {
            return new WP_Error( 'invalid_url', 'Empty or invalid image URL' );
        }

        // 1. Current attachment already represents this URL?
        if ( $current_attachment_id > 0 ) {
            $existing = (string) get_post_meta( $current_attachment_id, '_moneyweb_source_url', true );
            if ( $existing === $url && get_post_status( $current_attachment_id ) ) {
                return $current_attachment_id;
            }
        }

        // 2. Any other attachment on this subsite with the same source URL?
        $reusable = self::find_attachment_by_source_url( $url );
        if ( $reusable > 0 ) {
            return $reusable;
        }

        // 3. Sideload and tag.
        $new_id = self::sideload_image_raw( $url );
        if ( is_wp_error( $new_id ) ) {
            return $new_id;
        }
        update_post_meta( $new_id, '_moneyweb_source_url', $url );
        return $new_id;
    }

    private static function find_attachment_by_source_url( $url ) {
        $q = new WP_Query( [
            'post_type'              => 'attachment',
            'post_status'            => 'inherit',
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'fields'                 => 'ids',
            'meta_query'             => [
                [
                    'key'   => '_moneyweb_source_url',
                    'value' => $url,
                ],
            ],
        ] );
        return ! empty( $q->posts ) ? (int) $q->posts[0] : 0;
    }

    /**
     * Raw sideload — always downloads. Use resolve_image_attachment() for
     * idempotent behavior.
     */
    private static function sideload_image_raw( $url ) {
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

    /**
     * Content-aware repeater comparison.
     *
     * $intended_rows is the internal form used for update_field(): each row's
     * keys are full ACF field keys (e.g. `field_mw_core_opening_hours_day`).
     * $current_rows is what get_field() returns: rows keyed by sub-field NAME
     * (e.g. `day`). We map between the two via the sub_def list.
     *
     * Returns true iff every row matches in count, sub-fields, and per-type
     * normalized values.
     */
    private static function repeater_equivalent( $current_rows, $intended_rows, $sub_defs_list, $sub_field_key_prefix ) {
        if ( ! is_array( $current_rows ) ) {
            $current_rows = [];
        }
        if ( ! is_array( $intended_rows ) ) {
            return false;
        }
        if ( count( $current_rows ) !== count( $intended_rows ) ) {
            return false;
        }
        if ( count( $intended_rows ) === 0 ) {
            return true;
        }

        $i = 0;
        foreach ( $intended_rows as $intended ) {
            $current = isset( $current_rows[ $i ] ) ? $current_rows[ $i ] : null;
            if ( ! is_array( $current ) || ! is_array( $intended ) ) {
                return false;
            }
            foreach ( $sub_defs_list as $sub_def ) {
                if ( empty( $sub_def['key'] ) ) {
                    continue;
                }
                $name           = (string) $sub_def['key'];
                $sanitized_name = Moneyweb_ACF_Builder::sanitize_key( $name );
                $intended_key   = $sub_field_key_prefix . $sanitized_name;
                $type           = isset( $sub_def['type'] ) ? (string) $sub_def['type'] : 'text';

                $cur_val = array_key_exists( $name, $current ) ? $current[ $name ] : null;
                $int_val = array_key_exists( $intended_key, $intended ) ? $intended[ $intended_key ] : null;

                if ( ! self::values_equivalent_typed( $cur_val, $int_val, $type ) ) {
                    return false;
                }
            }
            $i++;
        }
        return true;
    }

    /**
     * Type-aware value comparison used for both scalars and repeater sub-fields.
     */
    private static function values_equivalent_typed( $a, $b, $type ) {
        if ( 'number' === $type ) {
            return (float) $a === (float) $b;
        }
        if ( 'true_false' === $type ) {
            return (bool) $a === (bool) $b;
        }
        if ( 'image' === $type ) {
            return (int) $a === (int) $b;
        }
        if ( 'text' === $type || 'wysiwyg' === $type ) {
            return rtrim( (string) $a ) === rtrim( (string) $b );
        }
        return (string) $a === (string) $b;
    }
}
