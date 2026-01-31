# MBR Advanced Asset Manager v2.0.0 - Installation & Usage Guide

## Quick Start

1. **Install the plugin** (upload ZIP via WordPress admin)
2. **Activate it**
3. **Go to Settings → Advanced Asset Manager**
4. **Select a page and click "Scan assets"**
5. **Check the boxes for assets to block**
6. **Click "Save blocklist"**
7. **Test your page** - it should load faster!

## Detailed Installation

### Method 1: WordPress Admin (Recommended)

1. Download `mbr-advanced-asset-manager-v2.0.0.zip`
2. Log into your WordPress admin panel
3. Go to **Plugins → Add New**
4. Click **Upload Plugin** button at the top
5. Choose the ZIP file and click **Install Now**
6. Click **Activate Plugin**

### Method 2: FTP/File Manager

1. Extract the ZIP file on your computer
2. Upload the `mbr-advanced-asset-manager` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin
4. Find "Rob's Advanced Asset Manager" and click **Activate**

### Method 3: WP-CLI

```bash
wp plugin install mbr-advanced-asset-manager-v2.0.0.zip --activate
```

## Optional: Enhanced Blocking with MU-Plugin

For maximum blocking effectiveness (recommended for advanced users):

1. Locate the `asm-blocker.php` file inside the plugin folder
2. Copy it to `/wp-content/mu-plugins/asm-blocker.php`
3. If the `mu-plugins` folder doesn't exist, create it first

**What does this do?**
- Adds client-side JavaScript blocking
- Intercepts dynamically loaded assets
- Provides more aggressive blocking for stubborn assets
- Works alongside the main plugin

**When do you need it?**
- You're using lazy-loading plugins
- Assets are loaded via JavaScript after page load
- Standard blocking isn't catching everything
- You want maximum performance optimization

## How to Use

### Step 1: Access the Admin Page

1. Go to **Settings → Advanced Asset Manager**
2. You'll see a dropdown of all your pages

### Step 2: Scan a Page

1. Select a page from the dropdown
2. Click **"Scan assets"** button
3. Wait while the plugin fetches and analyzes the page
4. Results will show all CSS and JavaScript files with their sizes

### Step 3: Review the Assets

The results table shows:
- **Handle**: WordPress's internal name for the asset
- **URL**: The full URL of the CSS/JS file
- **Source**: Where it comes from (Plugin, Theme, Core, External)
- **Size**: File size (helps identify big files worth blocking)
- **Action**: Checkbox to block + device selector

**Sorting Tips:**
- Assets are automatically sorted by size (largest first)
- Focus on blocking the largest files for maximum impact
- Look for duplicate functionality (multiple sliders, lightboxes, etc.)

### Step 4: Select Assets to Block

1. Check the box next to each asset you want to block
2. For each checked asset, choose the device:
   - **Any device**: Blocks on both mobile and desktop
   - **Desktop**: Only blocks on desktop computers
   - **Mobile**: Only blocks on mobile devices

**Safety Tip**: Don't block everything! Start with files you know are unnecessary.

### Step 5: Test with Preview (Recommended!)

1. Select assets to block (check the boxes)
2. Click **"Preview (dry run)"** button
3. A new browser tab opens showing the page with assets blocked
4. Check if the page still works correctly
5. If something breaks, go back and uncheck that asset

**Preview mode doesn't save anything** - it's just for testing!

### Step 6: Save Your Blocklist

Once you're happy with your selections:

1. Click **"Save blocklist"** button
2. You'll see a confirmation message
3. The assets are now blocked on the live page

### Step 7: Verify It's Working

1. Visit the page on your site (not in preview mode)
2. Open browser Dev Tools (F12)
3. Go to the Network tab
4. Refresh the page
5. The blocked assets should not appear in the network requests

## Understanding Device-Specific Blocking

### Use Case Examples:

**Block on Desktop Only:**
```
Scenario: Mobile menu script that desktop doesn't need
Action: Block on desktop, allow on mobile
Result: Saves bandwidth for desktop users
```

**Block on Mobile Only:**
```
Scenario: Heavy parallax effect that's disabled on mobile
Action: Block on mobile, allow on desktop
Result: Faster mobile page loads
```

**Block on Any Device:**
```
Scenario: Unused contact form CSS when page has no form
Action: Block on all devices
Result: Everyone gets faster load times
```

## Advanced Features

### Temporarily Disable Blocking

If you need to edit a page and want all assets loaded:

1. Scan the page
2. Check the **"Temporarily disable blocking on this page (for editors)"** checkbox
3. Now editors won't have assets blocked (regular visitors still do)
4. Useful for troubleshooting or editing with page builders

### Clear All Rules

To remove all blocking rules for a page:

1. Select the page
2. Click **"Scan assets"** (to load the interface)
3. Click **"Clear all rules for this page"** button
4. Confirm the action
5. All blocking is removed for that page

## Troubleshooting

### "Scan Failed" or Connection Errors

**Problem**: The scan can't fetch your page

**Common Causes:**
1. Security plugin blocking the request
2. Server firewall blocking loopback requests
3. Site under maintenance/coming soon mode
4. SSL certificate issues

**Solutions:**
```
1. Temporarily disable security plugins (Wordfence, Sucuri, etc.)
2. Check if your site loads normally in a browser
3. Check for .htaccess restrictions
4. Contact your hosting provider about loopback requests
5. Check server error logs
```

### "Access Denied (403)" Error

**Problem**: Your firewall is blocking the scan request

**Solutions:**
```
1. In Wordfence: 
   - Go to Wordfence → Firewall → Rate Limiting
   - Add your admin IP to allowlist

2. In Sucuri:
   - Temporarily set to audit mode
   - Or whitelist WordPress admin user agent

3. In Cloudflare:
   - Add page rule to bypass for /wp-admin/
   - Or temporarily pause Cloudflare

4. Server Firewall:
   - Contact host to whitelist loopback requests
   - May need to whitelist 127.0.0.1 or server IP
```

### Assets Not Being Blocked

**Problem**: You saved rules but assets still load

**Checklist:**
```
1. ✓ Did you click "Save blocklist"? (Preview doesn't save)
2. ✓ Clear all caches (plugin cache, server cache, CDN)
3. ✓ Test in incognito/private browsing mode
4. ✓ Check if "disable blocking for editors" is on
5. ✓ Verify you're not in a page builder edit mode
6. ✓ Make sure the plugin is activated
7. ✓ Check browser console for JavaScript errors
```

### Page Breaks After Blocking

**Problem**: Something stopped working after blocking assets

**Solutions:**
```
1. Identify which asset caused it:
   - Use preview mode to test one asset at a time
   - Check browser console for errors

2. Quick fix:
   - Go back to the page's blocklist
   - Uncheck the problematic asset
   - Save the blocklist

3. Alternative:
   - Try device-specific blocking
   - Block on desktop only if mobile needs it
   - Or vice versa
```

### Slow Scanning

**Problem**: Scan takes a long time

**Reasons:**
```
1. Page has many assets (100+)
2. Page is slow to load normally
3. External assets timing out
4. Large images or videos on page
```

**Solutions:**
```
1. Be patient - first scan is slowest (30 seconds max)
2. Optimize the page first (compress images, etc.)
3. Temporarily disable lazy loading during scan
4. Check your server response time
```

## Best Practices

### DO:
✅ Use preview mode before saving
✅ Start with the largest files
✅ Test thoroughly after blocking
✅ Block page by page (don't rush)
✅ Keep notes of what you block
✅ Clear caches after changes

### DON'T:
❌ Block everything at once
❌ Block without testing
❌ Skip preview mode
❌ Block core WordPress files
❌ Block jQuery (usually needed)
❌ Block files you don't recognize (test first)

### Recommended Workflow:

```
1. Scan page
2. Identify obvious unnecessary files
   - Unused plugins (sliders, galleries, etc.)
   - Duplicate functionality
   - External fonts you don't use
3. Check 3-5 assets to block
4. Preview to test
5. If good → Save, if broken → adjust
6. Clear caches
7. Test live page
8. Repeat for next page
```

## Performance Tips

### Maximum Impact Assets:

**High Priority (Block these first):**
- Google Fonts you don't use
- Unused page builder styles
- Slider plugins on pages without sliders
- Gallery plugins on pages without galleries
- Contact form CSS when no form present
- Social sharing scripts you don't need
- Analytics scripts if using multiple trackers

**Medium Priority:**
- Theme features you disabled
- Plugin styles for disabled features
- Unused icon fonts
- Extra jQuery UI themes

**Low Priority (Usually safe):**
- Optimization plugin CSS
- Small utility scripts
- Admin bar styles (if logged out)

### Typical Savings:

| Site Type | Avg. Blocked Assets | Avg. Size Saved |
|-----------|-------------------|-----------------|
| Basic Blog | 10-15 files | 500KB - 1MB |
| Business Site | 20-30 files | 1MB - 2MB |
| E-commerce | 30-50 files | 2MB - 3MB |
| Complex/Bloated | 50+ files | 3MB - 5MB+ |

## Site-Wide vs Page-Specific

### This Plugin = Page-Specific
- Block assets on individual pages
- Fine-grained control
- Perfect for varied page layouts

### For Site-Wide Blocking:
- Consider other plugins (Perfmatters, Asset CleanUp)
- Or use this plugin on every page (more time consuming)
- Or combine both approaches

## Page Builder Compatibility

The plugin automatically skips blocking when you're editing in:

- ✅ Elementor
- ✅ Beaver Builder  
- ✅ Divi Builder
- ✅ Visual Composer
- ✅ Oxygen Builder
- ✅ Bricks Builder
- ✅ Other common builders

You can safely block assets - they'll load normally in the editor!

## Maintenance

### Regular Checkups:

**After Plugin Updates:**
```
1. Scan important pages again
2. New assets may have been added
3. Old blocked assets may be renamed
```

**After Theme Changes:**
```
1. Re-scan all pages
2. Theme may load different assets
3. Update your blocklists
```

**Monthly:**
```
1. Review blocked assets
2. Remove blocking for assets no longer loaded
3. Look for new optimization opportunities
```

## Getting Help

### Before Asking for Help:

1. ✓ Read this guide thoroughly
2. ✓ Check error messages carefully
3. ✓ Try the troubleshooting steps
4. ✓ Test with all plugins except this one
5. ✓ Test with a default theme

### What to Include in Support Requests:

```
- WordPress version
- PHP version  
- Hosting provider
- Active plugins list
- Theme name
- Error message (exact text)
- Browser console errors
- What you were trying to do
- What actually happened
```

## Tips for Success

1. **Start Small**: Block 5-10 assets on one page first
2. **Test Everything**: Always use preview mode
3. **Document Changes**: Keep notes of what you block and why
4. **Clear Caches**: After every change
5. **Be Patient**: Optimization is iterative
6. **Monitor Results**: Use Google PageSpeed Insights before/after
7. **Don't Break Things**: If in doubt, don't block it

## Example Workflow

```
Monday: 
- Scan homepage
- Block 10 unused assets
- Test and save
- Result: 800KB saved, page loads 0.5s faster

Tuesday:
- Scan about page
- Block 5 assets
- Test and save
- Result: 300KB saved

Wednesday:
- Scan services page
- Block 15 assets (lots of unused sliders)
- Test and save  
- Result: 1.2MB saved, page loads 1s faster

Total for week: 2.3MB saved across 3 pages!
```

## Additional Resources

- **WordPress Codex**: wp_enqueue_scripts documentation
- **MDN**: CSS and JavaScript optimization guides  
- **Google PageSpeed**: Before/after testing
- **GTmetrix**: Detailed performance analysis
- **WebPageTest**: Waterfall charts to identify assets

## Summary

MBR Advanced Asset Manager v2.0.0 helps you:

✅ Identify unnecessary CSS and JavaScript files
✅ Block them on specific pages
✅ Control blocking per device (mobile/desktop)
✅ Test changes safely with preview mode
✅ Improve page load times significantly
✅ All without external services or APIs

Start optimizing today - your visitors (and Google) will thank you!
