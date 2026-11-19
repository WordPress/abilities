<?php
/**
 * Shared collection handling for the experimental site inventory queries.
 *
 * @package WordPress\Abilities
 */

declare( strict_types = 1 );

namespace WordPress\Abilities\Site;

use WordPress\Abilities\Abstract_Core_Ability;
use WP_REST_Controller;
use WP_REST_Request;

/**
 * Projects the current site's visible REST collection into a small inventory.
 */
abstract class Abstract_Inventory_Query extends Abstract_Core_Ability {
	protected const COLLECTION = '';
	protected const IDENTIFIER = '';
	protected const FIELDS     = array();

	/**
	 * Returns the Core controller responsible for collection and row visibility.
	 *
	 * @return WP_REST_Controller
	 */
	abstract protected function controller(): WP_REST_Controller;

	/** @inheritDoc */
	protected function ability_name(): string {
		return 'wp/' . static::COLLECTION . '-query';
	}

	/** @inheritDoc */
	protected function category_slug(): string {
		return 'site';
	}

	/**
	 * Status values available on the current installation.
	 *
	 * @return string[]
	 */
	protected function statuses(): array {
		return array( 'active', 'inactive' );
	}

	/** @inheritDoc */
	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'default'              => array(),
			'properties'           => array(
				static::IDENTIFIER => array(
					'type'      => 'string',
					'minLength' => 1,
				),
				'status'           => array(
					'type' => 'string',
					'enum' => $this->statuses(),
				),
			),
			'additionalProperties' => false,
		);
	}

	/** @inheritDoc */
	protected function output_schema(): array {
		$fields           = array_fill_keys( static::FIELDS, array( 'type' => 'string' ) );
		$fields['status'] = array(
			'type' => 'string',
			'enum' => $this->statuses(),
		);

		return array(
			'type'                 => 'object',
			'properties'           => array(
				static::COLLECTION => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'properties'           => $fields,
						'required'             => static::FIELDS,
						'additionalProperties' => false,
					),
				),
				'total'            => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
			),
			'required'             => array( static::COLLECTION, 'total' ),
			'additionalProperties' => false,
		);
	}

	/** @inheritDoc */
	protected function ability_meta(): array {
		return array(
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
				'open_world'  => false,
			),
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
		);
	}

	/** @inheritDoc */
	protected function required_capabilities(): array {
		// The controller selects the capability for the requested inventory.
		return array();
	}

	/**
	 * Build the same read request for the permission and execution callbacks.
	 *
	 * @param array $input Inventory filters.
	 * @return WP_REST_Request
	 */
	protected function request( array $input ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/wp/v2/' . static::COLLECTION );
		$request->set_param( 'context', 'view' );
		$request->set_param( '_fields', implode( ',', static::FIELDS ) );
		if ( isset( $input['status'] ) ) {
			$request->set_param( 'status', array( $input['status'] ) );
		}
		return $request;
	}

	/** @inheritDoc */
	protected function make_permission_callback(): callable {
		return function ( $input = array() ) {
			// Direct permission checks can receive input before the default is applied.
			$input = $input ?? array();
			if ( ! is_array( $input ) || is_wp_error( $this->validate_input( $input ) ) ) {
				return false;
			}
			return true === $this->controller()->get_items_permissions_check( $this->request( $input ) );
		};
	}

	/**
	 * Select the fields promised by this ability, including on filtered REST responses.
	 *
	 * @param array $item Core REST collection item.
	 * @return array
	 */
	protected function project_item( array $item ): array {
		return array_intersect_key( $item, array_flip( static::FIELDS ) );
	}

	/** @inheritDoc */
	protected function make_execute_callback(): callable {
		return function ( $input = array() ) {
			$response = $this->controller()->get_items( $this->request( $input ) );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$items = array();
			foreach ( $response->get_data() as $item ) {
				if ( isset( $input[ static::IDENTIFIER ] ) && $input[ static::IDENTIFIER ] !== $item[ static::IDENTIFIER ] ) {
					continue;
				}
				$items[] = $this->project_item( $item );
			}
			return array(
				static::COLLECTION => $items,
				'total'            => count( $items ),
			);
		};
	}
}
