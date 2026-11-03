<?php
/**
 * Adds integrations' abilities to the EMCP MCP server.
 *
 * @package EMCP_Exposer
 */

defined( 'ABSPATH' ) || exit;

/**
 * EMCP collects its tool names once, inside its own `wp_abilities_api_init`
 * callback, and passes them through the public `emcp_tools_ability_names`
 * filter. EMCP hooks that callback on plugins_loaded:20 at priority 10; most
 * plugins hook their ability registrations later at the same priority (Rank
 * Math on init:0), so EMCP would run first and their abilities would not exist
 * yet when the filter fires. The exposer moves EMCP's callback to
 * COLLECT_PRIORITY, then appends every accepted ability of each active
 * integration from the live registry.
 *
 * Both EMCP surfaces read that one name list: the full tool list and compact
 * mode (list-tools / get-tool-schema / call-tool). Every ability keeps its own
 * permission_callback.
 */
final class EMCP_Exposer {

	/** Priority EMCP's ability collection moves to: after default-priority registrations. */
	const COLLECT_PRIORITY = 100;

	/** Before EMCP's filter_disabled_tools (priority 10), so EMCP's disabled-tools option still applies. */
	const FILTER_PRIORITY = 5;

	/** Wires the hooks. */
	public static function boot(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'defer_emcp_collection' ), 100 );
		add_filter( 'emcp_tools_ability_names', array( __CLASS__, 'add_ability_names' ), self::FILTER_PRIORITY );
	}

	/**
	 * Active integrations, from the `emcp_exposer_integrations` filter.
	 *
	 * @return EMCP_Exposer_Integration[]
	 */
	public static function integrations(): array {
		/**
		 * Filters the integrations whose abilities are exposed on EMCP.
		 *
		 * @param EMCP_Exposer_Integration[] $integrations Integration instances.
		 */
		$integrations = (array) apply_filters( 'emcp_exposer_integrations', array() );

		return array_values(
			array_filter(
				$integrations,
				static function ( $integration ) {
					return $integration instanceof EMCP_Exposer_Integration && $integration->is_active();
				}
			)
		);
	}

	/**
	 * Re-hooks EMCP's `register_abilities` callback at COLLECT_PRIORITY.
	 *
	 * Found in the hook table rather than through EMCP_Tools_Plugin::instance(),
	 * so an EMCP build that skipped booting is never initialised from here.
	 */
	public static function defer_emcp_collection(): void {
		global $wp_filter;

		if ( did_action( 'wp_abilities_api_init' ) || ! isset( $wp_filter['wp_abilities_api_init'] ) ) {
			return;
		}

		foreach ( $wp_filter['wp_abilities_api_init']->callbacks as $priority => $callbacks ) {
			if ( $priority >= self::COLLECT_PRIORITY ) {
				continue;
			}
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];
				if ( is_array( $function )
					&& $function[0] instanceof EMCP_Tools_Plugin
					&& 'register_abilities' === $function[1] ) {
					remove_action( 'wp_abilities_api_init', $function, $priority );
					add_action( 'wp_abilities_api_init', $function, self::COLLECT_PRIORITY );
					return;
				}
			}
		}
	}

	/**
	 * Appends the integrations' abilities to EMCP's name list.
	 *
	 * @param mixed $names Ability names collected by EMCP.
	 * @return mixed
	 */
	public static function add_ability_names( $names ) {
		if ( ! is_array( $names ) || ! function_exists( 'wp_get_abilities' ) ) {
			return $names;
		}

		$integrations = self::integrations();
		if ( empty( $integrations ) ) {
			return $names;
		}

		$found = array();
		foreach ( $integrations as $integration ) {
			$found[ $integration->id() ] = array();
		}

		foreach ( wp_get_abilities() as $ability ) {
			$namespace = strstr( $ability->get_name(), '/', true );
			if ( false === $namespace ) {
				continue;
			}
			foreach ( $integrations as $integration ) {
				if ( in_array( $namespace, $integration->namespaces(), true ) && $integration->accepts( $ability ) ) {
					$found[ $integration->id() ][] = $ability->get_name();
					break;
				}
			}
		}

		foreach ( $found as $id => $integration_names ) {
			/**
			 * Filters one integration's ability names before they join EMCP.
			 *
			 * EMCP's own disabled-tools option still applies afterwards.
			 *
			 * @param string[] $integration_names Accepted ability names.
			 * @param string   $id                Integration id, e.g. `rank-math`.
			 */
			$integration_names = (array) apply_filters( 'emcp_exposer_ability_names', $integration_names, $id );
			$names             = array_merge( $names, $integration_names );
		}

		return array_values( array_unique( $names ) );
	}
}
