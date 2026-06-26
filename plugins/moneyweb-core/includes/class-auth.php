<?php
/**
 * API-key authentication for moneyweb/v1 REST routes.
 *
 * Reads X-Moneyweb-Key header and compares to per-subsite option `moneyweb_api_key`.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moneyweb_Auth {

    const HEADER = 'X-Moneyweb-Key';

    /**
     * permission_callback for REST routes.
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public static function check( $request ) {
        if ( ! moneyweb_core_acf_active() ) {
            return new WP_Error(
                'acf_not_active',
                'ACF Pro is required but not active',
                [ 'status' => 503 ]
            );
        }

        $provided = $request->get_header( 'x_moneyweb_key' );
        if ( empty( $provided ) ) {
            $provided = $request->get_header( 'x-moneyweb-key' );
        }
        $provided = is_string( $provided ) ? trim( $provided ) : '';

        $stored = get_option( MONEYWEB_CORE_API_KEY_OPTION, '' );
        $stored = is_string( $stored ) ? trim( $stored ) : '';

        if ( '' === $provided || '' === $stored || ! hash_equals( $stored, $provided ) ) {
            return new WP_Error(
                'unauthorized',
                'Invalid API key',
                [ 'status' => 401 ]
            );
        }

        return true;
    }
}
