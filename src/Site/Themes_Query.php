<?php
/**
 * Experimental read-only theme inventory.
 *
 * @package WordPress\Abilities
 */

declare( strict_types = 1 );

namespace WordPress\Abilities\Site;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Themes_Controller;

/** Lists installed themes visible on the current site. */
final class Themes_Query extends Abstract_Inventory_Query {
	protected const COLLECTION = 'themes';
	protected const IDENTIFIER = 'stylesheet';
	protected const FIELDS     = array( 'stylesheet', 'template', 'name', 'version', 'status' );

	/** @inheritDoc */
	protected function label(): string {
		return __( 'Query Installed Themes' );
	}

	/** @inheritDoc */
	protected function description(): string {
		return __( 'List visible installed themes, optionally filtered by status or exact stylesheet. The template identifies the parent of a child theme.' );
	}

	/** @inheritDoc */
	protected function controller(): WP_REST_Controller {
		return new WP_REST_Themes_Controller();
	}

	/** @inheritDoc */
	protected function request( array $input ): WP_REST_Request {
		// Core permits editors to read the active theme without listing other themes.
		if ( ! isset( $input['status'] ) && isset( $input['stylesheet'] ) && get_stylesheet() === $input['stylesheet'] ) {
			$input['status'] = 'active';
		}
		return parent::request( $input );
	}

	/** @inheritDoc */
	protected function project_item( array $item ): array {
		$item['name'] = $item['name']['raw'];
		return parent::project_item( $item );
	}
}
