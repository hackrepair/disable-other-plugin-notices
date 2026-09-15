# Disable Other Plugin Notices - Updates Roadmap

## Overview
This document tracks quality, compatibility, and robustness improvements for the **Disable Other Plugin Notices** WordPress plugin (v1.0.1 -> v1.0.2). These updates address edge cases identified during static analysis and code review with `wp-plugin-modernization`.

---

## Roadmap of Updates

### Item 1: Core Relocation Guard Attribute Quote Support
- **Target File**: `includes/class-dopn-notice-collector.php`
- **Issue**: WordPress core's `wp-admin/js/common.js` relocates any element matching `div.updated, div.error, div.notice` unless it has the `.below-h2` class. The existing method `guard_against_core_relocation()` only matches double-quoted class attributes (`class="..."`). If a third-party plugin uses single quotes (`class='...'`), `.below-h2` is not appended, allowing core's jQuery script to yank the notice out of the panel.
- **Solution**: Update the regex in `guard_against_core_relocation()` to match both single (`'`) and double (`"`) quotes using a backreference `(["'])(.*?)\2`.

### Item 2: Output Buffer & Execution Safety with `Throwable` Handling
- **Target File**: `includes/class-dopn-notice-collector.php`
- **Issue**: In `render()`, captured notice callbacks are invoked using `call_user_func( $entry['function'] )`. If any third-party plugin's notice callback throws a PHP 7/8 `Throwable` (fatal error or exception), the script terminates abruptly, leaving output buffers hanging and crashing the entire `wp-admin` page.
- **Solution**: Wrap the execution of each callback in a `try ... catch ( Throwable $e )` block. If an error occurs, cleanly unwind the output buffer for that specific callback, log the error when `WP_DEBUG` is active, and allow the remaining notices and the rest of `wp-admin` to render safely.

### Item 3: Symlink & Canonical Path Normalization for Roots Bedrock / Composer
- **Target File**: `includes/class-dopn-notice-collector.php`
- **Issue**: PHP's `ReflectionFunction::getFileName()` and `ReflectionMethod::getFileName()` return the canonical path with symlinks resolved. On modern deployment architectures (Roots Bedrock, Composer-managed plugins, or local development symlinks), `ABSPATH` and `DOPN_PLUGIN_DIR` may contain virtual paths while `$file` contains the resolved path. In such cases, `strpos()` checks could fail to match core or plugin paths.
- **Solution**: Resolve canonical paths using `wp_normalize_path( realpath(...) ?: ... )` when determining core paths and the plugin root.

### Item 4: PHPCS Suppression Comment Placement for Static Analyzers
- **Target File**: `includes/class-dopn-notice-collector.php`
- **Issue**: The `echo $markup;` statement on line 432 has an inline suppression comment `// phpcs:ignore ...`. Static analyzers like `wp-plugin-modernization` look for the suppression on the line immediately preceding the statement. Because it was on the same line, the analyzer flagged it as an unescaped dynamic output cue rather than recognizing it as a documented exception.
- **Solution**: Move the suppression comment directly above the `echo $markup;` statement with clear explanatory documentation.

### Item 5: Version Bump & Documentation
- **Target Files**:
  - `disable-other-plugin-notices.php` (`Version: 1.0.2`, `DOPN_VERSION` = `'1.0.2'`)
  - `readme.txt` (`Stable tag: 1.0.2`, add changelog entry for 1.0.2)
  - `README.md` (Update version reference if applicable)

---

## Verification & Validation Plan
1. **PHP Syntax Checks**: Run `php -l` on all modified files across PHP 7.4 to PHP 8.4 syntax standards.
2. **Static Analysis**: Re-run `wp-plugin-modernization` scan to verify:
   - 0 PHP lint failures.
   - 0 actionable review cues (the unescaped output is now properly categorized under mitigated observations).
   - Clean inventory & marker detection.
3. **Regex & Safety Testing**: Validate single and double quote class injection using isolated test assertions.
