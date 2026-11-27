<?php
/**
 * Registration excerpt from WordPress 6.9.0, src/wp-includes/abilities.php.
 * @see https://github.com/WordPress/wordpress-develop/blob/6.9.0/src/wp-includes/abilities.php
 */
$category_user = 'user';

wp_register_ability(
	'core/get-user-info',
	array(
		'label'               => __( 'Get User Information' ),
		'description'         => __( 'Returns basic profile details for the current authenticated user to support personalization, auditing, and access-aware behavior.' ),
		'category'            => $category_user,
		'output_schema'       => array(
			'type'                 => 'object',
			'required'             => array( 'id', 'display_name', 'user_nicename', 'user_login', 'roles', 'locale' ),
			'properties'           => array(
				'id'            => array(
					'type'        => 'integer',
					'description' => __( 'The user ID.' ),
				),
				'display_name'  => array(
					'type'        => 'string',
					'description' => __( 'The display name of the user.' ),
				),
				'user_nicename' => array(
					'type'        => 'string',
					'description' => __( 'The URL-friendly name for the user.' ),
				),
				'user_login'    => array(
					'type'        => 'string',
					'description' => __( 'The login username for the user.' ),
				),
				'roles'         => array(
					'type'        => 'array',
					'description' => __( 'The roles assigned to the user.' ),
					'items'       => array(
						'type' => 'string',
					),
				),
				'locale'        => array(
					'type'        => 'string',
					'description' => __( 'The locale string for the user, such as en_US.' ),
				),
			),
			'additionalProperties' => false,
		),
		'execute_callback'    => static function (): array {
			$current_user = wp_get_current_user();

			return array(
				'id'            => $current_user->ID,
				'display_name'  => $current_user->display_name,
				'user_nicename' => $current_user->user_nicename,
				'user_login'    => $current_user->user_login,
				'roles'         => $current_user->roles,
				'locale'        => get_user_locale( $current_user ),
			);
		},
		'permission_callback' => static function (): bool {
			return is_user_logged_in();
		},
		'meta'                => array(
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			'show_in_rest' => false,
		),
	)
);
