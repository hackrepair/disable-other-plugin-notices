<?php
/**
 * Collects admin notices that come from plugins and themes rather than WordPress.
 *
 * @package DisableOtherPluginNotices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Detaches third-party admin notice callbacks and reprints them inside one panel.
 *
 * Attribution is done by asking PHP where each registered callback was defined.
 * A callback declared inside wp-admin or wp-includes is WordPress itself and is
 * never touched, so core update, PHP version, and Site Health notices keep their
 * normal position at the top of the screen.
 *
 * The same attribution is also applied to in_admin_header itself, not just the
 * four notice hooks. Some plugins print a promotional banner straight into
 * in_admin_header instead of admin_notices, specifically because it renders
 * earlier, above the notice area, where a grouping plugin would otherwise never
 * see it. Since this class already runs on in_admin_header at priority 0 to
 * catch notices before WordPress prints them, it is well placed to also catch
 * banners hooked to that same action at a later priority, using the identical
 * core-path exemption so nothing WordPress itself prints there is touched.
 *
 * @since 1.0.0
 * @since 2.1.0 Also captures third-party callbacks on in_admin_header itself.
 * @since 2.2.0 Pairs with a client-side watcher for banners a plugin builds
 *              and inserts with JavaScript instead of printing through a
 *              notice hook -- see dopn-banner-watcher.js.
 */
class DOPN_Notice_Collector {

    /**
     * User meta key holding the per-user preference.
     *
     * @since 1.0.0
     * @var string
     */
    const USER_META_KEY = 'dopn_group_notices';

    /**
     * Callbacks removed from the notice hooks, in the order they were registered.
     *
     * @since 1.0.0
     * @var array
     */
    private $captured = array();

    /**
     * Whether any third-party notice callback was registered on this screen.
     *
     * @since 1.0.0
     * @var bool
     */
    private $found_third_party = false;

    /**
     * Whether the panel has already been printed on this request.
     *
     * @since 1.0.0
     * @var bool
     */
    private $rendered = false;

    /**
     * Normalized directories that belong to WordPress itself.
     *
     * @since 1.0.0
     * @var array
     */
    private $core_paths = array();

    /**
     * Normalized path to this plugin's own directory.
     *
     * @since 1.0.0
     * @var string
     */
    private $plugin_path = '';

    /**
     * Registers the hooks this class needs.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function init() {
        add_action( 'in_admin_header', array( $this, 'capture' ), 0 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Reads the per-user preference.
     *
     * Grouping is on for a user who has never changed the setting, which is the
     * behavior the plugin is named for.
     *
     * @since 1.0.0
     *
     * @param int $user_id User ID to read the preference for.
     * @return bool True when notices should be grouped for this user.
     */
    public static function is_enabled_for_user( $user_id ) {
        $stored  = get_user_meta( $user_id, self::USER_META_KEY, true );
        $enabled = ( '' === $stored ) ? true : ( '1' === (string) $stored );

        /**
         * Filters whether third-party notices are grouped for a user.
         *
         * @since 1.0.0
         *
         * @param bool $enabled Whether grouping is active.
         * @param int  $user_id User the preference was read for.
         */
        return (bool) apply_filters( 'dopn_grouping_enabled', $enabled, $user_id );
    }

    /**
     * Reports whether this screen has notices from plugins or themes.
     *
     * The Screen Options checkbox uses this so the control appears only where it
     * actually does something.
     *
     * @since 1.0.0
     *
     * @return bool True when a third-party notice callback was found.
     */
    public function found_third_party_notices() {
        return $this->found_third_party;
    }

    /**
     * Loads the panel stylesheet.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'dopn-admin',
            DOPN_PLUGIN_URL . 'assets/css/dopn-admin.css',
            array(),
            DOPN_VERSION
        );
    }

    /**
     * Loads the client-side banner watcher.
     *
     * Covers promo banners that a plugin builds and inserts with its own
     * JavaScript after the page has loaded, rather than printing through a
     * WordPress action hook. See dopn-banner-watcher.js for why the PHP-side
     * collector above cannot see this kind of banner at all.
     *
     * @since 2.2.0
     *
     * @return void
     */
    public function enqueue_scripts() {
        $user_id  = get_current_user_id();
        $selectors = $this->late_banner_selectors();

        if ( ! $user_id || empty( $selectors ) ) {
            return;
        }

        wp_enqueue_script(
            'dopn-banner-watcher',
            DOPN_PLUGIN_URL . 'assets/js/dopn-banner-watcher.js',
            array(),
            DOPN_VERSION,
            true
        );

        wp_localize_script(
            'dopn-banner-watcher',
            'dopnBannerWatcher',
            array(
                'groupingEnabled' => self::is_enabled_for_user( $user_id ),
                'selectors'       => array_values( $selectors ),
                'strings'         => array(
                    'label'     => __( 'Other plugin notices', 'disable-other-plugin-notices' ),
                    'singular'  => __( '%s notice from another plugin or theme', 'disable-other-plugin-notices' ),
                    'plural'    => __( '%s notices from other plugins and themes', 'disable-other-plugin-notices' ),
                ),
            )
        );
    }

    /**
     * Lists the CSS selectors the client-side watcher looks for.
     *
     * Kept as an explicit allowlist rather than a keyword or class-name
     * heuristic. Guessing at what a "promo banner" looks like from its
     * markup risks relocating something that only resembles one -- the same
     * reason the PHP-side collector attributes by callback source file
     * instead of by inspecting notice text. Selectors are added here only
     * once a real banner has been confirmed to bypass the hook-based
     * collector, the way Elementor's page-title banner did in 2.2.0.
     *
     * @since 2.2.0
     *
     * @return array Non-empty, de-duplicated CSS selector strings.
     */
    private function late_banner_selectors() {
        $defaults = array(
            // Elementor's "Go Pro, Go Limitless" banner: built by
            // e-conversion-banner.min.js and inserted next to the page
            // title (.wrap h1/h2) rather than printed by a notice hook.
            '#e-conversion-banner',
        );

        /**
         * Filters the CSS selectors the client-side banner watcher matches.
         *
         * @since 2.2.0
         *
         * @param array $selectors CSS selector strings.
         */
        $selectors = (array) apply_filters( 'dopn_js_late_banner_selectors', $defaults );

        $selectors = array_filter(
            array_unique( $selectors ),
            static function ( $selector ) {
                return is_string( $selector ) && '' !== trim( $selector );
            }
        );

        return $selectors;
    }

    /**
     * Removes third-party notice callbacks before WordPress runs them.
     *
     * Runs on in_admin_header, which fires after every plugin has registered its
     * notices and before WordPress prints them. Because this method is itself a
     * priority-0 callback on in_admin_header, the scan it starts also covers the
     * rest of that same action -- including any later-priority callback still
     * queued to run on it -- alongside the admin_notices-family hooks that fire
     * afterward.
     *
     * @since 1.0.0
     * @since 2.1.0 The scan now also covers in_admin_header itself.
     *
     * @return void
     */
    public function capture() {
        if ( ! is_admin() ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return;
        }

        $grouping = self::is_enabled_for_user( $user_id );

        foreach ( $this->notice_hooks() as $hook ) {
            $this->scan_hook( $hook, $grouping );
        }

        if ( ! empty( $this->captured ) ) {
            add_action( 'all_admin_notices', array( $this, 'render' ), PHP_INT_MAX );
        }
    }

    /**
     * Lists the notice hooks that apply to the current admin context.
     *
     * in_admin_header is included unconditionally -- unlike the notices hooks
     * it is not split by admin context, and it fires early enough that this
     * method is called from inside it (see capture()). Scanning it here, before
     * admin_notices and all_admin_notices, is what lets a banner hooked to
     * in_admin_header at a later priority than this class's own listener get
     * detached before it ever prints.
     *
     * @since 1.0.0
     * @since 2.1.0 Added in_admin_header.
     *
     * @return array Hook names, in the order WordPress fires them.
     */
    private function notice_hooks() {
        $hooks = array( 'in_admin_header' );

        if ( is_network_admin() ) {
            $hooks[] = 'network_admin_notices';
        } elseif ( is_user_admin() ) {
            $hooks[] = 'user_admin_notices';
        } else {
            $hooks[] = 'admin_notices';
        }

        $hooks[] = 'all_admin_notices';

        return $hooks;
    }

    /**
     * Inspects one notice hook and detaches the third-party callbacks on it.
     *
     * @since 1.0.0
     *
     * @param string $hook     Hook name to inspect.
     * @param bool   $grouping Whether callbacks should actually be detached.
     * @return void
     */
    private function scan_hook( $hook, $grouping ) {
        global $wp_filter;

        if ( ! isset( $wp_filter[ $hook ] ) || ! ( $wp_filter[ $hook ] instanceof WP_Hook ) ) {
            return;
        }

        // A copy, so detaching a callback does not disturb this loop.
        $registered = $wp_filter[ $hook ]->callbacks;

        foreach ( $registered as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                if ( ! isset( $callback['function'] ) ) {
                    continue;
                }

                $source = $this->callback_path( $callback['function'] );

                if ( ! $this->is_third_party( $source ) ) {
                    continue;
                }

                $this->found_third_party = true;

                if ( ! $grouping ) {
                    continue;
                }

                /**
                 * Filters whether one notice is moved into the panel.
                 *
                 * Return false to leave a particular notice in its normal place.
                 *
                 * @since 1.0.0
                 *
                 * @param bool   $collapse Whether to move this notice.
                 * @param string $source   File the notice callback was declared in.
                 * @param string $hook     Notice hook the callback is attached to.
                 * @param int    $priority Priority the callback is attached at.
                 */
                if ( ! apply_filters( 'dopn_collapse_notice', true, $source, $hook, $priority ) ) {
                    continue;
                }

                $this->captured[] = array(
                    'function' => $callback['function'],
                    'hook'     => $hook,
                    'priority' => $priority,
                );

                remove_action( $hook, $callback['function'], $priority );
            }
        }
    }

    /**
     * Finds the file a callback was declared in.
     *
     * @since 1.0.0
     *
     * @param callable|mixed $callback Callback registered on a notice hook.
     * @return string Normalized file path, or an empty string when it cannot be determined.
     */
    private function callback_path( $callback ) {
        try {
            if ( $callback instanceof Closure ) {
                $reflection = new ReflectionFunction( $callback );
            } elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
                $parts = explode( '::', $callback, 2 );

                $reflection = new ReflectionMethod( $parts[0], $parts[1] );
            } elseif ( is_string( $callback ) ) {
                if ( ! function_exists( $callback ) ) {
                    return '';
                }

                $reflection = new ReflectionFunction( $callback );
            } elseif ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
                $reflection = new ReflectionMethod( $callback[0], $callback[1] );
            } elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
                $reflection = new ReflectionMethod( $callback, '__invoke' );
            } else {
                return '';
            }
        } catch ( ReflectionException $exception ) {
            return '';
        }

        $file = $reflection->getFileName();

        return is_string( $file ) ? wp_normalize_path( $file ) : '';
    }

    /**
     * Decides whether a source file belongs to a plugin or theme.
     *
     * An unknown origin is treated as WordPress, so anything this plugin cannot
     * positively attribute is left alone.
     *
     * @since 1.0.0
     *
     * @param string $file Normalized path of the file a callback came from.
     * @return bool True when the file is outside WordPress and outside this plugin.
     */
    private function is_third_party( $file ) {
        if ( '' === $file ) {
            return false;
        }

        $real_file = realpath( $file );
        $file      = wp_normalize_path( false !== $real_file ? $real_file : $file );

        if ( '' === $this->plugin_path ) {
            $real_plugin       = realpath( DOPN_PLUGIN_DIR );
            $this->plugin_path = trailingslashit( wp_normalize_path( false !== $real_plugin ? $real_plugin : DOPN_PLUGIN_DIR ) );
        }

        if ( 0 === strpos( $file, $this->plugin_path ) ) {
            return false;
        }

        foreach ( $this->core_paths() as $core_path ) {
            if ( 0 === strpos( $file, $core_path ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the directories that hold WordPress itself.
     *
     * @since 1.0.0
     *
     * @return array Normalized directory paths with trailing slashes.
     */
    private function core_paths() {
        if ( empty( $this->core_paths ) ) {
            $real_abspath = realpath( ABSPATH );
            $abspath      = trailingslashit( wp_normalize_path( false !== $real_abspath ? $real_abspath : ABSPATH ) );

            $real_wpinc = realpath( ABSPATH . WPINC );
            $wpinc      = trailingslashit( wp_normalize_path( false !== $real_wpinc ? $real_wpinc : ( ABSPATH . WPINC ) ) );

            $this->core_paths = array(
                $abspath . 'wp-admin/',
                $wpinc,
            );
        }

        return $this->core_paths;
    }

    /**
     * Prints the collected notices inside one collapsed panel.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function render() {
        if ( $this->rendered || empty( $this->captured ) ) {
            return;
        }

        $this->rendered = true;

        $notices = array();

        foreach ( $this->captured as $entry ) {
            $level = ob_get_level();

            ob_start();

            try {
                call_user_func( $entry['function'] );
            } catch ( Throwable $e ) {
                while ( ob_get_level() > $level ) {
                    ob_end_clean();
                }

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf( 'DOPN notice callback error: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
                }

                continue;
            }

            /*
             * Buffers are unwound back to the level they were at before the
             * callback ran. A notice callback that leaves an output buffer open
             * would otherwise swallow the rest of the admin page, and one that
             * closes too many is simply left with nothing collected here.
             */
            $chunks = array();

            while ( ob_get_level() > $level ) {
                array_unshift( $chunks, (string) ob_get_clean() );
            }

            $markup = implode( '', $chunks );
            $markup = $this->guard_against_core_relocation( $markup );

            if ( '' !== trim( $markup ) ) {
                $notices[] = $markup;
            }
        }

        if ( empty( $notices ) ) {
            return;
        }

        $count = count( $notices );

        /**
         * Filters whether the panel starts expanded.
         *
         * @since 1.0.0
         *
         * @param bool $open  Whether the panel is open on page load.
         * @param int  $count Number of notices inside the panel.
         */
        $open = (bool) apply_filters( 'dopn_panel_open', false, $count );

        printf(
            '<div class="dopn-notices"><details class="dopn-notices__panel"%s>',
            esc_attr( $open ? ' open' : '' )
        );

        echo '<summary class="dopn-notices__summary">';
        echo '<span class="dopn-notices__label">' . esc_html__( 'Other plugin notices', 'disable-other-plugin-notices' ) . '</span>';
        echo '<span class="dopn-notices__count" aria-hidden="true">' . esc_html( number_format_i18n( $count ) ) . '</span>';
        echo '<span class="screen-reader-text">';
        printf(
            /* translators: %s: Number of notices grouped into the panel. */
            esc_html( _n( '%s notice from another plugin or theme', '%s notices from other plugins and themes', $count, 'disable-other-plugin-notices' ) ),
            esc_html( number_format_i18n( $count ) )
        );
        echo '</span>';
        echo '</summary>';

        echo '<div class="dopn-notices__list">';

        foreach ( $notices as $markup ) {
            /*
             * Printed verbatim on purpose. This is the exact markup the other
             * plugin or theme would have printed on this hook a moment earlier;
             * the only change is where it appears on the page. Escaping or
             * filtering it here would corrupt other people's notices, and no
             * request data is added to this output.
             */
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Re-emitting verbatim captured third-party markup.
            echo $markup;
        }

        echo '</div>';
        echo '</details></div>';
    }

    /**
     * Stops WordPress core's own admin JavaScript from pulling a notice back
     * out of the panel.
     *
     * wp-admin/js/common.js runs `$( 'div.updated, div.error, div.notice' )
     * .not( '.inline, .below-h2' ).insertAfter( $headerEnd )` on every admin
     * page that has a `.wp-header-end` marker, which is nearly every list and
     * edit screen. That selector has no idea an element is sitting inside this
     * plugin's collapsed panel: jQuery matches it anywhere in the document and
     * moves the matched element itself, leaving the panel's list empty while
     * the notice reappears in its usual spot. `below-h2` is the exact class
     * core's own selector excludes, so adding it to whatever element in the
     * captured markup carries `notice`, `updated`, or `error` keeps the
     * notice's styling, dismiss buttons, and behaviour untouched while making
     * it invisible to that relocation script.
     *
     * @since 1.0.1
     * @since 1.0.2 Support single-quoted HTML class attributes.
     *
     * @param string $markup Captured notice markup.
     * @return string Markup with `below-h2` added where core would otherwise match.
     */
    private function guard_against_core_relocation( $markup ) {
        return (string) preg_replace_callback(
            '/(<[a-z][a-z0-9]*\b[^>]*\bclass\s*=\s*(["\']))(.*?)\2/i',
            static function ( $matches ) {
                $classes   = $matches[3];
                $delimiter = $matches[2];

                if ( ! preg_match( '/(?:^|\s)(?:notice|updated|error)(?:\s|$)/', $classes ) ) {
                    return $matches[0];
                }

                if ( preg_match( '/(?:^|\s)below-h2(?:\s|$)/', $classes ) ) {
                    return $matches[0];
                }

                return $matches[1] . $classes . ' below-h2' . $delimiter;
            },
            $markup
        );
    }
}
