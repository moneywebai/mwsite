<?php
/**
 * GET /moneyweb/v1/schema
 *
 * Also provides Moneyweb_Schema::build_combined() — the single function that
 * merges Core + theme fields, validates, and annotates every field with
 * `source` and `target`. The REST handler, validator and site-data writer all
 * derive from the same combined view, so the schema/storage contract can never
 * disagree with itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moneyweb_Schema {

    /**
     * REST handler for GET /schema.
     */
    public static function handle( WP_REST_Request $request ) {
        $combined = self::build_combined();
        if ( is_wp_error( $combined ) ) {
            return self::wp_error_to_response( $combined );
        }
        return new WP_REST_Response( $combined, 200 );
    }

    /**
     * Build the combined schema (Core fields + theme fields) ready for /schema
     * response, validator and site-data writer.
     *
     * Returned shape:
     *   [
     *     'status' => 'ok',
     *     'theme' => string, 'theme_version' => string, 'schema_version' => int,
     *     'global' => [ { …field, source, target, automation? } ],
     *     'pages'  => [ page_key => { …meta, fields: [ {…field, source, target, automation?} ] } ],
     *   ]
     *
     * On collision/invalid manifest returns WP_Error with HTTP 422.
     *
     * @return array|WP_Error
     */
    public static function build_combined() {
        $manifest = Moneyweb_Manifest::get();
        if ( is_wp_error( $manifest ) ) {
            return $manifest;
        }

        $errors = [];

        // 1. Reserved-key collisions in theme.global.
        if ( ! empty( $manifest['global'] ) && is_array( $manifest['global'] ) ) {
            foreach ( $manifest['global'] as $f ) {
                if ( empty( $f['key'] ) ) {
                    continue;
                }
                if ( Moneyweb_Core_Fields::is_reserved( $f['key'] ) ) {
                    $errors[] = [
                        'code'    => 'reserved_field_key',
                        'field'   => (string) $f['key'],
                        'message' => 'Theme manifest uses a field key reserved by Moneyweb Core.',
                    ];
                }
            }
        }

        // 2. Every top-level field MUST declare automation.action.
        //    Sub_fields (repeater rows) are exempt — their meaning derives
        //    from the parent's automation.
        foreach ( self::iterate_top_level_fields( $manifest ) as $f ) {
            $action = isset( $f['automation']['action'] ) ? (string) $f['automation']['action'] : '';
            if ( '' === $action ) {
                $errors[] = [
                    'code'    => 'automation_action_missing',
                    'field'   => isset( $f['key'] ) ? (string) $f['key'] : '',
                    'message' => 'Every schema field must define automation.action.',
                ];
                continue;
            }
            if ( ! Moneyweb_Core_Fields::is_valid_automation_action( $action ) ) {
                $errors[] = [
                    'code'    => 'invalid_automation_action',
                    'field'   => isset( $f['key'] ) ? (string) $f['key'] : '',
                    'message' => sprintf( 'Unknown automation.action "%s"', $action ),
                ];
            }
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error(
                'invalid_manifest',
                'Theme manifest is invalid',
                [ 'status' => 422, 'errors' => $errors ]
            );
        }

        // 3. Build combined global (Core first, then theme extras).
        $global = [];
        foreach ( Moneyweb_Core_Fields::get_fields() as $f ) {
            $global[] = self::decorate_field( $f, 'core', 'global.' . $f['key'] );
        }
        if ( ! empty( $manifest['global'] ) && is_array( $manifest['global'] ) ) {
            foreach ( $manifest['global'] as $f ) {
                if ( empty( $f['key'] ) ) {
                    continue;
                }
                $global[] = self::decorate_field( $f, 'theme', 'global.' . $f['key'] );
            }
        }

        // 4. Pages.
        $pages = [];
        if ( ! empty( $manifest['pages'] ) && is_array( $manifest['pages'] ) ) {
            foreach ( $manifest['pages'] as $page_key => $page ) {
                if ( ! is_array( $page ) ) {
                    continue;
                }
                $entry = [
                    'title'    => isset( $page['title'] ) ? (string) $page['title'] : '',
                    'slug'     => isset( $page['slug'] ) ? (string) $page['slug'] : '',
                    'template' => isset( $page['template'] ) ? (string) $page['template'] : '',
                    'fields'   => [],
                ];
                if ( ! empty( $page['is_front_page'] ) ) {
                    $entry['is_front_page'] = true;
                }
                if ( ! empty( $page['label'] ) ) {
                    $entry['label'] = (string) $page['label'];
                }
                if ( ! empty( $page['fields'] ) && is_array( $page['fields'] ) ) {
                    foreach ( $page['fields'] as $f ) {
                        if ( empty( $f['key'] ) ) {
                            continue;
                        }
                        $target  = 'pages.' . $page_key . '.' . $f['key'];
                        $entry['fields'][] = self::decorate_field( $f, 'theme', $target );
                    }
                }
                $pages[ $page_key ] = $entry;
            }
        }

        return [
            'status'               => 'ok',
            'theme'                => (string) $manifest['theme'],
            'theme_version'        => (string) $manifest['theme_version'],
            'schema_version'       => (int) MONEYWEB_SCHEMA_VERSION,
            'theme_schema_version' => (int) $manifest['schema_version'],
            'global'               => $global,
            'pages'                => $pages,
        ];
    }

    /**
     * Adds `source` and `target` to a field, preserving optional metadata
     * (`customer_editable`, `description`, `default`, `automation`, `sub_fields`).
     */
    private static function decorate_field( $f, $source, $target ) {
        $out = [
            'key'    => (string) $f['key'],
            'target' => (string) $target,
            'source' => (string) $source,
            'type'   => isset( $f['type'] ) ? (string) $f['type'] : 'text',
        ];
        if ( ! empty( $f['required'] ) ) {
            $out['required'] = true;
        } else {
            $out['required'] = false;
        }
        if ( isset( $f['label'] ) ) {
            $out['label'] = (string) $f['label'];
        }
        foreach ( [ 'customer_editable', 'description', 'default', 'automation' ] as $opt ) {
            if ( array_key_exists( $opt, $f ) ) {
                $out[ $opt ] = $f[ $opt ];
            }
        }
        if ( isset( $f['sub_fields'] ) && is_array( $f['sub_fields'] ) ) {
            $out['sub_fields'] = array_values( $f['sub_fields'] );
        }
        return $out;
    }

    /**
     * Returns every top-level field definition (Core + theme.global + theme.pages.*.fields).
     * Sub_fields are NOT included — their semantics derive from the parent's automation.
     */
    private static function iterate_top_level_fields( $manifest ) {
        $out = [];
        foreach ( Moneyweb_Core_Fields::get_fields() as $f ) {
            $out[] = $f;
        }
        if ( ! empty( $manifest['global'] ) && is_array( $manifest['global'] ) ) {
            foreach ( $manifest['global'] as $f ) {
                $out[] = $f;
            }
        }
        if ( ! empty( $manifest['pages'] ) && is_array( $manifest['pages'] ) ) {
            foreach ( $manifest['pages'] as $page ) {
                if ( empty( $page['fields'] ) || ! is_array( $page['fields'] ) ) {
                    continue;
                }
                foreach ( $page['fields'] as $f ) {
                    $out[] = $f;
                }
            }
        }
        return $out;
    }

    /**
     * Converts a WP_Error into a Moneyweb-shaped REST response.
     */
    public static function wp_error_to_response( WP_Error $err ) {
        $data    = $err->get_error_data();
        $status  = isset( $data['status'] ) ? (int) $data['status'] : 500;
        $payload = [
            'status'  => 'error',
            'code'    => $err->get_error_code(),
            'message' => $err->get_error_message(),
        ];
        if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
            $payload['errors'] = $data['errors'];
        }
        return new WP_REST_Response( $payload, $status );
    }
}
