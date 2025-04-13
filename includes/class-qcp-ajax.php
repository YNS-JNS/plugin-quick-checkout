<?php
namespace Quick_Checkout_Popup;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for Quick Checkout Popup V1.
 * Includes Quantity, Variations, Coupons, Enhanced Validation.
 */
class QCP_Ajax {

    private static $_instance = null;
    private $options = null; // Cache options

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
            self::$_instance->load_options(); // Load options on instantiation
            self::$_instance->hooks();
        }
        return self::$_instance;
    }

    /** Load options once */
    private function load_options() {
        $this->options = get_option('qcp_settings', []);
    }

    /** Registers all AJAX action hooks. */
    private function hooks() {
        $actions_map = [
            'submit_checkout'    => 'handle_checkout_submission',
            'track_stat'         => 'ajax_track_stat',
            'get_variation_data' => 'handle_get_variation_data',
            'apply_coupon'       => 'handle_apply_coupon'
        ];
        foreach ($actions_map as $action => $method) {
            add_action( "wp_ajax_qcp_{$action}", array( $this, $method ) );
            add_action( "wp_ajax_nopriv_qcp_{$action}", array( $this, $method ) );
        }
    }

    /** Security Check Helper */
    private function check_nonce( $action = 'qcp_checkout_nonce', $query_arg = 'nonce' ) {
         if ( ! isset( $_POST[$query_arg] ) || ! wp_verify_nonce( sanitize_key( $_POST[$query_arg] ), $action ) ) {
            wp_send_json_error( [ 'errors' => ['global' => __( 'Security check failed.', 'quick-checkout-popup')] ], 403 );
        }
    }

    /** Validate phone number format */
    private function validate_phone_number( $phone ) {
        $digits_only = preg_replace( '/[^\d]/', '', $phone );
        return preg_match('/^[0-9\s\-\+\(\)]+$/', $phone) && strlen($digits_only) >= 7;
    }

    /** Helper to get field info */
     private function get_field_info($key) {
         $info = [ 'id' => '', 'label' => ucfirst($key), 'type' => 'text' ];
         switch ($key) {
            case 'name':
                $info['id'] = 'qcp_billing_name';
                $info['label'] = __('Full Name', 'quick-checkout-popup'); // Garde ton text domain
                break;
            case 'phone':
                $info['id'] = 'qcp_billing_phone';
                $info['label'] = __('Phone Number', 'woocommerce'); // Garde celui de WC pour cohérence éventuelle
                break;
            case 'city':
                $info['id'] = 'qcp_billing_city';
                // *** CHANGEMENT ICI ***
                $info['label'] = __('Town / City', 'quick-checkout-popup'); // Utilise ton text domain
                break;
            case 'address':
                $info['id'] = 'qcp_shipping_address_1';
                // *** CHANGEMENT ICI ***
                $info['label'] = __('Street Address', 'quick-checkout-popup'); // Utilise ton text domain
                $info['type'] = 'textarea';
                break;
         }
         return $info;
     }

    // --- Main Checkout Submission Handler ---
    public function handle_checkout_submission() {
        // ... (Le reste de la fonction reste inchangé) ...
        $this->check_nonce();
        error_log("QCP Log: handle_checkout_submission started.");

        parse_str( $_POST['form_data'] ?? '', $form_data );
        // $options = get_option('qcp_settings', []); // Use cached options
        $options = $this->options;
        $default_reqs = ['name' => true, 'phone' => true, 'city' => false, 'address' => false];
        $field_requirements = isset($options['field_requirements']) && is_array($options['field_requirements']) ? array_merge($default_reqs, $options['field_requirements']) : $default_reqs;
        $errors = []; $sanitized_data = [];

        // --- Sanitize & Validate Inputs ---
        $sanitized_data['product_id'] = isset($form_data['product_id']) ? absint($form_data['product_id']) : 0;
        $sanitized_data['variation_id'] = isset($form_data['variation_id']) ? absint($form_data['variation_id']) : 0;
        $sanitized_data['quantity'] = isset($form_data['quantity']) ? absint($form_data['quantity']) : 1;
        $parent_product_id = $sanitized_data['product_id']; // Keep parent ID for stats
        $product_id_to_check = $sanitized_data['variation_id'] > 0 ? $sanitized_data['variation_id'] : $parent_product_id;

        if ($sanitized_data['quantity'] < 1) $errors['qcp-quantity'] = __('Quantity must be at least 1.', 'quick-checkout-popup');
        if ( empty($parent_product_id) ) $errors['product_selection'] = __( 'Invalid product data received.', 'quick-checkout-popup' );

        // Billing Fields validation...
        $fields_to_validate = [ 'name','phone','city','address' ];
        foreach ($fields_to_validate as $key) {
             $field_info = $this->get_field_info($key); // Utilise la fonction modifiée
             $form_key = ($key === 'address') ? 'shipping_address_1' : 'billing_' . $key;
             $value = isset($form_data[$form_key]) ? trim(wp_unslash($form_data[$form_key])) : '';
             $field_id_attr = $field_info['id'];
             if ($field_info['type'] === 'textarea') $sanitized_data[$form_key] = sanitize_textarea_field($value); else $sanitized_data[$form_key] = sanitize_text_field($value);
             if (!empty($field_requirements[$key]) && empty($sanitized_data[$form_key])) {
                 // Utilise le label traduit par get_field_info (qui utilise maintenant ton text domain pour city/address)
                 $errors[$field_id_attr] = sprintf(__('%s is required.', 'quick-checkout-popup'), $field_info['label']);
             } elseif ($key === 'phone' && !empty($sanitized_data[$form_key]) && !$this->validate_phone_number($sanitized_data[$form_key])) {
                 $errors[$field_id_attr] = __('Invalid phone format.', 'quick-checkout-popup');
             }
        }
        $sanitized_data['coupon_code'] = isset($form_data['applied_coupon_code']) ? sanitize_text_field(wp_unslash($form_data['applied_coupon_code'])) : '';
        $sanitized_data['billing_email'] = '';

        // --- Check Product/Variation Validity ---
        // ... (inchangé) ...
        $product = null; $variation = null;
        if ( empty($errors) && $product_id_to_check > 0 ) {
            error_log("QCP Log: Checking product/variation ID: " . $product_id_to_check);
            $product_check = wc_get_product( $product_id_to_check );

            if (!$product_check) {
                $errors['product_selection'] = __( 'Product not found.', 'quick-checkout-popup' );
                error_log("QCP Log: Product check failed - Not Found (ID: {$product_id_to_check})");
            } elseif (!$product_check->is_purchasable()) {
                $errors['product_selection'] = __( 'This product cannot be purchased at this time.', 'quick-checkout-popup' );
                error_log("QCP Log: Product check failed - Not Purchasable (ID: {$product_id_to_check})");
            } elseif (!$product_check->is_in_stock()) {
                $errors['product_selection'] = __( 'This product is currently out of stock.', 'quick-checkout-popup' );
                 error_log("QCP Log: Product check failed - Out of Stock (ID: {$product_id_to_check})");
            } else {
                 if ($sanitized_data['variation_id'] > 0) {
                     if (!$product_check->is_type('variation')) {
                         $errors['product_selection'] = __( 'Invalid product variation selected.', 'quick-checkout-popup' );
                         error_log("QCP Log: Product check failed - Expected Variation, got " . $product_check->get_type());
                     } else {
                         $parent_product_check = wc_get_product($parent_product_id);
                         if (!$parent_product_check || !$parent_product_check->is_type('variable') || $product_check->get_parent_id() !== $parent_product_id) {
                             $errors['product_selection'] = __( 'Error validating product variation details.', 'quick-checkout-popup' );
                              error_log("QCP Log: Product check failed - Variation parent mismatch or invalid.");
                         } else {
                             $variation = $product_check; $product = $parent_product_check;
                             if ( ! $variation->has_enough_stock( $sanitized_data['quantity'] ) ) {
                                 $stock_qty = $variation->get_stock_quantity();
                                 $errors['qcp-quantity'] = $stock_qty > 0 ? sprintf( __('Only %s available (variation).', 'quick-checkout-popup'), $stock_qty ) : __('Selected variation is out of stock.', 'quick-checkout-popup');
                                 error_log("QCP Log: Product check failed - Variation stock insufficient.");
                             } else { error_log("QCP Log: Product check PASSED (Variation)."); }
                         }
                     }
                 } elseif ($product_check->is_type('simple')) {
                      $product = $product_check;
                      if ( ! $product->has_enough_stock( $sanitized_data['quantity'] ) ) {
                           $stock_qty = $product->get_stock_quantity();
                           $errors['qcp-quantity'] = $stock_qty > 0 ? sprintf( __('Only %s available.', 'quick-checkout-popup'), $stock_qty ) : __('This product is out of stock.', 'quick-checkout-popup');
                            error_log("QCP Log: Product check failed - Simple product stock insufficient.");
                      } else { error_log("QCP Log: Product check PASSED (Simple)."); }
                 } elseif ($product_check->is_type('variable')) {
                      $errors['product_selection'] = __( 'Please select product options first.', 'quick-checkout-popup' );
                       error_log("QCP Log: Product check failed - Variable submitted without variation ID.");
                 } else {
                      $errors['product_selection'] = __( 'This product type is not supported.', 'quick-checkout-popup' );
                       error_log("QCP Log: Product check failed - Unsupported type: " . $product_check->get_type());
                 }
            }
        } else { error_log("QCP Log: Skipping product check due to previous errors or invalid ID."); }

        // --- Return Errors If Any ---
        // ... (inchangé) ...
        if ( ! empty( $errors ) ) {
            error_log("QCP Log: Final Validation errors: " . print_r($errors, true));
            wp_send_json_error( [ 'errors' => $errors ], 400 );
        }

        // --- Double Check Product Object ---
        // ... (inchangé) ...
        if ( ! $product instanceof \WC_Product ) {
             error_log("QCP Order FATAL: \$product object invalid before create_order. ParentID:{$parent_product_id}, VarID:{$sanitized_data['variation_id']}");
             wp_send_json_error( [ 'errors' => ['global' => __('Internal Error: Invalid product data [A].', 'quick-checkout-popup')] ], 500 );
        }
         if ($sanitized_data['variation_id'] > 0 && ! $variation instanceof \WC_Product_Variation ) {
             error_log("QCP Order FATAL: \$variation object invalid. VarID:{$sanitized_data['variation_id']}");
             wp_send_json_error( [ 'errors' => ['global' => __('Internal Error: Invalid variation data [B].', 'quick-checkout-popup')] ], 500 );
         }

        // --- Create Order ---
        // ... (inchangé) ...
        error_log("QCP Log: Calling create_order. ProdID: " . $product->get_id() . ", VarID: " . $sanitized_data['variation_id'] . ", Qty: " . $sanitized_data['quantity']);
        $order_creator = QCP_Order::instance();
        $order_result = $order_creator->create_order(
            $sanitized_data, $product, $sanitized_data['coupon_code'], $sanitized_data['variation_id'],
            $variation instanceof \WC_Product_Variation ? $variation->get_variation_attributes() : []
        );

        // --- Handle Result ---
        // ... (inchangé) ...
        if ( is_wp_error( $order_result ) ) {
            $error_message = $order_result->get_error_message(); $error_code = $order_result->get_error_code();
            error_log("QCP Order Creation Failed (WP_Error code: {$error_code}): " . $error_message);
            wp_send_json_error( [ 'errors' => [ 'global' => $error_message ] ], 500 );
        }

        // --- Success ---
        // ... (inchangé) ...
        $order = $order_result; $order_id = $order->get_id();
        error_log("QCP Log: Order #{$order_id} created successfully.");

        // Google Sheets & Stats...
        $sheets_success = false;
        if ( !empty($options['enable_google_sheets']) && !empty($options['google_sheets_url']) && class_exists('Quick_Checkout_Popup\QCP_Sheets') ) {
            $sheets_result = QCP_Sheets::instance()->send_order_to_sheets($order, $sanitized_data);
            if ( !is_wp_error($sheets_result) && $sheets_result === true ) $sheets_success = true;
            else error_log('QCP GSheets Err #' . $order_id . ': ' . (is_wp_error($sheets_result) ? $sheets_result->get_error_message() : 'Unknown'));
        }

        if ( !empty($options['enable_stats']) && class_exists('Quick_Checkout_Popup\QCP_Stats') ) {
            QCP_Stats::instance()->record_success($parent_product_id);
        }

        // Final Response...
        $product_ref = $variation ?: $product; $product_details = $product_ref instanceof \WC_Product ? ['product_id' => $product_ref->get_id(), 'product_name' => $product_ref->get_name(), 'item_price' => $product_ref->get_price('edit')] : [];
        wp_send_json_success( [ 'message' => !empty($options['thank_you_message']) ? wp_kses_post($options['thank_you_message']) : __( 'Thank you! Order received.', 'quick-checkout-popup' ), 'order_id' => $order_id, 'order_details' => array_merge($product_details, ['order_id' => $order_id, 'total' => $order->get_total(), 'currency' => $order->get_currency(), 'quantity' => $sanitized_data['quantity']]), 'sheets_logged' => $sheets_success, ] );
    }

    // --- AJAX: Get Variation Data ---
    // ... (inchangé) ...
    public function handle_get_variation_data() {
        $this->check_nonce(); $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0; if (empty($product_id)) { wp_send_json_error(['message' => __('Invalid product ID.', 'quick-checkout-popup')], 400); } $product = wc_get_product($product_id); if (!$product || !$product->is_type('variable')) { wp_send_json_error(['message' => __('Product is not variable.', 'quick-checkout-popup')], 400); } $variations_data = $product->get_available_variations(); if (!is_array($variations_data)) $variations_data = []; global $post, $product; $original_post = $post; $original_product = $product; $attributes_html = ''; $post_obj = get_post($product_id); if ($post_obj) { $post = $post_obj; setup_postdata($post); $product = wc_get_product($product_id); try { ob_start(); woocommerce_variable_add_to_cart(); $attributes_html = ob_get_clean(); } catch (\Throwable $th) { error_log("QCP AJAX Error getting variation HTML: " . $th->getMessage()); $attributes_html = '<p class="qcp-error-text">' . __('Error loading options.', 'quick-checkout-popup') . '</p>'; } $post = $original_post; $product = $original_product; if ($original_post) setup_postdata($original_post); else wp_reset_postdata(); } else { error_log("QCP AJAX Error: Could not get post object for ID: " . $product_id); } if (empty($variations_data) && strpos($attributes_html, 'error-text') !== false) { wp_send_json_error(['message' => __('No variations available or error loading options.', 'quick-checkout-popup')], 404); } wp_send_json_success(['variations' => $variations_data, 'attributes_html' => $attributes_html]);
    }

    // --- AJAX: Apply Coupon ---
    // ... (inchangé) ...
    public function handle_apply_coupon() {
        $this->check_nonce(); if (!wc_coupons_enabled()) { wp_send_json_error(['message' => __('Coupons disabled.', 'woocommerce')], 400); } $coupon_code = isset($_POST['coupon_code']) ? wc_format_coupon_code(wp_unslash($_POST['coupon_code'])) : ''; $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0; $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0; $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1; if ($quantity < 1) $quantity = 1; if (empty($coupon_code)) { wp_send_json_error(['message' => __('Enter coupon code.', 'woocommerce')], 400); } $coupon = new \WC_Coupon($coupon_code); if (!$coupon->get_code() || !$coupon->is_valid()) { $error_msg = __('Invalid or expired coupon.', 'woocommerce'); if ($coupon->get_error_data()) { $error_data = $coupon->get_error_data(); $error_msg = $error_data['message'] ?? $error_msg; } wp_send_json_error(['message' => $error_msg], 400); } $product_to_validate = $variation_id > 0 ? wc_get_product($variation_id) : wc_get_product($product_id); if (!$product_to_validate) { wp_send_json_error(['message' => __('Product not found.', 'quick-checkout-popup')], 400); } $error_msg = ''; $subtotal = (float) $product_to_validate->get_price('edit') * $quantity; try { if (!$coupon->is_valid_for_product($product_to_validate, [])) throw new \Exception(__('Coupon not valid for product.', 'woocommerce')); if ($coupon->get_minimum_amount() > 0 && $subtotal < $coupon->get_minimum_amount()) throw new \Exception(sprintf(__('Min spend: %s.', 'woocommerce'), wc_price($coupon->get_minimum_amount()))); if ($coupon->get_maximum_amount() > 0 && $subtotal > $coupon->get_maximum_amount()) throw new \Exception(sprintf(__('Max spend: %s.', 'woocommerce'), wc_price($coupon->get_maximum_amount()))); if ($coupon->get_exclude_sale_items() && $product_to_validate->is_on_sale('edit')) throw new \Exception(__('Coupon not valid for sale items.', 'woocommerce')); } catch (\Exception $e) { $error_msg = $e->getMessage(); } if (!empty($error_msg)) { wp_send_json_error(['message' => $error_msg], 400); } $discount_amount = 0; $discount_type = $coupon->get_discount_type(); if ($discount_type === 'percent') $discount_amount = ($subtotal * ($coupon->get_amount() / 100)); elseif ($discount_type === 'fixed_product') $discount_amount = min($coupon->get_amount() * $quantity, $subtotal); elseif ($discount_type === 'fixed_cart') $discount_amount = min($coupon->get_amount(), $subtotal); $discount_amount = round($discount_amount, wc_get_price_decimals()); wp_send_json_success([ 'message' => __('Coupon applied.', 'woocommerce'), 'coupon_code' => $coupon_code, 'discount_amount' => $discount_amount, 'discount_type' => $discount_type ]);
    }


    /** Stats tracking handler */
    // ... (inchangé, vérifie déjà l'option 'enable_stats') ...
    public function ajax_track_stat() {
        $this->check_nonce('qcp_checkout_nonce', 'nonce');
        // $options = get_option('qcp_settings', []); // Use cached options
        $options = $this->options;

        $type = isset($_POST['stat_type']) ? sanitize_key($_POST['stat_type']) : '';
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if ( $type === 'click' && $product_id > 0 && !empty($options['enable_stats']) && class_exists('Quick_Checkout_Popup\QCP_Stats') ) {
            QCP_Stats::instance()->record_click($product_id);
            wp_send_json_success( ['message' => 'Click recorded'] );
        } elseif ( $type === 'click' && $product_id > 0 && empty($options['enable_stats']) ) {
            wp_send_json_success( ['message' => 'Stats disabled, click not recorded'] );
        } else {
            error_log("QCP Stat AJAX Error: Invalid request. Type: '{$type}', Prod ID: '{$product_id}', Stats Enabled: " . (!empty($options['enable_stats']) ? 'Yes' : 'No'));
            wp_send_json_error( ['message' => 'Invalid request.'], 400 );
        }
    }

} // End Class QCP_Ajax