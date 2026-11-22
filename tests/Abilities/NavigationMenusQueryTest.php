<?php
/**
 * Integration tests for the wp/navigation-menus-query Ability provided by the plugin.
 *
 * @package WordPress\Abilities
 */

namespace WordPress\Abilities\Tests;

use WP_Ability;
use WP_Abilities_Registry;
use WordPress\Abilities\Registrar;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use WordPress\Abilities\Navigation_Menus\Navigation_Menus_Query;

/**
 * Nav_Menus ability test case.
 *
 * @since x.x.x
 */
class NavigationMenusQueryTest extends TestCase {

	/**
	 * A nav menu created for the test, and assigned to the `primary` location.
	 *
	 * @since x.x.x
	 * @var int
	 */
	private $menu_id;

	/**
	 * Set up test case.
	 *
	 * @since x.x.x
	 */
	public function set_up(): void {
		parent::set_up();

		register_nav_menu( 'primary', 'Primary Menu' );

		$this->menu_id = (int) wp_create_nav_menu( 'Test Menu' );

		wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-title'  => 'Home',
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
			)
		);

		set_theme_mod( 'nav_menu_locations', array( 'primary' => $this->menu_id ) );
	}

	/** Registers the package before the abilities registry boots. */
	public static function set_up_before_class(): void {
		register_nav_menu( 'primary', 'Primary Menu' );
		register_nav_menu( 'footer', 'Footer Menu' );
		Registrar::register_all();
		WP_Abilities_Registry::get_instance();
	}

	/**
	 * Tear down test case.
	 *
	 * @since x.x.x
	 */
	public function tear_down(): void {
		wp_delete_nav_menu( $this->menu_id );
		remove_theme_mod( 'nav_menu_locations' );
		$user_id = get_current_user_id();
		wp_set_current_user( 0 );
		if ( $user_id ) {
			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			wp_delete_user( $user_id );
		}

		parent::tear_down();
	}

	/**
	 * Logs in as an administrator so the ability's permission check passes.
	 *
	 * @since x.x.x
	 */
	private function become_admin(): void {
		wp_set_current_user(
			wp_insert_user(
				array(
					'user_login' => 'nav-test-admin',
					'user_pass'  => 'unused',
					'role'       => 'administrator',
				)
			)
		);
	}

	/**
	 * The ability is registered in the `site-editing` category and flagged read-only.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_ability_is_registered(): void {
		$ability = wp_get_ability( 'wp/navigation-menus-query' );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( 'wp/navigation-menus-query', $ability->get_name() );
		$this->assertSame( 'site-editing', $ability->get_category() );
		$this->assertSame( 'experimental', $ability->get_meta_item( 'stability', '' ) );
		$this->assertSame( 'object', $ability->get_input_schema()['type'] );
		$this->assertCount( 2, $ability->get_output_schema()['oneOf'] );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ) );

		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}

	/**
	 * A single menu, with its items, is returned by ID.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_returns_a_single_menu_by_id(): void {
		$this->become_admin();

		$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array( 'id' => $this->menu_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( $this->menu_id, $result['id'] );
		$this->assertSame( 'Test Menu', $result['name'] );
		$this->assertSame( array( 'primary' ), $result['locations'] );
		$this->assertCount( 1, $result['items'] );
		$this->assertSame( 'Home', $result['items'][0]['title'] );
		$this->assertSame( home_url( '/' ), $result['items'][0]['url'] );
	}

	/**
	 * A single menu, with its items, is returned by slug.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_returns_a_single_menu_by_slug(): void {
		$this->become_admin();

		$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array( 'slug' => 'test-menu' ) );

		$this->assertSame( $this->menu_id, $result['id'] );
		$this->assertCount( 1, $result['items'] );
	}

	/**
	 * The menu assigned to a registered theme location is returned.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_returns_the_menu_assigned_to_a_location(): void {
		$this->become_admin();

		$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array( 'location' => 'primary' ) );

		$this->assertSame( $this->menu_id, $result['id'] );
	}

	/**
	 * A location with no assigned menu errors instead of returning an empty result.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_errors_when_location_has_no_assigned_menu(): void {
		register_nav_menu( 'footer', 'Footer Menu' );

		$this->become_admin();

		$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array( 'location' => 'footer' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * An unknown menu ID errors instead of returning an empty result.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_errors_when_menu_not_found(): void {
		$this->become_admin();

		$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array( 'id' => 999999 ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Without input, the ability lists every menu alongside registered locations and their
	 * current assignments.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_lists_all_menus_with_registered_locations_and_assignments(): void {
		$this->become_admin();

		$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array() );

		$this->assertCount( 1, $result['menus'] );
		$this->assertSame( $this->menu_id, $result['menus'][0]['id'] );
		$this->assertArrayNotHasKey( 'items', $result['menus'][0] );

		$this->assertSame( 'Primary Menu', $result['registered_locations']->primary );
		$this->assertSame( $this->menu_id, $result['location_assignments']->primary );
	}

	/**
	 * The `search` filter narrows the collection to menus matching the term.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_filters_collection_by_search(): void {
		$this->become_admin();

		$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array( 'search' => 'Nonexistent' ) );

		$this->assertSame( array(), $result['menus'] );
	}

	/**
	 * Users without `edit_theme_options` cannot run the ability.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_requires_edit_theme_options(): void {
		wp_set_current_user(
			wp_insert_user(
				array(
					'user_login' => 'nav-test-subscriber',
					'user_pass'  => 'unused',
					'role'       => 'subscriber',
				)
			)
		);

		$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array( 'id' => $this->menu_id ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Schema-valid object input behaves like its array form.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_accepts_object_input(): void {
		$this->become_admin();

		$result = wp_get_ability( 'wp/navigation-menus-query' )->execute(
			(object) array(
				'id' => $this->menu_id,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( $this->menu_id, $result['id'] );
	}

	/**
	 * Non-array and non-object inputs fall back to default collection queries.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_handles_non_array_input(): void {
		$this->become_admin();

		$nav_menus = new Navigation_Menus_Query();
		$result    = $nav_menus->execute_get_nav_menus( null );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'menus', $result );
	}

	/**
	 * Non-WP_Term elements returned by wp_get_nav_menus are skipped in collection queries.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_skips_non_term_elements_in_collection(): void {
		$this->become_admin();

		$filter = static function ( $menus ) {
			return array_merge( (array) $menus, array( false ) );
		};
		add_filter( 'wp_get_nav_menus', $filter );

		try {
			$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array() );
		} finally {
			remove_filter( 'wp_get_nav_menus', $filter );
		}

		$this->assertCount( 1, $result['menus'] );
		$this->assertSame( $this->menu_id, $result['menus'][0]['id'] );
	}

	/**
	 * Registered theme locations with no assigned menu (ID 0) are omitted from assignments.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_omits_unassigned_locations_from_assignments(): void {
		register_nav_menu( 'footer', 'Footer Menu' );
		set_theme_mod(
			'nav_menu_locations',
			array(
				'primary' => $this->menu_id,
				'footer'  => 0,
			)
		);

		$this->become_admin();

		$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array() );

		$this->assertTrue( property_exists( $result['location_assignments'], 'primary' ) );
		$this->assertSame( $this->menu_id, $result['location_assignments']->primary );
		$this->assertFalse( property_exists( $result['location_assignments'], 'footer' ) );
	}

	/**
	 * Menu locations assigned to a different menu are skipped when resolving menu locations.
	 *
	 * @since x.x.x
	 */
	public function test_navigation_menus_query_filters_out_other_menu_locations(): void {
		register_nav_menu( 'footer', 'Footer Menu' );
		$second_menu_id = (int) wp_create_nav_menu( 'Second Menu' );

		set_theme_mod(
			'nav_menu_locations',
			array(
				'primary' => $this->menu_id,
				'footer'  => $second_menu_id,
			)
		);

		$this->become_admin();

		try {
			$result = wp_get_ability( 'wp/navigation-menus-query' )->execute( array( 'id' => $this->menu_id ) );

			$this->assertSame( array( 'primary' ), $result['locations'] );
		} finally {
			wp_delete_nav_menu( $second_menu_id );
		}
	}
}
