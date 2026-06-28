<?php
/**
 * Validates a /site-data payload against the combined Core+theme schema.
 *
 * The validator does NOT care whether a global field is Core or theme — both
 * appear in $combined['global']. The site-data writer uses the `source`
 * marker to choose the correct ACF field key prefix when persisting.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moneyweb_Validator {

    /**
     * @param array $payload
     * @param array $combined  Output of Moneyweb_Schema::build_combined()
     * @return array { errors: array, warnings: array }
     */
    public static function validate( $payload, $combined ) {
        $errors   = [];
        $warnings = [];

        // 1. Theme.
        $payload_theme  = isset( $payload['theme'] ) ? (string) $payload['theme'] : '';
        $expected_theme = isset( $combined['theme'] ) ? (string) $combined['theme'] : '';
        if ( '' === $payload_theme || $payload_theme !== $expected_theme ) {
            $errors[] = [
                'code'    => 'theme_mismatch',
                'message' => sprintf(
                    "Payload theme '%s' does not match active theme '%s'",
                    $payload_theme,
                    $expected_theme
                ),
            ];
        }

        // 2. Schema version (strict match).
        $payload_sv  = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : 0;
        $expected_sv = isset( $combined['schema_version'] ) ? (int) $combined['schema_version'] : 0;
        if ( $payload_sv !== $expected_sv ) {
            $errors[] = [
                'code'    => 'schema_version_mismatch',
                'message' => sprintf( 'Expected schema_version %d, got %d', $expected_sv, $payload_sv ),
            ];
        }

        if ( ! empty( $errors ) ) {
            return [ 'errors' => $errors, 'warnings' => $warnings ];
        }

        // 3. Required globals (Core + theme combined).
        $global_payload = isset( $payload['global'] ) && is_array( $payload['global'] )
            ? $payload['global']
            : [];
        $known_global = [];
        foreach ( $combined['global'] as $f ) {
            if ( empty( $f['key'] ) ) {
                continue;
            }
            $known_global[] = (string) $f['key'];
            if ( ! empty( $f['required'] ) && ! self::value_present( $global_payload, $f['key'] ) ) {
                $errors[] = [
                    'code'    => 'required_field_missing',
                    'field'   => (string) $f['key'],
                    'scope'   => 'global',
                    'source'  => isset( $f['source'] ) ? (string) $f['source'] : '',
                    'message' => 'Required field missing',
                ];
            }
        }

        // 4. Required page fields + unknown-field warnings per page.
        $pages_payload = isset( $payload['pages'] ) && is_array( $payload['pages'] )
            ? $payload['pages']
            : [];

        foreach ( $combined['pages'] as $page_key => $page ) {
            $page_payload = isset( $pages_payload[ $page_key ] ) && is_array( $pages_payload[ $page_key ] )
                ? $pages_payload[ $page_key ]
                : [];

            $known_page = [];
            if ( ! empty( $page['fields'] ) && is_array( $page['fields'] ) ) {
                foreach ( $page['fields'] as $f ) {
                    if ( empty( $f['key'] ) ) {
                        continue;
                    }
                    $known_page[] = (string) $f['key'];
                    if ( ! empty( $f['required'] ) && ! self::value_present( $page_payload, $f['key'] ) ) {
                        $errors[] = [
                            'code'    => 'required_field_missing',
                            'field'   => (string) $f['key'],
                            'scope'   => 'page:' . $page_key,
                            'message' => 'Required field missing',
                        ];
                    }
                }
            }
            foreach ( $page_payload as $k => $v ) {
                if ( ! in_array( (string) $k, $known_page, true ) ) {
                    $warnings[] = [
                        'code'    => 'unknown_field',
                        'page'    => (string) $page_key,
                        'field'   => (string) $k,
                        'message' => 'Field not in schema — skipped',
                    ];
                }
            }
        }

        // 5. Unknown pages.
        foreach ( $pages_payload as $page_key => $_v ) {
            if ( ! isset( $combined['pages'][ $page_key ] ) ) {
                $warnings[] = [
                    'code'    => 'unknown_page',
                    'page'    => (string) $page_key,
                    'message' => 'Page not in schema — skipped',
                ];
            }
        }

        // 6. Unknown global fields.
        foreach ( $global_payload as $k => $_v ) {
            if ( ! in_array( (string) $k, $known_global, true ) ) {
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
