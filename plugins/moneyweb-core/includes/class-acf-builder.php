<?php
/**
 * Registers ACF field groups + options page from the manifest.
 *
 * Field keys are deterministic:
 *   group_mw_global, group_mw_{page}
 *   field_mw_global_{key}, field_mw_{page}_{key}
 *   field_mw_{page}_{repeater}_{sub_key}
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moneyweb_ACF_Builder {

    public static function register_options_page() {
        if ( ! function_exists( 'acf_add_options_page' ) ) {
            return;
        }
        acf_add_options_page( [
            'page_title' => 'Moneyweb Indstillinger',
            'menu_title' => 'Moneyweb',
            'menu_slug'  => MONEYWEB_CORE_OPTIONS_PAGE,
            'capability' => 'manage_options',
            'redirect'   => false,
            'autoload'   => true,
        ] );
    }

    /**
     * @param array $manifest
     */
    public static function register_from_manifest( $manifest ) {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        // Global options field group.
        if ( ! empty( $manifest['global'] ) ) {
            $group = [
                'key'      => 'group_mw_global',
                'title'    => 'Moneyweb — Globale felter',
                'fields'   => self::build_fields( $manifest['global'], 'global' ),
                'location' => [
                    [
                        [
                            'param'    => 'options_page',
                            'operator' => '==',
                            'value'    => MONEYWEB_CORE_OPTIONS_PAGE,
                        ],
                    ],
                ],
                'menu_order'      => 0,
                'position'        => 'normal',
                'style'           => 'default',
                'label_placement' => 'top',
                'active'          => true,
            ];
            acf_add_local_field_group( $group );
        }

        // Per-page field groups.
        if ( ! empty( $manifest['pages'] ) && is_array( $manifest['pages'] ) ) {
            foreach ( $manifest['pages'] as $page_key => $page ) {
                if ( ! is_array( $page ) || empty( $page['fields'] ) ) {
                    continue;
                }
                $safe_page_key = self::sanitize_key( $page_key );

                $location = self::build_page_location( $page );

                $group = [
                    'key'      => 'group_mw_' . $safe_page_key,
                    'title'    => 'Moneyweb — ' . ( isset( $page['label'] ) ? $page['label'] : ucfirst( $page_key ) ),
                    'fields'   => self::build_fields( $page['fields'], $safe_page_key ),
                    'location' => $location,
                    'menu_order'      => 0,
                    'position'        => 'normal',
                    'style'           => 'default',
                    'label_placement' => 'top',
                    'active'          => true,
                ];
                acf_add_local_field_group( $group );
            }
        }
    }

    private static function build_page_location( $page ) {
        if ( ! empty( $page['is_front_page'] ) ) {
            return [
                [
                    [
                        'param'    => 'page_type',
                        'operator' => '==',
                        'value'    => 'front_page',
                    ],
                ],
            ];
        }
        $template = isset( $page['template'] ) ? $page['template'] : '';
        return [
            [
                [
                    'param'    => 'page_template',
                    'operator' => '==',
                    'value'    => $template,
                ],
            ],
        ];
    }

    /**
     * @param array  $fields
     * @param string $scope_key  'global' or sanitized page key
     */
    private static function build_fields( $fields, $scope_key ) {
        $out = [];
        if ( ! is_array( $fields ) ) {
            return $out;
        }
        $key_prefix = 'field_mw_' . $scope_key . '_';
        foreach ( $fields as $f ) {
            if ( ! is_array( $f ) || empty( $f['key'] ) || empty( $f['type'] ) ) {
                continue;
            }
            $field_key  = $key_prefix . self::sanitize_key( $f['key'] );
            $built      = self::build_single_field( $f, $field_key, $scope_key );
            if ( $built ) {
                $out[] = $built;
            }
        }
        return $out;
    }

    private static function build_single_field( $f, $field_key, $scope_key ) {
        $type     = $f['type'];
        $name     = self::sanitize_key( $f['key'] );
        $label    = isset( $f['label'] ) ? $f['label'] : $name;
        $required = ! empty( $f['required'] ) ? 1 : 0;

        $common = [
            'key'      => $field_key,
            'label'    => $label,
            'name'     => $name,
            'required' => $required,
        ];

        switch ( $type ) {
            case 'text':
                return $common + [ 'type' => 'text' ];

            case 'wysiwyg':
                return $common + [
                    'type'         => 'wysiwyg',
                    'tabs'         => 'all',
                    'toolbar'      => 'full',
                    'media_upload' => 1,
                ];

            case 'image':
                return $common + [
                    'type'          => 'image',
                    'return_format' => 'id',
                    'preview_size'  => 'medium',
                    'library'       => 'all',
                ];

            case 'true_false':
                return $common + [
                    'type' => 'true_false',
                    'ui'   => 1,
                ];

            case 'number':
                return $common + [ 'type' => 'number' ];

            case 'repeater':
                $sub_prefix = $field_key . '_';
                $sub_fields = [];
                if ( ! empty( $f['sub_fields'] ) && is_array( $f['sub_fields'] ) ) {
                    foreach ( $f['sub_fields'] as $sf ) {
                        if ( ! is_array( $sf ) || empty( $sf['key'] ) || empty( $sf['type'] ) ) {
                            continue;
                        }
                        $sub_key  = $sub_prefix . self::sanitize_key( $sf['key'] );
                        $built    = self::build_single_field( $sf, $sub_key, $scope_key );
                        if ( $built ) {
                            $sub_fields[] = $built;
                        }
                    }
                }
                return $common + [
                    'type'         => 'repeater',
                    'layout'       => 'block',
                    'button_label' => 'Tilføj',
                    'sub_fields'   => $sub_fields,
                ];

            default:
                return null;
        }
    }

    public static function sanitize_key( $value ) {
        $value = strtolower( (string) $value );
        $value = preg_replace( '/[^a-z0-9_]/', '_', $value );
        return $value;
    }
}
