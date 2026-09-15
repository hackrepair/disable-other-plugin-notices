=== Disable Other Plugin Notices ===
Contributors: hackrepair
Tags: admin notices, notices, dashboard, admin, clutter
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
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

Three filters are available:

* `dopn_grouping_enabled` - override the per-user setting.
* `dopn_collapse_notice` - keep an individual notice in its normal position.
* `dopn_panel_open` - render the panel expanded instead of collapsed.

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

= Can I keep one particular notice out of the panel? =

Yes, with the `dopn_collapse_notice` filter. It receives the file the notice callback was declared in, so you can leave a single plugin's notices in place:

`add_filter( 'dopn_collapse_notice', function ( $collapse, $source ) {
    if ( false !== strpos( $source, '/plugins/my-important-plugin/' ) ) {
        return false;
    }
    return $collapse;
}, 10, 2 );`

= What data does the plugin store? =

One user meta value per user who changes the setting, recording whether grouping is on. Nothing else is written to the database. Uninstalling the plugin deletes that value for every user.

== Changelog ==

= 1.0.1 =
* Fixed: a grouped notice could be pulled back out of the panel by WordPress core's own admin JavaScript, which relocates any `.notice`, `.updated`, or `.error` element to just below the page title on screens that have a `.wp-header-end` marker (nearly every list and edit screen). The panel's notice count was still correct; only the visible list was affected. Notices are now marked so core's relocation script skips them, leaving their own styling and dismiss buttons untouched.

= 1.0.0 =
* Initial release.
* Groups notices from plugins, themes, mu-plugins, and drop-ins into one collapsed panel on every admin screen.
* Leaves notices printed by WordPress core in their normal position.
* Adds a per-user on/off checkbox to the Screen Options tab.
* Adds the `dopn_grouping_enabled`, `dopn_collapse_notice`, and `dopn_panel_open` filters.

== Upgrade Notice ==

= 1.0.1 =
Fixes grouped notices sometimes reappearing outside the panel due to WordPress core's own notice-relocation script. Update recommended.

= 1.0.0 =
First release.
