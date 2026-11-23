<?php
/**
 * Tests: `wp/templates-query` (port of WordPress/ai PR #1309's templates tests, on this
 * package's per-ability contract shape).
 *
 * Runs under both wp-env matrix environments: trunk (7.2-dev) and stable. The `wp/` name
 * is never core-provided, so the package copy registers and executes identically on both.
 *
 * The tests switch between two fixture themes in `tests/Fixtures/themes`: a block theme
 * that ships `index.html` and `404.html`, and a classic theme.
 *
 * @package WordPress\Abilities
 */

declare( strict_types = 1 );

namespace WordPress\Abilities\Tests;

use WP_Abilities_Registry;
use WP_Ability;
use WP_Error;
use WP_REST_Request;
use WordPress\Abilities\Manifest;
use WordPress\Abilities\Registrar;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Per-ability contract tests for `wp/templates-query`.
 */
final class TemplatesQueryTest extends TestCase {

	/**
	 * The ability name.
	 */
	private const ABILITY = 'wp/templates-query';

	/**
	 * The fixture block theme.
	 */
	private const BLOCK_THEME = 'abilities-test-block-theme';

	/**
	 * The fixture classic theme.
	 */
	private const CLASSIC_THEME = 'abilities-test-classic-theme';

	/**
	 * Cached IDs of the mocked users, keyed by role.
	 *
	 * @var array<string, int>
	 */
	private array $user_ids = array();

	/**
	 * IDs of the templates a test created.
	 *
	 * @var list<int>
	 */
	private array $template_ids = array();

	/**
	 * The theme directories before the fixture directory was registered.
	 *
	 * @var list<string>
	 */
	private array $original_theme_directories = array();

	/**
	 * The active theme before the test switched it.
	 *
	 * @var string
	 */
	private string $original_stylesheet = '';

	/**
	 * Settles the registry as the other ability suites do.
	 */
	public static function set_up_before_class(): void {
		Registrar::register_all();

		if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
			// Fires a fresh boot; a no-op once a previous suite's boot settled the registry.
			WP_Abilities_Registry::get_instance();
		}
	}

	/**
	 * Makes the fixture themes available.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->original_theme_directories = $GLOBALS['wp_theme_directories'];
		$this->original_stylesheet        = get_stylesheet();
		register_theme_directory( dirname( __DIR__ ) . '/Fixtures/themes' );
		wp_clean_themes_cache();
	}

	/**
	 * Removes the test templates and users, and restores the active theme.
	 */
	public function tear_down(): void {
		foreach ( $this->template_ids as $id ) {
			wp_delete_post( $id, true );
		}
		$this->template_ids = array();

		switch_theme( $this->original_stylesheet );
		$GLOBALS['wp_theme_directories'] = $this->original_theme_directories; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the global the test changed.
		wp_clean_themes_cache();

		if ( ! empty( $this->user_ids ) && ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		foreach ( $this->user_ids as $id ) {
			if ( $id > 1 ) {
				wp_delete_user( $id );
			}
		}
		$this->user_ids = array();
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * The registered `wp/templates-query` instance.
	 */
	private function get_registered(): WP_Ability {
		$ability = WP_Abilities_Registry::get_instance()->get_registered( self::ABILITY );
		self::assertNotNull( $ability, 'The ability should be registered through the Registrar.' );

		return $ability;
	}

	/**
	 * Logs in a mocked user of the given role.
	 *
	 * @param string $role The role.
	 */
	private function log_in_as( string $role ): void {
		if ( ! isset( $this->user_ids[ $role ] ) ) {
			$this->user_ids[ $role ] = (int) wp_insert_user(
				array(
					'user_login' => 'tpl_' . $role,
					'user_pass'  => wp_generate_password(),
					'role'       => $role,
				)
			);
		}
		wp_set_current_user( $this->user_ids[ $role ] );
	}

	/**
	 * Runs the ability as an administrator and returns its result.
	 *
	 * @param mixed $input Optional. The ability input. Default null.
	 * @return array<string, mixed> The ability result.
	 */
	private function run_ability( $input = null ): array {
		$this->log_in_as( 'administrator' );

		$result = $this->get_registered()->execute( $input );
		self::assertIsArray( $result, 'The ability should return the theme and its templates.' );

		return $result;
	}

	/**
	 * Returns the result's templates keyed by slug.
	 *
	 * A numeric slug, such as `404`, becomes an integer key, as PHP casts it.
	 *
	 * @param array<string, mixed> $result The ability result.
	 * @return array<int|string, array<string, mixed>> The templates keyed by slug.
	 */
	private function templates_by_slug( array $result ): array {
		return array_column( $result['templates'], null, 'slug' );
	}

	/**
	 * Creates a published template for the active theme, as the Site Editor stores one.
	 *
	 * @param string $slug  The template slug.
	 * @param string $title The template title.
	 */
	private function create_user_template( string $slug, string $title ): void {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Edited</p><!-- /wp:paragraph -->',
			),
			true
		);
		self::assertIsInt( $post_id, 'The test template should be created.' );
		wp_set_post_terms( $post_id, get_stylesheet(), 'wp_theme' );

		$this->template_ids[] = $post_id;
	}

	/**
	 * Asserts a WP_Error with the exact code.
	 *
	 * @param string $code   The expected error code.
	 * @param mixed  $actual The value to check.
	 */
	private function assert_wp_error( string $code, $actual ): void {
		self::assertInstanceOf( WP_Error::class, $actual, "Expected WP_Error {$code}." );
		self::assertSame( $code, $actual->get_error_code() );
	}

	/**
	 * Registered exactly once, with the PR's annotations and the experimental provenance.
	 *
	 * The `wp/` name is never core-provided, so the package copy registers on both
	 * environments and the provenance does not branch.
	 */
	public function test_registered_once_with_annotations_and_provenance(): void {
		$ability = $this->get_registered();
		$meta    = $ability->get_meta();

		self::assertSame( 'Query Templates', $ability->get_label(), 'The ability should use the PR\'s label.' );
		self::assertSame( 'site', $ability->get_category(), 'The ability should use the site category.' );
		self::assertTrue( (bool) ( $meta['show_in_rest'] ?? false ), 'The ability should be exposed in REST.' );
		self::assertSame( array( 'public' => true ), $meta['mcp'] ?? null, 'The mcp meta opts the ability in for MCP-tool exposure.' );
		self::assertSame(
			array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
				'open_world'  => false,
			),
			$meta['annotations'] ?? null,
			'The ability should be annotated as a read-only, local read.'
		);
		self::assertSame( 'experimental', $meta['stability'] ?? null );
		self::assertSame( 'wordpress/abilities', $meta['source'] ?? null );
		self::assertSame( Manifest::VERSION, $meta['package_version'] ?? null );
		self::assertArrayNotHasKey( 'renamed_from', $meta, 'The port carries no renamed_from lineage.' );

		Registrar::register_all();
		Registrar::register_all();
		self::assertSame( $ability, $this->get_registered(), 'No double registration: the single instance holds.' );
	}

	/**
	 * The input schema takes no input but accepts an empty object, and has no `properties`
	 * key: PHP encodes an empty array as the JSON array `[]`, which JSON Schema validators
	 * such as the one in `@wordpress/abilities` reject before the ability runs.
	 */
	public function test_input_schema_takes_an_empty_object(): void {
		$schema = $this->get_registered()->get_input_schema();

		self::assertSame(
			array(
				'type'                 => 'object',
				'default'              => array(),
				'additionalProperties' => false,
			),
			$schema,
			'The input schema should take no input.'
		);
		self::assertStringNotContainsString( '"properties":[]', (string) wp_json_encode( $schema ), 'No properties should be encoded as a JSON array.' );
	}

	/**
	 * The output schema describes the theme and its templates, all fields required.
	 */
	public function test_output_schema_describes_the_theme_and_its_templates(): void {
		$schema = $this->get_registered()->get_output_schema();

		self::assertSame( 'object', $schema['type'] );
		self::assertFalse( $schema['additionalProperties'] );
		self::assertSame( array( 'block_theme', 'theme', 'templates' ), $schema['required'] );
		self::assertSame( 'boolean', $schema['properties']['block_theme']['type'] );
		self::assertSame( 'string', $schema['properties']['theme']['type'] );
		self::assertSame( 'array', $schema['properties']['templates']['type'] );

		$item = $schema['properties']['templates']['items'];
		self::assertSame( 'object', $item['type'] );
		self::assertFalse( $item['additionalProperties'] );
		self::assertSame( array( 'slug', 'title', 'source', 'has_theme_file' ), $item['required'] );
		self::assertArrayNotHasKey( 'enum', $item['properties']['source'], 'The source is open: filters may add values.' );
		self::assertSame( 'boolean', $item['properties']['has_theme_file']['type'] );
	}

	/**
	 * Only users who can edit theme options may run the ability.
	 */
	public function test_requires_edit_theme_options(): void {
		$ability = $this->get_registered();

		$this->log_in_as( 'administrator' );
		self::assertTrue( $ability->check_permissions(), 'An administrator can edit theme options.' );

		$this->log_in_as( 'editor' );
		self::assertFalse( $ability->check_permissions(), 'An editor cannot edit theme options.' );

		$this->log_in_as( 'subscriber' );
		self::assertFalse( $ability->check_permissions(), 'A subscriber cannot edit theme options.' );

		wp_set_current_user( 0 );
		self::assertFalse( $ability->check_permissions(), 'A logged-out user cannot edit theme options.' );
	}

	/**
	 * A user without the capability gets an error instead of the templates.
	 */
	public function test_execute_refuses_a_user_without_the_capability(): void {
		switch_theme( self::BLOCK_THEME );
		$this->log_in_as( 'editor' );

		$this->assert_wp_error( 'ability_invalid_permissions', $this->get_registered()->execute() );
	}

	/**
	 * A block theme is reported with the templates it ships as files.
	 */
	public function test_reports_a_block_theme_and_its_template_files(): void {
		switch_theme( self::BLOCK_THEME );

		$result    = $this->run_ability();
		$templates = $this->templates_by_slug( $result );

		self::assertTrue( $result['block_theme'] );
		self::assertSame( 'Abilities Test Block Theme', $result['theme'] );
		self::assertArrayHasKey( '404', $templates );
		self::assertArrayHasKey( 'index', $templates );
		self::assertSame( 'theme', $templates['404']['source'] );
		self::assertTrue( $templates['404']['has_theme_file'] );
		self::assertNotSame( '', $templates['404']['title'] );
	}

	/**
	 * A template the theme ships that a user edited is reported as custom, with its theme file.
	 */
	public function test_reports_an_edited_theme_template_as_custom_with_a_theme_file(): void {
		switch_theme( self::BLOCK_THEME );
		$this->create_user_template( '404', 'Edited 404' );

		$templates = $this->templates_by_slug( $this->run_ability() );

		self::assertSame( 'custom', $templates['404']['source'] );
		self::assertTrue( $templates['404']['has_theme_file'] );
		self::assertSame( 'Edited 404', $templates['404']['title'] );
		self::assertSame( 'theme', $templates['index']['source'] );
	}

	/**
	 * A template a user created, which the theme does not ship, has no theme file.
	 */
	public function test_reports_a_user_created_template_without_a_theme_file(): void {
		switch_theme( self::BLOCK_THEME );
		$this->create_user_template( 'page-about', 'About' );

		$templates = $this->templates_by_slug( $this->run_ability() );

		self::assertSame( 'custom', $templates['page-about']['source'] );
		self::assertFalse( $templates['page-about']['has_theme_file'] );
		self::assertSame( 'About', $templates['page-about']['title'] );
	}

	/**
	 * Templates are sorted by slug, whether or not a user edited them.
	 */
	public function test_sorts_templates_by_slug(): void {
		switch_theme( self::BLOCK_THEME );
		$this->create_user_template( 'single', 'Single' );

		$slugs  = array_column( $this->run_ability()['templates'], 'slug' );
		$sorted = $slugs;
		sort( $sorted, SORT_STRING );

		self::assertSame( $sorted, $slugs );
		self::assertNotSame( 'single', $slugs[0], 'The edited template is not listed first.' );
	}

	/**
	 * A classic theme is reported as one, without block templates.
	 */
	public function test_reports_a_classic_theme_without_templates(): void {
		switch_theme( self::CLASSIC_THEME );

		$result = $this->run_ability();

		self::assertFalse( $result['block_theme'] );
		self::assertSame( 'Abilities Test Classic Theme', $result['theme'] );
		self::assertSame( array(), $result['templates'] );
	}

	/**
	 * No input and an empty object are both accepted, as clients that always send an object do.
	 */
	public function test_accepts_no_input_and_an_empty_object(): void {
		switch_theme( self::BLOCK_THEME );

		self::assertTrue( $this->run_ability()['block_theme'], 'No input should be accepted.' );
		self::assertTrue( $this->run_ability( array() )['block_theme'], 'An empty object should be accepted.' );
	}

	/**
	 * Input the ability does not take, or input that is not an object, fails closed.
	 */
	public function test_malformed_input_fails_closed(): void {
		$this->log_in_as( 'administrator' );
		$ability = $this->get_registered();

		$this->assert_wp_error( 'ability_invalid_input', $ability->execute( array( 'slug' => '404' ) ) );
		$this->assert_wp_error( 'ability_invalid_input', $ability->execute( 'slug=404' ) );
	}

	/**
	 * The run endpoint runs the ability with GET, as for a read-only ability, rejects other
	 * methods, and serves schemas without a `properties` encoded as a JSON array.
	 */
	public function test_runs_over_rest_with_get(): void {
		switch_theme( self::BLOCK_THEME );
		$this->log_in_as( 'administrator' );

		$route    = '/wp-abilities/v1/abilities/' . self::ABILITY;
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route . '/run' ) );

		self::assertSame( 200, $response->get_status(), 'A GET request should run the ability.' );
		$data = $response->get_data();
		self::assertTrue( $data['block_theme'] );
		self::assertArrayHasKey( '404', $this->templates_by_slug( $data ) );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'POST', $route . '/run' ) );
		self::assertSame( 405, $response->get_status(), 'A POST request should be rejected.' );

		$data = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) )->get_data();
		self::assertStringNotContainsString( '"properties":[]', (string) wp_json_encode( $data['input_schema'] ) );
		self::assertStringNotContainsString( '"properties":[]', (string) wp_json_encode( $data['output_schema'] ) );
	}
}
