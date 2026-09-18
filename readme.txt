=== Disable Other Plugin Notices ===
Contributors: hackrepair
Tags: admin notices, notices, dashboard, admin, clutter
Requires at least: 6.7
Tested up to: 8.3.0
Requires PHP: 7.4
Stable tag: 2.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Groups admin notices from other plugins and themes into one collapsed panel, while WordPress core notices stay where they are.

== Description ==

Every plugin thinks its notice is the important one. Open wp-admin on a busy site and the actual page starts three screens down, under review requests, upgrade offers, setup wizards, and license reminders.

Disable Other Plugin Notices takes the notices that come from plugins and themes and puts them in one collapsed panel labeled "Other plugin notices." Nothing is deleted and nothing is silenced. The notices are still there, still complete, still dismissible, one click away in the panel. Everything else on the screen moves back up where it belongs.

Notices from WordPress itself are never touched. Core update reminders, PHP version warnings, Site Health results, and settings-saved messages keep their normal position at the top of the screen. That distinction is the whole point of the plugin: the notices that matter for running the site stay in front of you, and the marketing sits in a drawer.

= How it decides what is a core notice =

When an admin page is about to print its notices, the plugin asks PHP which file each registered notice callback was written in. A callback declared inside wp-admin or wp-includes is WordPress itself and is left alone. A callback declared anywhere else, such as a plugin folder, a theme, an mu-plugin, or a drop-in, is moved into the panel.

This is attribution by origin rather than guesswork about wording or CSS classes, so a plugin cannot avoid the panel by styling its notice to look like a core message, and a core notice cannot be swept up by accident. When the origin of a callback cannot be determined, the notice is left exactly where it is.

= Per user, not per site =

Grouping is on the moment the plugin is activated. Any user who prefers the old behavior can turn it off for their own account in the Screen Options tab, on any screen where a notice was grouped. The setting is stored per user, so one administrator switching it off does not change what anyone else sees.

= What it does not do =

It does not delete notices, dismiss them on your behalf, write to other plugins' options, phone home, collect analytics, add a menu item, add a settings page, add an upsell, or display a notice of its own. It runs only in wp-admin and does nothing on the front end.

= For developers =

Four filters are available:

* `dopn_grouping_enabled` - override the per-user setting.
* `dopn_collapse_notice` - keep an individual notice in its normal position.
* `dopn_panel_open` - render the panel expanded instead of collapsed.
* `dopn_show_screen_option` - suppress the Screen Options checkbox on a specific screen.

== Installation ==

1. Upload the `disable-other-plugin-notices` folder to `/wp-content/plugins/`, or install the plugin through the Plugins screen in WordPress.
2. Activate the plugin through the Plugins screen.
3. That is all. Grouping is active immediately, with no configuration.

To turn grouping off for your own account, open the Screen Options tab at the top right of any admin screen that has grouped notices, clear the "Group notices from other plugins and themes into one panel" checkbox, and click Apply.

== Frequently Asked Questions ==

= Does this hide WordPress security or update notices? =

No. Notices printed by WordPress core are never moved. Core update nags, PHP version warnings, Site Health criticals, and the messages WordPress prints after you save settings all stay in their normal place at the top of the screen.

= Where do the other notices go? =

Into a collapsed panel at the top of the same screen, in the order WordPress would have printed them. Click the panel to expand it. The notices inside keep their own markup, styling, dismiss buttons, and links, so anything you could do with a notice before you can still do inside the panel.

= Will a plugin's setup wizard or activation notice still work? =

Yes. The notice is printed a fraction of a second later than it otherwise would have been and in a different spot on the page. Its buttons and links behave exactly as before.

= Are there notices it cannot catch? =

Yes, and this is worth knowing before you install. The plugin works with notices registered on the standard WordPress notice hooks, which is how the overwhelming majority are printed. A plugin that echoes its notice directly into the page from some other hook, or that injects one with JavaScript after the page loads, is printing outside the system and will keep appearing in its usual place.

= Does it work on multisite? =

Yes. Network admin screens and user admin screens are handled alongside regular admin screens, and the per-user setting follows the user across the network.

= Why does it say "Other plugin notices" when a notice was from a theme? =

The wording matches what site owners call the problem. Themes print notices far less often than plugins do, and adding "and theme" to every label takes space without adding clarity. The plugin handles theme notices identically.

= What if I want a specific notice to stay where it was? =

Use the `dopn_collapse_notice` filter:

`add_filter( 'dopn_collapse_notice', function( $collapse, $source, $hook, $priority ) {
    if ( false !== strpos( $source, 'woocommerce' ) ) {
        return false;
    }
    return $collapse;
}, 10, 2 );`

= What data does the plugin store? =

One user meta value per user who changes the setting, recording whether grouping is on. Nothing else is written to the database. Uninstalling the plugin deletes that value for every user.

= How do updates work? =

This plugin is not distributed on the WordPress.org repository, so it checks its own GitHub releases instead. When a new version is published there, the Plugins screen shows an "update available" notice and offers a one-click update, the same as any WordPress.org plugin. The only outbound request this adds is the periodic check against GitHub for a newer release; nothing about your site's content, users, or configuration is sent.

= How can I suggest improvements or report bugs? =

Reach out to Jim Walker, The Hack Repair Guy, by email at [jim (at) hackrepair (dot) com](mailto:jim%40hackrepair%2Ecom).

== Changelog ==

= 2.2.1 =
* Added: A branded header image for the WordPress View Details screen in standard and Retina sizes.
* Changed: Compatibility metadata now reports WordPress 8.3.0 exactly instead of the update checker's 8.3.999 fallback.
* Documentation: Added an FAQ contact for suggesting improvements or reporting bugs, with an obfuscated email link.
* Documentation: Standardized PHP requirement displays on `Requires PHP: 7.4`.

= 2.2.0 =
* Added: A lightweight client-side watcher that catches promo banners a plugin builds and inserts with its own JavaScript after the page has loaded, instead of printing them through a WordPress action hook. Elementor's "Go Pro, Go Limitless" banner turned out to be exactly this case: its markup is assembled and inserted by e-conversion-banner.min.js next to the page title, not printed via in_admin_header as the banner's PHP registration suggested, which is why the 2.1.0 fix below did not catch it. This banner is now grouped like any other.
* For developers: Added the `dopn_js_late_banner_selectors` filter to add more CSS selectors for this client-side watcher to catch.

= 2.1.0 =
* Fixed: some plugins print a promotional banner directly into the in_admin_header hook instead of admin_notices, specifically so it renders above the notice area where a grouping plugin would not see it (for example, Elementor's "Go Pro" banner on the Plugins screen). This plugin now applies the same attribution it already uses for notices to in_admin_header itself, so those banners are grouped too.

= 2.0.0 =
* Added: Automatic update checks against this plugin's GitHub releases, so the Plugins screen now shows an "update available" notice and one-click update, the same as a WordPress.org plugin.
* Documentation: Listed the `dopn_show_screen_option` filter under "For developers" (it has existed since 1.0.0 but was missing from this list).

= 1.0.2 =
* Fixed: Supported single-quoted HTML class attributes when marking notices with `below-h2` so core's relocation script skips them.
* Enhanced: Wrapped notice callback replay in `Throwable` error handling to prevent broken third-party notices from crashing the admin or breaking output buffers.
* Enhanced: Added canonical path resolution for symlinked WordPress roots, Bedrock deployments, and Composer-managed plugins.
* Code Quality: Moved the PHPCS output escaping suppression comment above the echo statement for compatibility with static code analyzers.

= 1.0.1 =
* Fixed: a grouped notice could be pulled back out of the panel by WordPress core's own admin JavaScript, which relocates any `.notice`, `.updated`, or `.error` element to just below the page title on screens that have a `.wp-header-end` marker (nearly every list and edit screen). The panel's notice count was still correct; only the visible list was affected. Notices are now marked so core's relocation script skips them, leaving their own styling and dismiss buttons untouched.

= 1.0.0 =
* Initial release.
* Groups notices from plugins, themes, mu-plugins, and drop-ins into one collapsed panel on every admin screen.
* Leaves notices printed by WordPress core in their normal position.
* Adds a per-user on/off checkbox to the Screen Options tab.
* Adds the `dopn_grouping_enabled`, `dopn_collapse_notice`, and `dopn_panel_open` filters.

== Upgrade Notice ==

= 2.2.1 =
Adds the branded View Details header and improves compatibility and support information.

= 2.2.0 =
Catches promo banners built and inserted by a plugin's own JavaScript after the page loads (Elementor's "Go Pro" banner on the Plugins screen is one of these) which the hook-based grouping in 2.1.0 could not see.

= 2.1.0 =
Groups promotional banners that some plugins print via in_admin_header instead of the normal notice hooks (for example, Elementor's "Go Pro" banner), which previously stayed visible even with grouping on.

= 2.0.0 =
Adds automatic update checks against GitHub releases. After this update, future releases can be installed with the normal one-click WordPress updater instead of a manual re-upload.

= 1.0.2 =
Adds single-quote attribute compatibility for core notice relocation, resilience against broken third-party callbacks, and symlink path resolution.

= 1.0.1 =
Fixes grouped notices sometimes reappearing outside the panel due to WordPress core's own notice-relocation script. Update recommended.

= 1.0.0 =
First release.
