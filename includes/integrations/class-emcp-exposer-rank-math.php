<?php
/**
 * Rank Math SEO integration.
 *
 * @package EMCP_Exposer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rank Math SEO registers its MCP tools as `rank-math/*` abilities (1.0.271+)
 * on init:0 and marks every one `meta.mcp.public`.
 */
final class EMCP_Exposer_Rank_Math extends EMCP_Exposer_Integration {

	public function id(): string {
		return 'rank-math';
	}

	public function is_active(): bool {
		return defined( 'RANK_MATH_VERSION' );
	}

	public function namespaces(): array {
		return array( 'rank-math' );
	}
}
