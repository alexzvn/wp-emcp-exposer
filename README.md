# EMCP Exposer

Puts other plugins' Abilities API tools on the EMCP Tools MCP server (`/wp-json/mcp/emcp-tools-server`), so one EMCP connector reaches them. No changes to EMCP or to the exposed plugins.

Ships one integration: **Rank Math SEO** (every `rank-math/*` ability marked `meta.mcp.public`, 29 on Rank Math 1.0.280).

## How it works

EMCP builds its tool list once, inside its `wp_abilities_api_init` callback, and passes it through the public `emcp_tools_ability_names` filter. The exposer:

1. Moves EMCP's callback from priority 10 to 100. Plugins that register abilities at priority 10 (Rank Math does) are then already in the registry.
2. Hooks `emcp_tools_ability_names` at priority 5. For each active integration it appends every accepted ability in that integration's namespaces.

Both EMCP surfaces read that list: the full tool list and compact mode (`list-tools` / `get-tool-schema` / `call-tool`). EMCP's disabled-tools setting still applies, because it runs at priority 10, after the exposer. Each exposed ability keeps its own `permission_callback`.

## Adding an integration

Extend `EMCP_Exposer_Integration` and register an instance:

```php
final class My_Exposer_Integration extends EMCP_Exposer_Integration {
	public function id(): string { return 'my-plugin'; }
	public function is_active(): bool { return defined( 'MY_PLUGIN_VERSION' ); }
	public function namespaces(): array { return array( 'my-plugin' ); }

	// Optional. By default only abilities with meta.mcp.public are exposed.
	public function accepts( WP_Ability $ability ): bool {
		return parent::accepts( $ability ) && 'my-plugin/dangerous' !== $ability->get_name();
	}
}

add_filter( 'emcp_exposer_integrations', function ( array $integrations ) {
	$integrations[] = new My_Exposer_Integration();
	return $integrations;
} );
```

For a built-in integration, put the class in `includes/integrations/` and register it in `emcp-exposer.php`, the same way Rank Math is registered.

## Filters

| Filter | Arguments | Use |
| --- | --- | --- |
| `emcp_exposer_integrations` | `EMCP_Exposer_Integration[]` | Add, replace or remove integrations |
| `emcp_exposer_ability_names` | `string[] $names, string $id` | Drop names from one integration (e.g. Rank Math's `set-*` writers) |

## Hiding tools

EMCP's admin Tools tab may not list the exposed tools, so turn them off with code or WP-CLI instead:

```php
// Keep Rank Math's settings writers off EMCP.
add_filter( 'emcp_exposer_ability_names', function ( array $names, string $id ) {
	return 'rank-math' === $id
		? array_values( array_filter( $names, fn( $n ) => ! str_starts_with( $n, 'rank-math/set-' ) ) )
		: $names;
}, 10, 2 );
```

Or add names to EMCP's disabled-tools option (note: this replaces the option's current value):

```bash
wp option update emcp_tools_disabled_tools '["rank-math/set-module-status"]' --format=json
```

## Rank Math notes

- Rank Math only registers its abilities once setup is complete: the site is connected to a Rank Math account, or registration was skipped in the wizard. On a fresh install that hasn't done either, it exposes nothing.
- Tool names appear as `rank-math-<name>` in `tools/list`, because the MCP adapter sanitizes `/` in tool names. Compact mode uses the original `rank-math/<name>`.
- With these tools on EMCP, you don't need a separate Rank Math connector. That sidesteps the OAuth discovery conflict, where EMCP owns the root `/.well-known/oauth-*` documents.
