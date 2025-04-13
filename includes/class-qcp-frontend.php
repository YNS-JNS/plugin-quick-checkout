<?php
namespace Quick_Checkout_Popup;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QCP_Frontend {

    private static $_instance = null;

    public static function instance() {
         if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
            self::$_instance->hooks();
        }
        return self::$_instance;
    }

    private function hooks() {
        $options = get_option('qcp_settings', []);
        $enable_button_replace = $options['enable_button_replace'] ?? true;
        $enable_popup_checkout = $options['enable_popup_checkout'] ?? true;
        $enable_variations = apply_filters('qcp_enable_variations', true);

        if ( $enable_button_replace ) {
            add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'qcp_loop_button_replacement' ), 99, 3 );
            add_action( 'woocommerce_single_product_summary', function() use ($enable_variations) {
                global $product;
                if ($product && ($product->is_type('simple') || ($enable_variations && $product->is_type('variable')))) {
                    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
                }
            }, 1 );
            add_action( 'woocommerce_single_product_summary', array( $this, 'render_qcp_button_single' ), 30 );
        }

        if ( $enable_popup_checkout ) {
           add_action( 'wp_footer', array( $this, 'render_checkout_popup_structure' ) );
        }
    }

    /** Replacement for Add to Cart button on loops/archives. */
    public function qcp_loop_button_replacement( $html, $product, $args ) {
        if ( ! $product || ! $product->is_purchasable() ) return $html;
        $options = get_option('qcp_settings', []);
        $button_text_simple = !empty($options['button_text']) ? esc_html($options['button_text']) : __( 'Buy Now', 'quick-checkout-popup' );
        $button_text_variable = __( 'Select Options', 'quick-checkout-popup' );
        $enable_variations = apply_filters('qcp_enable_variations', true);

        if ( $product->is_type( 'simple' ) && $product->is_in_stock() ) {
            $img_url = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
            $price = $product->get_price('edit');
            return sprintf( '<button type="button" class="button qcp-buy-now-button %s" data-product-id="%d" data-quantity="1" data-product-name="%s" data-product-price-html="%s" data-product-image-url="%s" data-product-price="%s" data-product-type="simple">%s</button>', esc_attr( $args['class'] ?? 'qcp-loop-button' ), esc_attr( $product->get_id() ), esc_attr( $product->get_name() ), esc_attr( $product->get_price_html() ), esc_url( $img_url ?: wc_placeholder_img_src() ), esc_attr( $price ), $button_text_simple );
        } elseif ( $enable_variations && $product->is_type( 'variable' ) && $product->has_child() && $product->is_in_stock() ) {
             $img_url = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
             return sprintf( '<button type="button" class="button qcp-buy-now-button %s" data-product-id="%d" data-quantity="1" data-product-name="%s" data-product-price-html="%s" data-product-image-url="%s" data-product-price="" data-product-type="variable">%s</button>', esc_attr( $args['class'] ?? 'qcp-loop-button' ), esc_attr( $product->get_id() ), esc_attr( $product->get_name() ), esc_attr( $product->get_price_html() ), esc_url( $img_url ?: wc_placeholder_img_src() ), $button_text_variable ); // Ensure Text is here
        }
        return ''; // Remove button for unsupported types on loop
    }

    /** Render the QCP button on single product pages for simple or variable. */
     public function render_qcp_button_single() {
        global $product;
        if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() ) return;

        $options = get_option('qcp_settings', []);
        $button_text_simple = !empty($options['button_text']) ? esc_html($options['button_text']) : __( 'Buy Now', 'quick-checkout-popup' );
        // *** Utiliser un texte clair pour le bouton variable ***
        $button_text_variable = __( 'Quick Buy / Select Options', 'quick-checkout-popup' );
        $button_classes = 'single_add_to_cart_button button alt qcp-buy-now-button qcp-single-button'; // Base classes
        $product_image_url = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_single');
        $enable_variations = apply_filters('qcp_enable_variations', true);

        // Simple Product
        if ( $product->is_type( 'simple' ) && $product->is_in_stock() ) {
            $raw_price = $product->get_price('edit');
            echo sprintf(
                // *** Utilisation du span pour le texte comme dans le template ? Non, le texte direct suffit ici. ***
                '<button type="button" class="%s" data-product-id="%d" data-quantity="1" data-product-name="%s" data-product-price-html="%s" data-product-image-url="%s" data-product-price="%s" data-product-type="simple">%s</button>',
                 esc_attr($button_classes), esc_attr( $product->get_id() ), esc_attr( $product->get_name() ), esc_attr( $product->get_price_html() ),
                 esc_url( $product_image_url ?: wc_placeholder_img_src('woocommerce_single') ), esc_attr( $raw_price ), $button_text_simple
            );
        }
        // Variable Product
        elseif ( $enable_variations && $product->is_type( 'variable' ) && $product->has_child() ) {
            $variations = $product->get_available_variations();
            $is_purchasable = false; foreach($variations as $v){ if($v['is_purchasable'] && $v['is_in_stock']){ $is_purchasable = true; break; } }

            if ( !empty($variations) && $is_purchasable ) {
                 // *** Vérifier que $button_text_variable est bien la dernière variable de sprintf ***
                 // Ajouter la classe 'qcp-variable-button' pour ciblage CSS potentiel
                echo sprintf(
                    '<button type="button" class="%s qcp-variable-button" data-product-id="%d" data-quantity="1" data-product-name="%s" data-product-price-html="%s" data-product-image-url="%s" data-product-price="" data-product-type="variable">%s</button>',
                     esc_attr($button_classes), esc_attr( $product->get_id() ), esc_attr( $product->get_name() ), esc_attr( $product->get_price_html() ),
                     esc_url( $product_image_url ?: wc_placeholder_img_src('woocommerce_single') ),
                     $button_text_variable // *** Assurez-vous que ce texte est bien passé ***
                );
            } else {
                 echo '<p class="stock out-of-stock">' . esc_html__( 'This product is currently unavailable.', 'woocommerce' ) . '</p>';
            }
        }
     }

    /** Render the hidden popup HTML structure in the footer. */
    public function render_checkout_popup_structure() {
         if ( is_admin() ) return;
         wc_get_template('popup-checkout-form.php', [], 'quick-checkout-popup', QCP_PLUGIN_DIR . 'templates/');
    }
} // End Class