<?php
/**
 * Plugin Name:       EMCP Exposer
 * Description:       Exposes other plugins' Abilities API tools on the EMCP Tools MCP server, so one EMCP connector reaches them. Ships a Rank Math SEO integration; more integrations plug in through the emcp_exposer_integrations filter.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package EMCP_Exposer
 */

defined( 'ABSPATH' ) || exit;

define( 'EMCP_EXPOSER_DIR', __DIR__ . '/' );

require_once EMCP_EXPOSER_DIR . 'includes/class-emcp-exposer-integration.php';
require_once EMCP_EXPOSER_DIR . 'includes/class-emcp-exposer.php';
require_once EMCP_EXPOSER_DIR . 'includes/integrations/class-emcp-exposer-rank-math.php';

add_filter(
	'emcp_exposer_integrations',
	static function ( array $integrations ): array {
		$integrations[] = new EMCP_Exposer_Rank_Math();
		return $integrations;
	},
	0
);

EMCP_Exposer::boot();
