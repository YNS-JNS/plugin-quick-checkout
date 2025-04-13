<?php
namespace Quick_Checkout_Popup;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class V1 - With Advanced Features.
 */
final class QCP_Main {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
            self::$_instance->includes();
            self::$_instance->hooks();
        }
        return self::$_instance;
    }

    private function includes() {
        require_once QCP_PLUGIN_DIR . 'includes/class-qcp-frontend.php';
        require_once QCP_PLUGIN_DIR . 'includes/class-qcp-ajax.php';
        require_once QCP_PLUGIN_DIR . 'includes/class-qcp-admin.php';
        require_once QCP_PLUGIN_DIR . 'includes/class-qcp-order.php';

        // Optional includes based on settings
        $options = get_option('qcp_settings', []); // Retrieve options once

        // Google Sheets
        if (!empty($options['enable_google_sheets']) && file_exists(QCP_PLUGIN_DIR . 'includes/class-qcp-sheets.php')) {
            require_once QCP_PLUGIN_DIR . 'includes/class-qcp-sheets.php';
        }

        // *** CHANGEMENT : Vérifie l'option 'enable_stats' avant d'inclure ***
        if ( !empty($options['enable_stats']) && file_exists(QCP_PLUGIN_DIR . 'includes/class-qcp-stats.php')) {
            require_once QCP_PLUGIN_DIR . 'includes/class-qcp-stats.php';
        }
    }

    private function hooks() {
        QCP_Frontend::instance();
        QCP_Ajax::instance(); // Handles all AJAX now
        QCP_Admin::instance();
        // Other classes instantiated/used statically by Ajax/Frontend

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
    }

    public function enqueue_frontend_assets() {
        $options = get_option('qcp_settings', []);
        $enable_popup_checkout = $options['enable_popup_checkout'] ?? true;
        $enable_button_replace = $options['enable_button_replace'] ?? true;

        // Load assets only if features enabled, not admin, and potentially on product pages or archives
        if ( ($enable_button_replace || $enable_popup_checkout) && !is_admin() &&
             (is_product() || is_shop() || is_product_category() || is_product_tag() || is_front_page() || is_page() /* Add more conditions if needed */ ) )
        {
            $css_file_path = QCP_PLUGIN_DIR . 'assets/css/frontend.css';
            $js_file_path = QCP_PLUGIN_DIR . 'assets/js/frontend.js';
            $css_version = QCP_VERSION . '.' . (file_exists($css_file_path) ? filemtime($css_file_path) : '0');
            $js_version = QCP_VERSION . '.' . (file_exists($js_file_path) ? filemtime($js_file_path) : '0');

            wp_enqueue_style('qcp-frontend-style', QCP_PLUGIN_URL . 'assets/css/frontend.css', [], $css_version);
            wp_enqueue_script('qcp-frontend-script', QCP_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], $js_version, true);

            // --- Prepare Data for JS ---
            $price_format_params = [
                 'currency_symbol'    => get_woocommerce_currency_symbol(),
                 'currency_pos'       => get_option( 'woocommerce_currency_pos' ),
                 'thousand_separator' => wc_get_price_thousand_separator(),
                 'decimal_separator'  => wc_get_price_decimal_separator(),
                 'decimals'           => wc_get_price_decimals(),
             ];

            wp_localize_script( 'qcp-frontend-script', 'qcp_params', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'qcp_checkout_nonce' ), // Used for checkout, stats, coupon, variation ajax
                'thank_you_message' => $options['thank_you_message'] ?? __( 'Thank you for your order! We will contact you shortly.', 'quick-checkout-popup' ),
                'validation_messages' => [
                    'required'           => __( 'This field is required.', 'quick-checkout-popup' ),
                    'phone_format_invalid' => __( 'Please enter a valid phone number.', 'quick-checkout-popup' ),
                    'numeric_error'      => __('Please enter a valid number.', 'quick-checkout-popup'),
                    'min_quantity_error' => __('Quantity must be at least 1.', 'quick-checkout-popup'),
                ],
                'coupon_messages' => [
                    'error'           => __('Error applying coupon.', 'quick-checkout-popup'),
                    'applied'         => __('Coupon applied successfully.', 'quick-checkout-popup'),
                    'removed'         => __('Coupon removed.', 'quick-checkout-popup'),
                    'enter_code'      => __('Please enter a coupon code.', 'quick-checkout-popup'),
                    'apply_text'      => __('Apply', 'quick-checkout-popup'),
                    'applying_text'   => __('Applying...', 'quick-checkout-popup'),
                    'remove_text'     => __('Remove', 'quick-checkout-popup'),
                    'removing_text'   => __('Removing...', 'quick-checkout-popup'),
                ],
                'variation_messages' => [
                     'select_options'   => __('Please select product options.', 'quick-checkout-popup'),
                     'unavailable'      => __('Sorry, this combination is unavailable.', 'quick-checkout-popup'),
                     'out_of_stock'     => __('Sorry, this combination is out of stock.', 'quick-checkout-popup'),
                     'adding_options'   => __('Loading options...', 'quick-checkout-popup'), // Placeholder text
                     'options_loaded'   => __('Options loaded.', 'quick-checkout-popup'), // Placeholder text
                     'select_variation' => __('Select Variation', 'quick-checkout-popup'), // Button text
                     'variation_selected' => __('Proceed to Checkout', 'quick-checkout-popup'), // Button text
                ],
                'placeholder_image_url' => function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('woocommerce_single') : '', // Use larger placeholder
                'ga_id'                 => $options['ga_id'] ?? '',
                'fb_pixel_id'           => $options['fb_pixel_id'] ?? '',
                'price_format'          => $price_format_params,
                'enable_coupons'        => apply_filters('qcp_enable_coupons', true), // Filter to easily disable coupons
                'enable_variations'     => apply_filters('qcp_enable_variations', true), // Filter to easily disable variations
                'enable_stats'          => !empty($options['enable_stats']), // *** AJOUT : Passe l'état des stats au JS ***
            ) );
        }
    }

} // End Class QCP_Main