<?php
/**
 * Template for the Quick Checkout Popup Form V1 (Modern UI/UX)
 * Version: 1.0.2 - Corrected file path and forced translations.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// --- Options & Requirements ---
$options = get_option('qcp_settings', []);
$default_reqs = ['name' => true, 'phone' => true, 'city' => false, 'address' => false];
$field_requirements = isset($options['field_requirements']) && is_array($options['field_requirements']) ? array_merge($default_reqs, $options['field_requirements']) : $default_reqs;
$success_title = __('Order Placed Successfully!', 'quick-checkout-popup');
$success_message = $options['thank_you_message'] ?? __( 'Thank you! We will contact you shortly.', 'quick-checkout-popup' );
$enable_coupons = apply_filters('qcp_enable_coupons', true) && wc_coupons_enabled();

// --- User Data ---
$current_user = wp_get_current_user(); $user_data = ['name' => '', 'email' => '', 'phone' => '', 'city' => '', 'address' => '']; if ( $current_user->exists() ) { $user_id = $current_user->ID; $user_data['name'] = trim( $current_user->user_firstname . ' ' . $current_user->user_lastname ) ?: $current_user->display_name; $user_data['email'] = $current_user->user_email; $user_data['phone'] = get_user_meta( $user_id, 'billing_phone', true ); $user_data['city'] = get_user_meta( $user_id, 'billing_city', true ); $user_data['address'] = get_user_meta( $user_id, 'billing_address_1', true ); }

/** Helper for required attributes */
function qcp_get_required_markup_v3( $field_key, $requirements ) {
    $is_required = !empty($requirements[$field_key]);
    return [
        'required_attr' => $is_required ? 'required aria-required="true"' : '',
        // *** Utilise ton text domain pour le title de l'abbr 'required' ***
        'required_label' => $is_required ? ' <abbr class="required" title="' . esc_attr__('required', 'quick-checkout-popup') . '">*</abbr>' : '',
        'required_class' => $is_required ? 'validate-required' : '',
    ];
}
?>
<div id="qcp-popup-overlay" class="qcp-popup-hidden">
    <div id="qcp-popup-wrap" class="qcp-popup-hidden" role="dialog" aria-modal="true" aria-labelledby="qcp-popup-main-title">
        <button type="button" class="qcp-popup-close" aria-label="<?php esc_attr_e('Close', 'quick-checkout-popup'); ?>">×</button>
        <h2 id="qcp-popup-main-title" class="qcp-popup-main-title screen-reader-text"><?php esc_html_e('Quick Checkout', 'quick-checkout-popup'); ?></h2>

        <?php // Processing Overlay ?>
        <div id="qcp-processing-overlay" class="qcp-processing-overlay" style="display: none;">
            <div class="qcp-processing-spinner-wrap">
                <div class="qcp-spinner qcp-processing-spinner"></div>
                <span class="qcp-processing-text"><?php esc_html_e('Processing your order...', 'quick-checkout-popup'); ?></span>
            </div>
        </div>

        <?php // Loading State ?>
        <div id="qcp-loading-view" class="qcp-popup-view qcp-loading-view" style="display: none;">
            <div class="qcp-loading-spinner-wrap"><div class="qcp-spinner qcp-loading-spinner"></div></div>
            <p><?php esc_html_e('Loading...', 'quick-checkout-popup'); ?></p>
        </div>

        <?php // Variation Selection View ?>
        <div id="qcp-variation-view" class="qcp-popup-view qcp-variation-view" style="display: none;">
            <div class="qcp-popup-content">
                 <h3 class="qcp-view-title"><?php esc_html_e('Select Options', 'quick-checkout-popup'); ?></h3>
                 <div class="qcp-section qcp-variation-product-section">
                    <div class="qcp-product-details">
                        <div class="qcp-product-image-wrap"><img src="<?php echo esc_url(wc_placeholder_img_src('woocommerce_single')); ?>" alt="" id="qcp-variation-product-image" class="qcp-product-image" /></div>
                        <div class="qcp-product-info"><h4 id="qcp-variation-product-name" class="qcp-product-name"></h4><div id="qcp-variation-product-price" class="qcp-product-price price"></div><p id="qcp-variation-stock-status" class="qcp-stock-status stock"></p></div>
                    </div>
                 </div>
                 <div id="qcp-variation-options" class="qcp-variation-options variations"><p class="qcp-variation-loading-text"><?php esc_html_e('Loading options...', 'quick-checkout-popup'); ?></p></div>
                 <div id="qcp-variation-messages" class="qcp-form-messages" style="display:none;"></div>
                 <button type="button" class="button alt qcp-button qcp-select-variation-button" id="qcp-select-variation-button" disabled><?php esc_html_e('Select Variation', 'quick-checkout-popup'); ?></button>
            </div>
        </div>

        <?php // Checkout Form View ?>
        <div id="qcp-checkout-view" class="qcp-popup-view qcp-checkout-view" style="display: none;">
            <div class="qcp-popup-content">
                <form id="qcp-checkout-form" class="qcp-checkout woocommerce-checkout" method="post" novalidate>
                    <h3 class="qcp-view-title"><?php esc_html_e('Your Order', 'quick-checkout-popup'); // Utilise ton domaine ?></h3>
                    <div class="qcp-section qcp-product-section">
                        <div class="qcp-product-details">
                            <div class="qcp-product-image-wrap"><img src="<?php echo esc_url(wc_placeholder_img_src('woocommerce_single')); ?>" alt="" id="qcp-product-image" class="qcp-product-image"/></div>
                            <div class="qcp-product-info">
                                <h4 id="qcp-product-name" class="qcp-product-name"></h4><div id="qcp-product-price" class="qcp-product-price price" data-base-price=""></div>
                                <div class="qcp-quantity-wrap quantity">
                                    <label for="qcp-quantity" class="screen-reader-text"><?php esc_html_e('Quantity', 'quick-checkout-popup'); // Utilise ton domaine ?></label>
                                    <button type="button" class="qcp-qty-btn qcp-qty-minus" aria-label="<?php esc_attr_e('Decrease quantity', 'quick-checkout-popup'); // Utilise ton domaine ?>">-</button>
                                    <input type="number" id="qcp-quantity" class="input-text qty text qcp-qty-input" name="quantity" value="1" min="1" step="1" inputmode="numeric" autocomplete="off" aria-label="<?php esc_attr_e('Product quantity', 'quick-checkout-popup'); // Utilise ton domaine ?>">
                                    <button type="button" class="qcp-qty-btn qcp-qty-plus" aria-label="<?php esc_attr_e('Increase quantity', 'quick-checkout-popup'); // Utilise ton domaine ?>">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php wp_nonce_field( 'qcp_checkout_nonce', 'qcp_nonce_field' ); ?> <input type="hidden" id="qcp-product-id-input" name="product_id" value=""> <input type="hidden" id="qcp-variation-id-input" name="variation_id" value=""> <input type="hidden" id="qcp-applied-coupon-code" name="applied_coupon_code" value="">
                    <div class="qcp-section qcp-billing-section">
                        <h4 class="qcp-section-title"><?php esc_html_e('Shipping Details', 'quick-checkout-popup'); // Utilise ton domaine ?></h4>
                        <div class="qcp-form-fields woocommerce-billing-fields">
                            <?php
                                $fields_order = ['name', 'phone', 'city', 'address'];
                                $labels = [
                                    'name' => __('Full Name', 'quick-checkout-popup'),
                                    'phone' => __('Phone Number', 'woocommerce'), // Garde WC ici car souvent bien traduit
                                    // *** CHANGEMENT ICI : Utilise ton domaine pour forcer ta traduction ***
                                    'city' => __('Town / City', 'quick-checkout-popup'),
                                    'address' => __('Street Address', 'quick-checkout-popup'),
                                ];
                                $placeholders = [
                                     // *** CHANGEMENT ICI : Utilise ton domaine pour forcer ta traduction ***
                                    'address' => __('House number and street name', 'quick-checkout-popup'),
                                ];
                                $autocompletes = [
                                    'name' => 'name',
                                    'phone' => 'tel',
                                    'city' => 'address-level2',
                                    'address' => 'street-address'
                                ];
                            ?>
                            <?php foreach ($fields_order as $key) :
                                $req = qcp_get_required_markup_v3($key, $field_requirements);
                                $field_id = 'qcp_billing_' . $key;
                                $field_name = ($key === 'address') ? 'shipping_address_1' : 'billing_' . $key;
                                $field_type = ($key === 'phone') ? 'tel' : (($key === 'address') ? 'textarea' : 'text');
                                $autocomplete = $autocompletes[$key] ?? '';
                                $placeholder = $placeholders[$key] ?? '';
                            ?>
                                <p class="form-row form-row-wide <?php echo esc_attr($req['required_class']); ?>" id="<?php echo esc_attr($field_id); ?>_field" data-priority="">
                                    <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($labels[$key]); echo $req['required_label']; ?></label>
                                    <span class="woocommerce-input-wrapper">
                                    <?php if ($field_type === 'textarea'): ?>
                                        <textarea class="input-text" name="<?php echo esc_attr($field_name); ?>" id="<?php echo esc_attr($field_id); ?>" rows="3" placeholder="<?php echo esc_attr($placeholder); ?>" autocomplete="<?php echo esc_attr($autocomplete); ?>" <?php echo $req['required_attr']; ?>><?php echo esc_textarea($user_data[$key]); ?></textarea>
                                    <?php else: ?>
                                        <input type="<?php echo esc_attr($field_type); ?>" class="input-text" name="<?php echo esc_attr($field_name); ?>" id="<?php echo esc_attr($field_id); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" autocomplete="<?php echo esc_attr($autocomplete); ?>" value="<?php echo esc_attr($user_data[$key]); ?>" <?php echo $req['required_attr']; ?>>
                                    <?php endif; ?>
                                    </span>
                                    <span class="qcp-field-error-message" role="alert" aria-live="polite"></span>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                     <?php if ($enable_coupons) : ?>
                     <div class="qcp-section qcp-coupon-section">
                         <h4 class="qcp-section-title qcp-section-title-coupon"><?php esc_html_e('Apply Coupon', 'quick-checkout-popup'); // Utilise ton domaine ?></h4>
                         <div class="qcp-coupon-form">
                             <label for="qcp-coupon-code" class="screen-reader-text"><?php esc_html_e('Coupon code', 'quick-checkout-popup'); // Utilise ton domaine ?></label>
                             <input type="text" name="coupon_code" class="input-text qcp-coupon-input" placeholder="<?php esc_attr_e('Coupon code', 'quick-checkout-popup'); // Utilise ton domaine ?>" id="qcp-coupon-code" value="" />
                             <button type="button" class="button qcp-button qcp-apply-coupon-button" id="qcp-apply-coupon-button" name="apply_coupon"><span class="qcp-button-text"><?php esc_html_e('Apply', 'quick-checkout-popup'); ?></span><span class="qcp-spinner qcp-button-spinner" style="display: none;" aria-hidden="true"></span></button>
                             <button type="button" class="button qcp-button-alt qcp-remove-coupon-button" id="qcp-remove-coupon-button" name="remove_coupon" style="display:none;"><span class="qcp-button-text"><?php esc_html_e('Remove', 'quick-checkout-popup'); ?></span><span class="qcp-spinner qcp-button-spinner" style="display: none;" aria-hidden="true"></span></button>
                         </div>
                         <div id="qcp-coupon-messages" class="qcp-form-messages" style="display: none;"></div>
                     </div>
                     <?php endif; ?>
                     <div class="qcp-section qcp-info-section"><div class="qcp-info-box"><div class="qcp-info-item"><span class="qcp-info-icon"></span><span class="qcp-info-text"><?php esc_html_e('Free Shipping', 'quick-checkout-popup'); // Utilise ton domaine ?></span></div><div class="qcp-info-item"><span class="qcp-info-icon"></span><span class="qcp-info-text"><?php esc_html_e('Cash on Delivery', 'quick-checkout-popup'); // Utilise ton domaine ?></span></div></div></div>
                     <div id="qcp-form-errors" class="qcp-form-messages qcp-global-errors" role="alert" style="display: none;"></div>
                     <div class="qcp-submit-section">
                        <?php // *** CHANGEMENT ICI POUR LE BOUTON : Utilise ton domaine *** ?>
                        <button type="submit" class="button alt qcp-button qcp-submit-button" id="qcp-submit-button" name="qcp_submit" value="<?php esc_attr_e( 'Place Order', 'quick-checkout-popup' ); ?>">
                            <span class="qcp-button-text"><?php esc_html_e( 'Place Order', 'quick-checkout-popup' ); ?></span>
                            <span class="qcp-spinner qcp-button-spinner" style="display: none;" aria-hidden="true"></span>
                        </button>
                     </div>
                </form>
            </div>
        </div>

        <?php // Success View ?>
        <div id="qcp-success-view" class="qcp-popup-view qcp-success-view" style="display: none;">
             <div class="qcp-popup-content qcp-success-content">
                 <div class="qcp-success-icon-wrap"><svg class="qcp-success-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"><circle class="qcp-success-icon--circle" cx="26" cy="26" r="25" fill="none"/><path class="qcp-success-icon--check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/></svg></div>
                 <h3 class="qcp-success-title"><?php echo esc_html($success_title); ?></h3><div id="qcp-success-message" class="qcp-success-text"><?php echo wp_kses_post($success_message); ?></div>
                 <button type="button" class="button qcp-button qcp-popup-close qcp-success-close-button"><?php esc_html_e('Continue Shopping', 'quick-checkout-popup'); // Utilise ton domaine ?></button>
             </div>
        </div>

    </div> <?php // End #qcp-popup-wrap ?>
</div> <?php // End #qcp-popup-overlay ?>