<?php
/**
 * Clear explanations for unsuccessful manual update checks.
 *
 * @package DisableOtherPluginNotices
 */

defined( 'ABSPATH' ) || exit;

/** Present update failures without implying the plugin itself is broken. */
final class DOPN_Update_Notices {
    /**
     * Update checker.
     *
     * @var object
     */
    private $checker;
    /**
     * Saved technical errors for this request.
     *
     * @var array
     */
    private $errors = array();

    /**
     * Register this plugin's notice hooks.
     *
     * @param object $checker Update checker.
     */
    public function __construct( $checker ) {
        $this->checker = $checker;
        add_action( 'puc_api_error', array( $this, 'record_limit' ), 10, 4 );
        add_action( 'admin_init', array( $this, 'prepare_notice' ), 20 );
        add_filter( $checker->getUniqueName( 'manual_check_message' ), array( $this, 'message' ), 10, 2 );
    }

    /**
     * Record only an explicitly confirmed GitHub rate limit.
     *
     * @param mixed  $error API error.
     * @param mixed  $response HTTP response.
     * @param string $url Request URL.
     * @param string $slug Plugin slug.
     */
    public function record_limit( $error, $response, $url, $slug ) {
        if ( $slug !== $this->checker->slug || 'api.github.com' !== wp_parse_url( $url, PHP_URL_HOST ) || ! is_array( $response ) ) {
            return;
        }
        $code      = (int) wp_remote_retrieve_response_code( $response );
        $remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
        $retry     = wp_remote_retrieve_header( $response, 'retry-after' );
        if ( ! in_array( $code, array( 403, 429 ), true ) || ( '0' !== (string) $remaining && '' === (string) $retry && 429 !== $code ) ) {
            return;
        }
        $reset = (int) wp_remote_retrieve_header( $response, 'x-ratelimit-reset' );
        if ( is_numeric( $retry ) ) {
            $reset = max( $reset, time() + (int) $retry );
        } elseif ( $retry ) {
            $reset = max( $reset, (int) strtotime( $retry ) );
        }
        set_site_transient( 'dopn_github_limit', array( 'reset' => $reset ), max( 60, min( DAY_IN_SECONDS, $reset - time() ) ) );
    }

    /** Move verbose library errors into the technical disclosure. */
    public function prepare_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice selection; the library authorizes the actual check.
        if ( ! current_user_can( 'update_plugins' ) || ! isset( $_GET['puc_slug'], $_GET['puc_update_check_result'] ) || sanitize_key( wp_unslash( $_GET['puc_slug'] ) ) !== $this->checker->slug || 'error' !== sanitize_key( wp_unslash( $_GET['puc_update_check_result'] ) ) ) {
            return;
        }
        $key          = $this->checker->getUniqueName( 'manual_check_errors' );
        $this->errors = (array) get_site_transient( $key );
        delete_site_transient( $key );
    }

    /**
     * Explain a failed check, preserving successful results.
     *
     * @param string $message Existing escaped message.
     * @param string $status Check result.
     * @return string
     */
    public function message( $message, $status ) {
        if ( 'error' !== $status ) {
            return $message;
        }
        $limit = get_site_transient( 'dopn_github_limit' );
        $text  = 'Disable Other Plugin Notices could not reach its update service. Please try again later. This does not affect its notice grouping.';
        if ( is_array( $limit ) && isset( $limit['reset'] ) && $limit['reset'] > time() ) {
            $text = 'GitHub’s request limit has been reached. Please try again after ' . wp_date( 'g:i a T', $limit['reset'] ) . '. Disable Other Plugin Notices continues to work normally.';
        } elseif ( is_array( $limit ) ) {
            $text = 'GitHub temporarily limited update requests. Please try again later. Disable Other Plugin Notices continues to work normally.';
        }
        $html    = '<strong>Update check temporarily unavailable</strong><br>' . esc_html( $text );
        $details = '';
        foreach ( $this->errors as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['error'] ) || ! is_wp_error( $item['error'] ) ) {
                continue;
            }
            $details .= '<p>' . esc_html( $item['error']->get_error_message() ) . ' <code>' . esc_html( $item['error']->get_error_code() ) . '</code></p>';
        }
        if ( $details ) {
            $html .= '<details><summary>Technical details</summary>' . $details . '</details>';
        }
        return $html;
    }
}
