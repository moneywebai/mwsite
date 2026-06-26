<?php
/**
 * GET /moneyweb/v1/schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moneyweb_Schema {

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

        $out = [
            'status'         => 'ok',
            'theme'          => (string) $manifest['theme'],
            'theme_version'  => (string) $manifest['theme_version'],
            'schema_version' => (int) $manifest['schema_version'],
            'global'         => array_values( $manifest['global'] ),
            'pages'          => self::normalize_pages( $manifest['pages'] ),
        ];

        return new WP_REST_Response( $out, 200 );
    }

    private static function normalize_pages( $pages ) {
        $out = [];
        if ( ! is_array( $pages ) ) {
            return $out;
        }
        foreach ( $pages as $key => $page ) {
            if ( ! is_array( $page ) ) {
                continue;
            }
            $out[ $key ] = [
                'title'    => isset( $page['title'] ) ? (string) $page['title'] : '',
                'slug'     => isset( $page['slug'] ) ? (string) $page['slug'] : '',
                'template' => isset( $page['template'] ) ? (string) $page['template'] : '',
                'fields'   => isset( $page['fields'] ) ? array_values( (array) $page['fields'] ) : [],
            ];
            if ( ! empty( $page['is_front_page'] ) ) {
                $out[ $key ]['is_front_page'] = true;
            }
            if ( ! empty( $page['label'] ) ) {
                $out[ $key ]['label'] = (string) $page['label'];
            }
        }
        return $out;
    }
}
