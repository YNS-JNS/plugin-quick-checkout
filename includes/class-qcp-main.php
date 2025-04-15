<?php
namespace Quick_Checkout_Popup;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class V1.3.0 - Added MAD currency symbol filter.
 */
final class QCP_Main {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
            self::$_instance->includes();
            self::$_instance->hooks(); // Assure-toi que hooks est appelé après includes
        }
        return self::$_instance;
    }

    private function includes() {
        require_once QCP_PLUGIN_DIR . 'includes/class-qcp-frontend.php';
        require_once QCP_PLUGIN_DIR . 'includes/class-qcp-ajax.php';
        require_once QCP_PLUGIN_DIR . 'includes/class-qcp-admin.php';
        require_once QCP_PLUGIN_DIR . 'includes/class-qcp-order.php';

        $options = get_option('qcp_settings', []);

        if (!empty($options['enable_google_sheets']) && file_exists(QCP_PLUGIN_DIR . 'includes/class-qcp-sheets.php')) {
            require_once QCP_PLUGIN_DIR . 'includes/class-qcp-sheets.php';
        }

        if ( !empty($options['enable_stats']) && file_exists(QCP_PLUGIN_DIR . 'includes/class-qcp-stats.php')) {
            require_once QCP_PLUGIN_DIR . 'includes/class-qcp-stats.php';
        }
    }

    private function hooks() {
        // Instancier les classes principales
        QCP_Frontend::instance();
        QCP_Ajax::instance();
        QCP_Admin::instance();
        // Les autres classes sont typiquement instanciées ou utilisées statiquement au besoin par Ajax/Frontend

        // Actions et Filtres Généraux
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // *** AJOUT DU FILTRE POUR LE SYMBOLE MAD ***
        add_filter( 'woocommerce_currency_symbol', array( $this, 'qcp_change_mad_currency_symbol'), 99, 2 );
    }

    /**
     * Change MAD currency symbol to 'dh'.
     *
     * @param string $currency_symbol The default currency symbol.
     * @param string $currency        The currency code.
     * @return string The modified currency symbol.
     */
    public function qcp_change_mad_currency_symbol( $currency_symbol, $currency ) {
        if ( $currency === 'MAD' ) {
             // Vérifie si le symbole n'est pas déjà 'dh' pour éviter des boucles si une autre fonction le fait déjà
             if ($currency_symbol !== 'dh') {
                return 'dh';
             }
        }
        return $currency_symbol; // Retourne le symbole original pour les autres devises
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
            // Utilise la constante QCP_VERSION si elle est définie et à jour, sinon fallback
            $version = defined('QCP_VERSION') ? QCP_VERSION : '1.2.4'; // Assure-toi que QCP_VERSION est défini dans quick-checkout-popup.php
            $css_version = $version . '.' . (file_exists($css_file_path) ? filemtime($css_file_path) : '0');
            $js_version = $version . '.' . (file_exists($js_file_path) ? filemtime($js_file_path) : '0');

            wp_enqueue_style('qcp-frontend-style', QCP_PLUGIN_URL . 'assets/css/frontend.css', [], $css_version);
            // Ajout dépendance accounting via wc-price-format
            wp_enqueue_script('qcp-frontend-script', QCP_PLUGIN_URL . 'assets/js/frontend.js', ['jquery', 'wc-price-format'], $js_version, true);

            // --- Prepare Data for JS ---
            // Récupère les paramètres de formatage WooCommerce
            $currency_pos = get_option( 'woocommerce_currency_pos' );
            $thousand_separator = wc_get_price_thousand_separator();
            $decimal_separator = wc_get_price_decimal_separator();
            $decimals = wc_get_price_decimals();
            // Important: Appelle get_woocommerce_currency_symbol() APRÈS que notre filtre soit ajouté !
            $currency_symbol = get_woocommerce_currency_symbol();

            $price_format_params = [
                 'currency_symbol'    => $currency_symbol, // Utilise le symbole potentiellement filtré
                 'currency_pos'       => $currency_pos,
                 'thousand_separator' => $thousand_separator,
                 'decimal_separator'  => $decimal_separator,
                 'decimals'           => $decimals,
             ];

            $submit_button_base = __('Place Order', 'quick-checkout-popup'); // Traduction
            $total_label = __('Total', 'quick-checkout-popup'); // Traduction

            wp_localize_script( 'qcp-frontend-script', 'qcp_params', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'qcp_checkout_nonce' ),
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
                     'adding_options'   => __('Loading options...', 'quick-checkout-popup'),
                     'options_loaded'   => __('Options loaded.', 'quick-checkout-popup'),
                     'select_variation' => __('Select Variation', 'quick-checkout-popup'),
                     'variation_selected' => __('Proceed to Checkout', 'quick-checkout-popup'),
                ],
                'placeholder_image_url' => function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('woocommerce_single') : '',
                'ga_id'                 => $options['ga_id'] ?? '',
                'fb_pixel_id'           => $options['fb_pixel_id'] ?? '',
                'price_format'          => $price_format_params, // Contient le symbole potentiellement modifié
                'enable_coupons'        => apply_filters('qcp_enable_coupons', true) && wc_coupons_enabled(),
                'enable_variations'     => apply_filters('qcp_enable_variations', true),
                'enable_stats'          => !empty($options['enable_stats']),
                'submit_button_text_base' => $submit_button_base, // Texte de base
                'total_label'           => $total_label,           // Label Total
            ) );
        }
    }

} // End Class QCP_Main