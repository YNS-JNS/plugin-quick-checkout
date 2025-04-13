<?php
/**
 * Plugin Name:       Quick Checkout Popup for WooCommerce V1
 * Plugin URI:        https://github.com/yns-jns/plugin-quick-checkout-popup/
 * Description:       Replaces standard checkout with a one-click "Buy Now" popup. Includes variations, quantity, coupons.
 * Version:           1.0.0
 * Author:            AIT M'BAREK Youness
 * Author URI:        https://github.com/yns-jns
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       quick-checkout-popup
 * Domain Path:       /languages
 * WC requires at least: 6.0
 * WC tested up to:      [Current WC Version]
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'QCP_VERSION', '1.8.2' ); // Updated version
define( 'QCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QCP_PLUGIN_FILE', __FILE__ );

/**
 * Load plugin textdomain on init hook.
 */
function qcp_load_textdomain() {
	load_plugin_textdomain( 'quick-checkout-popup', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
// *** CHANGE: Use 'init' hook instead of 'plugins_loaded' ***
add_action( 'init', 'qcp_load_textdomain' );

/**
 * Check if WooCommerce is active.
 */
if ( ! function_exists( 'qcp_is_woocommerce_active' ) ) {
    function qcp_is_woocommerce_active() {
        // Check if the WooCommerce class exists AND function is available (safer)
        return class_exists( 'woocommerce' ) && function_exists('WC');
    }
}

/**
 * Initialize the plugin main class.
 * Runs on plugins_loaded to ensure WC is loaded if active.
 */
function qcp_init() {
    // Check for WooCommerce dependency
    if ( ! qcp_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'qcp_woocommerce_inactive_notice' );
        return; // Stop initialization if WC not active
    }

    // Include and instantiate the main class
    require_once QCP_PLUGIN_DIR . 'includes/class-qcp-main.php';
    Quick_Checkout_Popup\QCP_Main::instance();
}
// Keep initialization on plugins_loaded to ensure WC context is available for class loading
add_action( 'plugins_loaded', 'qcp_init' );

/**
 * Admin notice if WooCommerce is inactive.
 */
function qcp_woocommerce_inactive_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <?php
            echo wp_kses_post(
                sprintf(
                    /* translators: %s: Quick Checkout Popup for WooCommerce */
                    __( '<strong>%s</strong> requires WooCommerce to be installed and activated. Please install or activate WooCommerce.', 'quick-checkout-popup' ),
                    'Quick Checkout Popup for WooCommerce'
                )
            );
            ?>
        </p>
    </div>
    <?php
}

// Optional: Activation/Deactivation Hooks
// register_activation_hook( __FILE__, 'qcp_activate' );
// register_deactivation_hook( __FILE__, 'qcp_deactivate' );

// function qcp_activate() { /* Actions on activation */ }
// function qcp_deactivate() { /* Actions on deactivation */ }