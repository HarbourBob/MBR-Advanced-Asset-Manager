<?php
/**
 * Advanced Asset Manager - Blocker Engine v6.3.1 (MU-Plugin)
 *
 * - Per-device & per-template rules (any / specific template) for server-side dequeue and client-side blocking.
 * - Dry-run preview cookie + ?asm_preview=1 support (client-side only in preview).
 * - Strong hybrid blocking and scan-safe behaviour.
 * - Supports temporary disable for editors via _asm_disable meta key.
 */

if (!defined('ABSPATH')) exit;

function mbr_asm_is_scanning_ctx() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only debug flag for internal scanning context
    return (defined('MBR_ASM_SCANNING') && MBR_ASM_SCANNING) || isset($_GET['mbr_asm_scan']);
}
function mbr_asm_is_preview_ctx() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview flag for front-end display
    return isset($_GET['mbr_asm_preview']);
}
function mbr_asm_is_disabled_for_editors($post_id) {
    $disabled = get_post_meta($post_id, '_mbr_asm_disable', true);
    if (!$disabled) return false;
    
    // If disabled, check if current user is an editor
    return is_user_logged_in() && (
        current_user_can('edit_posts') || 
        current_user_can('edit_pages') || 
        current_user_can('edit_others_pages')
    );
}
function mbr_asm_device_current(){ return wp_is_mobile() ? 'mobile' : 'desktop'; }
function mbr_asm_template_current(){
    $post_id = get_queried_object_id();
    if (!$post_id) return 'default';
    $slug = get_page_template_slug($post_id);
    return $slug ? $slug : 'default';
}
function mbr_asm_get_saved_blocklist_for($post_id){
    $items = get_post_meta($post_id, '_mbr_asm_blocklist_v1', true);
    if (!is_array($items)) $items = [];
    return $items;
}
function mbr_asm_filter_by_device_template($items){
    $dev = mbr_asm_device_current();
    $tpl = mbr_asm_template_current();
    $out = [];
    foreach ($items as $row) {
        $device = isset($row['device']) ? sanitize_key($row['device']) : 'any';
        $template = isset($row['template']) ? sanitize_text_field($row['template']) : 'any';
        $device_ok = ($device === 'any' || $device === $dev);
        $template_ok = ($template === 'any' || $template === $tpl);
        if ($device_ok && $template_ok) $out[] = $row;
    }
    return $out;
}
function mbr_asm_abs_url($u) {
    $u = (string)$u;
    if ($u === '') return $u;
    if (strpos($u, '//') === 0) return (is_ssl() ? 'https:' : 'http:') . $u;
    $parts = wp_parse_url($u);
    if (!isset($parts['host'])) {
        $base = site_url('/');
        if (isset($u[0]) && $u[0] === '/') return rtrim($base, '/') . $u;
        return rtrim($base, '/') . '/' . ltrim($u, '/');
    }
    return $u;
}
function mbr_asm_url_variants($u){
    $out = [];
    $abs = mbr_asm_abs_url($u);
    $nohash = strtok($abs, '#');
    $noq = strtok($nohash, '?'); if (!$noq) $noq = $nohash;
    $out[] = $abs; $out[] = $nohash; $out[] = $noq;
    $decoded = urldecode($noq);
    if ($decoded !== $noq) $out[] = $decoded;
    $path = wp_parse_url($noq, PHP_URL_PATH);
    if ($path) {
        $parts = array_values(array_filter(explode('/', $path)));
        $cnt = count($parts);
        for ($k=1; $k<=min(4,$cnt); $k++){
            $suffix = implode('/', array_slice($parts, $cnt-$k));
            $out[] = $suffix;
        }
        $base = basename($path);
        if ($base) $out[] = $base;
    }
    if (str_ends_with($noq, '.rtl.css')) $out[] = substr($noq, 0, -8) . '.css';
    return array_values(array_unique($out));
}

function mbr_asm_is_critical_wp_script($handle, $url) {
    // Never block these essential WordPress core scripts
    $critical_handles = [
        'jquery-core',
        'jquery',
        'jquery-migrate',
        'wp-polyfill',
        'wp-polyfill-inert',
        'wp-polyfill-fetch',
        'regenerator-runtime',
        'wp-hooks',
        'wp-i18n',
    ];
    
    if (in_array($handle, $critical_handles, true)) {
        return true;
    }
    
    // Check if URL is from wp-includes (core WordPress)
    if ($url && strpos($url, '/wp-includes/js') !== false) {
        // Allow blocking of wp-includes/js EXCEPT for polyfills and essential libraries
        if (strpos($url, 'wp-polyfill') !== false || 
            strpos($url, 'jquery') !== false ||
            strpos($url, 'regenerator-runtime') !== false ||
            strpos($url, 'wp-hooks') !== false ||
            strpos($url, 'wp-i18n') !== false) {
            return true;
        }
    }
    
    return false;
}

// SERVER-SIDE: dequeue (skip in preview)
add_action('wp_enqueue_scripts', function() {
    if (mbr_asm_is_scanning_ctx() || mbr_asm_is_preview_ctx()) return;
    if (is_admin() || !is_singular()) return;
    $post_id = get_queried_object_id();
    if (!$post_id) return;
    
    // Check if blocking is temporarily disabled for editors
    if (mbr_asm_is_disabled_for_editors($post_id)) return;

    $items = mbr_asm_filter_by_device_template( mbr_asm_get_saved_blocklist_for($post_id) );
    if (empty($items)) return;

    $blocked_styles = []; $blocked_scripts = [];
    foreach ($items as $row) {
        $url = isset($row['url']) ? esc_url_raw($row['url']) : '';
        $type = isset($row['type']) ? sanitize_key($row['type']) : '';
        if (!$url) continue;
        if ($type === 'style') $blocked_styles[] = $url;
        elseif ($type === 'script') $blocked_scripts[] = $url;
    }

    $blocked_variants = [];
    foreach (array_merge($blocked_styles, $blocked_scripts) as $b) foreach (mbr_asm_url_variants($b) as $v) $blocked_variants[$v]=true;

    global $wp_styles, $wp_scripts;
    if ($wp_styles && is_object($wp_styles)) {
        foreach ($wp_styles->registered as $handle => $obj) {
            if (empty($obj->src)) continue;
            $src = mbr_asm_abs_url($obj->src);
            $match = false; foreach (mbr_asm_url_variants($src) as $v) if (isset($blocked_variants[$v])) { $match=true; break; }
            if ($match) { wp_dequeue_style($handle); wp_deregister_style($handle); }
        }
    }
    if ($wp_scripts && is_object($wp_scripts)) {
        foreach ($wp_scripts->registered as $handle => $obj) {
            if (empty($obj->src)) continue;
            
            // CRITICAL: Never block essential WordPress core scripts
            $src = mbr_asm_abs_url($obj->src);
            if (mbr_asm_is_critical_wp_script($handle, $src)) {
                continue; // Skip blocking this script
            }
            
            $match = false; foreach (mbr_asm_url_variants($src) as $v) if (isset($blocked_variants[$v])) { $match=true; break; }
            if ($match) { wp_dequeue_script($handle); wp_deregister_script($handle); }
        }
    }

    add_filter('style_loader_src', function($src) use ($blocked_variants){ foreach (mbr_asm_url_variants($src) as $v) if (isset($blocked_variants[$v])) return ''; return $src; }, 9999);
    add_filter('script_loader_src', function($src) use ($blocked_variants){ foreach (mbr_asm_url_variants($src) as $v) if (isset($blocked_variants[$v])) return ''; return $src; }, 9999);
    add_filter('style_loader_tag', function($html, $handle, $href) use ($blocked_variants){ foreach (mbr_asm_url_variants($href) as $v) if (isset($blocked_variants[$v])) return ''; return $html; }, 9999, 3);
    add_filter('script_loader_tag', function($tag, $handle, $src) use ($blocked_variants){ foreach (mbr_asm_url_variants($src) as $v) if (isset($blocked_variants[$v])) return ''; return $tag; }, 9999, 3);
    
    // Additional filter to catch scripts in the queue before printing
    add_filter('print_scripts_array', function($to_do) use ($blocked_variants) {
        global $wp_scripts;
        if (!$wp_scripts || !is_object($wp_scripts)) return $to_do;
        
        foreach ($to_do as $key => $handle) {
            if (isset($wp_scripts->registered[$handle])) {
                $obj = $wp_scripts->registered[$handle];
                if (!empty($obj->src)) {
                    $src = mbr_asm_abs_url($obj->src);
                    foreach (mbr_asm_url_variants($src) as $v) {
                        if (isset($blocked_variants[$v])) {
                            unset($to_do[$key]);
                            break;
                        }
                    }
                }
            }
        }
        return array_values($to_do);
    }, 9999);
}, 100);

// CLIENT-SIDE: includes preview override
add_action('wp_head', function () {
    if (is_admin() || !is_singular()) return;
    if (mbr_asm_is_scanning_ctx()) return;

    $post_id = get_queried_object_id();
    if (!$post_id) return;
    
    // Check if blocking is temporarily disabled for editors
    $is_disabled = mbr_asm_is_disabled_for_editors($post_id);

    $saved = mbr_asm_filter_by_device_template( mbr_asm_get_saved_blocklist_for($post_id) );

    $styles = []; $scripts = [];
    foreach ($saved as $row) {
        $url = isset($row['url']) ? esc_url_raw($row['url']) : '';
        $type = isset($row['type']) ? sanitize_key($row['type']) : '';
        if (!$url) continue;
        
        // For scripts, check if it's a critical WordPress core script
        if ($type === 'script') {
            // Check URL patterns for critical scripts
            if (strpos($url, 'wp-polyfill') !== false ||
                strpos($url, 'jquery') !== false ||
                strpos($url, 'regenerator-runtime') !== false ||
                strpos($url, 'wp-hooks') !== false ||
                strpos($url, 'wp-i18n') !== false) {
                continue; // Don't add to blocked list
            }
            $scripts[] = $url;
        } elseif ($type === 'style') {
            $styles[] = $url;
        }
    }

    ?>
    <script id="asm-blocker" type="text/javascript">
    (function() {
        // Check if disabled for this editor
        const isDisabledForEditor = <?php echo $is_disabled ? 'true' : 'false'; ?>;
        if (isDisabledForEditor) return; // Skip all client-side blocking
        
        if (!('URL' in window)) return;
        const origin = window.location.origin;
        const isPreview = <?php echo mbr_asm_is_preview_ctx() ? 'true' : 'false'; ?>;
        const postID = <?php echo (int) $post_id; ?>;

        function readPreviewCookie(){
            try{
                const m = document.cookie.match(/(?:^|;\s*)mbr_asm_preview_blocklist=([^;]+)/);
                if (!m) return null;
                const data = JSON.parse(decodeURIComponent(m[1]));
                if (!data || typeof data !== 'object') return null;
                return data[postID] || null;
            }catch(e){ return null; }
        }

        function variants(u){
            try {
                const url = new URL(u, origin);
                const full = url.toString();
                url.hash='';
                const noHash = url.toString();
                const noQuery = new URL(noHash); noQuery.search='';
                const nqs = noQuery.toString();
                const dec = decodeURI(nqs);
                const list = [full, noHash, nqs];
                if (dec !== nqs) list.push(dec);
                const path = noQuery.pathname || '';
                if (path) {
                    const parts = path.split('/').filter(Boolean);
                    for (let k=1; k<=Math.min(4, parts.length); k++){
                        list.push(parts.slice(-k).join('/'));
                    }
                    const base = parts[parts.length-1]; if (base) list.push(base);
                }
                if (nqs.endsWith('.rtl.css')) list.push(nqs.replace(/\.rtl\.css$/, '.css'));
                return Array.from(new Set(list));
            } catch(e) {
                const s = String(u||'');
                const n = s.split('#')[0]; const q = n.split('?')[0];
                return Array.from(new Set([s, n, q]));
            }
        }

        const savedStyles = <?php echo wp_json_encode(array_values(array_unique($styles))); ?>;
        const savedScripts = <?php echo wp_json_encode(array_values(array_unique($scripts))); ?>;

        let styles = savedStyles.slice();
        let scripts = savedScripts.slice();
        if (isPreview) {
            const preview = readPreviewCookie();
            if (Array.isArray(preview)) {
                styles = []; scripts = [];
                preview.forEach(it => {
                    if (!it || !it.url || !it.type) return;
                    // client-side preview DOES respect device/template because cookie created from UI selection already set them
                    if (it.type === 'style') styles.push(it.url);
                    else if (it.type === 'script') scripts.push(it.url);
                });
            }
        }

        const blockedSet = new Set();
        styles.concat(scripts).forEach(u => variants(u).forEach(v => blockedSet.add(v)));
        function isBlocked(u){ return variants(u).some(v => blockedSet.has(v)); }
        function kill(node){ try{ node.parentNode && node.parentNode.removeChild(node);}catch(e){} }
        function maybeBlock(node){
            if (!node || node.nodeType !== 1) return;
            const tag = node.tagName;
            if (tag === 'LINK') {
                const rel = (node.rel||'').toLowerCase();
                const as = (node.as||'').toLowerCase();
                const href = node.href || node.getAttribute('data-rocketlazyloadscript') || '';
                if (rel==='preload' && (as==='style' || as==='script') && href && isBlocked(href)) { kill(node); return; }
                if ((rel==='stylesheet' || /\.css(\?|$)/i.test(href)) && href && isBlocked(href)) { try{ node.disabled = true; }catch(e){} kill(node); return; }
            } else if (tag === 'SCRIPT') {
                const src = node.src || node.getAttribute('data-rocketlazyloadscript') || '';
                if (src && isBlocked(src)) { 
                    // Enhanced blocking: prevent execution more aggressively
                    try { 
                        node.type = 'javascript/blocked'; 
                        node.removeAttribute('src');
                        node.src = ''; 
                        node.textContent = ''; 
                    } catch(e) {}
                    kill(node); 
                    return; 
                }
            } else if (tag === 'STYLE') {
                try {
                    const sheet = node.sheet;
                    if (sheet && sheet.cssRules) {
                        for (let i = sheet.cssRules.length-1; i>=0; i--){
                            const rule = sheet.cssRules[i];
                            if (rule.type === CSSRule.IMPORT_RULE && rule.href && isBlocked(rule.href)) sheet.deleteRule(i);
                        }
                    } else if (node.textContent && /@import/i.test(node.textContent)) {
                        let txt = node.textContent;
                        const urls = txt.match(/@import\s+url\((['"]?)([^'")]+)\1\)\s*;?/gi) || [];
                        urls.forEach(m => {
                            const href = (m.match(/url\((['"]?)([^'")]+)\1\)/i)||[])[2] || '';
                            if (href && isBlocked(href)) txt = txt.replace(m, '/* asm removed import */');
                        });
                        node.textContent = txt;
                    }
                } catch(e){}
            }
        }

        const _append = Node.prototype.appendChild;
        Node.prototype.appendChild = function(ch){ maybeBlock(ch); return _append.call(this, ch); };
        const _insert = Node.prototype.insertBefore;
        Node.prototype.insertBefore = function(ch, ref){ maybeBlock(ch); return _insert.call(this, ch, ref); };
        const _setAttr = Element.prototype.setAttribute;
        Element.prototype.setAttribute = function(name, value){ _setAttr.call(this, name, value); if (['href','src','rel','as','media','data-rocketlazyloadscript'].includes(String(name).toLowerCase())) maybeBlock(this); };

        try {
            const ScriptProto = HTMLScriptElement.prototype;
            const LinkProto = HTMLLinkElement.prototype;
            const descS = Object.getOwnPropertyDescriptor(ScriptProto, 'src');
            if (descS && descS.configurable) {
                const originalSet = descS.set || function(v) { _setAttr.call(this, 'src', v); };
                Object.defineProperty(ScriptProto, 'src', { 
                    set: function(v) { 
                        // Check if blocked BEFORE setting src
                        if (v && isBlocked(v)) {
                            try { 
                                this.type = 'javascript/blocked';
                                _setAttr.call(this, 'data-blocked-src', v);
                                return; // Don't set src at all
                            } catch(e) {}
                        }
                        try { originalSet.call(this, v); } catch(e) {}
                        maybeBlock(this); 
                    }, 
                    get: descS.get || function() { return this.getAttribute('src'); },
                    configurable: true,
                    enumerable: true
                });
            }
            const descL = Object.getOwnPropertyDescriptor(LinkProto, 'href');
            if (descL && descL.configurable) {
                Object.defineProperty(LinkProto, 'href', { set: function(v){ try{ _setAttr.call(this, 'href', v); }catch(e){} maybeBlock(this); }, get: function(){ return this.getAttribute('href'); } });
            }
        } catch(e){}

        const _write = document.write.bind(document);
        document.write = function(str){
            try {
                if (/<script[^>]+src=/i.test(str)) {
                    const m = String(str).match(/src\s*=\s*["']([^"']+)["']/i);
                    if (m && m[1] && isBlocked(m[1])) return;
                }
            } catch(e){}
            return _write(str);
        };

        document.querySelectorAll('link[rel="stylesheet"], link[href*=".css"], link[rel="preload"][as], script[src], script[type="module"], style, link[data-rocketlazyloadscript], script[data-rocketlazyloadscript]').forEach(maybeBlock);
        
        // CRITICAL: Block any scripts already in DOM immediately (run this synchronously)
        document.querySelectorAll('script[src]').forEach(function(s){
            const src = s.src || s.getAttribute('src') || '';
            if (src && isBlocked(src)) {
                try {
                    s.type = 'javascript/blocked';
                    s.removeAttribute('src');
                    s.src = '';
                    s.textContent = '';
                    if (s.parentNode) s.parentNode.removeChild(s);
                } catch(e) {}
            }
        });

        try {
            for (const sheet of Array.from(document.styleSheets)) {
                const owner = sheet.ownerNode; const href = (sheet.href || (owner && owner.href) || '');
                if (href && isBlocked(href)) { try{ sheet.disabled = true; }catch(e){} if (owner && owner.tagName==='LINK'){ owner.disabled=true; kill(owner); } }
            }
        } catch(e){}

        const observer = new MutationObserver(function(mList){
            for (const m of mList) {
                if (m.type === 'childList' && m.addedNodes) { m.addedNodes.forEach(maybeBlock); }
                else if (m.type === 'attributes' && m.target) { maybeBlock(m.target); }
            }
        });
        observer.observe(document.documentElement || document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['href','rel','as','src','media','type','data-rocketlazyloadscript']
        });

        window.addEventListener('load', function(){
            try {
                document.querySelectorAll('style').forEach(maybeBlock);
                document.querySelectorAll('link[rel="stylesheet"], link[href*=".css"], link[rel="preload"][as], link[data-rocketlazyloadscript], script[data-rocketlazyloadscript]').forEach(maybeBlock);
            } catch(e){}
            setTimeout(function(){ observer.disconnect(); }, 6000);
        });
    })();
    </script>
    <?php
}, 0);