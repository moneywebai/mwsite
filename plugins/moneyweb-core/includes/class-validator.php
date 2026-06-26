<?php
/**
 * Validates a /site-data payload against the active manifest.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moneyweb_Validator {

    /**
     * Validate the payload against the manifest.
     *
     * @param array $payload
     * @param array $manifest
     * @return array { errors: array, warnings: array }
     */
    public static function validate( $payload, $manifest ) {
        $errors   = [];
        $warnings = [];

        // 1. Theme.
        $payload_theme = isset( $payload['theme'] ) ? (string) $payload['theme'] : '';
        $manifest_theme = isset( $manifest['theme'] ) ? (string) $manifest['theme'] : '';
        if ( '' === $payload_theme || $payload_theme !== $manifest_theme ) {
            $errors[] = [
                'code'    => 'theme_mismatch',
                'message' => sprintf(
                    "Payload theme '%s' does not match active theme '%s'",
                    $payload_theme,
                    $manifest_theme
                ),
            ];
        }

        // 2. Schema version.
        $payload_sv  = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : 0;
        $manifest_sv = isset( $manifest['schema_version'] ) ? (int) $manifest['schema_version'] : 0;
        if ( $payload_sv !== $manifest_sv ) {
            $errors[] = [
                'code'    => 'schema_version_mismatch',
                'message' => sprintf( 'Expected schema_version %d, got %d', $manifest_sv, $payload_sv ),
            ];
        }

        // If structural errors so far, abort early.
        if ( ! empty( $errors ) ) {
            return [ 'errors' => $errors, 'warnings' => $warnings ];
        }

        // 3. Required global fields.
        $global_payload = isset( $payload['global'] ) && is_array( $payload['global'] )
            ? $payload['global']
            : [];
        foreach ( $manifest['global'] as $f ) {
            if ( empty( $f['key'] ) ) {
                continue;
            }
            if ( ! empty( $f['required'] ) && ! self::value_present( $global_payload, $f['key'] ) ) {
                $errors[] = [
                    'code'    => 'required_field_missing',
                    'field'   => $f['key'],
                    'scope'   => 'global',
                    'message' => 'Required field missing',
                ];
            }
        }

        // 4. Required per-page fields + unknown-field warnings.
        $pages_payload = isset( $payload['pages'] ) && is_array( $payload['pages'] )
            ? $payload['pages']
            : [];

        foreach ( $manifest['pages'] as $page_key => $page ) {
            $page_payload = isset( $pages_payload[ $page_key ] ) && is_array( $pages_payload[ $page_key ] )
                ? $pages_payload[ $page_key ]
                : [];

            if ( ! empty( $page['fields'] ) && is_array( $page['fields'] ) ) {
                foreach ( $page['fields'] as $f ) {
                    if ( empty( $f['key'] ) ) {
                        continue;
                    }
                    if ( ! empty( $f['required'] ) && ! self::value_present( $page_payload, $f['key'] ) ) {
                        $errors[] = [
                            'code'    => 'required_field_missing',
                            'field'   => $f['key'],
                            'scope'   => 'page:' . $page_key,
                            'message' => 'Required field missing',
                        ];
                    }
                }
            }

            // Warnings for unknown fields on this page.
            $known = self::field_keys( $page['fields'] ?? [] );
            foreach ( $page_payload as $k => $v ) {
                if ( ! in_array( $k, $known, true ) ) {
                    $warnings[] = [
                        'code'    => 'unknown_field',
                        'page'    => $page_key,
                        'field'   => (string) $k,
                        'message' => 'Field not in schema — skipped',
                    ];
                }
            }
        }

        // Warnings for unknown pages.
        foreach ( $pages_payload as $page_key => $vals ) {
            if ( ! isset( $manifest['pages'][ $page_key ] ) ) {
                $warnings[] = [
                    'code'    => 'unknown_page',
                    'page'    => (string) $page_key,
                    'message' => 'Page not in schema — skipped',
                ];
            }
        }

        // Warnings for unknown global fields.
        $known_global = self::field_keys( $manifest['global'] );
        foreach ( $global_payload as $k => $v ) {
            if ( ! in_array( $k, $known_global, true ) ) {
                $warnings[] = [
                    'code'    => 'unknown_field',
                    'scope'   => 'global',
                    'field'   => (string) $k,
                    'message' => 'Field not in schema — skipped',
                ];
            }
        }

        return [ 'errors' => $errors, 'warnings' => $warnings ];
    }

    private static function field_keys( $fields ) {
        $out = [];
        if ( ! is_array( $fields ) ) {
            return $out;
        }
        foreach ( $fields as $f ) {
            if ( ! empty( $f['key'] ) ) {
                $out[] = $f['key'];
            }
        }
        return $out;
    }

    private static function value_present( $arr, $key ) {
        if ( ! array_key_exists( $key, $arr ) ) {
            return false;
        }
        $v = $arr[ $key ];
        if ( is_string( $v ) ) {
            return '' !== trim( $v );
        }
        if ( is_array( $v ) ) {
            return ! empty( $v );
        }
        if ( null === $v ) {
            return false;
        }
        return true;
    }
}
