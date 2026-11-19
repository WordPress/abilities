<?php
/**
 * Contract tests for the experimental plugin and theme inventories.
 *
 * @package WordPress\Abilities
 */

declare( strict_types = 1 );

namespace WordPress\Abilities\Tests;

use WP_Abilities_Registry;
use WP_Ability;
use WP_REST_Response;
use WordPress\Abilities\Registrar;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/** Exercises the registered abilities with real Core permission checks. */
final class InventoryQueryTest extends TestCase {
	/** @var int Temporary user's ID. */
	private int $user_id = 0;

	/** @var string Directory of the temporary plugin. */
	private string $plugin_dir = '';

	/** Queue both inventories when this suite runs on its own. */
	public static function set_up_before_class(): void {
		Registrar::register_all();
		WP_Abilities_Registry::get_instance();
	}

	/** Restore test data and the current user. */
	public function tear_down(): void {
		wp_set_current_user( 0 );
		if ( $this->user_id ) {
			if ( is_multisite() ) {
				wpmu_delete_user( $this->user_id );
			} else {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $this->user_id );
			}
		}
		if ( '' !== $this->plugin_dir ) {
			wp_delete_file( $this->plugin_dir . '/inventory.php' );
			rmdir( $this->plugin_dir );
			wp_clean_plugins_cache( false );
		}
		parent::tear_down();
	}

	/**
	 * Look up an inventory through the package's public registration path.
	 *
	 * @param string $collection Collection name.
	 * @return WP_Ability
	 */
	private function ability( string $collection ): WP_Ability {
		$ability = wp_get_ability( 'wp/' . $collection . '-query' );
		self::assertInstanceOf( WP_Ability::class, $ability );
		return $ability;
	}

	/**
	 * Set the current site user for permission checks.
	 *
	 * @param string $role Site role.
	 */
	private function login_as( string $role ): void {
		$id = wp_insert_user(
			array(
				'user_login' => 'inventory-' . wp_generate_uuid4(),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		self::assertIsInt( $id );
		$this->user_id = $id;
		wp_set_current_user( $id );
	}

	/**
	 * Install an inert local plugin header; Core reads Network from disk on multisite.
	 *
	 * @return string The Core REST plugin identifier.
	 */
	private function create_network_plugin(): string {
		$dir              = 'inventory-' . wp_generate_uuid4();
		$this->plugin_dir = WP_PLUGIN_DIR . '/' . $dir;
		self::assertTrue( wp_mkdir_p( $this->plugin_dir ) );
		file_put_contents( $this->plugin_dir . '/inventory.php', "<?php\n/*\nPlugin Name: Inventory Fixture\nVersion: 1.2.3\nNetwork: true\n*/\n" );
		wp_clean_plugins_cache( false );
		return $dir . '/inventory';
	}

	/** Both schemas and exposure annotations are available through the registry. */
	public function test_registration_contract(): void {
		foreach ( array(
			'plugins' => 'plugin',
			'themes'  => 'stylesheet',
		) as $collection => $id ) {
			$ability = $this->ability( $collection );
			$input   = $ability->get_input_schema();
			$output  = $ability->get_output_schema();
			self::assertSame( array( $id, 'status' ), array_keys( $input['properties'] ) );
			self::assertFalse( $input['additionalProperties'] );
			self::assertSame( array( $collection, 'total' ), $output['required'] );
			self::assertTrue( $ability->get_meta()['annotations']['readonly'] );
			self::assertTrue( $ability->get_meta()['mcp']['public'] );
		}
	}

	/** A caller without inventory access receives no rows, including on exact lookups. */
	public function test_unprivileged_queries_are_denied(): void {
		$this->login_as( 'subscriber' );
		foreach ( array(
			'plugins' => array( 'plugin' => 'example' ),
			'themes'  => array( 'stylesheet' => get_stylesheet() ),
		) as $collection => $filter ) {
			$ability = $this->ability( $collection );
			self::assertNotTrue( $ability->check_permissions( $filter ) );
			self::assertInstanceOf( \WP_Error::class, $ability->execute( $filter ) );
			self::assertInstanceOf( \WP_Error::class, $ability->execute() );
		}
	}

	/** Even an administrator cannot turn an invalid filter into an unrestricted request. */
	public function test_malformed_direct_permission_inputs_fail_closed(): void {
		$this->login_as( 'administrator' );
		foreach ( array(
			'plugins' => 'plugin',
			'themes'  => 'stylesheet',
		) as $collection => $id ) {
			$ability = $this->ability( $collection );
			foreach ( array( 'active', array( 'typo' => 'active' ), array( 'status' => 'unknown' ), array( $id => '' ), array( $id => array() ) ) as $input ) {
				self::assertNotTrue( $ability->check_permissions( $input ) );
				self::assertInstanceOf( \WP_Error::class, $ability->execute( $input ) );
			}
		}
	}

	/** Plugin filters intersect, and a network-only row and its count follow Core visibility. */
	public function test_plugin_visibility_and_activation_status(): void {
		$this->login_as( 'administrator' );
		$plugin  = $this->create_network_plugin();
		$ability = $this->ability( 'plugins' );
		$menu    = static function () {
			return array( 'plugins' => 1 );
		};
		$active  = static function () use ( $plugin ) {
			return array( $plugin . '.php' => 1 );
		};
		add_filter( 'pre_site_option_menu_items', $menu );
		try {
			$result = $ability->execute( array( 'plugin' => $plugin ) );
			if ( is_multisite() ) {
				self::assertSame(
					array(
						'plugins' => array(),
						'total'   => 0,
					),
					$result
				);
				grant_super_admin( $this->user_id );
				try {
					$visible = $ability->execute( array( 'plugin' => $plugin ) );
					self::assertSame( 1, $visible['total'] );
					self::assertSame( 'inactive', $visible['plugins'][0]['status'] );
				} finally {
					revoke_super_admin( $this->user_id );
				}
				add_filter( 'pre_site_option_active_sitewide_plugins', $active );
				$status = 'network-active';
			} else {
				$status = 'inactive';
			}

			$input  = array(
				'plugin' => $plugin,
				'status' => $status,
			);
			$result = $ability->execute( $input );
			self::assertIsArray( $result );
			self::assertSame( 1, $result['total'] );
			self::assertEquals(
				array(
					'plugin'  => $plugin,
					'name'    => 'Inventory Fixture',
					'version' => '1.2.3',
					'status'  => $status,
				),
				$result['plugins'][0]
			);
			self::assertSame(
				array(
					'plugins' => array(),
					'total'   => 0,
				),
				$ability->execute(
					array(
						'plugin' => $plugin,
						'status' => 'active',
					)
				)
			);
		} finally {
			remove_filter( 'pre_site_option_active_sitewide_plugins', $active );
			remove_filter( 'pre_site_option_menu_items', $menu );
		}
	}

	/** A theme manager can query the full collection and select an exact visible row. */
	public function test_theme_manager_can_list_themes(): void {
		$this->login_as( 'administrator' );
		$ability = $this->ability( 'themes' );
		self::assertTrue( $ability->check_permissions() );
		$result = $ability->execute();
		self::assertIsArray( $result );
		self::assertCount( $result['total'], $result['themes'] );
		self::assertContains( get_stylesheet(), wp_list_pluck( $result['themes'], 'stylesheet' ) );
		self::assertTrue( rest_validate_value_from_schema( $result, $ability->get_output_schema() ) );

		$inactive = $ability->execute( array( 'status' => 'inactive' ) );
		self::assertIsArray( $inactive );
		self::assertNotContains( get_stylesheet(), wp_list_pluck( $inactive['themes'], 'stylesheet' ) );
		self::assertSame( array(), $ability->execute( array( 'stylesheet' => 'missing-' . wp_generate_uuid4() ) )['themes'] );
	}

	/** Active-theme readers can retrieve the current stylesheet without enumerating others. */
	public function test_contributor_active_theme_and_exact_filters(): void {
		$this->login_as( 'contributor' );
		$ability    = $this->ability( 'themes' );
		$stylesheet = get_stylesheet();
		$mock       = static function ( WP_REST_Response $response ): WP_REST_Response {
			$data                 = $response->get_data();
			$data['name']         = array(
				'raw'      => 'Example Child',
				'rendered' => '<b>Example Child</b>',
			);
			$data['template']     = 'example-parent';
			$data['private_note'] = 'Only the inventory fields are public.';
			$response->set_data( $data );
			return $response;
		};
		add_filter( 'rest_prepare_theme', $mock );
		try {
			$result = $ability->execute( array( 'status' => 'active' ) );
			self::assertIsArray( $result );
			self::assertSame( 1, $result['total'] );
			$row = $result['themes'][0];
			self::assertSame( $stylesheet, $row['stylesheet'] );
			self::assertSame( 'example-parent', $row['template'] );
			self::assertSame( 'Example Child', $row['name'] );
			self::assertSame( 'active', $row['status'] );
			self::assertArrayNotHasKey( 'private_note', $row );
			self::assertSame( $result, $ability->execute( array( 'stylesheet' => $stylesheet ) ) );
			self::assertSame(
				array(
					'themes' => array(),
					'total'  => 0,
				),
				$ability->execute(
					array(
						'stylesheet' => 'other',
						'status'     => 'active',
					)
				)
			);
			self::assertInstanceOf( \WP_Error::class, $ability->execute() );
			self::assertInstanceOf( \WP_Error::class, $ability->execute( array( 'status' => 'inactive' ) ) );
			self::assertInstanceOf( \WP_Error::class, $ability->execute( array( 'stylesheet' => 'other' ) ) );
		} finally {
			remove_filter( 'rest_prepare_theme', $mock );
		}
	}
}
