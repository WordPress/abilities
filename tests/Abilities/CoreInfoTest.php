<?php
/**
 * Tests for the three procedural info abilities introduced in WordPress 6.9.
 *
 * @package WordPress\Abilities
 */

declare( strict_types = 1 );

namespace WordPress\Abilities\Tests;

use WordPress\Abilities\Info\Get_Environment_Info;
use WordPress\Abilities\Info\Get_Site_Info;
use WordPress\Abilities\Info\Get_User_Info;
use WP_Error;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

final class CoreInfoTest extends TestCase {
	/**
	 * A fresh subscriber for checking the difference between signed-in and admin access.
	 *
	 * @var int
	 */
	private int $subscriber;

	public function set_up(): void {
		parent::set_up();
		$user = wp_insert_user(
			array(
				'user_login' => 'core-info-' . wp_generate_uuid4(),
				'user_pass'  => wp_generate_password(),
				'role'       => 'subscriber',
			)
		);
		self::assertIsInt( $user );
		$this->subscriber = $user;
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		wp_delete_user( $this->subscriber );
		parent::tear_down();
	}

	public function test_site_fields_and_access(): void {
		$ability = new Get_Site_Info();
		$fields  = array( 'name', 'description', 'url', 'wpurl', 'admin_email', 'charset', 'language', 'version' );
		self::assertSame( $fields, $ability->get_input_schema()['properties']['fields']['items']['enum'] );
		self::assertSame( $fields, array_keys( $ability->get_output_schema()['properties'] ) );
		self::assertTrue( $ability->get_meta_item( 'show_in_rest' ) );

		wp_set_current_user( $this->subscriber );
		self::assertFalse( $ability->check_permissions() );
		self::assertInstanceOf( WP_Error::class, $ability->execute() );

		wp_set_current_user( 1 );
		self::assertTrue( $ability->check_permissions() );
		$title = static fn () => 'Backported site title';
		add_filter( 'pre_option_blogname', $title );
		try {
			self::assertSame( array( 'name' => 'Backported site title' ), $ability->execute( array( 'fields' => array( 'name' ) ) ) );
			self::assertSame( array_combine( $fields, array_map( 'get_bloginfo', $fields ) ), $ability->execute() );
			self::assertInstanceOf( WP_Error::class, $ability->execute( array( 'fields' => array( 'unknown' ) ) ) );
		} finally {
			remove_filter( 'pre_option_blogname', $title );
		}
	}

	public function test_user_info_returns_only_the_current_user(): void {
		$ability = new Get_User_Info();
		$fields  = array( 'id', 'display_name', 'user_nicename', 'user_login', 'roles', 'locale' );
		self::assertSame( array(), $ability->get_input_schema() );
		self::assertSame( $fields, $ability->get_output_schema()['required'] );
		self::assertFalse( $ability->get_meta_item( 'show_in_rest' ) );

		wp_set_current_user( 0 );
		self::assertFalse( $ability->check_permissions() );
		self::assertInstanceOf( WP_Error::class, $ability->execute() );

		$user = wp_set_current_user( $this->subscriber );
		self::assertTrue( $ability->check_permissions() );
		self::assertSame(
			array(
				'id'            => $user->ID,
				'display_name'  => $user->display_name,
				'user_nicename' => $user->user_nicename,
				'user_login'    => $user->user_login,
				'roles'         => $user->roles,
				'locale'        => get_user_locale( $user ),
			),
			$ability->execute()
		);
	}

	public function test_environment_info_requires_an_administrator(): void {
		global $wpdb;
		$ability = new Get_Environment_Info();
		self::assertSame( array(), $ability->get_input_schema() );
		self::assertSame( array( 'environment', 'php_version', 'db_server_info', 'wp_version' ), $ability->get_output_schema()['required'] );
		self::assertTrue( $ability->get_meta_item( 'show_in_rest' ) );
		wp_set_current_user( $this->subscriber );
		self::assertFalse( $ability->check_permissions() );
		self::assertInstanceOf( WP_Error::class, $ability->execute() );

		wp_set_current_user( 1 );
		self::assertTrue( $ability->check_permissions() );
		self::assertSame(
			array(
				'environment'    => wp_get_environment_type(),
				'php_version'    => phpversion(),
				'db_server_info' => $wpdb->db_server_info(),
				'wp_version'     => get_bloginfo( 'version' ),
			),
			$ability->execute()
		);
	}
}
