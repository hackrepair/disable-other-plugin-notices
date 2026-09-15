<?php
/**
 * Adds the per-user Screen Options control.
 *
 * @package DisableOtherPluginNotices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the "Other plugin notices" checkbox in Screen Options.
 *
 * The checkbox is added to the Screen Options tab WordPress already provides, so
 * the plugin adds no admin menu item and no settings page. It appears only on
 * screens where another plugin or theme actually printed a notice.
 *
 * @since 1.0.0
 */
class DOPN_Screen_Option {

    /**
     * Collector that knows what was found on the current screen.
     *
     * @since 1.0.0
     * @var DOPN_Notice_Collector
     */
    private $collector;

    /**
     * Whether the checkbox was added to the current screen.
     *
     * @since 1.0.0
     * @var bool
     */
    private $rendered = false;

    /**
     * Sets up the control.
     *
     * @since 1.0.0
     *
     * @param DOPN_Notice_Collector $collector Collector for the current screen.
     */
    public function __construct( DOPN_Notice_Collector $collector ) {
        $this->collector = $collector;
    }

    /**
     * Registers the hooks this class needs.
     *
     * The save runs on wp_loaded because WordPress processes Screen Options in
     * set_screen_options(), which happens before admin_init and can redirect.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function init() {
        add_action( 'wp_loaded', array( $this, 'maybe_save' ) );
        add_filter( 'screen_settings', array( $this, 'render' ), 10, 2 );
        add_filter( 'screen_options_show_submit', array( $this, 'show_submit' ), 10, 2 );
    }

    /**
     * Appends the checkbox to the Screen Options tab.
     *
     * @since 1.0.0
     *
     * @param string    $settings Screen settings markup collected so far.
     * @param WP_Screen $screen   Screen the settings are rendered for.
     * @return string Screen settings markup.
     */
    public function render( $settings, $screen ) {
        if ( ! $this->collector->found_third_party_notices() ) {
            return $settings;
        }

        /**
         * Filters whether the Screen Options checkbox is offered on a screen.
         *
         * @since 1.0.0
         *
         * @param bool      $show   Whether to add the checkbox.
         * @param WP_Screen $screen Current screen.
         */
        if ( ! apply_filters( 'dopn_show_screen_option', true, $screen ) ) {
            return $settings;
        }

        $this->rendered = true;

        $enabled = DOPN_Notice_Collector::is_enabled_for_user( get_current_user_id() );

        $markup  = '<fieldset class="dopn-screen-option">';
        $markup .= '<legend>' . esc_html__( 'Other plugin notices', 'disable-other-plugin-notices' ) . '</legend>';
        $markup .= '<input type="hidden" name="dopn_screen_option" value="1" />';
        $markup .= '<label for="dopn-group-notices">';
        $markup .= '<input type="checkbox" id="dopn-group-notices" name="dopn_group_notices" value="1"' . checked( $enabled, true, false ) . ' /> ';
        $markup .= esc_html__( 'Group notices from other plugins and themes into one panel', 'disable-other-plugin-notices' );
        $markup .= '</label>';
        $markup .= '</fieldset>';

        return $settings . $markup;
    }

    /**
     * Makes sure the Screen Options tab has an Apply button.
     *
     * Screens without a per-page option do not show one by default.
     *
     * @since 1.0.0
     *
     * @param bool      $show_button Whether WordPress would show the button.
     * @param WP_Screen $screen      Current screen.
     * @return bool Whether to show the button.
     */
    public function show_submit( $show_button, $screen ) {
        unset( $screen );

        return $this->rendered ? true : $show_button;
    }

    /**
     * Saves the checkbox when the Screen Options form is submitted.
     *
     * The form is the one WordPress renders in WP_Screen::render_screen_options(),
     * so the request is authenticated with the core screen-options nonce.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function maybe_save() {
        if ( ! is_admin() || wp_doing_ajax() ) {
            return;
        }

        if ( ! isset( $_POST['dopn_screen_option'], $_POST['screenoptionnonce'] ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['screenoptionnonce'] ) );

        if ( ! wp_verify_nonce( $nonce, 'screen-options-nonce' ) ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return;
        }

        // The write only ever touches the acting user's own preference, so the
        // baseline capability for reaching wp-admin is the correct gate here.
        if ( ! current_user_can( 'read' ) ) {
            return;
        }

        $enabled = isset( $_POST['dopn_group_notices'] ) ? '1' : '0';

        update_user_meta( $user_id, DOPN_Notice_Collector::USER_META_KEY, $enabled );
    }
}
