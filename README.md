# Disable Other Plugin Notices

**Groups admin notices from other plugins and themes into one collapsed panel — WordPress core notices stay exactly where they are.**

[![License: GPL v2+](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)](LICENSE)
[![Requires PHP: 7.4](https://img.shields.io/badge/Requires%20PHP-7.4-777bb4.svg)](https://www.php.net/)
[![Requires WordPress: 6.7+](https://img.shields.io/badge/WordPress-6.7%2B-21759b.svg)](https://wordpress.org/)
[![Tested up to: 7.1](https://img.shields.io/badge/Tested%20up%20to-7.1-21759b.svg)](https://wordpress.org/)

Every plugin thinks its notice is the important one. Open `wp-admin` on a busy site and the actual page starts three screens down, under review requests, upgrade offers, setup wizards, and license reminders.

**Disable Other Plugin Notices** takes the notices that come from plugins and themes and puts them in one collapsed panel labeled "Other plugin notices." Nothing is deleted and nothing is silenced — every notice is still there, still complete, still dismissible, one click away. Everything else on the screen moves back up where it belongs.

Notices from WordPress itself are never touched. Core update reminders, PHP version warnings, Site Health results, and settings-saved messages keep their normal position at the top of the screen. That distinction is the whole point of the plugin: the notices that matter for running the site stay in front of you, and the marketing sits in a drawer.

## Demo (Click to View on YouTube)

[![Disable Other Plugin Notices demo](https://img.youtube.com/vi/y45eFF2J6FU/maxresdefault.jpg)](https://youtu.be/y45eFF2J6FU)

*30 seconds: three other-plugin notices go from cluttering the top of the screen to one collapsed panel, leaving WordPress's own notices in place. Click to watch.*

📖 **[Full documentation →](https://hackrepair.github.io/disable-other-plugin-notices/)**

## How it decides what is a core notice

Most notice-hiding plugins work by matching CSS classes or scraping text, which a plugin can dodge by changing its markup and a core notice can get caught by accident.

This plugin hooks in after every plugin has registered its notices and, for each one, asks PHP which file the registered callback was actually declared in, using [Reflection](https://www.php.net/manual/en/book.reflection.php). A callback declared inside `wp-admin/` or `wp-includes/` is WordPress itself and is left alone. For eligible third-party callbacks, the plugin runs the callback on its original hook and buffers its markup for the panel.

This is attribution by origin rather than guesswork about wording or CSS classes, so a plugin cannot avoid the panel by styling its notice to look like a core message, and a core notice cannot be swept up by accident. When the origin of a callback cannot be determined, the notice is left exactly where it is.

The panel's markup is also marked so WordPress core's own `common.js` notice-relocation script (the one that moves `.notice`/`.updated`/`.error` elements to just below the page title) leaves grouped notices where the panel put them, instead of pulling them back out.

## Installation

The plugin isn't on the WordPress.org repository. Instead:

1. Download the [latest release](../../releases) zip, or clone this repository.
2. Upload the `disable-other-plugin-notices` folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins screen.
4. That's it — grouping is active immediately, with no configuration.

To turn grouping off for your own account, open the **Screen Options** tab at the top right of any admin screen that has grouped notices, clear the checkbox, and click **Apply**. The setting is per user, so one administrator switching it off doesn't change what anyone else sees.

## Updates

Since the plugin isn't on WordPress.org, it checks this repository's [releases](../../releases) directly. When a new version is published here with a ZIP named `disable-other-plugin-notices.<version>.zip`, the Plugins screen shows the normal "update available" notice and offers a one-click update. Releases without a matching uploaded ZIP are skipped.

## What it does not do

It does not delete notices, dismiss them on your behalf, write to other plugins' options, collect analytics, add a menu item, add a settings page, add an upsell, or display a notice of its own. It runs only in `wp-admin` and does nothing on the front end. The only outbound request it makes is the periodic update check against this repository, covered in [Updates](#updates) above; nothing about your site's content, users, or configuration is included in it.

## Filters

| Filter | Fires with | Purpose |
| --- | --- | --- |
| `dopn_grouping_enabled` | `bool $enabled, int $user_id` | Override the per-user on/off setting entirely, e.g. to force it on for everyone. |
| `dopn_collapse_notice` | `bool $collapse, string $source, string $hook, int $priority, callable $callback` | Return `false` to keep an exact notice callback in its normal position. The fifth argument is optional for existing filters. |
| `dopn_panel_open` | `bool $open, int $count` | Render the panel expanded by default instead of collapsed. |
| `dopn_show_screen_option` | `bool $show, WP_Screen $screen` | Suppress the Screen Options checkbox on a specific screen. |

Example — keep one important plugin's notices out of the panel:

```php
add_filter( 'dopn_collapse_notice', function ( $collapse, $source ) {
    if ( false !== strpos( $source, '/plugins/my-important-plugin/' ) ) {
        return false;
    }
    return $collapse;
}, 10, 2 );
```

To keep a confirmed security alert visible while grouping promotional notices from the same plugin, identify the alert callback on the target WordPress installation and configure a precise opt-out. For example, in a site-specific mu-plugin (replace `my_security_alert_callback` with the verified callback name):

```php
add_filter( 'dopn_collapse_notice', function ( $collapse, $source, $hook, $priority, $callback ) {
    if ( 'admin_notices' === $hook && 'my_security_alert_callback' === $callback ) {
        return false;
    }
    return $collapse;
}, 10, 5 );
```

For object methods and closures, compare the callable against the original registered callable from the site; a callback name from another plugin version may differ. A source filename or a red notice alone does not identify an urgent security alert. There are no bundled automatic security exceptions; site owners can configure exceptions for alerts confirmed on their installations.

## FAQ

**Does this hide WordPress security or update notices?**
No. Notices printed by WordPress core are never moved.

**Are there notices it cannot catch?**
Yes. The plugin handles standard WordPress notice hooks and `in_admin_header`. It also moves late JavaScript banners that match an explicit selector, including Elementor's conversion banner. Direct output from unrelated hooks and JavaScript banners without a configured selector remain in their usual positions.

**Does it work on multisite?**
Yes. Network admin and user admin screens are handled alongside regular admin screens, and the per-user setting follows the user across the network.

**What data does the plugin store?**
One user meta value per user who changes the setting. Nothing else is written to the database. Uninstalling the plugin deletes that value for every user.

**How can I suggest improvements or report bugs?**
Reach out to Jim Walker, The Hack Repair Guy, by email at [jim (at) hackrepair (dot) com](mailto:jim%40hackrepair%2Ecom).

## Contributors

**[Jim Walker](https://hackrepair.com)** — The Hack Repair Guy — author and maintainer.

## Contributing

Issues and pull requests are welcome.

Run the repository tests with `php .github/tests/test-notice-collector.php` and `php .github/tests/test-updater.php`. The browser watcher test uses `jsdom`: `node .github/tests/test-banner-watcher.js`. The real `WP_Hook` test takes a WordPress checkout path: `php .github/tests/test-real-wp-hook.php /path/to/wordpress`.

## License

GPLv2 or later. See [LICENSE](LICENSE).
