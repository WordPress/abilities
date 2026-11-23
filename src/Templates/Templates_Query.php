<?php
/**
 * Canonical WordPress abilities: read-only templates query ability.
 *
 * Ported from WordPress/ai PR #1309 (https://github.com/WordPress/ai/pull/1309),
 * on this package's Abstract_Core_Ability authoring surface. The PR's
 * `core/templates-query` name is not copied: the ability ships under the
 * experimental tier name `wp/templates-query` (see NAME-LIFECYCLE.md; the
 * name-lifecycle rule carries no aliases). Everything else mirrors the PR as
 * written: label, description, schemas, annotations, and the
 * `edit_theme_options` capability floor.
 *
 * The registration entry points of the PR (its init()/register() wiring and
 * the stand-down when a copy is already registered) are not ported:
 * registration is owned by the package's Registrar. The PR's `public` meta
 * flag is replaced by `mcp.public`, as on the package's other `wp/` read
 * abilities. No plugin helpers, no experiment flags, no AI plumbing: the
 * class is core-landing-ready and self-contained.
 *
 * @package WordPress\Abilities
 */
declare( strict_types = 1 );

namespace WordPress\Abilities\Templates;

use WP_Block_Template;

use WordPress\Abilities\Abstract_Core_Ability;

/**
 * Registers the read-only `wp/templates-query` ability. It reports whether the active
 * theme is a block theme, and lists the block templates the site has for that theme:
 * where each one comes from, and whether the theme ships it as a file.
 *
 * An agent can call it before proposing a change to how a page looks, instead of finding
 * out from an error that the theme does not support block templates or that a template
 * has already been edited.
 *
 * Only templates (`wp_template`) are listed, not template parts.
 *
 * @package WordPress\Abilities
 */
final class Templates_Query extends Abstract_Core_Ability {

	/**
	 * The ability category used for the templates ability.
	 *
	 * @var string
	 */
	private const CATEGORY = 'site';

	/**
	 * The canonical ability name, under the experimental tier prefix.
	 *
	 * @return string
	 */
	protected function ability_name(): string {
		return 'wp/templates-query';
	}

	/**
	 * The ability label, as the source PR registers it.
	 *
	 * @return string
	 */
	protected function label(): string {
		return __( 'Query Templates' );
	}

	/**
	 * The ability description, as the source PR registers it.
	 *
	 * @return string
	 */
	protected function description(): string {
		return __( 'Returns whether the active theme is a block theme, its name, and the block templates the site has for it. Each template has its slug, its title, where its current version comes from (theme, custom or plugin), and whether the theme ships it as a file. A template with the source "custom" has been created or edited by a user. Template parts are not included. Requires an authenticated user who can edit theme options.' );
	}

	/**
	 * The ability category, the core-registered site category.
	 *
	 * @return string
	 */
	protected function category_slug(): string {
		return self::CATEGORY;
	}

	/**
	 * The input JSON Schema: the ability takes no input.
	 *
	 * An empty object is accepted, so a client that always sends an object can call it.
	 *
	 * The schema has no `properties` key: an empty PHP array is encoded as the JSON array
	 * `[]`, which JSON Schema validators such as the one in `@wordpress/abilities` reject.
	 *
	 * @return array<string, mixed> The input JSON Schema.
	 */
	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'default'              => array(),
			'additionalProperties' => false,
		);
	}

	/**
	 * The output JSON Schema.
	 *
	 * @return array<string, mixed> The output JSON Schema.
	 */
	protected function output_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'block_theme' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the active theme is a block theme. Block templates can only be used with a block theme.' ),
				),
				'theme'       => array(
					'type'        => 'string',
					'description' => __( 'The name of the active theme.' ),
				),
				'templates'   => array(
					'type'        => 'array',
					'description' => __( 'The block templates the site has for the active theme, sorted by slug.' ),
					'items'       => $this->get_template_schema(),
				),
			),
			'required'             => array( 'block_theme', 'theme', 'templates' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * The execute callback.
	 *
	 * @return callable(mixed): array<string, mixed> The execute callback.
	 */
	protected function make_execute_callback(): callable {
		return function (): array {
			return $this->execute_templates_query();
		};
	}

	/**
	 * No permission callback: the `edit_theme_options` floor from
	 * required_capabilities() owns authorization.
	 *
	 * @return null No permission callback.
	 */
	protected function make_permission_callback(): ?callable {
		return null;
	}

	/**
	 * The authored ability meta.
	 *
	 * @return array<string, mixed> The ability meta.
	 */
	protected function ability_meta(): array {
		return array(
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
				// MCP clients assume open-world (may reach external systems) when the
				// hint is absent; this ability only reads the local site.
				'open_world'  => false,
			),
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
			),
		);
	}

	/**
	 * The request-level capability floor: the capability the Site Editor requires.
	 *
	 * Templates change how the whole site looks, so reading them is gated on the
	 * same capability as editing them.
	 *
	 * @return list<string> The required capabilities.
	 */
	protected function required_capabilities(): array {
		return array( 'edit_theme_options' );
	}

	/**
	 * Executes the `wp/templates-query` ability.
	 *
	 * @return array{block_theme: bool, theme: string, templates: list<array{slug: string, title: string, source: string, has_theme_file: bool}>} The theme and its templates.
	 */
	private function execute_templates_query(): array {
		return array(
			'block_theme' => wp_is_block_theme(),
			'theme'       => (string) wp_get_theme()->get( 'Name' ),
			'templates'   => $this->get_templates(),
		);
	}

	/**
	 * Returns the active theme's templates, sorted by slug.
	 *
	 * Sorted so the order does not depend on which templates a user has edited:
	 * get_block_templates() lists edited templates before the theme's files.
	 *
	 * @return list<array{slug: string, title: string, source: string, has_theme_file: bool}> The templates.
	 */
	private function get_templates(): array {
		$templates = array_map( array( $this, 'format_template' ), get_block_templates( array(), 'wp_template' ) );

		usort(
			$templates,
			static function ( array $a, array $b ): int {
				return strcmp( $a['slug'], $b['slug'] );
			}
		);

		return $templates;
	}

	/**
	 * Formats one template for the ability output.
	 *
	 * @param WP_Block_Template $template The template.
	 * @return array{slug: string, title: string, source: string, has_theme_file: bool} The template fields the ability returns.
	 */
	private function format_template( WP_Block_Template $template ): array {
		return array(
			'slug'           => (string) $template->slug,
			'title'          => (string) $template->title,
			'source'         => (string) $template->source,
			'has_theme_file' => (bool) $template->has_theme_file,
		);
	}

	/**
	 * Builds the schema of one template in the output.
	 *
	 * The source is not limited to an enum: a template's source comes from core and from
	 * filters, and an unexpected value should not fail the whole response.
	 *
	 * @return array<string, mixed> The template JSON Schema.
	 */
	private function get_template_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'slug'           => array(
					'type'        => 'string',
					'description' => __( 'The template slug, such as "404", "single" or "page-about".' ),
				),
				'title'          => array(
					'type'        => 'string',
					'description' => __( 'The template title.' ),
				),
				'source'         => array(
					'type'        => 'string',
					'description' => __( 'Where the current version of the template comes from: "theme" for the theme\'s file, "custom" for a version a user created or edited, or "plugin" for a template a plugin registered.' ),
				),
				'has_theme_file' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the active theme ships this template as a file. When a "custom" template has a theme file, resetting it in the Site Editor brings back the theme\'s version.' ),
				),
			),
			'required'             => array( 'slug', 'title', 'source', 'has_theme_file' ),
			'additionalProperties' => false,
		);
	}
}
