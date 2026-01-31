=== MBR Advanced Asset Manager ===

Plugin URI: https://littlewebshack.com
Author: Robert Palmer
Author URI: https://madebyrobert.co.uk
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 2.3.0
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Easily manage and block unnecessary CSS/JS assets from running on pages.

== Description ==

MBR Advanced Asset Cleaner helps you optimize your WordPress site by selectively blocking unnecessary CSS and JavaScript files on specific pages. This can dramatically improve page load times and reduce bandwidth usage.

**NEW IN VERSION 2.0.0: COMPLETELY STANDALONE!**

This version has been completely rewritten to work without any external services or APIs. Everything runs locally on your WordPress installation:

* ✅ No browserless.io API required
* ✅ No external API keys needed
* ✅ No data sent to third-party services
* ✅ All scanning happens locally via WordPress loopback requests
* ✅ Better error messages and troubleshooting guidance

**Key Features:**

* **Local Scanning** - Scans pages using WordPress's built-in HTTP functions
* **Device-Specific Rules** - Block assets for mobile, desktop, or both
* **Preview Mode** - Test blocking rules before saving them (dry-run)
* **Size Analysis** - See file sizes to identify the biggest opportunities
* **Easy Interface** - Simple admin page to scan, select, and block assets
* **WordPress Integration** - Uses native WordPress enqueue system for clean blocking
* **Editor Override** - Temporarily disable blocking for logged-in editors

== Installation ==

**STANDARD INSTALLATION:**

1. Download the plugin ZIP file
2. Go to WordPress admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and activate the plugin
4. Go to Settings → Advanced Asset Manager to start optimizing

**OPTIONAL MU-PLUGIN FOR ENHANCED BLOCKING:**

For the strongest possible asset blocking (recommended for advanced users):

1. Copy or move `asm-blocker.php` to `/wp-content/mu-plugins/`
2. If the `mu-plugins` folder doesn't exist, create it
3. This enables both server-side and client-side blocking for maximum effectiveness

The MU-plugin is optional but recommended for sites with aggressive lazy-loading plugins or dynamic asset injection.

== How It Works ==

**Version 2.0.0 Technical Details:**

The plugin now uses a completely local scanning approach:

1. **Loopback Scanning**: When you click "Scan assets", the plugin makes an HTTP request to your own site using WordPress's `wp_remote_get()` function
2. **Asset Detection**: Parses the HTML response to find all CSS and JS files
3. **Handle Resolution**: Matches found assets to WordPress enqueue handles
4. **Size Detection**: Determines file sizes using HEAD requests and Content-Length headers
5. **Local Storage**: Saves blocking rules in post meta (no external database)
6. **Runtime Blocking**: Dequeues/deregisters assets on the frontend based on saved rules

**No External Dependencies:**
* No API calls to browserless.io
* No npm packages or external libraries
* No CDN dependencies
* All code runs within your WordPress installation

== Frequently Asked Questions ==

= What happened to the browserless.io integration? =

Version 2.0.0 is completely rewritten and removes the browserless.io dependency. The plugin now works entirely locally using WordPress's built-in HTTP functions. This makes it:
* Faster (no external API delays)
* More reliable (no external service downtime)
* More private (no data leaves your server)
* Easier to use (no API keys required)

= Testing =
Flush all caches, including any CSS/JS.  Always test on an incognito/private browser.

= What if I get a 403 error when scanning? =

If you see "Access denied (403)", your site's firewall or security plugin may be blocking the loopback request. Try:

1. Temporarily disable security plugins (Wordfence, Sucuri, etc.)
2. Check your server firewall settings
3. Whitelist the WordPress admin IP address
4. Contact your hosting provider if issues persist

The plugin now provides clearer error messages to help troubleshoot issues.

= Will this break my site? =

The plugin includes a "Preview (dry run)" feature that lets you test blocking rules before saving them. Always test in preview mode first! 

Additionally, there's a "Temporarily disable blocking" option for editors, so you can quickly disable rules if needed.

= How much can I save? =

Results vary, but most sites can:
* Reduce page size by 1-2MB on average
* Block 30-50% of unused CSS/JS files
* Improve page load times by 20-40%

The exact savings depend on your theme and installed plugins.

= Does it work with page builders? =

Yes! The plugin intelligently skips blocking when page builders are in edit mode:
* Elementor
* Beaver Builder
* Divi Builder
* Visual Composer
* Oxygen Builder
* Bricks Builder

Assets are only blocked on the public-facing site, never in the page builder editors.

== Changelog ==

= 2.3.0 =
Minor bug fixes

= 2.0.0 (2024-11-27) =
**MAJOR UPDATE: No External Dependencies**

* **Removed**: Browserless.io API integration completely removed
* **Removed**: ASB_BROWSERLESS_API_KEY constant no longer needed
* **Added**: Fully local scanning using WordPress wp_remote_get()
* **Improved**: Better error messages with troubleshooting guidance
* **Improved**: Enhanced loopback scanning with proper headers and cookies
* **Improved**: Timeout increased from 25s to 30s for slow-loading pages
* **Updated**: Admin description to clarify no external services
* **Updated**: Version bump to 2.0.0 to reflect major architecture change
* **Security**: All data processing happens locally, no external data transmission

= 1.2.4 (Previous) =
* Previous version with browserless.io integration
* Required API key for fallback scanning
* Maintained for reference only

== Upgrade Notice ==

= 2.0.0 =
Major update! Browserless.io integration has been completely removed. The plugin now works 100% locally. If you were using the ASB_BROWSERLESS_API_KEY constant, you can remove it - it's no longer needed.

== Technical Details ==

**System Requirements:**
* WordPress 5.8 or higher
* PHP 7.4 or higher
* Ability to make loopback HTTP requests (most hosts support this)

**Data Storage:**
* Blocking rules: Stored in post meta (`_mbr_asm_blocklist_v1`)
* Disable flag: Stored in post meta (`_mbr_asm_disable`)
* Preview data: Stored in browser cookies (temporary)

**Performance Impact:**
* Admin scanning: One-time HTTP request per page scan
* Frontend blocking: Minimal overhead (just WordPress hooks)
* No database queries beyond standard post meta

**Privacy:**
* No external API calls
* No data collection
* No analytics or tracking
* No cookies (except temporary preview cookie)

== Support ==

For issues, questions, or feature requests:
* Email: rob@littlewebshack.com
* Support, Bugs & Feature Requests: https//littlewebshack.com/

== Credits ==

Developed by Robert Palmer

== License ==

This plugin is licensed under the GPLv3 or later.
