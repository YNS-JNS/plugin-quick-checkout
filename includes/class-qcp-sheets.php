<?php
namespace Quick_Checkout_Popup;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles sending order data to Google Sheets via Apps Script Web App.
 */
class QCP_Sheets {

     private static $_instance = null;

     public static function instance() {
         if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Sends order data to Google Apps Script Web App using JSON format.
     * Uses 'billingCity' key to match the existing Sheet header.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @param array $customer_data Sanitized customer input data (name, phone, city, address).
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public function send_order_to_sheets( $order, $customer_data ) {
        $options = get_option('qcp_settings', []);
        $webhook_url = $options['google_sheets_url'] ?? null;
        $secret_key = $options['google_sheets_secret'] ?? null;
        $order_id = $order->get_id();

        // --- Validation: Check URL and Secret Key ---
        if ( empty($webhook_url) || ! filter_var($webhook_url, FILTER_VALIDATE_URL) ) {
             error_log("QCP Sheets JSON Error (Order #{$order_id}): Webhook URL is missing or invalid.");
             return new \WP_Error('sheets_config_error', __('Google Sheets Web App URL is missing or invalid.', 'quick-checkout-popup'));
         }
         if ( empty($secret_key) ) {
              error_log("QCP Sheets JSON Error (Order #{$order_id}): Secret Key is not configured.");
              return new \WP_Error('sheets_config_error', __('Google Sheets Secret Key is not configured.', 'quick-checkout-popup'));
         }

        // --- Prepare Data Payload ---
        $product_id = '';
        $product_sku = '';
        $product_name = '';
        $item_total = 0;

        $items = $order->get_items();
        if (!empty($items)) {
             $first_item = reset($items);
             if ($first_item instanceof \WC_Order_Item_Product) {
                  $product = $first_item->get_product();
                  if ($product) {
                      $product_id = $product->get_id();
                      $product_sku = $product->get_sku() ?: 'N/A';
                      $product_name = $product->get_name();
                  }
                  $item_total = $first_item->get_total();
             }
        }

        // --- Build the Payload Array ---
        // *** CORRECTION: Utiliser 'billingCity' pour correspondre à l'en-tête existant ***
        $payload = array(
            'secretKey'         => $secret_key,
            'orderId'           => $order_id,
            'orderDate'         => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
            'customerName'      => $customer_data['billing_name'] ?? $order->get_formatted_billing_full_name(),
            'customerPhone'     => $customer_data['billing_phone'] ?? $order->get_billing_phone(),
            // *** CORRECTION ICI ***
            'billingCity'       => $customer_data['billing_city'] ?? $order->get_billing_city(), // <-- Utilisation de 'billingCity'
            'shippingAddress'   => $customer_data['shipping_address_1'] ?? $order->get_shipping_address_1(),
            // 'customerEmail'      => '', // Email toujours supprimé

            // Product Data
            'productId'         => $product_id,
            'productSku'        => $product_sku,
            'productName'       => $product_name,

            // Financial Data
            'itemTotal'         => wc_format_decimal($item_total, wc_get_price_decimals()),
            'orderTotal'        => wc_format_decimal($order->get_total(), wc_get_price_decimals()),
            'currency'          => $order->get_currency(),

            // Order Meta Data
            'paymentMethod'     => $order->get_payment_method_title(),
            'orderStatus'       => $order->get_status()
        );

        // Encode to JSON
        $payload_json = json_encode($payload);
        if ($payload_json === false) {
            $json_error = json_last_error_msg();
            error_log("QCP Sheets JSON Error (Order #{$order_id}): Failed to encode payload. Error: {$json_error}. Data: " . print_r($payload, true));
            return new \WP_Error('sheets_payload_error', __('Failed to prepare data for Google Sheets (JSON encode error).', 'quick-checkout-popup'));
        }

        // --- Send Request using wp_remote_post ---
        $args = array(
            'method'      => 'POST',
            'body'        => $payload_json,
            'headers'     => ['Content-Type' => 'application/json'],
            'timeout'     => 30,
            'redirection' => 5,
            'blocking'    => true,
            'sslverify'   => true,
            'data_format' => 'body',
        );

        error_log("QCP Sheets JSON (Order #{$order_id}): Sending POST to: " . $webhook_url);

        $response = wp_remote_post( $webhook_url, $args );

        // --- Handle the Response ---
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            error_log("QCP Sheets JSON WP_Error (Order #{$order_id}): Request failed. Error: " . $error_message);
            return new \WP_Error('sheets_http_error', __('Could not connect to Google Sheets server: ', 'quick-checkout-popup') . $error_message);
        } else {
            $status_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $body_data = json_decode($body, true);

            error_log("QCP Sheets JSON Response (Order #{$order_id}): Status: {$status_code}. Body: " . substr($body, 0, 500));

            if ( $status_code >= 200 && $status_code < 300 && isset($body_data['status']) && $body_data['status'] === 'success' ) {
                return true; // Success!
            } else {
                 $error_message = __('Unknown error logging to the sheet.', 'quick-checkout-popup');
                 if (isset($body_data['message'])) {
                     $error_message = $body_data['message'];
                 } elseif (!empty($body) && (strpos(wp_remote_retrieve_header($response, 'content-type'), 'json') === false)) {
                      $error_message = 'Server responded with HTTP ' . $status_code . '. Response: ' . substr(strip_tags($body), 0, 150) . '...';
                 } else {
                      $error_message = 'Server responded with HTTP ' . $status_code . '.';
                 }
                 error_log("QCP Sheets JSON API Error (Order #{$order_id}): " . $error_message);
                 return new \WP_Error('sheets_api_error', __('Failed to send data to Google Sheets: ', 'quick-checkout-popup') . $error_message);
            }
        }
    } // End send_order_to_sheets

} // End Class QCP_Sheets