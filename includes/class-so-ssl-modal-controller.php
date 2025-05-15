<?php
/**
 * Modal Controller to prevent multiple modals from displaying at once
 * Add this to a new file: includes/class-so-ssl-modal-controller.php
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class So_SSL_Modal_Controller {
    
    /**
     * Initialize the modal controller
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'setup_modal_priorities'), 1);
    }
    
    /**
     * Setup modal display priorities
     */
    public static function setup_modal_priorities() {
        // Priority order:
        // 1. Admin Agreement (most important)
        // 2. Privacy Compliance
        
        // Check if modals are enabled
        $admin_agreement_enabled = get_option('so_ssl_enable_admin_agreement', 1);
        $privacy_compliance_enabled = get_option('so_ssl_enable_privacy_compliance', 0);
        
        // Setup filters to control modal display
        add_filter('so_ssl_should_show_privacy_modal', array(__CLASS__, 'should_show_privacy_modal'));
        add_filter('so_ssl_should_show_admin_agreement_modal', array(__CLASS__, 'should_show_admin_agreement_modal'));
    }
    
    /**
     * Determine if privacy modal should show
     */
    public static function should_show_privacy_modal($show) {
        // Don't show privacy modal if admin agreement is active
        if (apply_filters('so_ssl_showing_admin_agreement', false)) {
            return false;
        }
        
        return $show;
    }
    
    /**
     * Determine if admin agreement modal should show
     */
    public static function should_show_admin_agreement_modal($show) {
        // Admin agreement has highest priority, always show if needed
        return $show;
    }
    
    /**
     * Helper method to check which modal is currently active
     */
    public static function get_active_modal() {
        if (apply_filters('so_ssl_showing_admin_agreement', false)) {
            return 'admin_agreement';
        }
        
        if (apply_filters('so_ssl_show_privacy_modal', false)) {
            return 'privacy_compliance';
        }
        
        return null;
    }
}

// Initialize the modal controller
So_SSL_Modal_Controller::init();
