<?php
/**
 * WordPress 6.9 registration for core/get-site-info.
 *
 * @package WordPress\Abilities
 */

declare( strict_types = 1 );

namespace WordPress\Abilities\Info;

/** Provides the core/get-site-info ability when Core has not registered it. */
final class Get_Site_Info extends Abstract_Info_Ability {
	protected function ability_name(): string {
		return 'core/get-site-info';
	}

	protected function registration_args(): array {
		$category_site        = 'site';
		$site_info_properties = array(
			'name'        => array(
				'type'        => 'string',
				'description' => __( 'The site title.' ),
			),
			'description' => array(
				'type'        => 'string',
				'description' => __( 'The site tagline.' ),
			),
			'url'         => array(
				'type'        => 'string',
				'description' => __( 'The site home URL.' ),
			),
			'wpurl'       => array(
				'type'        => 'string',
				'description' => __( 'The WordPress installation URL.' ),
			),
			'admin_email' => array(
				'type'        => 'string',
				'description' => __( 'The site administrator email address.' ),
			),
			'charset'     => array(
				'type'        => 'string',
				'description' => __( 'The site character encoding.' ),
			),
			'language'    => array(
				'type'        => 'string',
				'description' => __( 'The site language locale code.' ),
			),
			'version'     => array(
				'type'        => 'string',
				'description' => __( 'The WordPress version.' ),
			),
		);
		$site_info_fields     = array_keys( $site_info_properties );
		return array(
			'label'               => __( 'Get Site Information' ),
			'description'         => __( 'Returns site information configured in WordPress. By default returns all fields, or optionally a filtered subset.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'fields' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $site_info_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $site_info_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $site_info_fields ): array {
				$input = is_array( $input ) ? $input : array();
				$requested_fields = ! empty( $input['fields'] ) ? $input['fields'] : $site_info_fields;

				$result = array();
				foreach ( $requested_fields as $field ) {
					$result[ $field ] = get_bloginfo( $field );
				}

				return $result;
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		);
	}
}
