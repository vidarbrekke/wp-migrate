# MK WooCommerce Plugin Starter

Hooks-first, class-based, theme-agnostic starter for building WordPress + WooCommerce plugins (DRY & YAGNI).

## Quick Start
1. Copy this folder to `wp-content/plugins/mk-wc-plugin-starter`.
2. Run `composer dump-autoload -o` inside the plugin folder.
3. Activate **MK WooCommerce Plugin Starter** in WP Admin → Plugins.
4. Open **Settings → MK WC Starter** to toggle the sample setting.

## Structure
- `mk-wc-plugin-starter.php` — bootstrap, constants, autoload, boot.
- `src/` — namespaced services (hooks-first). Add new services and register them in `Plugin::register_services()`.
- `assets/` — minimal CSS/JS, enqueued only when needed.
- `languages/` — text domain placeholder.

## Conventions
- PSR-4 autoloading (`MK\WcPluginStarter\` → `/src`).
- No globals; each service implements `Contracts\Registrable::register()`.
- Security: sanitize inputs, escape outputs, use nonces and capability checks.
- Performance: enqueue conditionally, cache expensive work, batch long tasks.

## Extend
- Create a class under `src/Feature/YourFeature.php` implementing `Registrable`.
- Instantiate in `Plugin::register_services()`.
- Add actions/filters in `register()`.
