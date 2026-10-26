# View Details companion guide

## Companion-plugin guide — October 25, 2026 (pending release)

The native View Details **Other THRG Plugins** tab lists the other five tools: Disable Other Plugin Notices, Group Password Reset, Plugin Detective, Site Inspector, Post Update Email Notifier and Plugin Archiver, excluding the current plugin. Plugin Archiver always links to https://wordpress.org/plugins/hackrepair-plugin-archiver/; the GitHub-distributed tools link to their own repositories. Cards are informational links, with no automatic installs or external requests. Archiver retains WordPress.org updates and metadata. No published version, tag or release asset is changed by this source update.

Verification: shared PHPCS and `php tests/companion-plugins.php` pass for this checkout. Native WordPress View Details on Zendogs renders five distinct companion cards, with no self-link and Archiver linked only to WordPress.org. Archiver uses `plugins_api_result` for official metadata and preserves ratings/version/update controls; the test used a temporary loader beside the older installed Archiver, not a full upgrade. Temporary preview files are restored after testing; no reset, archive, delete or email action was performed. Published releases remain unchanged.


## 2.5.9 release preparation — October 25, 2026

User approved packaging and publication for the five GitHub-updated plugins. Plugin Archiver is explicitly excluded from this release pass. Version headers/constants, stable tag, README and release notes are aligned at 2.5.9. Release checks and publication results follow below.
