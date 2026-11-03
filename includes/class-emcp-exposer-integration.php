<?php
/**
 * Base class for one exposed plugin.
 *
 * @package EMCP_Exposer
 */

defined( 'ABSPATH' ) || exit;

/**
 * One plugin whose abilities EMCP Exposer puts on the EMCP MCP server.
 *
 * A new integration extends this class, names its plugin's ability
 * namespaces, says when the plugin is active, and registers an instance on the
 * `emcp_exposer_integrations` filter. Override accepts() to keep some of the
 * plugin's abilities off EMCP.
 */
abstract class EMCP_Exposer_Integration {

	/** Stable id, e.g. `rank-math`; passed to the per-integration filters. */
	abstract public function id(): string;

	/** Whether the target plugin is loaded on this request. */
	abstract public function is_active(): bool;

	/**
	 * Ability namespaces the plugin registers, without the trailing slash.
	 *
	 * @return string[]
	 */
	abstract public function namespaces(): array;

	/**
	 * Whether to expose one ability from the plugin's namespaces.
	 *
	 * Default: only abilities the plugin itself opted into MCP
	 * (`meta.mcp.public`, resolved the way the MCP adapter resolves it).
	 *
	 * @param WP_Ability $ability Registered ability.
	 */
	public function accepts( WP_Ability $ability ): bool {
		if ( class_exists( '\WP\MCP\Abilities\McpAbilityExposure' ) ) {
			return \WP\MCP\Abilities\McpAbilityExposure::is_public( $ability );
		}
		$meta = $ability->get_meta();
		return ! empty( $meta['mcp']['public'] );
	}
}
