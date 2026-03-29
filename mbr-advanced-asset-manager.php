<?php
/**
 * Plugin Name:       MBR Advanced Asset Manager
 * Plugin URI:        https://littlewebshack.com
 * Description:       Easily manage/block unnecessary and unwanted CSS (styles)/JS (scripts) from running on individual pages. Save on average 2-3MB. No external services required.
 * Version:           2.5.0
 * Author:            Robert Palmer
 * Author URI:        https://littlewebshack.com
 * Text Domain:       mbr-advanced-asset-manager
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Buy Me a Coffee
add_filter( 'plugin_row_meta', function ( $links, $file, $data ) {
    if ( ! function_exists( 'plugin_basename' ) || $file !== plugin_basename( __FILE__ ) ) {
        return $links;
    }

    $url = 'https://buymeacoffee.com/robertpalmer/';
    // translators: %s: The name of the plugin author.
    $links[] = sprintf(
		// translators: %s: The name of the plugin author.
        '<a href="%s" target="_blank" rel="noopener nofollow" aria-label="%s">☕ %s</a>',
        esc_url( $url ),
		// translators: %s: The name of the plugin author.
        esc_attr( sprintf( __( 'Buy %s a coffee', 'mbr-advanced-asset-manager' ), isset( $data['AuthorName'] ) ? $data['AuthorName'] : __( 'the author', 'mbr-advanced-asset-manager' ) ) ),
        esc_html__( 'Buy me a coffee', 'mbr-advanced-asset-manager' )
    );

    return $links;
}, 10, 3 );

final class MBR_Advanced_Asset_Manager {
    private static $instance = null;
    const META_KEY = '_mbr_asm_blocklist_v1';
    const META_DISABLE = '_mbr_asm_disable';
    const GLOBAL_KEY = 'mbr_asm_global_blocklist';

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'wp_ajax_mbr_asm_get_assets', [ $this, 'ajax_get_assets' ] );
        add_action( 'wp_ajax_mbr_asm_save_blocklist', [ $this, 'ajax_save_blocklist' ] );
        add_action( 'wp_ajax_mbr_asm_clear_blocklist', [ $this, 'ajax_clear_blocklist' ] );
        add_action( 'wp_ajax_mbr_asm_set_disabled', [ $this, 'ajax_set_disabled' ] );
        add_action( 'wp_ajax_mbr_asm_save_global_blocklist', [ $this, 'ajax_save_global_blocklist' ] );
        add_action( 'wp_ajax_mbr_asm_get_global_blocklist', [ $this, 'ajax_get_global_blocklist' ] );
        
        // Frontend blocking - use MULTIPLE hooks to catch scripts at different stages
        if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            // Primary hook - runs with most plugins
            add_action( 'wp_enqueue_scripts', [ $this, 'block_assets' ], 999 );
            
            // Secondary hook - catches late-loading scripts
            add_action( 'wp_enqueue_scripts', [ $this, 'block_assets' ], 99999 );
            
            // Print hooks - run right before output
            add_action( 'wp_print_scripts', [ $this, 'block_assets' ], 999 );
            add_action( 'wp_print_styles', [ $this, 'block_assets' ], 999 );
            
            // Head hook - very late
            add_action( 'wp_head', [ $this, 'block_assets' ], 999 );
        }
    }
    
    public function block_assets() {
        // Prevent running multiple times
        static $already_run = false;
        if ( $already_run ) {
            return;
        }
        
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }
        
        // Scan mode requires admin permission
        if ( isset( $_GET['mbr_asm_scan'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            return;
        }
        
        if ( isset( $_GET['mbr_asm_nocache'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        
        // Get page ID
        $page_id = get_queried_object_id();
        if ( ! $page_id && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if ( ! $page_id && isset( $_GET['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page_id = absint( $_GET['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if ( ! $page_id && isset( $_GET['preview_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page_id = absint( $_GET['preview_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if ( ! $page_id && isset( $_POST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $page_id = absint( $_POST['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
        
        if ( $page_id ) {
            $disabled = get_post_meta( $page_id, self::META_DISABLE, true );
            if ( $disabled && is_user_logged_in() && ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) || current_user_can( 'edit_others_pages' ) ) ) {
                return;
            }
        }
        
        // Skip in page builder edit modes
        if ( isset( $_GET['elementor-preview'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            isset( $_GET['elementor_library'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            isset( $_GET['elementor-preview-mode'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ( isset( $_GET['action'] ) && in_array( $_GET['action'], [ 'elementor', 'elementor_ajax' ], true ) ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ( isset( $_POST['action'] ) && strpos( sanitize_text_field( wp_unslash( $_POST['action'] ) ), 'elementor' ) !== false ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }
        
        if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) ) {
            $elementor = \Elementor\Plugin::instance();
            if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
                return;
            }
            if ( isset( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) && $elementor->preview->is_preview_mode() ) {
                return;
            }
        }
        
        if ( isset( $_GET['fl_builder'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            isset( $_GET['et_fb'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            isset( $_GET['vc_editable'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            isset( $_GET['ct_builder'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            isset( $_GET['bricks'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        
        $preview_mode = isset( $_GET['mbr_asm_preview'] ) && $_GET['mbr_asm_preview'] === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        
        if ( ! $page_id ) {
            // No page ID, but global blocklist may still apply
            $blocklist = [];
            $global_blocklist = get_option( self::GLOBAL_KEY, [] );
            if ( is_array( $global_blocklist ) && ! empty( $global_blocklist ) ) {
                $blocklist = $global_blocklist;
            }
            if ( empty( $blocklist ) ) {
                return;
            }
        } else {
        
        // Get blocklist
        $blocklist = [];
        if ( $preview_mode && isset( $_COOKIE['mbr_asm_preview_blocklist'] ) ) {
            // Sanitize cookie data before processing
            $raw_cookie = sanitize_text_field( wp_unslash( $_COOKIE['mbr_asm_preview_blocklist'] ) );
            
            // Decode and validate JSON
            $preview_data = json_decode( urldecode( $raw_cookie ), true );
            
            // Validate the data structure and sanitize contents
            if ( is_array( $preview_data ) && isset( $preview_data[ $page_id ] ) && is_array( $preview_data[ $page_id ] ) ) {
                $validated_blocklist = [];
                
                // Validate and sanitize each item
                foreach ( $preview_data[ $page_id ] as $item ) {
                    if ( ! is_array( $item ) ) {
                        continue;
                    }
                    
                    // Validate required fields
                    if ( ! isset( $item['url'] ) || ! isset( $item['type'] ) ) {
                        continue;
                    }
                    
                    // Sanitize and validate URL
                    $url = esc_url_raw( $item['url'] );
                    if ( empty( $url ) ) {
                        continue;
                    }
                    
                    // Validate type
                    $type = sanitize_key( $item['type'] );
                    if ( ! in_array( $type, [ 'style', 'script' ], true ) ) {
                        continue;
                    }
                    
                    // Validate device
                    $device = isset( $item['device'] ) ? sanitize_key( $item['device'] ) : 'any';
                    if ( ! in_array( $device, [ 'any', 'mobile', 'desktop' ], true ) ) {
                        $device = 'any';
                    }
                    
                    $validated_blocklist[] = [
                        'url' => $url,
                        'type' => $type,
                        'device' => $device,
                    ];
                }
                
                $blocklist = $validated_blocklist;
            }
        } else {
            $blocklist = get_post_meta( $page_id, self::META_KEY, true );
        }
        
        if ( ! is_array( $blocklist ) ) {
            $blocklist = [];
        }
        
        // Merge global blocklist (Block on All Pages)
        $global_blocklist = get_option( self::GLOBAL_KEY, [] );
        if ( is_array( $global_blocklist ) && ! empty( $global_blocklist ) ) {
            $blocklist = array_merge( $blocklist, $global_blocklist );
        }
        
        if ( empty( $blocklist ) ) {
            return;
        }
        
        } // end else (has page_id)
        
        $is_mobile = wp_is_mobile();
        $device = $is_mobile ? 'mobile' : 'desktop';
        
        global $wp_styles, $wp_scripts;
        
        $blocked_count = 0;
        $skipped_count = 0;
        $not_found_urls = []; // Track scripts we couldn't block via handle
        
        foreach ( $blocklist as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            
            $url = $item['url'] ?? '';
            $type = $item['type'] ?? '';
            $item_device = $item['device'] ?? 'any';
            
            if ( ! $url || ! $type ) {
                continue;
            }
            
            if ( $item_device !== 'any' && $item_device !== $device ) {
                continue;
            }
            
            if ( $type === 'style' && ! empty( $wp_styles ) && is_object( $wp_styles ) ) {
                $handle = $this->find_handle_by_url( $wp_styles, $url );
                if ( $handle ) {
                    wp_dequeue_style( $handle );
                    wp_deregister_style( $handle );
                    $blocked_count++;
                } else {
                    $skipped_count++;
                    $not_found_urls[] = [ 'url' => $url, 'type' => 'style' ];
                }
            } elseif ( $type === 'script' && ! empty( $wp_scripts ) && is_object( $wp_scripts ) ) {
                $handle = $this->find_handle_by_url( $wp_scripts, $url );
                if ( $handle ) {
                    // Never block critical WordPress core scripts
                    if ( $this->is_critical_script( $handle, $url ) ) {
                        continue;
                    }
                    wp_dequeue_script( $handle );
                    wp_deregister_script( $handle );
                    $blocked_count++;
                } else {
                    // Check if it's a critical script even without handle
                    if ( ! $this->is_critical_script( '', $url ) ) {
                        $skipped_count++;
                        $not_found_urls[] = [ 'url' => $url, 'type' => 'script' ];
                    }
                }
            }
        }
        
        // For scripts without handles, use client-side blocking
        if ( ! empty( $not_found_urls ) ) {
            add_action( 'wp_footer', function() use ( $not_found_urls ) {
                static $injected = false;
                if ( $injected ) {
                    return; // Already injected, don't duplicate
                }
                $injected = true;
                $this->inject_client_side_blocker( $not_found_urls );
            }, 1 );
        }
        
        // Mark as completed
        $already_run = true;
    }
    
    private function inject_client_side_blocker( $urls ) {
        if ( empty( $urls ) ) {
            return;
        }
        
        $styles = [];
        $scripts = [];
        
        foreach ( $urls as $item ) {
            if ( $item['type'] === 'style' ) {
                $styles[] = $item['url'];
            } elseif ( $item['type'] === 'script' ) {
                $scripts[] = $item['url'];
            }
        }
        
        ?>
        <script id="mbr-asm-fallback-blocker" type="text/javascript">
        (function() {
            if (!('URL' in window)) return;
            const origin = window.location.origin;

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

            const blockedStyles = <?php echo wp_json_encode( array_values( array_unique( $styles ) ) ); ?>;
            const blockedScripts = <?php echo wp_json_encode( array_values( array_unique( $scripts ) ) ); ?>;

            // Critical scripts that should NEVER be blocked
            const criticalPatterns = [
                '/wp-includes/js/jquery/jquery',
                '/wp-includes/js/jquery/jquery-migrate',
                'wp-polyfill',
                'regenerator-runtime',
                '/wp-includes/js/dist/hooks',
                '/wp-includes/js/dist/i18n',
                '/wp-includes/js/dist/api-fetch',
                '/wp-includes/js/dist/dom-ready',
                '/wp-includes/js/dist/element'
            ];

            function isCritical(url) {
                const urlStr = String(url || '');
                return criticalPatterns.some(pattern => urlStr.includes(pattern));
            }

            const blockedSet = new Set();
            blockedStyles.concat(blockedScripts).forEach(u => {
                if (!isCritical(u)) {
                    variants(u).forEach(v => blockedSet.add(v));
                }
            });
            
            function isBlocked(u){ 
                if (isCritical(u)) return false; // Never block critical scripts
                return variants(u).some(v => blockedSet.has(v)); 
            }
            function kill(node){ try{ if(node.parentNode) node.parentNode.removeChild(node);}catch(e){} }
            
            function maybeBlock(node){
                if (!node || node.nodeType !== 1) return;
                const tag = node.tagName;
                
                if (tag === 'LINK') {
                    const rel = (node.rel||'').toLowerCase();
                    const href = node.href || '';
                    if (rel==='stylesheet' && href && isBlocked(href)) {
                        try{ node.disabled = true; }catch(e){}
                        kill(node);
                        return;
                    }
                } else if (tag === 'SCRIPT') {
                    const src = node.src || '';
                    if (src && isBlocked(src)) {
                        try {
                            node.type = 'javascript/blocked';
                            node.removeAttribute('src');
                            node.src = '';
                            node.textContent = '';
                        } catch(e) {}
                        kill(node);
                        return;
                    }
                }
            }

            // Block existing elements
            document.querySelectorAll('link[rel="stylesheet"], script[src]').forEach(maybeBlock);

            // Intercept new elements
            const _append = Node.prototype.appendChild;
            Node.prototype.appendChild = function(ch){ maybeBlock(ch); return _append.call(this, ch); };
            const _insert = Node.prototype.insertBefore;
            Node.prototype.insertBefore = function(ch, ref){ maybeBlock(ch); return _insert.call(this, ch, ref); };

            // Watch for attribute changes
            const observer = new MutationObserver(function(mList){
                for (const m of mList) {
                    if (m.type === 'childList' && m.addedNodes) {
                        m.addedNodes.forEach(maybeBlock);
                    }
                }
            });
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true
            });

            window.addEventListener('load', function(){
                setTimeout(function(){ observer.disconnect(); }, 3000);
            });
        })();
        </script>
        <?php
    }
    
    private function is_critical_script( $handle, $url ) {
        // Critical handles that should never be blocked
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
            'wp-api-fetch',
            'wp-dom-ready',
            'wp-element',
            'wp-escape-html',
            'wp-url',
        ];
        
        if ( in_array( $handle, $critical_handles, true ) ) {
            return true;
        }
        
        // Check URL patterns for critical scripts
        $critical_patterns = [
            '/wp-includes/js/dist/vendor/wp-polyfill',
            '/wp-includes/js/dist/vendor/regenerator-runtime',
            '/wp-includes/js/jquery/jquery',
            '/wp-includes/js/jquery/jquery-migrate',
            '/wp-includes/js/dist/hooks',
            '/wp-includes/js/dist/i18n',
            '/wp-includes/js/dist/api-fetch',
            '/wp-includes/js/dist/dom-ready',
            '/wp-includes/js/dist/element',
        ];
        
        foreach ( $critical_patterns as $pattern ) {
            if ( strpos( $url, $pattern ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    private function find_handle_by_url( $wp_dependencies, $target_url ) {
        if ( ! is_object( $wp_dependencies ) ) {
            return false;
        }
        
        $target_variants = $this->url_variants( $target_url );
        
        foreach ( $wp_dependencies->registered as $handle => $obj ) {
            if ( empty( $obj->src ) ) {
                continue;
            }
            $src = $this->abs_url( $obj->src );
            $src_variants = $this->url_variants( $src );
            
            // Check if any variant matches
            foreach ( $target_variants as $target_v ) {
                foreach ( $src_variants as $src_v ) {
                    if ( $target_v === $src_v ) {
                        return $handle;
                    }
                }
            }
        }
        
        return false;
    }
    
    private function normalize_url( $url ) {
        $url = strtok( $url, '?' ); // Remove query string
        $url = strtok( $url, '#' ); // Remove hash
        return rtrim( $url, '/' );
    }

    public function add_menu() {
        add_options_page(
            __( 'Advanced Asset Manager', 'mbr-advanced-asset-manager' ),
            __( 'Advanced Asset Manager', 'mbr-advanced-asset-manager' ),
            'manage_options',
            'advanced_asset_manager',
            [ $this, 'settings_page_html' ]
        );
    }

    public function admin_assets( $hook ) {
        if ( $hook !== 'settings_page_advanced_asset_manager' ) {
            return;
        }
        wp_enqueue_script( 'jquery' );

        $inline_css = '
/* ═══ Dark Mode Admin UI ═══ */
.asm-wrap{background:#1e1e2e;color:#cdd6f4;border-radius:12px;padding:24px 28px;margin:20px 20px 20px 0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif}
.asm-wrap h1{color:#cba6f7;font-size:22px;font-weight:700;margin:0 0 6px;letter-spacing:-.3px}
.asm-wrap .asm-subtitle{color:#a6adc8;font-size:13px;margin:0 0 20px}
.asm-table{border-collapse:separate;border-spacing:0;width:100%;border-radius:8px;overflow:hidden;border:1px solid #313244}
.asm-table th{background:#181825;color:#a6adc8;font-size:11px;text-transform:uppercase;letter-spacing:.6px;padding:10px 14px;border-bottom:2px solid #313244;text-align:left;font-weight:600}
.asm-table td{background:#1e1e2e;padding:10px 14px;border-bottom:1px solid #313244;vertical-align:middle;font-size:13px;color:#cdd6f4}
.asm-table tbody tr:hover td{background:#262637}
.asm-table tbody tr.asm-row-critical td{background:#45324e!important}
.column-url{word-break:break-all;color:#89b4fa;font-family:"SF Mono",Monaco,Inconsolata,monospace;font-size:12px}
.asm-controls{margin:0 0 20px}
.asm-muted{color:#6c7086}
.asm-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}

/* ═══ Select + Buttons ═══ */
.asm-wrap select,.asm-wrap .asm-device{background:#313244;color:#fff;border:1px solid #45475a;border-radius:6px;padding:6px 10px;font-size:13px;outline:none;cursor:pointer}
.asm-wrap select:focus,.asm-wrap .asm-device:focus{border-color:#89b4fa;box-shadow:0 0 0 2px rgba(137,180,250,.2)}
.asm-wrap select option{background:#313244;color:#fff}
.asm-wrap select optgroup{background:#181825;color:#cba6f7;font-weight:700;font-style:normal;font-size:12px;letter-spacing:.3px}
.asm-wrap .button{background:#313244;color:#cdd6f4;border:1px solid #45475a;border-radius:6px;padding:6px 16px;font-size:13px;cursor:pointer;transition:all .15s ease}
.asm-wrap .button:hover{background:#45475a;border-color:#585b70;color:#fff}
.asm-wrap .button:focus{box-shadow:0 0 0 2px rgba(137,180,250,.25);outline:none}
.asm-wrap .button-primary{background:#89b4fa;color:#1e1e2e;border-color:#89b4fa;font-weight:600}
.asm-wrap .button-primary:hover{background:#74c7ec;border-color:#74c7ec;color:#1e1e2e}
.asm-wrap .button-danger{background:#f38ba8;color:#1e1e2e;border-color:#f38ba8;font-weight:600}
.asm-wrap .button-danger:hover{background:#eba0ac;border-color:#eba0ac;color:#1e1e2e}

/* ═══ Toggle Switch ═══ */
.asm-toggle{position:relative;display:inline-flex;align-items:center;cursor:pointer;gap:8px;font-size:13px;user-select:none}
.asm-toggle input{position:absolute;opacity:0;width:0;height:0;pointer-events:none}
.asm-toggle .asm-toggle-track{position:relative;width:40px;height:22px;background:#45475a;border-radius:11px;transition:background .2s ease;flex-shrink:0}
.asm-toggle .asm-toggle-track::after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;background:#6c7086;border-radius:50%;transition:all .2s ease;box-shadow:0 1px 3px rgba(0,0,0,.3)}
.asm-toggle input:checked+.asm-toggle-track{background:#89b4fa}
.asm-toggle input:checked+.asm-toggle-track::after{transform:translateX(18px);background:#1e1e2e}
.asm-toggle input:focus-visible+.asm-toggle-track{box-shadow:0 0 0 2px rgba(137,180,250,.4)}
.asm-toggle .asm-toggle-label{color:#cdd6f4;font-size:12px}

/* Block toggle - red */
.asm-toggle-block input:checked+.asm-toggle-track{background:#f38ba8}
.asm-toggle-block input:checked+.asm-toggle-track::after{background:#1e1e2e}

/* Global toggle - purple */
.asm-toggle-global input:checked+.asm-toggle-track{background:#cba6f7}
.asm-toggle-global input:checked+.asm-toggle-track::after{background:#1e1e2e}

/* ═══ Stats Header ═══ */
.asm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;background:#181825;border:1px solid #313244;border-radius:10px;padding:18px 22px;margin:0 0 20px}
.asm-stat-label{font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:#6c7086;font-weight:600;margin-bottom:4px}
.asm-stat-value{font-size:22px;font-weight:700;font-variant-numeric:tabular-nums}
.asm-stat-blocked{color:#f38ba8}
.asm-stat-saved{color:#a6e3a1}

/* ═══ Section Headers ═══ */
.asm-section-header{margin:24px 0 12px;padding:12px 16px;border-radius:8px;font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px}
.asm-section-css{background:rgba(137,180,250,.08);border-left:4px solid #89b4fa;color:#89b4fa}
.asm-section-js{background:rgba(166,227,161,.08);border-left:4px solid #a6e3a1;color:#a6e3a1}

/* ═══ Toolbar ═══ */
.asm-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:12px 0}

/* ═══ Overlays ═══ */
.asm-overlay{display:none;position:fixed;inset:0;background:rgba(17,17,27,.85);z-index:9999;backdrop-filter:blur(4px)}
.asm-overlay-inner{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;color:#cdd6f4}
.asm-overlay .spinner{filter:brightness(2)}

/* ═══ Scroll Top ═══ */
#asm_scroll_top{display:none;position:fixed;bottom:20px;right:20px;background:#89b4fa;color:#1e1e2e;border:none;border-radius:8px;padding:12px 16px;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.4);z-index:9998;font-size:13px;font-weight:700;transition:all .2s ease}
#asm_scroll_top:hover{background:#74c7ec;transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.5)}

/* ═══ Notice Overrides ═══ */
.asm-wrap .notice{background:#313244;border-color:#45475a;color:#cdd6f4;border-radius:6px;padding:10px 14px}
.asm-wrap .notice-error{border-left-color:#f38ba8}
.asm-wrap .notice-success{border-left-color:#a6e3a1}

/* ═══ Critical Tag ═══ */
.asm-critical-badge{color:#f38ba8;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.4px}

/* ═══ Source Tag ═══ */
.asm-source{display:inline-block;background:#313244;color:#a6adc8;padding:2px 8px;border-radius:4px;font-size:11px}

/* ═══ Action Cell ═══ */
.asm-action-cell{display:flex;flex-direction:column;gap:6px}
.asm-action-row{display:flex;align-items:center;gap:10px}
';
        wp_register_style( 'asm-safe-inline', false );
        wp_enqueue_style( 'asm-safe-inline' );
        wp_add_inline_style( 'asm-safe-inline', $inline_css );

        $data = [
            'ajax'  => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'mbr_asm_safe' ),
            'preview_nonce' => wp_create_nonce( 'mbr_asm_preview_nonce' ),
        ];
        wp_register_script( 'asm-safe-admin', false, [ 'jquery' ], '2.0.0', true );
        wp_add_inline_script( 'asm-safe-admin', 'window.ASM_SAFE=' . wp_json_encode( $data ) . ';' );
        wp_enqueue_script( 'asm-safe-admin' );
    }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $pages = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );
        
        // Build grouped list of all public post types
        $public_types = get_post_types( [ 'public' => true ], 'objects' );
        $grouped_posts = [];
        foreach ( $public_types as $pt ) {
            if ( $pt->name === 'attachment' ) {
                continue;
            }
            $query = new WP_Query( [
                'post_type'      => $pt->name,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
                'no_found_rows'  => true,
            ] );
            if ( $query->have_posts() ) {
                $grouped_posts[ $pt->labels->name ] = $query->posts;
            }
            wp_reset_postdata();
        }
        ?>
        <div class="asm-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p class="asm-subtitle"><?php esc_html_e( 'Scan any page, post or custom post type. Sort by size and block per device. Use "Preview (dry run)" to test without saving. No external services required.', 'mbr-advanced-asset-manager' ); ?></p>
            
            <!-- Stats Header -->
            <div id="asm_stats_header" class="asm-stats" style="display:none;">
                <div>
                    <div class="asm-stat-label"><?php esc_html_e( 'Files Blocked', 'mbr-advanced-asset-manager' ); ?></div>
                    <div id="asm_stat_blocked" class="asm-stat-value asm-stat-blocked">0</div>
                </div>
                <div>
                    <div class="asm-stat-label"><?php esc_html_e( 'Total Size Saved', 'mbr-advanced-asset-manager' ); ?></div>
                    <div id="asm_stat_saved" class="asm-stat-value asm-stat-saved">0 KB</div>
                </div>
                <div>
                    <div class="asm-stat-label"><?php esc_html_e( 'Global Rules', 'mbr-advanced-asset-manager' ); ?></div>
                    <div id="asm_stat_global" class="asm-stat-value" style="color:#cba6f7;">0</div>
                </div>
            </div>

            <div class="asm-controls">
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <label for="asm_page" style="font-weight:600;color:#a6adc8;"><?php esc_html_e( 'Content:', 'mbr-advanced-asset-manager' ); ?></label>
                    <select id="asm_page">
                        <option value=""><?php esc_html_e( '— Select —', 'mbr-advanced-asset-manager' ); ?></option>
                        <?php foreach ( $grouped_posts as $type_label => $posts ) : ?>
                            <optgroup label="<?php echo esc_attr( $type_label ); ?>">
                                <?php foreach ( $posts as $p ) : ?>
                                    <option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html( $p->post_title ?: "ID {$p->ID}" ); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button button-primary" id="asm_scan"><?php esc_html_e( 'Scan assets', 'mbr-advanced-asset-manager' ); ?></button>
                    <span id="asm_spinner" class="spinner" style="float:none;margin:0;display:none;filter:brightness(2);"></span>
                </div>
                <div class="asm-toolbar">
                    <button type="button" class="button" id="asm_preview" style="display:none"><?php esc_html_e( 'Preview (dry run)', 'mbr-advanced-asset-manager' ); ?></button>
                    <button type="button" class="button button-primary" id="asm_save" style="display:none"><?php esc_html_e( 'Save blocklist', 'mbr-advanced-asset-manager' ); ?></button>
                    <button type="button" class="button button-danger" id="asm_clear" style="display:none"><?php esc_html_e( 'Clear all rules for this page', 'mbr-advanced-asset-manager' ); ?></button>
                    <label class="asm-toggle" style="display:none"><input type="checkbox" id="asm_disable"><span class="asm-toggle-track"></span><span class="asm-toggle-label"><?php esc_html_e( 'Temporarily disable blocking on this page (for editors)', 'mbr-advanced-asset-manager' ); ?></span></label>
                    <span id="asm_feedback" class="asm-muted" style="margin-left:8px;"></span>
                </div>
            </div>

            <div id="asm_totals" class="asm-muted" style="margin-bottom:12px;font-size:12px;"></div>
            <div id="asm_results"></div>
            
            <div id="asm_loading_overlay" class="asm-overlay">
                <div class="asm-overlay-inner">
                    <span class="spinner is-active" style="float:none;visibility:visible;width:30px;height:30px;"></span>
                    <div style="margin-top:12px;font-weight:600;font-size:16px;"><?php esc_html_e( 'Loading Assets…', 'mbr-advanced-asset-manager' ); ?></div>
                </div>
            </div>
            <div id="asm_saving_overlay" class="asm-overlay">
                <div class="asm-overlay-inner">
                    <span class="spinner is-active" style="float:none;visibility:visible;width:30px;height:30px;"></span>
                    <div style="margin-top:12px;font-weight:600;font-size:16px;"><?php esc_html_e( 'Saving Assets…', 'mbr-advanced-asset-manager' ); ?></div>
                </div>
            </div>
            
            <button id="asm_scroll_top" title="Scroll to top">↑ Top</button>
        </div>
        <script>
        jQuery(function($){
            const $page = $('#asm_page'), $scan = $('#asm_scan'), $spinner = $('#asm_spinner'),
                  $results = $('#asm_results'), $save = $('#asm_save'), $fb = $('#asm_feedback'),
                  $preview = $('#asm_preview'), $clear = $('#asm_clear'), $disable = $('#asm_disable');

            let currentDisabled = false;
            let lastScanData = null;
            let globalBlocklist = [];

            // Load global blocklist on init
            $.post(window.ASM_SAFE.ajax, {
                action: 'mbr_asm_get_global_blocklist',
                _ajax_nonce: window.ASM_SAFE.nonce
            }, function(res){
                if (res.success && Array.isArray(res.data)) {
                    globalBlocklist = res.data;
                }
            });

            // Scroll to top
            const $scrollBtn = $('#asm_scroll_top');
            $(window).on('scroll', function(){
                if ($(window).scrollTop() > 300) {
                    $scrollBtn.fadeIn();
                } else {
                    $scrollBtn.fadeOut();
                }
            });
            $scrollBtn.on('click', function(){
                $('html, body').animate({scrollTop: 0}, 400);
            });

            function addParam(url, key, value){
                try{
                    const u = new URL(url, window.location.origin);
                    u.searchParams.set(key, value);
                    return u.toString();
                }catch(e){
                    const sep = url.indexOf('?') === -1 ? '?' : '&';
                    return url + sep + encodeURIComponent(key) + '=' + encodeURIComponent(value);
                }
            }

            function norm(u){
                try{ 
                    const url = new URL(u, location.origin); 
                    url.search=''; 
                    url.hash=''; 
                    return url.toString(); 
                }
                catch(e){ 
                    return String(u||''); 
                }
            }
            
            function humanBytes(n){
                if (n === 0) return '0 B';
                if (!n || n < 0) return '—';
                const units = ['B','KB','MB','GB'];
                let i=0, val=n;
                while (val >= 1024 && i < units.length-1){ val/=1024; i++; }
                return (Math.round(val*10)/10)+' '+units[i];
            }

            function isGlobal(url, type) {
                return globalBlocklist.some(g => norm(g.url) === norm(url) && g.type === type);
            }
            
            function row(label, url, source, type, size, checked, device){
                const isCritical = type === 'script' && (
                    url.includes('/wp-includes/js/jquery/jquery') ||
                    url.includes('/wp-includes/js/jquery/jquery-migrate') ||
                    url.includes('wp-polyfill') ||
                    url.includes('regenerator-runtime') ||
                    url.includes('/wp-includes/js/dist/hooks') ||
                    url.includes('/wp-includes/js/dist/i18n') ||
                    url.includes('/wp-includes/js/dist/api-fetch') ||
                    url.includes('/wp-includes/js/dist/dom-ready') ||
                    url.includes('/wp-includes/js/dist/element') ||
                    label === 'jquery' || label === 'jquery-core' || label === 'jquery-migrate'
                );
                
                const warning = isCritical ? ' <span class="asm-critical-badge" title="Critical WordPress script — blocking may break your site.">⚠ CRITICAL</span>' : '';
                const disableAttr = isCritical ? ' disabled' : '';
                const isGlobalChecked = isGlobal(url, type);
                const isBlocked = checked || isGlobalChecked;
                
                const devSel = '<select class="asm-device" data-url="' + url + '" data-type="' + type + '"' + (isCritical ? ' disabled' : '') + '>' +
                    '<option value="any"' + (device==='any'?' selected':'') + '>Any device</option>' +
                    '<option value="desktop"' + (device==='desktop'?' selected':'') + '>Desktop</option>' +
                    '<option value="mobile"' + (device==='mobile'?' selected':'') + '>Mobile</option>' +
                    '</select>';
                
                return '<tr class="' + (isCritical ? 'asm-row-critical' : '') + '">' +
                    '<td>' + (label || '<em class="asm-muted">—</em>') + warning + '</td>' +
                    '<td class="column-url">' + url + '</td>' +
                    '<td><span class="asm-source">' + (source || '') + '</span></td>' +
                    '<td class="asm-num" data-bytes="' + (size||0) + '">' + humanBytes(size) + '</td>' +
                    '<td><div class="asm-action-cell">' +
                        '<div class="asm-action-row">' +
                            '<label class="asm-toggle asm-toggle-block"><input type="checkbox" class="asm-block" data-url="' + url + '" data-type="' + type + '" ' + (isBlocked?'checked':'') + disableAttr + '><span class="asm-toggle-track"></span><span class="asm-toggle-label">' + (isCritical ? 'Protected' : 'Block') + '</span></label>' +
                            '&nbsp;' + devSel +
                        '</div>' +
                        (!isCritical ? '<div class="asm-action-row">' +
                            '<label class="asm-toggle asm-toggle-global"><input type="checkbox" class="asm-global" data-url="' + url + '" data-type="' + type + '" ' + (isGlobalChecked?'checked':'') + '><span class="asm-toggle-track"></span><span class="asm-toggle-label">All Pages</span></label>' +
                        '</div>' : '') +
                    '</div></td>' +
                '</tr>';
            }
            
            function notice(type, msg){ 
                return '<div class="notice notice-' + type + '"><p>' + msg + '</p></div>'; 
            }

            function buildMap(existing){
                const map = {};
                if (Array.isArray(existing)){
                    existing.forEach(it => {
                        if (it.url && it.type){
                            const k = norm(it.url) + '|||' + (it.type||'');
                            map[k] = it.device || 'any';
                        }
                    });
                }
                return map;
            }

            function updateStats(items){
                if (!lastScanData) return;
                
                let totalSaved = 0;
                const allAssets = [...(lastScanData.styles || []), ...(lastScanData.scripts || [])];
                
                items.forEach(item => {
                    const asset = allAssets.find(a => norm(a.url) === norm(item.url));
                    if (asset && asset.size) {
                        totalSaved += asset.size;
                    }
                });

                // Count globals
                const globalCount = globalBlocklist.length;

                if (items.length > 0 || globalCount > 0) {
                    $('#asm_stats_header').show();
                    $('#asm_stat_blocked').text(items.length + ' file' + (items.length !== 1 ? 's' : ''));
                    $('#asm_stat_saved').text(humanBytes(totalSaved));
                    $('#asm_stat_global').text(globalCount + ' rule' + (globalCount !== 1 ? 's' : ''));
                } else {
                    $('#asm_stats_header').hide();
                }
            }

            // Handle global toggle changes
            $(document).on('change', '.asm-global', function(){
                const $g = $(this);
                const url = $g.data('url');
                const type = $g.data('type');
                const checked = $g.is(':checked');
                
                if (checked) {
                    // Also check the block toggle
                    const $block = $('.asm-block[data-url="' + url + '"][data-type="' + type + '"]');
                    $block.prop('checked', true);
                    
                    // Add to global
                    const device = $('.asm-device[data-url="' + url + '"][data-type="' + type + '"]').val() || 'any';
                    if (!globalBlocklist.some(g => norm(g.url) === norm(url) && g.type === type)) {
                        globalBlocklist.push({ url: url, type: type, device: device });
                    }
                } else {
                    // Remove from global
                    globalBlocklist = globalBlocklist.filter(g => !(norm(g.url) === norm(url) && g.type === type));
                }
                
                // Save global list
                $.post(window.ASM_SAFE.ajax, {
                    action: 'mbr_asm_save_global_blocklist',
                    _ajax_nonce: window.ASM_SAFE.nonce,
                    items: JSON.stringify(globalBlocklist)
                }, function(res){
                    if (res.success) {
                        $fb.text('✔ Global rules updated').css('color','#a6e3a1');
                        setTimeout(() => $fb.text(''), 3000);
                        $('#asm_stat_global').text(globalBlocklist.length + ' rule' + (globalBlocklist.length !== 1 ? 's' : ''));
                    }
                });
            });

            function renderResults(data){
                const styles = data.styles || [];
                const scripts = data.scripts || [];
                const existing = data.blocked || [];
                const map = buildMap(existing);
                const disabled = data.disabled || false;

                currentDisabled = disabled;
                $disable.prop('checked', disabled);

                let blockedCount = 0;
                let totalSaved = 0;
                existing.forEach(item => {
                    blockedCount++;
                    const allAssets = [...styles, ...scripts];
                    const asset = allAssets.find(a => norm(a.url) === norm(item.url));
                    if (asset && asset.size) {
                        totalSaved += asset.size;
                    }
                });

                const globalCount = globalBlocklist.length;

                if (blockedCount > 0 || globalCount > 0) {
                    $('#asm_stats_header').show();
                    $('#asm_stat_blocked').text(blockedCount + ' file' + (blockedCount !== 1 ? 's' : ''));
                    $('#asm_stat_saved').text(humanBytes(totalSaved));
                    $('#asm_stat_global').text(globalCount + ' rule' + (globalCount !== 1 ? 's' : ''));
                } else {
                    $('#asm_stats_header').hide();
                }

                let html = '';
                if (data.error){
                    html = notice('error', data.error);
                } else {
                    const combinedTotals = (data.total_styles||0) + (data.total_scripts||0);
                    $('#asm_totals').html('Total scanned size: <strong>' + humanBytes(combinedTotals) + '</strong> (styles: ' + humanBytes(data.total_styles||0) + ', scripts: ' + humanBytes(data.total_scripts||0) + ')');

                    if (styles.length > 0) {
                        html += '<div class="asm-section-header asm-section-css">Style Files (CSS) — ' + styles.length + ' files</div>';
                        html += '<table class="asm-table">';
                        html += '<thead><tr><th>Handle</th><th>URL</th><th>Source</th><th>Size</th><th>Action</th></tr></thead><tbody>';
                        styles.forEach(s => {
                            const k = norm(s.url) + '|||style';
                            const chk = !!map[k];
                            const dev = map[k] || 'any';
                            html += row(s.handle, s.url, s.source, 'style', s.size, chk, dev);
                        });
                        html += '</tbody></table>';
                    }

                    if (scripts.length > 0) {
                        html += '<div class="asm-section-header asm-section-js">JavaScript Files (JS) — ' + scripts.length + ' files</div>';
                        html += '<table class="asm-table">';
                        html += '<thead><tr><th>Handle</th><th>URL</th><th>Source</th><th>Size</th><th>Action</th></tr></thead><tbody>';
                        scripts.forEach(s => {
                            const k = norm(s.url) + '|||script';
                            const chk = !!map[k];
                            const dev = map[k] || 'any';
                            html += row(s.handle, s.url, s.source, 'script', s.size, chk, dev);
                        });
                        html += '</tbody></table>';
                    }
                }

                $results.html(html);
                $save.show(); 
                $clear.show(); 
                $preview.show();
                $disable.parent().show();
            }

            $scan.on('click', function(e){
                e.preventDefault();
                const pid = parseInt($page.val(), 10);
                if (!pid){ alert('Select a page'); return; }

                $('#asm_loading_overlay').show();
                $results.empty(); 
                $save.hide(); 
                $clear.hide(); 
                $preview.hide();
                $disable.parent().hide();
                $fb.text('');
                $('#asm_totals').html('');

                $.post(window.ASM_SAFE.ajax, {
                    action: 'mbr_asm_get_assets',
                    _ajax_nonce: window.ASM_SAFE.nonce,
                    page_id: pid
                }, function(res){
                    $('#asm_loading_overlay').hide();
                    if (res.success && res.data){
                        lastScanData = res.data;
                        renderResults(res.data);
                    } else {
                        $results.html(notice('error', res.data?.error || 'Unknown error'));
                    }
                }).fail(function(){
                    $('#asm_loading_overlay').hide();
                    $results.html(notice('error', 'Request failed'));
                });
            });

            $save.on('click', function(e){
                e.preventDefault();
                const pid = parseInt($page.val(), 10);
                if (!pid) return;

                const items = [];
                $('.asm-block:checked').each(function(){
                    const $cb = $(this);
                    const url = $cb.data('url');
                    const type = $cb.data('type');
                    // Skip items that are global-only (don't double-save)
                    const isGlobalOnly = isGlobal(url, type) && !$('.asm-global[data-url="' + url + '"][data-type="' + type + '"]').is(':checked');
                    const device = $('.asm-device[data-url="' + url + '"][data-type="' + type + '"]').val() || 'any';
                    items.push({ url, type, device });
                });

                $('#asm_saving_overlay').show();
                $.post(window.ASM_SAFE.ajax, {
                    action: 'mbr_asm_save_blocklist',
                    _ajax_nonce: window.ASM_SAFE.nonce,
                    page_id: pid,
                    items: JSON.stringify(items)
                }, function(res){
                    $('#asm_saving_overlay').hide();
                    if (res.success){
                        $fb.text('✔ Saved ' + (res.data.saved||0) + ' rule(s)').css('color','#a6e3a1');
                        setTimeout(() => $fb.text(''), 4000);
                        updateStats(items);
                    } else {
                        $fb.text('✖ ' + (res.data||'Error')).css('color','#f38ba8');
                    }
                }).fail(function(){
                    $('#asm_saving_overlay').hide();
                    $fb.text('✖ Save failed').css('color','#f38ba8');
                });
            });

            $clear.on('click', function(e){
                e.preventDefault();
                if (!confirm('Clear all blocking rules for this page?')) return;
                const pid = parseInt($page.val(), 10);
                if (!pid) return;

                $('#asm_saving_overlay').show();
                $.post(window.ASM_SAFE.ajax, {
                    action: 'mbr_asm_clear_blocklist',
                    _ajax_nonce: window.ASM_SAFE.nonce,
                    page_id: pid
                }, function(res){
                    $('#asm_saving_overlay').hide();
                    if (res.success){
                        $('.asm-block').prop('checked', false);
                        // Re-check any that are still global
                        globalBlocklist.forEach(g => {
                            const $block = $('.asm-block[data-url="' + g.url + '"][data-type="' + g.type + '"]');
                            if ($block.length) $block.prop('checked', true);
                        });
                        $fb.text('✔ Page rules cleared').css('color','#a6e3a1');
                        setTimeout(() => $fb.text(''), 3000);
                        $('#asm_stats_header').hide();
                    } else {
                        $fb.text('✖ Error').css('color','#f38ba8');
                    }
                }).fail(function(){
                    $('#asm_saving_overlay').hide();
                    $fb.text('✖ Failed').css('color','#f38ba8');
                });
            });

            $preview.on('click', function(e){
                e.preventDefault();
                const pid = parseInt($page.val(), 10);
                if (!pid || !lastScanData || !lastScanData.permalink) return;

                const items = [];
                $('.asm-block:checked').each(function(){
                    const $cb = $(this);
                    const url = $cb.data('url');
                    const type = $cb.data('type');
                    const device = $('.asm-device[data-url="'+url+'"][data-type="'+type+'"]').val() || 'any';
                    items.push({ url, type, device });
                });

                try {
                    const previewData = {};
                    previewData[pid] = items;
                    localStorage.setItem('mbr_asm_preview_blocklist', JSON.stringify(previewData));
                    localStorage.setItem('mbr_asm_preview_expires', Date.now() + 3600000);
                } catch(e) {
                    alert('Failed to save preview data: ' + e.message);
                    return;
                }

                setTimeout(() => {
                    const previewUrl = addParam(lastScanData.permalink, 'mbr_asm_preview', '1') + '&t=' + Date.now();
                    window.open(previewUrl, 'mbr_asm_preview_tab');
                }, 50);
            });

            $disable.on('change', function(){
                const pid = parseInt($page.val(), 10);
                if (!pid) return;

                const disabled = $disable.is(':checked') ? 1 : 0;
                $.post(window.ASM_SAFE.ajax, {
                    action: 'mbr_asm_set_disabled',
                    _ajax_nonce: window.ASM_SAFE.nonce,
                    page_id: pid,
                    disabled: disabled
                }, function(res){
                    if (res.success){
                        currentDisabled = res.data.disabled;
                        $fb.text('✔ Updated').css('color','#a6e3a1');
                        setTimeout(() => $fb.text(''), 3000);
                    } else {
                        $fb.text('✖ Error').css('color','#f38ba8');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_get_assets() {
        check_ajax_referer( 'mbr_asm_safe' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 200 );
        }

        $page_id = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;
        if ( ! $page_id ) {
            wp_send_json_error( [ 'error' => 'Missing page_id' ], 200 );
        }

        $url = get_permalink( $page_id );
        if ( ! $url ) {
            wp_send_json_error( [ 'error' => 'Could not get permalink' ], 200 );
        }

        $scan_url = add_query_arg( 'mbr_asm_scan', '1', $url );

        $existing = get_post_meta( $page_id, self::META_KEY, true );
        if ( ! is_array( $existing ) ) {
            $existing = [];
        }

        // Scan using local loopback method
        $result = $this->scan_via_loopback( $scan_url );

        if ( empty( $result['error'] ) ) {
            $index = $this->build_handle_index();
            $result = $this->augment_with_handles_sizes_and_sort( $result, $index );
            $result['blocked'] = $existing;
            $result['permalink'] = $url;
            $result['disabled'] = (bool) get_post_meta( $page_id, self::META_DISABLE, true );
        }

        wp_send_json_success( $result, 200 );
    }

    private function scan_via_loopback( $url ) {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
        $headers = [
            'User-Agent' => $ua,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-GB,en;q=0.9',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Referer' => admin_url( 'options-general.php?page=advanced_script_manager' ),
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Forwarded-For' => ( function() {
                $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';
                return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '127.0.0.1';
            } )(),
        ];

        $cookies = [];
        if ( isset( $_COOKIE ) && is_array( $_COOKIE ) ) {
            foreach ( $_COOKIE as $name => $value ) {
                if ( strpos( $name, 'wordpress' ) !== false || strpos( $name, 'wp-' ) !== false ) {
                    $cookies[] = new WP_Http_Cookie( [ 'name' => $name, 'value' => $value ] );
                }
            }
        }

        $args = [
            'timeout' => 30,
            'redirection' => 5,
            'sslverify' => true,
            'headers' => $headers,
            'cookies' => $cookies,
            'httpversion' => '1.1',
            'blocking' => true,
        ];
        $resp = wp_remote_get( add_query_arg( 'mbr_asm_nocache', '1', $url ), $args );

        if ( is_wp_error( $resp ) ) {
            return [ 'error' => 'Fetch failed: ' . $resp->get_error_message() . '. Try checking your site\'s firewall or security settings.' ];
        }
        $code = wp_remote_retrieve_response_code( $resp );
        $html = wp_remote_retrieve_body( $resp );

        if ( $code >= 500 ) {
            $snippet = $this->first_text_snippet( $html );
            return [ 'error' => 'Server error (HTTP ' . $code . ')' . ( $snippet ? ' — ' . $snippet : '' ) ];
        }
        if ( $code === 403 ) {
            $snippet = $this->first_text_snippet( $html );
            return [ 'error' => 'Access denied (403). Your firewall/WAF may be blocking the scan. Try temporarily disabling security plugins.' . ( $snippet ? ' — ' . $snippet : '' ) ];
        }
        if ( $code < 200 || $code >= 300 || empty( $html ) ) {
            return [ 'error' => 'Unexpected response (HTTP ' . $code . ')' . ( empty( $html ) ? ' or empty body' : '' ) ];
        }

        return $this->parse_assets( $html );
    }

    private function parse_assets( $html ) {
        $styles = [];
        $scripts = [];
        
        // Parse CSS files from <link> tags
        if ( preg_match_all( '/<link[^>]*?href\s*=\s*["\']([^"\']+\.css(?:\?[^"\']*)?)["\'][^>]*>/i', $html, $m ) ) {
            foreach ( array_unique( $m[1] ) as $href ) {
                $styles[] = [ 'handle' => '', 'url' => $this->abs_url( $href ), 'source' => $this->source_of( $href ) ];
            }
        }
        
        // Parse JS files from <script> tags
        if ( preg_match_all( '/<script[^>]*?src\s*=\s*["\']([^"\']+\.js(?:\?[^"\']*)?)["\']/i', $html, $m2 ) ) {
            foreach ( array_unique( $m2[1] ) as $src ) {
                $scripts[] = [ 'handle' => '', 'url' => $this->abs_url( $src ), 'source' => $this->source_of( $src ) ];
            }
        }
        
        return [ 'styles' => $styles, 'scripts' => $scripts ];
    }

    private function build_handle_index() {
        $index = [ 'styles' => [], 'scripts' => [] ];
        global $wp_styles, $wp_scripts;

        if ( ! empty( $wp_styles ) && is_object( $wp_styles ) ) {
            foreach ( $wp_styles->registered as $h => $obj ) {
                if ( empty( $obj->src ) ) {
                    continue;
                }
                $src = $this->abs_url( $obj->src );
                $this->index_handle_url( $index['styles'], $h, $src );
            }
        }
        if ( ! empty( $wp_scripts ) && is_object( $wp_scripts ) ) {
            foreach ( $wp_scripts->registered as $h => $obj ) {
                if ( empty( $obj->src ) ) {
                    continue;
                }
                $src = $this->abs_url( $obj->src );
                $this->index_handle_url( $index['scripts'], $h, $src );
            }
        }
        return $index;
    }

    private function index_handle_url( &$bucket, $handle, $src ) {
        $variants = $this->url_variants( $src );
        foreach ( $variants as $v ) {
            $bucket[ $v ][] = $handle;
        }
    }

    private function url_variants( $u ) {
        $out = [];
        $abs = $this->abs_url( $u );
        $nohash = strtok( $abs, '#' );
        $noq = strtok( $nohash, '?' );
        if ( ! $noq ) {
            $noq = $nohash;
        }
        $out[] = $abs;
        $out[] = $nohash;
        $out[] = $noq;
        $decoded = urldecode( $noq );
        if ( $decoded !== $noq ) {
            $out[] = $decoded;
        }
        $path = wp_parse_url( $noq, PHP_URL_PATH );
        if ( $path ) {
            $parts = array_values( array_filter( explode( '/', $path ) ) );
            $cnt = count( $parts );
            for ( $k = 1; $k <= min( 4, $cnt ); $k++ ) {
                $suffix = implode( '/', array_slice( $parts, $cnt - $k ) );
                $out[] = $suffix;
            }
            $base = basename( $path );
            if ( $base ) {
                $out[] = $base;
            }
        }
        if ( str_ends_with( $noq, '.rtl.css' ) ) {
            $out[] = substr( $noq, 0, -8 ) . '.css';
        }
        return array_values( array_unique( $out ) );
    }

    private function resolve_handle_from_index( $indexBucket, $url ) {
        $candidates = [];
        $variants = $this->url_variants( $url );
        foreach ( $variants as $v ) {
            if ( isset( $indexBucket[ $v ] ) ) {
                foreach ( $indexBucket[ $v ] as $h ) {
                    $candidates[ $h ] = max( $candidates[ $h ] ?? 0, strlen( $v ) );
                }
            }
        }
        if ( empty( $candidates ) ) {
            return '';
        }
        arsort( $candidates );
        return array_key_first( $candidates );
    }

    private function augment_with_handles_sizes_and_sort( $result, $index ) {
        $maxCheck = 120;
        $count = 0;
        $total_styles = 0;
        $total_scripts = 0;

        if ( isset( $result['styles'] ) && is_array( $result['styles'] ) ) {
            foreach ( $result['styles'] as &$s ) {
                $s['handle'] = $this->resolve_handle_from_index( $index['styles'], $s['url'] );
                if ( $count++ <= $maxCheck ) {
                    $size = $this->get_asset_size( $s['url'] );
                    $s['size'] = $size;
                    $s['size_human'] = $this->human_readable_bytes( $size );
                    $total_styles += $size;
                } else {
                    $s['size'] = 0;
                    $s['size_human'] = '—';
                }
            }
            unset( $s );
        }

        if ( isset( $result['scripts'] ) && is_array( $result['scripts'] ) ) {
            foreach ( $result['scripts'] as &$s ) {
                $s['handle'] = $this->resolve_handle_from_index( $index['scripts'], $s['url'] );
                if ( $count++ <= $maxCheck ) {
                    $size = $this->get_asset_size( $s['url'] );
                    $s['size'] = $size;
                    $s['size_human'] = $this->human_readable_bytes( $size );
                    $total_scripts += $size;
                } else {
                    $s['size'] = 0;
                    $s['size_human'] = '—';
                }
            }
            unset( $s );
        }

        // Sort by size
        if ( isset( $result['styles'] ) && is_array( $result['styles'] ) ) {
            usort( $result['styles'], fn( $a, $b ) => ( $b['size'] ?? 0 ) <=> ( $a['size'] ?? 0 ) );
        }
        if ( isset( $result['scripts'] ) && is_array( $result['scripts'] ) ) {
            usort( $result['scripts'], fn( $a, $b ) => ( $b['size'] ?? 0 ) <=> ( $a['size'] ?? 0 ) );
        }

        $result['total_styles'] = $total_styles;
        $result['total_scripts'] = $total_scripts;
        return $result;
    }

    private function abs_url( $u ) {
        $u = (string) $u;
        if ( $u === '' ) {
            return $u;
        }
        if ( strpos( $u, '//' ) === 0 ) {
            return ( is_ssl() ? 'https:' : 'http:' ) . $u;
        }
        $parts = wp_parse_url( $u );
        if ( ! isset( $parts['host'] ) ) {
            $base = site_url( '/' );
            if ( isset( $u[0] ) && $u[0] === '/' ) {
                return rtrim( $base, '/' ) . $u;
            }
            return rtrim( $base, '/' ) . '/' . ltrim( $u, '/' );
        }
        return $u;
    }

    private function get_asset_size( $url ) {
        // Validate and sanitize URL
        $url = esc_url_raw( $url );
        if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return 0;
        }
        
        // Parse URL and validate
        $parsed = wp_parse_url( $url );
        
        // Only allow http/https protocols
        if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], [ 'http', 'https' ], true ) ) {
            return 0;
        }
        
        // Block internal/private IPs to prevent SSRF attacks
        if ( isset( $parsed['host'] ) ) {
            $host = strtolower( $parsed['host'] );
            
            // Block localhost variations
            $blocked_hosts = [
                'localhost',
                '127.0.0.1',
                '0.0.0.0',
                '::1',
                '0:0:0:0:0:0:0:1',
            ];
            
            if ( in_array( $host, $blocked_hosts, true ) ) {
                return 0;
            }
            
            // Resolve hostname and check if it's a private IP
            $ip = gethostbyname( $host );
            
            // Block if it resolves to a private or reserved IP range
            if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
                return 0;
            }
        }
        
        $timeout = 12;
        
        // Enable SSL verification for security
        $argsHead = [ 
            'timeout' => $timeout, 
            'redirection' => 3, 
            'sslverify' => true,
            'httpversion' => '1.1',
        ];
        
        $resp = wp_remote_head( $url, $argsHead );
        if ( ! is_wp_error( $resp ) ) {
            $code = wp_remote_retrieve_response_code( $resp );
            if ( $code >= 200 && $code < 400 ) {
                $len = wp_remote_retrieve_header( $resp, 'content-length' );
                if ( is_array( $len ) ) {
                    $len = reset( $len );
                }
                $len = is_numeric( $len ) ? (int) $len : 0;
                if ( $len > 0 ) {
                    return $len;
                }
            }
        }
        
        // Try with Range header for servers that don't support HEAD
        $argsGet = [ 
            'timeout' => $timeout, 
            'redirection' => 3, 
            'sslverify' => true,
            'httpversion' => '1.1',
            'headers' => [ 'Range' => 'bytes=0-0' ],
        ];
        
        $resp2 = wp_remote_get( $url, $argsGet );
        if ( ! is_wp_error( $resp2 ) ) {
            $code2 = wp_remote_retrieve_response_code( $resp2 );
            if ( $code2 >= 200 && $code2 < 400 ) {
                $range = wp_remote_retrieve_header( $resp2, 'content-range' );
                if ( $range && preg_match( '/\/(\d+)$/', $range, $m ) ) {
                    $total = (int) $m[1];
                    if ( $total > 0 ) {
                        return $total;
                    }
                }
                $body = wp_remote_retrieve_body( $resp2 );
                if ( is_string( $body ) && strlen( $body ) > 0 ) {
                    return strlen( $body );
                }
            }
        }
        return 0;
    }

    private function source_of( $src ) {
        $src = (string) $src;
        $content = content_url();
        if ( strpos( $src, $content ) !== false ) {
            if ( preg_match( '#/plugins/([^/]+)/#', $src, $m ) ) {
                return 'Plugin: ' . str_replace( '-', ' ', $m[1] );
            }
            if ( preg_match( '#/themes/([^/]+)/#', $src, $m ) ) {
                return 'Theme: ' . str_replace( '-', ' ', $m[1] );
            }
            return 'wp-content';
        }
        if ( strpos( $src, includes_url() ) !== false ) {
            return 'WordPress Core';
        }
        return 'External';
    }

    private function first_text_snippet( $html ) {
        if ( ! is_string( $html ) || $html === '' ) {
            return '';
        }
        $text = trim( wp_strip_all_tags( $html ) );
        if ( $text === '' ) {
            return '';
        }
        $text = preg_replace( '/\s+/', ' ', $text );
        return mb_substr( $text, 0, 240 ) . ( mb_strlen( $text ) > 240 ? '…' : '' );
    }

    private function human_readable_bytes( $n ) {
        $n = (int) $n;
        if ( $n <= 0 ) {
            return '—';
        }
        $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
        $i = 0;
        $val = $n;
        while ( $val >= 1024 && $i < count( $units ) - 1 ) {
            $val /= 1024;
            $i++;
        }
        return round( $val, 1 ) . ' ' . $units[ $i ];
    }

    public function ajax_save_blocklist() {
        check_ajax_referer( 'mbr_asm_safe' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 200 );
        }
        $page_id = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;
        $items = isset( $_POST['items'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['items'] ) ), true ) : [];
        if ( ! $page_id ) {
            wp_send_json_error( 'Missing page_id', 200 );
        }
        if ( ! is_array( $items ) ) {
            wp_send_json_error( 'Invalid items payload', 200 );
        }

        $clean = [];
        foreach ( $items as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $url = isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '';
            $type = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : '';
            $device = isset( $row['device'] ) ? sanitize_key( $row['device'] ) : 'any';
            if ( ! $url ) {
                continue;
            }
            if ( $type !== 'style' && $type !== 'script' ) {
                continue;
            }
            if ( ! in_array( $device, [ 'any', 'mobile', 'desktop' ], true ) ) {
                $device = 'any';
            }
            
            // Skip critical WordPress scripts
            if ( $type === 'script' && $this->is_critical_script( '', $url ) ) {
                continue;
            }
            
            $clean[] = [ 'url' => $url, 'type' => $type, 'device' => $device ];
        }

        update_post_meta( $page_id, self::META_KEY, $clean );
        wp_send_json_success( [ 'saved' => count( $clean ) ], 200 );
    }

    public function ajax_clear_blocklist() {
        check_ajax_referer( 'mbr_asm_safe' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 200 );
        }
        $page_id = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;
        if ( ! $page_id ) {
            wp_send_json_error( 'Missing page_id', 200 );
        }
        delete_post_meta( $page_id, self::META_KEY );
        wp_send_json_success( [ 'cleared' => true ], 200 );
    }

    public function ajax_set_disabled() {
        check_ajax_referer( 'mbr_asm_safe' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 200 );
        }
        $page_id = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;
        $disabled = isset( $_POST['disabled'] ) ? absint( wp_unslash( $_POST['disabled'] ) ) : 0;
        if ( ! $page_id ) {
            wp_send_json_error( 'Missing page_id', 200 );
        }
        if ( $disabled ) {
            update_post_meta( $page_id, self::META_DISABLE, 1 );
        } else {
            delete_post_meta( $page_id, self::META_DISABLE );
        }
        wp_send_json_success( [ 'disabled' => (bool) $disabled ], 200 );
    }

    public function ajax_save_global_blocklist() {
        check_ajax_referer( 'mbr_asm_safe' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 200 );
        }
        $items = isset( $_POST['items'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['items'] ) ), true ) : [];
        if ( ! is_array( $items ) ) {
            wp_send_json_error( 'Invalid items payload', 200 );
        }

        $clean = [];
        foreach ( $items as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $url = isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '';
            $type = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : '';
            $device = isset( $row['device'] ) ? sanitize_key( $row['device'] ) : 'any';
            if ( ! $url ) {
                continue;
            }
            if ( $type !== 'style' && $type !== 'script' ) {
                continue;
            }
            if ( ! in_array( $device, [ 'any', 'mobile', 'desktop' ], true ) ) {
                $device = 'any';
            }
            if ( $type === 'script' && $this->is_critical_script( '', $url ) ) {
                continue;
            }
            $clean[] = [ 'url' => $url, 'type' => $type, 'device' => $device ];
        }

        update_option( self::GLOBAL_KEY, $clean, false );
        wp_send_json_success( [ 'saved' => count( $clean ) ], 200 );
    }

    public function ajax_get_global_blocklist() {
        check_ajax_referer( 'mbr_asm_safe' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 200 );
        }
        $items = get_option( self::GLOBAL_KEY, [] );
        if ( ! is_array( $items ) ) {
            $items = [];
        }
        wp_send_json_success( $items, 200 );
    }
}

MBR_Advanced_Asset_Manager::instance();
