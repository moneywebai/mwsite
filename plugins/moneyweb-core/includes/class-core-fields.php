<?php
/**
 * Single source of truth for Moneyweb Core's always-on global fields.
 *
 * These fields exist on every Moneyweb site regardless of which child theme is
 * active. Their keys are reserved — a child theme may not redefine them.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moneyweb_Core_Fields {

    const VERSION = '1.0.0';

    /**
     * Allowed values for `automation.action` on any schema field (Core or theme).
     */
    const AUTOMATION_ACTIONS = [
        'copy_from_onboarding',
        'generate_text',
        'find_image',
        'generate_image',
        'find_or_generate_image',
        'select_color',
        'use_default',
        'manual',
    ];

    /**
     * Returns the Core global field definitions in display order.
     *
     * Each definition follows the same shape as theme manifest fields.
     * `automation` here is the default Core automation — onboarding copy.
     *
     * @return array
     */
    public static function get_fields() {
        return [
            [
                'key'               => 'company_name',
                'type'              => 'text',
                'required'          => true,
                'label'             => 'Virksomhedsnavn',
                'customer_editable' => true,
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'company_name',
                ],
            ],
            [
                'key'               => 'company_phone',
                'type'              => 'text',
                'required'          => true,
                'label'             => 'Telefon',
                'customer_editable' => true,
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'company_phone',
                ],
            ],
            [
                'key'               => 'company_email',
                'type'              => 'text',
                'required'          => true,
                'label'             => 'E-mail',
                'customer_editable' => true,
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'company_email',
                ],
            ],
            [
                'key'               => 'company_address',
                'type'              => 'text',
                'required'          => false,
                'label'             => 'Adresse',
                'customer_editable' => true,
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'company_address',
                ],
            ],
            [
                'key'               => 'company_cvr',
                'type'              => 'text',
                'required'          => false,
                'label'             => 'CVR-nummer',
                'customer_editable' => true,
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'company_cvr',
                ],
            ],
            [
                'key'               => 'logo',
                'type'              => 'image',
                'required'          => false,
                'label'             => 'Logo',
                'customer_editable' => true,
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'logo',
                ],
            ],
            [
                'key'               => 'facebook_url',
                'type'              => 'text',
                'required'          => false,
                'label'             => 'Facebook-URL',
                'customer_editable' => true,
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'facebook_url',
                ],
            ],
            [
                'key'               => 'instagram_url',
                'type'              => 'text',
                'required'          => false,
                'label'             => 'Instagram-URL',
                'customer_editable' => true,
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'instagram_url',
                ],
            ],
            [
                'key'               => 'linkedin_url',
                'type'              => 'text',
                'required'          => false,
                'label'             => 'LinkedIn-URL',
                'customer_editable' => true,
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'linkedin_url',
                ],
            ],
            [
                'key'               => 'opening_hours',
                'type'              => 'repeater',
                'required'          => false,
                'label'             => 'Åbningstider',
                'customer_editable' => true,
                'sub_fields'        => [
                    [ 'key' => 'day',    'type' => 'text',       'required' => true,  'label' => 'Dag' ],
                    [ 'key' => 'open',   'type' => 'text',       'required' => false, 'label' => 'Åbner' ],
                    [ 'key' => 'close',  'type' => 'text',       'required' => false, 'label' => 'Lukker' ],
                    [ 'key' => 'closed', 'type' => 'true_false', 'required' => false, 'label' => 'Lukket' ],
                    [ 'key' => 'note',   'type' => 'text',       'required' => false, 'label' => 'Bemærkning' ],
                ],
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'opening_hours',
                ],
            ],
            [
                'key'               => 'maps_url',
                'type'              => 'text',
                'required'          => false,
                'label'             => 'Google Maps-URL',
                'customer_editable' => true,
                'automation'        => [
                    'action'         => 'copy_from_onboarding',
                    'onboarding_key' => 'maps_url',
                ],
            ],
        ];
    }

    /**
     * Returns the flat list of reserved Core field keys.
     *
     * @return string[]
     */
    public static function get_reserved_keys() {
        $out = [];
        foreach ( self::get_fields() as $f ) {
            if ( ! empty( $f['key'] ) ) {
                $out[] = (string) $f['key'];
            }
        }
        return $out;
    }

    public static function is_reserved( $key ) {
        return in_array( (string) $key, self::get_reserved_keys(), true );
    }

    public static function is_valid_automation_action( $action ) {
        return in_array( (string) $action, self::AUTOMATION_ACTIONS, true );
    }
}
