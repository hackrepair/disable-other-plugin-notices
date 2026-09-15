<?php
/**
 * Plugin controller.
 *
 * @package DisableOtherPluginNotices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the collector and the Screen Options control together.
 *
 * @since 1.0.0
 */
final class DOPN_Plugin {

    /**
     * Shared instance.
     *
     * @since 1.0.0
     * @var DOPN_Plugin|null
     */
    private static $instance = null;

    /**
     * Notice collector.
     *
     * @since 1.0.0
     * @var DOPN_Notice_Collector|null
     */
    private $collector = null;

    /**
     * Screen Options control.
     *
     * @since 1.0.0
     * @var DOPN_Screen_Option|null
     */
    private $screen_option = null;

    /**
     * Whether boot() has already run.
     *
     * @since 1.0.0
     * @var bool
     */
    private $booted = false;

    /**
     * Returns the shared instance.
     *
     * @since 1.0.0
     *
     * @return DOPN_Plugin The plugin controller.
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Registers everything the plugin does.
     *
     * The plugin does nothing at all on front-end requests.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot() {
        if ( $this->booted || ! is_admin() ) {
            return;
        }

        $this->booted = true;

        $this->collector = new DOPN_Notice_Collector();
        $this->collector->init();

        $this->screen_option = new DOPN_Screen_Option( $this->collector );
        $this->screen_option->init();
    }

    /**
     * Returns the notice collector.
     *
     * @since 1.0.0
     *
     * @return DOPN_Notice_Collector|null Collector, or null before boot.
     */
    public function collector() {
        return $this->collector;
    }
}
