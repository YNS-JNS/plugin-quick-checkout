<?php
namespace Quick_Checkout_Popup;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles programmatic order creation for Quick Checkout Popup V1.
 */
class QCP_Order {

    private static $_instance = null;

    public static function instance() {
         if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Creates a WooCommerce order programmatically.
     * Includes robust checks and error handling.
     *
     * @param array       $data         Sanitized form data.
     * @param \WC_Product $product      Parent WC_Product object.
     * @param string|null $coupon_code  Coupon code to apply.
     * @param int|null    $variation_id Variation ID if variable product.
     * @param array|null  $attributes   Variation attributes if variable product.
     *
     * @return \WC_Order|\WP_Error WC_Order object on success, WP_Error on failure.
     */
    public function create_order( $data, $product, $coupon_code = null, $variation_id = 0, $attributes = [] ) {
        // --- Initial Parameter Validation ---
        if ( ! $product instanceof \WC_Product ) {
             error_log('QCP Order Creation Error: Invalid parent product object passed.');
             return new \WP_Error( 'invalid_product_object', __( 'Internal Error: Invalid product data.', 'quick-checkout-popup' ) );
        }
        $variation = null;
        if ( $variation_id > 0 ) {
            $variation = wc_get_product($variation_id);
            if ( ! $variation instanceof \WC_Product_Variation || $variation->get_parent_id() !== $product->get_id() ) {
                 error_log("QCP Order Creation Error: Invalid variation object for ID {$variation_id}.");
                 return new \WP_Error( 'invalid_variation_object', __( 'Internal Error: Invalid variation data.', 'quick-checkout-popup' ) );
            }
        }

        $order = false; $order_id = 0;
        $quantity = isset($data['quantity']) ? absint($data['quantity']) : 1;
        if ($quantity < 1) $quantity = 1;

        // Product/Variation to add to the order
        $product_to_add = $product; // Default to parent/simple
        $args_for_add_product = [];
        if ($variation instanceof \WC_Product_Variation) {
             $args_for_add_product = ['variation_id' => $variation_id, 'variation' => $attributes];
             $item_to_check = $variation; // Check variation properties
        } else {
             $item_to_check = $product; // Check simple product properties
        }

        // --- Pre-Checks (before creating order object) ---
         if ( ! $item_to_check instanceof \WC_Product ) {
              return new \WP_Error( 'invalid_item_check', __( 'Internal Error: Could not validate item.', 'quick-checkout-popup' ) );
         }
         if ( ! $item_to_check->is_purchasable() ) { return new \WP_Error('item_not_purchasable', __('Item cannot be purchased.', 'quick-checkout-popup')); }
         if ( ! $item_to_check->has_enough_stock( $quantity ) ) { return new \WP_Error('item_no_stock', __('Not enough stock.', 'quick-checkout-popup')); }


        // --- Main Order Creation Block ---
        try {
            $order = wc_create_order( [ 'status' => 'pending' ] ); // Start as pending
            if ( is_wp_error( $order ) ) {
                 error_log('QCP wc_create_order() failed: ' . $order->get_error_message());
                 throw new \Exception( __( 'Could not initiate order.', 'quick-checkout-popup' ) );
            }
            $order_id = $order->get_id();
            error_log("QCP Log: Draft Order #{$order_id} created."); // DEBUG

            // Add Product
            $order->add_product( $product_to_add, $quantity, $args_for_add_product );
            error_log("QCP Log: Product added to order #{$order_id}."); // DEBUG

            // Set Addresses
            $address = [ /* ... same billing fields ... */
                 'first_name' => $data['billing_name'] ?? '', 'last_name'  => '', 'email' => '',
                 'phone'      => $data['billing_phone'] ?? '', 'address_1'  => $data['shipping_address_1'] ?? '',
                 'city'       => $data['billing_city'] ?? '', 'postcode'   => '',
                 'country'    => WC()->countries->get_base_country(), 'state' => WC()->countries->get_base_state(),
            ];
            $order->set_address( $address, 'billing' );
            $order->set_address( $address, 'shipping' );

            // Set Customer
            if ( is_user_logged_in() ) $order->set_customer_id( get_current_user_id() );

            // Apply Coupon
            if ( !empty($coupon_code) && wc_coupons_enabled() ) {
                error_log("QCP Log: Attempting to apply coupon '{$coupon_code}' to order #{$order_id}."); // DEBUG
                $coupon_result = $order->apply_coupon( $coupon_code );
                if (is_wp_error($coupon_result)) {
                    // Log error, add note, but continue order creation
                    $coupon_error = $coupon_result->get_error_message();
                    error_log("QCP Order #{$order_id}: Coupon '{$coupon_code}' apply failed: " . $coupon_error);
                    $order->add_order_note( sprintf( __('Coupon "%s" could not be applied. Reason: %s', 'quick-checkout-popup'), esc_html($coupon_code), esc_html($coupon_error) ) );
                } else {
                     error_log("QCP Log: Coupon '{$coupon_code}' applied to order #{$order_id}."); // DEBUG
                }
            }

            // Set Payment Method
            $options = get_option('qcp_settings', []); $selected_gateway_id = $options['default_payment_gateway'] ?? 'cod';
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $payment_gateway = null; $used_gateway_id = '';
            if ( isset( $available_gateways[ $selected_gateway_id ] ) && $available_gateways[ $selected_gateway_id ]->is_available() ) { $payment_gateway = $available_gateways[ $selected_gateway_id ]; $used_gateway_id = $selected_gateway_id; }
            elseif ( isset( $available_gateways['cod'] ) && $available_gateways['cod']->is_available() ) { $payment_gateway = $available_gateways['cod']; $used_gateway_id = 'cod'; error_log("QCP Order #{$order_id}: Fallback COD."); }
            if ( !$payment_gateway instanceof \WC_Payment_Gateway ) throw new \Exception( __( 'No suitable payment method available.', 'quick-checkout-popup' ) );
            $order->set_payment_method( $payment_gateway );
            error_log("QCP Log: Payment method '{$used_gateway_id}' set for order #{$order_id}."); // DEBUG

            // Calculate Totals
            error_log("QCP Log: Calculating totals for order #{$order_id}."); // DEBUG
            $order->calculate_totals(true);

            // Save Order
             error_log("QCP Log: Saving order #{$order_id}."); // DEBUG
            $save_result = $order->save();
            if ( ! $save_result ) {
                // Try to get last error if save returns falsy
                 $last_error = wc_get_notices('error'); // Check WC notices
                 $error_msg = !empty($last_error) ? implode(' ', $last_error) : __( 'Failed to save order data.', 'quick-checkout-popup' );
                 throw new \Exception( $error_msg );
            }

            // Final Status & Stock Reduction
             error_log("QCP Log: Updating status and stock for order #{$order_id}."); // DEBUG
            $final_status = apply_filters('qcp_default_order_status', 'processing', $order);
            $order->update_status( $final_status, __( 'Order via Quick Checkout.', 'quick-checkout-popup' ), true );
            wc_reduce_stock_levels($order_id);

            error_log("QCP Order #{$order_id} FULLY CREATED. Payment: {$used_gateway_id}. Status: {$final_status}. Qty: {$quantity}. VarID: {$variation_id}.");
            return $order; // Success!

        } catch ( \Throwable $e ) { // Catch Throwable for broader error catching (PHP 7+)
            $context = $order_id ? " (Order ID: {$order_id})" : ($product ? " (Product ID: {$product->get_id()})" : "");
            error_log('QCP Order Creation Exception' . $context . ': ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString()); // Log full trace

            // Attempt to delete draft order if created but failed later
            if ($order instanceof \WC_Order && $order->get_id() && $order->get_status() === 'pending') {
                 wp_delete_post($order->get_id(), true);
                 error_log("QCP: Deleted draft order #{$order->get_id()} due to exception.");
            }
            // Return WP_Error with the exception message
            return new \WP_Error( 'order_creation_exception', $e->getMessage() );
        }
    } // End create_order

} // End Class QCP_Order