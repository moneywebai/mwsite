<?php
/**
 * Locates and parses the active child theme's moneyweb-theme.json.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moneyweb_Manifest {

    const FILENAME = 'moneyweb-theme.json';

    /**
     * Returns parsed manifest array, or WP_Error.
     *
     * @return array|WP_Error
     */
    public static function get() {
        $path = trailingslashit( get_stylesheet_directory() ) . self::FILENAME;

        if ( ! file_exists( $path ) ) {
            return new WP_Error(
                'no_manifest',
                sprintf( 'Active theme has no %s', self::FILENAME ),
                [ 'status' => 404 ]
            );
        }

        $raw = file_get_contents( $path );
        if ( false === $raw ) {
            return new WP_Error(
                'manifest_read_error',
                'Unable to read manifest file',
                [ 'status' => 500 ]
            );
        }

        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error(
                'manifest_invalid_json',
                'Manifest is not valid JSON: ' . json_last_error_msg(),
                [ 'status' => 500 ]
            );
        }

        $required_top = [ 'theme', 'theme_version', 'schema_version' ];
        foreach ( $required_top as $key ) {
            if ( ! isset( $data[ $key ] ) ) {
                return new WP_Error(
                    'manifest_missing_key',
                    sprintf( 'Manifest is missing required key "%s"', $key ),
                    [ 'status' => 500 ]
                );
            }
        }

        if ( ! isset( $data['global'] ) || ! is_array( $data['global'] ) ) {
            $data['global'] = [];
        }
        if ( ! isset( $data['pages'] ) || ! is_array( $data['pages'] ) ) {
            $data['pages'] = [];
        }

        return $data;
    }
}
