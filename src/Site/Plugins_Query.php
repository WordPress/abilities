<?php
/**
 * Experimental read-only plugin inventory.
 *
 * @package WordPress\Abilities
 */

declare( strict_types = 1 );

namespace WordPress\Abilities\Site;

use WP_REST_Controller;
use WP_REST_Plugins_Controller;

/** Lists installed plugins visible on the current site. */
final class Plugins_Query extends Abstract_Inventory_Query {
	protected const COLLECTION = 'plugins';
	protected const IDENTIFIER = 'plugin';
	protected const FIELDS     = array( 'plugin', 'name', 'version', 'status' );

	/** @inheritDoc */
	protected function label(): string {
		return __( 'Query Installed Plugins' );
	}

	/** @inheritDoc */
	protected function description(): string {
		return __( 'List visible installed plugins, optionally filtered by activation status or exact plugin identifier (the plugin file relative to the plugins directory, without the final .php).' );
	}

	/** @inheritDoc */
	protected function controller(): WP_REST_Controller {
		return new WP_REST_Plugins_Controller();
	}

	/** @inheritDoc */
	protected function statuses(): array {
		$statuses = parent::statuses();
		if ( is_multisite() ) {
			$statuses[] = 'network-active';
		}
		return $statuses;
	}
}
