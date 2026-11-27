<?php
/**
 * Adapter for the registration arrays used by the WordPress 6.9 info abilities.
 *
 * @package WordPress\Abilities
 */

declare( strict_types = 1 );

namespace WordPress\Abilities\Info;

use WordPress\Abilities\Abstract_Core_Ability;

/**
 * Keeps each original registration together, including values shared by its
 * schemas and callbacks. Core's class-based abilities use separate methods.
 */
abstract class Abstract_Info_Ability extends Abstract_Core_Ability {
	/**
	 * Registration arguments, evaluated once per ability instance.
	 *
	 * @var array<string, mixed>
	 */
	private array $args;

	/**
	 * Returns the arguments from the original wp_register_ability() call.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function registration_args(): array;

	protected function prepare(): void {
		$this->args = $this->registration_args();
	}

	protected function label(): string {
		return $this->args['label'];
	}

	protected function description(): string {
		return $this->args['description'];
	}

	protected function category_slug(): string {
		return $this->args['category'];
	}

	protected function input_schema(): array {
		return $this->args['input_schema'] ?? array();
	}

	protected function output_schema(): array {
		return $this->args['output_schema'];
	}

	protected function make_execute_callback(): callable {
		return $this->args['execute_callback'];
	}

	protected function make_permission_callback(): callable {
		return $this->args['permission_callback'];
	}

	protected function required_capabilities(): array {
		return array();
	}

	protected function ability_meta(): array {
		return $this->args['meta'];
	}
}
