<?php
namespace Quick_Checkout_Popup;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QCP_Stats {

    private static $_instance = null;
    private $click_option_name = 'qcp_button_clicks';
    private $success_option_name = 'qcp_successful_orders';
    // Add options for popup opens, submission attempts if needed

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
            // *** CHANGEMENT : Appel à hooks() supprimé ***
        }
        return self::$_instance;
    }

    // *** CHANGEMENT : Méthode hooks() entièrement supprimée ***
    // La logique des hooks AJAX est gérée par QCP_Ajax

    // --- Tracking Methods (called internally or via AJAX handler in QCP_Ajax) ---

    /** Increment button click count */
    public function record_click( $product_id = 0 ) {
        $count = (int) get_option( $this->click_option_name, 0 );
        update_option( $this->click_option_name, $count + 1, 'no' ); // 'no' for autoload
        // Log click recording
        error_log("QCP Stat Recorded: Click for Product ID: {$product_id}. Total clicks: " . ($count + 1));
    }

    /** Increment successful order count */
    public function record_success( $product_id = 0 ) {
        $count = (int) get_option( $this->success_option_name, 0 );
        update_option( $this->success_option_name, $count + 1, 'no' );
        // Log success recording
        error_log("QCP Stat Recorded: Success for Product ID: {$product_id}. Total successes: " . ($count + 1));
    }

    // Add methods for tracking popup opens, failures etc. if needed

    // *** CHANGEMENT : Méthode ajax_track_stat() entièrement supprimée ***
    // La gestion de la requête AJAX est faite dans QCP_Ajax::ajax_track_stat()

    // --- Display Methods ---

    /**
     * Display stats in the admin settings page table.
     */
    public function display_stats_table() { // Nom correct pour correspondre à l'appel dans QCP_Admin
        $clicks = (int) get_option( $this->click_option_name, 0 );
        $successes = (int) get_option( $this->success_option_name, 0 );
        $conversion_rate = ( $clicks > 0 ) ? round( ( $successes / $clicks ) * 100, 2 ) : 0;

        // Display within the structure provided by QCP_Admin (no <h2> needed here)
        echo '<table class="form-table">'; // Use standard WP admin table style
        echo '<tbody>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__( '"Buy Now" Clicks Tracked', 'quick-checkout-popup' ) . '</th>';
        echo '<td>' . esc_html( number_format_i18n( $clicks ) ) . '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Successful Orders via Popup', 'quick-checkout-popup' ) . '</th>';
        echo '<td>' . esc_html( number_format_i18n( $successes ) ) . '</td>';
        echo '</tr>';

         echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Conversion Rate (Clicks to Orders)', 'quick-checkout-popup' ) . '</th>';
        echo '<td>' . esc_html( $conversion_rate ) . '%</td>';
        echo '</tr>';

        // Add rows for abandonment rate etc. if those stats are added later

        echo '</tbody>';
        echo '</table>';
         // Consider adding a reset button with AJAX functionality later
         // echo '<p><button type="button" class="button" id="qcp-reset-stats-button">' . esc_html__('Reset Statistics', 'quick-checkout-popup') . '</button></p>';
    }
}