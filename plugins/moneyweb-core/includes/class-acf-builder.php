<?php
/**
 * Registers ACF field groups + options page from Core and from the manifest.
 *
 * Field keys are deterministic:
 *   group_mw_core_global,         field_mw_core_{key}                         (Core, always-on)
 *   group_mw_theme_global,        field_mw_theme_global_{key}                 (theme global extras)
 *   group_mw_{page},              field_mw_{page}_{key}                       (theme page fields)
 *   (repeater sub-fields)         field_mw_{scope}_{key}_{sub_key}
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
     * Registers Core's always-on global field group on the options page.
     * Field keys are prefixed `field_mw_core_`.
     */
    public static function register_core_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }
        $core_fields = Moneyweb_Core_Fields::get_fields();
        if ( empty( $core_fields ) ) {
            return;
        }
        acf_add_local_field_group( [
            'key'             => 'group_mw_core_global',
            'title'           => 'Moneyweb — Kernefelter',
            'fields'          => self::build_fields( $core_fields, 'core', 'field_mw_core_' ),
            'location'        => [
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
        ] );
    }

    /**
     * Registers theme-specific global fields + per-page field groups from the manifest.
     *
     * Caller must have already verified that the manifest does not collide with
     * Core's reserved keys.
     *
     * @param array $manifest
     */
    public static function register_from_manifest( $manifest ) {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        // Theme global extras (not Core).
        if ( ! empty( $manifest['global'] ) && is_array( $manifest['global'] ) ) {
            $group = [
                'key'             => 'group_mw_theme_global',
                'title'           => 'Moneyweb — Theme-felter',
                'fields'          => self::build_fields( $manifest['global'], 'theme_global', 'field_mw_theme_global_' ),
                'location'        => [
                    [
                        [
                            'param'    => 'options_page',
                            'operator' => '==',
                            'value'    => MONEYWEB_CORE_OPTIONS_PAGE,
                        ],
                    ],
                ],
                'menu_order'      => 10,
                'position'        => 'normal',
                'style'           => 'default',
                'label_placement' => 'top',
                'active'          => true,
            ];
            if ( ! empty( $group['fields'] ) ) {
                acf_add_local_field_group( $group );
            }
        }

        // Per-page field groups.
        if ( ! empty( $manifest['pages'] ) && is_array( $manifest['pages'] ) ) {
            foreach ( $manifest['pages'] as $page_key => $page ) {
                if ( ! is_array( $page ) || empty( $page['fields'] ) ) {
                    continue;
                }
                $safe_page_key = self::sanitize_key( $page_key );
                $prefix        = 'field_mw_' . $safe_page_key . '_';
                $location      = self::build_page_location( $page );

                $group = [
                    'key'             => 'group_mw_' . $safe_page_key,
                    'title'           => 'Moneyweb — ' . ( isset( $page['label'] ) ? $page['label'] : ucfirst( $page_key ) ),
                    'fields'          => self::build_fields( $page['fields'], $safe_page_key, $prefix ),
                    'location'        => $location,
                    'menu_order'      => 20,
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
     * @param string $scope_key   'core', 'theme_global', or sanitized page key
     * @param string $key_prefix  full ACF field-key prefix used at this scope
     */
    private static function build_fields( $fields, $scope_key, $key_prefix ) {
        $out = [];
        if ( ! is_array( $fields ) ) {
            return $out;
        }
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

            case 'color':
                return $common + [
                    'type'          => 'color_picker',
                    'return_format' => 'string',
                ];

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
