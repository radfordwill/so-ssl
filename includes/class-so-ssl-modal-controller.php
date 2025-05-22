<?php
/**
 * Modal Controller to prevent multiple modals from displaying at once
 * Add this to a new file: includes/class-so-ssl-modal-controller.php
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class So_SSL_Modal_Controller {

	/**
	 * Initialize the modal controller
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'setup_modal_priorities' ), 1 );
		add_action( 'admin_enqueue_scripts', array(
			__CLASS__,
			'enqueue_modal_assets'
		) );
		add_action( 'admin_footer', array( __CLASS__, 'render_modal_html' ) );
		add_action( 'wp_ajax_so_ssl_save_agreement', array(
			__CLASS__,
			'save_agreement_acceptance'
		) );
	}

	/**
	 * Setup modal display priorities
	 */
	public static function setup_modal_priorities() {
		// Priority order:
		// 1. Admin Agreement (most important)
		// 2. Privacy Compliance

		// Check if modals are enabled
		$admin_agreement_enabled    = get_option( 'so_ssl_enable_admin_agreement', 1 );
		$privacy_compliance_enabled = get_option( 'so_ssl_enable_privacy_compliance', 0 );

		// Setup filters to control modal display
		add_filter( 'so_ssl_should_show_privacy_modal', array(
			__CLASS__,
			'should_show_privacy_modal'
		) );
		add_filter( 'so_ssl_should_show_admin_agreement_modal', array(
			__CLASS__,
			'should_show_admin_agreement_modal'
		) );
	}

	/**
	 * Determine if privacy modal should show
	 */
	public static function should_show_privacy_modal( $show ) {
		// Don't show privacy modal if admin agreement is active
		if ( apply_filters( 'so_ssl_showing_admin_agreement', false ) ) {
			return false;
		}

		return $show;
	}

	/**
	 * Determine if admin agreement modal should show
	 */
	public static function should_show_admin_agreement_modal( $show ) {
		// Admin agreement has highest priority, always show if needed
		return $show;
	}

	/**
	 * Helper method to check which modal is currently active
	 */
	public static function get_active_modal() {
		if ( apply_filters( 'so_ssl_showing_admin_agreement', false ) ) {
			return 'admin_agreement';
		}

		if ( apply_filters( 'so_ssl_show_privacy_modal', false ) ) {
			return 'privacy_compliance';
		}

		return null;
	}

	/**
	 * Check if current page is a plugin page.
	 *
	 * @return boolean
	 */
	private static function is_plugin_page() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		// Add your plugin's page screen IDs here
		return ! empty( $screen ) && strpos( $screen->id, 'so-ssl' ) !== false;
	}

	/**
	 * Enqueue modal styles and scripts.
	 */
	public static function enqueue_modal_assets() {
		// Only enqueue on plugin pages and if modal should be shown
		if ( self::is_plugin_page() && apply_filters( 'so_ssl_showing_admin_agreement', false ) ) {
			wp_enqueue_style(
				'so-ssl-modal-style',
				plugins_url( 'assets/css/so-ssl-modal.css', dirname( __FILE__ ) ),
				array(),
				SO_SSL_VERSION
			);

			wp_enqueue_script(
				'so-ssl-modal-script',
				plugins_url( 'assets/js/so-ssl-modal.js', dirname( __FILE__ ) ),
				array( 'jquery' ),
				SO_SSL_VERSION,
				true
			);

			// Add nonce for AJAX
			wp_localize_script(
				'so-ssl-modal-script',
				'soSslModal',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'so_ssl_modal_nonce' )
				)
			);
		}
	}

	/**
	 * Render modal HTML.
	 */
	public static function render_modal_html() {
		// Only render if on plugin page and modal should be shown
		if ( ! self::is_plugin_page() || ! apply_filters( 'so_ssl_showing_admin_agreement', false ) ) {
			return;
		}
		?>
        <div id="sslAgreementModal" class="so-ssl-modal-overlay"
             style="display: none;">
            <div class="so-ssl-modal-content">
                <h2><?php esc_html_e( 'SSL Agreement Required', 'so-ssl' ); ?></h2>
                <p><?php esc_html_e( 'You must accept the SSL agreement to continue.', 'so-ssl' ); ?></p>
                <button id="soSslAcceptButton" class="button button-primary">
					<?php esc_html_e( 'Accept Agreement', 'so-ssl' ); ?>
                </button>
            </div>
        </div>
		<?php
	}

	/**
	 * AJAX handler for saving agreement acceptance.
	 */
	public static function save_agreement_acceptance() {
		check_ajax_referer( 'so_ssl_modal_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		update_user_meta( get_current_user_id(), 'so_ssl_agreement_accepted', true );
		wp_send_json_success();
	}
}

// Initialize the modal controller
So_SSL_Modal_Controller::init();