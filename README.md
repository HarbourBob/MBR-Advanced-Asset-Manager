# MBR Advanced Asset Manager

[![WordPress Plugin](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv3-green.svg)](https://www.gnu.org/licenses/gpl-3.0.html)
[![Version](https://img.shields.io/badge/Version-2.5.0-orange.svg)](https://github.com/harbourbob/mbr-advanced-asset-manager)
[![Downloads](https://img.shields.io/badge/Downloads-10K%2B-brightgreen.svg)](https://github.com/harbourbob/mbr-advanced-asset-manager/releases)
[![Made by Robert](https://img.shields.io/badge/Made%20by-Robert-brightgreen.svg)](https://madebyrobert.co.uk)
[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-%E2%98%95-yellow.svg)](https://buymeacoffee.com/robertpalmer/)

![MBR Advanced Asset Manager Screenshot](screenshot.png)

A powerful WordPress plugin that helps you optimise page load times by selectively blocking unnecessary CSS and JavaScript files on any page, post, or custom post type. Features a dark-mode admin UI with per-page and global blocking controls. Completely standalone with no external dependencies.

[**Download Latest Release**](https://github.com/harbourbob/mbr-advanced-asset-manager/releases) · [**Report a Bug**](https://github.com/harbourbob/mbr-advanced-asset-manager/issues) · [**Request a Feature**](https://github.com/harbourbob/mbr-advanced-asset-manager/issues)

---

## Key Features

- **100% Local** — No external APIs, everything runs on your WordPress installation
- **Asset Analysis** — See file sizes to identify optimisation opportunities
- **Device-Specific Blocking** — Block assets for mobile, desktop, or both
- **Block on All Pages** — Toggle any asset to be blocked globally across your entire site
- **All Post Types** — Manage assets on pages, posts, WooCommerce products, or any public custom post type
- **Preview Mode** — Test blocking rules safely before applying them
- **Dark Mode UI** — Stylish dark admin interface with toggle switches and colour-coded controls
- **Page Builder Compatible** — Works seamlessly with Elementor, Divi, Beaver Builder, and more
- **Performance Focus** — Reduce page size by 1–3MB and improve load times by 20–40%

---

## What's New

### Version 2.5.0 — Posts, CPTs & UI Polish

- Support for Posts and all public Custom Post Types (not just Pages)
- Dropdown groups items by post type using optgroups (Pages, Posts, Products, etc.)
- Fixed page refresh after save — scan results are preserved
- Improved dropdown text readability on dark theme

### Version 2.4.0 — Global Blocking & Dark Mode

- **Block on All Pages** — toggle any asset to be blocked globally across every page on your site
- **Dark Mode UI** — Catppuccin Mocha colour palette with stylish toggle switches replacing checkboxes
- **Colour-coded toggles** — red for block, purple for global, blue for settings
- MU-Plugin blocker updated to merge global blocklist with per-page rules
- Global blocking works on non-singular pages (archives, 404, etc.)

### Version 2.0.0 — Completely Standalone

- Removed browserless.io dependency entirely
- Fully local scanning using WordPress native functions
- No data leaves your server

---

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Ability to make loopback HTTP requests (standard on most hosts)

---

## Installation

### Standard Installation

1. Download the latest release ZIP file
2. Navigate to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**
5. Go to **Settings → Advanced Asset Manager** to start optimising

### Manual Installation

1. Extract the ZIP file
2. Upload the `mbr-advanced-asset-manager` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin panel

### WP-CLI Installation

```bash
wp plugin install mbr-advanced-asset-manager.zip --activate
```

### Optional: Enhanced Blocking (MU-Plugin)

For maximum blocking effectiveness:

1. Copy `asm-blocker.php` from the plugin folder to `/wp-content/mu-plugins/`
2. Create the `mu-plugins` folder if it doesn't exist
3. This enables additional client-side blocking for stubborn assets

---

## Usage

### Quick Start Guide

1. **Access the plugin** — Go to **Settings → Advanced Asset Manager**
2. **Select content** — Choose any page, post, or custom post type from the grouped dropdown
3. **Scan assets** — Click "Scan assets" to analyse the page
4. **Review results** — See all CSS/JS files sorted by size
5. **Toggle blocking** — Use the red "Block" toggle for per-page blocking
6. **Go global** — Use the purple "All Pages" toggle to block an asset site-wide
7. **Choose devices** — Select mobile, desktop, or both for each asset
8. **Preview first** — Click "Preview (dry run)" to test safely
9. **Save blocklist** — Once tested, click "Save blocklist" to apply

### Understanding the Results

The scan shows:

- **Handle** — WordPress's internal name for the asset
- **URL** — Full path to the CSS/JS file
- **Source** — Origin (Plugin, Theme, Core, External)
- **Size** — File size (focus on largest files first)
- **Action** — Block toggle, All Pages toggle, and device selector

### Per-Page vs Global Blocking

**Per-page blocking** (red toggle) saves rules against that specific page, post, or CPT item. The asset is only blocked when that particular piece of content is viewed.

**Global blocking** (purple "All Pages" toggle) saves the rule in a separate site-wide option. The asset is blocked on every front-end page load — including archives, search results, and 404 pages. This is ideal for assets you never need anywhere on your site.

### Device-Specific Blocking Examples

```
Scenario: Mobile menu script not needed on desktop
→ Block on Desktop only

Scenario: Heavy parallax effects disabled on mobile
→ Block on Mobile only

Scenario: Unused contact form CSS
→ Block on Any device (or use All Pages if it's never needed)
```

---

## How It Works

The plugin uses a completely local scanning approach:

1. **Loopback Scanning** — Makes HTTP request to your own site using `wp_remote_get()`
2. **Asset Detection** — Parses HTML to find all CSS and JS files
3. **Handle Resolution** — Matches assets to WordPress enqueue handles
4. **Size Detection** — Determines file sizes via HEAD requests
5. **Local Storage** — Saves per-page rules in post meta, global rules in `wp_options`
6. **Runtime Blocking** — Dequeues assets on frontend based on saved rules (per-page + global merged)

---

## Page Builder Compatibility

Automatically detects and skips blocking when editing in:

- Elementor (editor and preview modes)
- Beaver Builder
- Divi Builder
- Visual Composer
- Oxygen Builder
- Bricks Builder
- Other common page builders

Assets are only blocked on the public-facing site, never in editor mode.

---

## Best Practices

### DO:

- Always use preview mode before saving
- Start with the largest files
- Test thoroughly after blocking
- Use "All Pages" for assets you're certain aren't needed anywhere
- Clear all caches after changes

### DON'T:

- Block everything at once without testing
- Skip preview mode
- Block core WordPress files (they're protected, but still)
- Block assets you don't recognise (test first)
- Use global blocking for assets that are only unwanted on specific pages

### High-Impact Targets

Block these types of files first for maximum performance gains:

- Unused Google Fonts
- Slider plugins on pages without sliders
- Gallery plugins on pages without galleries
- Contact form CSS when no form is present
- Duplicate functionality (multiple lightboxes, etc.)
- Social sharing scripts you don't use

---

## Troubleshooting

### Scan Failed or Connection Errors

**Common causes:** security plugin blocking loopback requests, server firewall restrictions, site under maintenance mode, SSL certificate issues.

**Solutions:**

1. Temporarily disable security plugins (Wordfence, Sucuri)
2. Check server firewall settings
3. Verify site loads normally in browser
4. Contact hosting provider about loopback requests

### Access Denied (403) Error

- Whitelist WordPress admin IP in security plugin
- Temporarily disable firewall rules
- Check `.htaccess` restrictions
- Contact host to whitelist loopback requests

### Assets Not Being Blocked

1. Did you click "Save blocklist"? (Preview doesn't save)
2. Clear all caches (plugin, server, CDN)
3. Test in incognito/private browsing
4. Verify "disable blocking for editors" is off
5. Ensure you're not in page builder edit mode

---

## Typical Performance Gains

| Site Type | Blocked Assets | Size Saved | Speed Improvement |
|-----------|----------------|------------|-------------------|
| Basic Blog | 10–15 files | 500KB – 1MB | 20–30% faster |
| Business Site | 20–30 files | 1–2MB | 30–40% faster |
| E-commerce | 30–50 files | 2–3MB | 40–50% faster |
| Complex Sites | 50+ files | 3–5MB+ | 50%+ faster |

---

## Privacy & Security

- **No External APIs** — All processing happens locally
- **No Data Collection** — Nothing is tracked or collected
- **No Analytics** — No usage tracking
- **No Cookies** — Except temporary preview cookie
- **Open Source** — Full transparency

---

## Technical Details

**Data Storage:**

- Per-page blocking rules: `_mbr_asm_blocklist_v1` post meta
- Global blocking rules: `mbr_asm_global_blocklist` in `wp_options`
- Disable flag: `_mbr_asm_disable` post meta
- Preview data: Browser localStorage (temporary)

**Performance Impact:**

- Admin scanning: One-time HTTP request per scan
- Frontend blocking: Minimal overhead (WordPress hooks only)
- No additional database queries beyond post meta and one option lookup

---

## Changelog

### [2.5.0]

- Added support for Posts and all public Custom Post Types
- Dropdown groups items by post type using optgroups
- Updated label from "Page" to "Content"
- Fixed page refresh after save with preventDefault on all handlers
- Improved dropdown text readability on dark theme
- Styled optgroup headers with purple accent

### [2.4.0]

- Added "Block on All Pages" global blocking feature
- Added global rules counter in stats header
- New AJAX endpoints for saving/loading the global blocklist
- MU-Plugin blocker merges global blocklist with per-page rules
- Global blocking works on non-singular pages (archives, 404, etc.)
- Dark mode UI overhaul with Catppuccin Mocha colour palette
- Replaced all checkboxes with toggle switches
- Colour-coded toggles — red for block, purple for global, blue for settings
- Improved table styling, section headers, and overall layout

### [2.3.0]

- Minor bug fixes

### [2.0.0]

**Major Update: No External Dependencies**

- Removed browserless.io API integration
- Added fully local scanning using WordPress `wp_remote_get()`
- Improved error messages with troubleshooting guidance
- Enhanced loopback scanning with proper headers
- Increased timeout from 25s to 30s
- Updated admin descriptions
- All data processing now happens locally

### [1.2.4]

- Initial public release with browserless.io integration

---

## Contributing

Contributions are welcome. If you find a bug or have a feature request, please [open an issue](https://github.com/harbourbob/mbr-advanced-asset-manager/issues).

For code contributions:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Commit your changes
4. Push to the branch
5. Open a Pull Request

---

## Support

- **Bug reports:** [GitHub Issues](https://github.com/harbourbob/mbr-advanced-asset-manager/issues)
- **Feature requests:** [GitHub Issues](https://github.com/harbourbob/mbr-advanced-asset-manager/issues)
- **Website:** [littlewebshack.com](https://littlewebshack.com)
- **Author:** [madebyrobert.co.uk](https://madebyrobert.co.uk)
- **Coffee:** [buymeacoffee.com/robertpalmer](https://buymeacoffee.com/robertpalmer/)

---

## License

This plugin is licensed under the [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html).

100% free. No premium tiers. No upsells. No tracking.

---

**Made by Robert Palmer | Free & Open Source WordPress Tools**
