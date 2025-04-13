<?php
namespace Quick_Checkout_Popup;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the admin settings page for Quick Checkout Popup using Tabs UI.
 */
class QCP_Admin {

    private static $_instance = null;
    private $settings_page_slug = 'qcp-settings';
    private $option_group = 'qcp_options_group';
    private $option_name = 'qcp_settings';

    // Define sections and their associated tab info
    // Structure: section_id => ['title' => Tab Title, 'id' => Tab Content ID]
    private $sections = [];

    private $managed_popup_fields = [
        'name'    => ['label' => 'Name', 'default_required' => true],
        'phone'   => ['label' => 'Phone', 'default_required' => true],
        'city'    => ['label' => 'City', 'default_required' => false],
        'address' => ['label' => 'Address', 'default_required' => false],
    ];

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
            self::$_instance->initialize_sections(); // Initialize sections property
            self::$_instance->hooks();
        }
        return self::$_instance;
    }

    /** Initialize the sections array */
    private function initialize_sections() {
        $this->sections = [
            'qcp_activation_section' => ['title' => __('Activation & Stats', 'quick-checkout-popup'), 'id' => 'qcp-tab-activation'], // Renamed tab title
            'qcp_fields_section'     => ['title' => __('Form Fields', 'quick-checkout-popup'), 'id' => 'qcp-tab-fields'],
            'qcp_order_section'      => ['title' => __('Order & Integrations', 'quick-checkout-popup'), 'id' => 'qcp-tab-order'],
            'qcp_thankyou_section'   => ['title' => __('Thank You', 'quick-checkout-popup'), 'id' => 'qcp-tab-thankyou'],
            'qcp_tracking_section'   => ['title' => __('Tracking', 'quick-checkout-popup'), 'id' => 'qcp-tab-tracking'],
            'qcp_stats_section'      => ['title' => __('Statistics View', 'quick-checkout-popup'), 'id' => 'qcp-tab-stats'], // Renamed tab title
        ];
    }

    private function hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Quick Checkout Settings', 'quick-checkout-popup' ),
            __( 'Quick Checkout', 'quick-checkout-popup' ),
            'manage_woocommerce',
            $this->settings_page_slug,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings, sections, and fields.
     * Section callbacks are now optional as content is rendered via fields.
     */
    public function register_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );

        // Section 1: Button & Popup Activation
        add_settings_section('qcp_activation_section', '', null, $this->settings_page_slug); // Title rendered by tab
        add_settings_field('enable_button_replace', __('Enable "Buy Now" Button', 'quick-checkout-popup'), array($this, 'render_checkbox_field'), $this->settings_page_slug, 'qcp_activation_section', ['id' => 'enable_button_replace', 'default' => true, 'desc' => __('Replaces Add to Cart with "Buy Now" for simple/variable products.', 'quick-checkout-popup')]);
        add_settings_field('button_text', __('"Buy Now" Button Text (Simple)', 'quick-checkout-popup'), array($this, 'render_text_field'), $this->settings_page_slug, 'qcp_activation_section', ['id' => 'button_text', 'default' => __('Buy Now', 'quick-checkout-popup')]);
        add_settings_field('enable_popup_checkout', __('Enable Popup Checkout', 'quick-checkout-popup'), array($this, 'render_checkbox_field'), $this->settings_page_slug, 'qcp_activation_section', ['id' => 'enable_popup_checkout', 'default' => true, 'desc' => __('Open checkout form in a popup on button click.', 'quick-checkout-popup')]);
        // *** NOUVEAU CHAMP ***
        add_settings_field('enable_stats', __('Enable Statistics Tracking', 'quick-checkout-popup'), array($this, 'render_checkbox_field'), $this->settings_page_slug, 'qcp_activation_section', ['id' => 'enable_stats', 'default' => false, 'desc' => __('Track button clicks and successful orders via popup.', 'quick-checkout-popup')]);


        // Section 2: Popup Form Fields
        add_settings_section('qcp_fields_section', '', array($this, 'render_fields_section_description'), $this->settings_page_slug); // Keep description
        add_settings_field('field_requirements', __('Field Requirements', 'quick-checkout-popup'), array( $this, 'render_field_requirements_table' ), $this->settings_page_slug, 'qcp_fields_section');

        // Section 3: Order & Integrations
        add_settings_section('qcp_order_section', '', null, $this->settings_page_slug);
        add_settings_field('default_payment_gateway', __('Default Payment Method', 'quick-checkout-popup'), array($this, 'render_payment_gateway_select_field'), $this->settings_page_slug, 'qcp_order_section', ['id' => 'default_payment_gateway', 'default' => 'cod', 'desc' => __('Select the payment method for Quick Checkout orders.', 'quick-checkout-popup')]);
        add_settings_field('enable_google_sheets', __('Enable Google Sheets Sync', 'quick-checkout-popup'), array($this, 'render_checkbox_field'), $this->settings_page_slug, 'qcp_order_section', ['id' => 'enable_google_sheets']);
        add_settings_field('google_sheets_url', __('Google Apps Script URL', 'quick-checkout-popup'), array($this, 'render_text_field'), $this->settings_page_slug, 'qcp_order_section', ['id' => 'google_sheets_url', 'type' => 'url', 'placeholder' => 'https://script.google.com/.../exec', 'desc' => __('Enter the Web App URL from Google Apps Script.', 'quick-checkout-popup')]);
        add_settings_field('google_sheets_secret', __('Google Sheets Secret Key', 'quick-checkout-popup'), array($this, 'render_text_field'), $this->settings_page_slug, 'qcp_order_section', ['id' => 'google_sheets_secret', 'type' => 'password', 'desc' => __('Enter a secret key matching your Apps Script.', 'quick-checkout-popup')]);

        // Section 4: Thank You Popup
        add_settings_section('qcp_thankyou_section', '', null, $this->settings_page_slug);
        add_settings_field('thank_you_message', __('Confirmation Message', 'quick-checkout-popup'), array($this, 'render_textarea_field'), $this->settings_page_slug, 'qcp_thankyou_section', ['id' => 'thank_you_message', 'rows' => 4, 'default' => __('Thank you for your order! We will contact you shortly.', 'quick-checkout-popup')]);

        // Section 5: Tracking & Analytics
        add_settings_section('qcp_tracking_section', '', null, $this->settings_page_slug);
        add_settings_field('ga_id', __('Google Analytics ID (GA4: G-XXXX)', 'quick-checkout-popup'), array($this, 'render_text_field'), $this->settings_page_slug, 'qcp_tracking_section', ['id' => 'ga_id']);
        add_settings_field('fb_pixel_id', __('Facebook Pixel ID', 'quick-checkout-popup'), array($this, 'render_text_field'), $this->settings_page_slug, 'qcp_tracking_section', ['id' => 'fb_pixel_id']);

        // Section 6: Statistics View
        add_settings_section('qcp_stats_section', '', array($this, 'render_stats_section_content'), $this->settings_page_slug); // Keep content callback
        // No fields needed for stats section, content rendered by callback
    }

    /**
     * Render the settings page using a tabbed interface.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'quick-checkout-popup' ) );
        }
        ?>
        <div class="wrap qcp-settings-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php settings_errors(); ?>

            <h2 class="nav-tab-wrapper qcp-nav-tab-wrapper">
                <?php
                $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : array_key_first($this->sections); // Default to first tab
                foreach ($this->sections as $section_id => $tab_info) {
                    $class = ($active_tab === $section_id) ? 'nav-tab nav-tab-active' : 'nav-tab';
                    $url = add_query_arg(['page' => $this->settings_page_slug, 'tab' => $section_id], admin_url('admin.php'));
                    printf('<a href="%s" class="%s" id="tab-link-%s">%s</a>',
                        esc_url($url),
                        esc_attr($class),
                        esc_attr($section_id),
                        esc_html($tab_info['title'])
                    );
                }
                ?>
            </h2>

            <form action="options.php" method="post" class="qcp-settings-form">
                <?php settings_fields( $this->option_group ); ?>

                <div class="qcp-tab-content-wrapper">
                <?php
                // Render content for each tab
                foreach ($this->sections as $section_id => $tab_info) {
                    $display_style = ($active_tab === $section_id) ? 'display:block;' : 'display:none;';
                    echo '<div id="' . esc_attr($tab_info['id']) . '" class="qcp-tab-content" style="' . esc_attr($display_style) . '">';

                     // Render the description if the callback exists for this section
                     global $wp_settings_sections;
                     if ( isset( $wp_settings_sections[ $this->settings_page_slug ][ $section_id ]['callback'] ) ) {
                         call_user_func( $wp_settings_sections[ $this->settings_page_slug ][ $section_id ]['callback'], $wp_settings_sections[ $this->settings_page_slug ][ $section_id ] );
                     }

                    // Render the fields for this section specifically
                    if ($section_id !== 'qcp_stats_section') {
                        echo '<table class="form-table" role="presentation">';
                        do_settings_fields( $this->settings_page_slug, $section_id );
                        echo '</table>';
                    } else {
                        // For stats, the content callback (render_stats_section_content) renders the table
                         do_settings_fields( $this->settings_page_slug, $section_id ); // Render fields if any (none currently)
                    }

                    echo '</div>'; // End .qcp-tab-content
                }
                ?>
                </div> <?php // End .qcp-tab-content-wrapper ?>

                <?php submit_button( __( 'Save Settings', 'quick-checkout-popup' ) ); ?>
            </form>
        </div> <?php // End .wrap ?>
        <?php
    }

    // --- Sanitization ---
    public function sanitize_settings( $input ) {
        $sanitized_input = [];
        $existing_options = get_option($this->option_name, []);

        // Activation & Stats Section
        $sanitized_input['enable_button_replace'] = isset( $input['enable_button_replace'] ) ? 1 : 0;
        $sanitized_input['button_text'] = isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : '';
        $sanitized_input['enable_popup_checkout'] = isset( $input['enable_popup_checkout'] ) ? 1 : 0;
        // *** SANITIZE NOUVEAU CHAMP ***
        $sanitized_input['enable_stats'] = isset( $input['enable_stats'] ) ? 1 : 0;

        // Fields Section
        $sanitized_input['field_requirements'] = [];
        $submitted_requirements = $input['field_requirements'] ?? [];
        foreach (array_keys($this->managed_popup_fields) as $field_key) {
            $sanitized_input['field_requirements'][$field_key] = ( isset($submitted_requirements[$field_key]) && $submitted_requirements[$field_key] === '1' ) ? 1 : 0;
        }

        // Order & Integrations Section
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $sanitized_input['default_payment_gateway'] = 'cod';
        if ( isset( $input['default_payment_gateway'] ) && array_key_exists( $input['default_payment_gateway'], $available_gateways ) ) {
            $sanitized_input['default_payment_gateway'] = sanitize_key( $input['default_payment_gateway'] );
        }
        $sanitized_input['enable_google_sheets'] = isset( $input['enable_google_sheets'] ) ? 1 : 0;
        $sanitized_input['google_sheets_url'] = isset( $input['google_sheets_url'] ) ? esc_url_raw( trim($input['google_sheets_url']) ) : '';
        $sanitized_input['google_sheets_secret'] = isset( $input['google_sheets_secret'] ) ? sanitize_text_field( trim($input['google_sheets_secret']) ) : '';

        // Thank You Section
        $sanitized_input['thank_you_message'] = isset( $input['thank_you_message'] ) ? wp_kses_post( trim($input['thank_you_message']) ) : '';

        // Tracking Section
        $sanitized_input['ga_id'] = isset( $input['ga_id'] ) ? sanitize_text_field( trim($input['ga_id']) ) : '';
        $sanitized_input['fb_pixel_id'] = isset( $input['fb_pixel_id'] ) ? sanitize_text_field( trim($input['fb_pixel_id']) ) : '';

        // Merge new sanitized values with existing ones
        $final_options = array_merge($existing_options, $sanitized_input);

        // Clean up potentially old/unused keys (optional but good practice)
        unset($final_options['field_order']);
        unset($final_options['required_fields']);

        return $final_options;
    }


    // --- Section Callbacks (Only those needed for descriptions/content) ---

    public function render_fields_section_description() {
        echo '<p>' . esc_html__( 'Select which fields should be required in the popup form. Email field is currently removed.', 'quick-checkout-popup' ) . '</p>';
    }

    public function render_stats_section_content() {
         echo '<p>' . __('View basic usage statistics for the Quick Checkout feature. Tracking must be enabled in the "Activation & Stats" tab.', 'quick-checkout-popup') . '</p>';
         $options = get_option('qcp_settings', []);
         if (!empty($options['enable_stats']) && class_exists('Quick_Checkout_Popup\QCP_Stats')) {
             QCP_Stats::instance()->display_stats_table(); // Call the correct method
         } elseif (empty($options['enable_stats'])) {
             echo '<p><em>' . __('Statistics tracking is currently disabled.', 'quick-checkout-popup') . '</em></p>';
         } else {
             echo '<p><em>' . __('Statistics module is not active or available.', 'quick-checkout-popup') . '</em></p>';
         }
    }

    // --- Field Rendering Callbacks ---
    // (render_text_field, render_checkbox_field, render_textarea_field,
    // render_field_requirements_table, get_translated_field_label,
    // render_payment_gateway_select_field) restent les mêmes que précédemment.
    // ... [Coller les callbacks de rendu des champs ici si nécessaire] ...

    // --- Field Rendering Callbacks ---

    public function render_text_field( $args ) {
        $options = get_option( $this->option_name, [] );
        $id = esc_attr($args['id']);
        $value = $options[$id] ?? ($args['default'] ?? '');
        $type = $args['type'] ?? 'text';
        $desc = $args['desc'] ?? '';
        $placeholder = $args['placeholder'] ?? '';
        $option_name_attr = esc_attr($this->option_name);

        printf(
            '<input type="%s" id="%s" name="%s[%s]" value="%s" placeholder="%s" class="%s" aria-describedby="%s-description"/>', // Added aria
            esc_attr($type), $id, $option_name_attr, $id,
            esc_attr($value), esc_attr($placeholder),
            esc_attr( ($type === 'url' || $type === 'text' || $type === 'password') ? 'regular-text' : '' ),
            $id // for aria-describedby
        );
         if ($desc) {
             printf('<p class="description" id="%s-description">%s</p>', $id, wp_kses_post($desc));
         }
    }

     public function render_checkbox_field( $args ) {
        $options = get_option( $this->option_name, [] );
        $id = esc_attr($args['id']);
        $default = $args['default'] ?? false;
        // IMPORTANT: Check if the key exists in options, otherwise use default. Handles first load.
        $is_checked = array_key_exists($id, $options) ? ($options[$id] == 1) : $default;
        $checked_attr = checked( $is_checked, true, false );
        $desc = $args['desc'] ?? '';
        $option_name_attr = esc_attr($this->option_name);

        printf(
            '<input type="checkbox" id="%s" name="%s[%s]" value="1" %s %s />',
            $id,
            $option_name_attr,
            $id,
            $checked_attr,
             $desc ? 'aria-describedby="' . $id . '-description"' : '' // Add aria only if desc exists
        );
        if ($desc) {
            // Separate description paragraph linked by aria-describedby
             printf('<p class="description" id="%s-description" style="display:inline-block; margin-left: 5px;">%s</p>', $id, wp_kses_post($desc));
        }
    }

     public function render_textarea_field( $args ) {
         $options = get_option( $this->option_name, [] );
         $id = esc_attr($args['id']);
         $value = $options[$id] ?? ($args['default'] ?? '');
         $rows = $args['rows'] ?? 5;
         $cols = $args['cols'] ?? 50;
         $desc = $args['desc'] ?? '';
         $placeholder = $args['placeholder'] ?? '';
         $option_name_attr = esc_attr($this->option_name);

         printf(
             '<textarea id="%s" name="%s[%s]" rows="%d" cols="%d" placeholder="%s" class="large-text code" %s>%s</textarea>',
             $id, $option_name_attr, $id,
             esc_attr($rows), esc_attr($cols), esc_attr($placeholder),
             $desc ? 'aria-describedby="' . $id . '-description"' : '',
             esc_textarea($value)
         );
          if ($desc) {
             printf('<p class="description" id="%s-description">%s</p>', $id, wp_kses_post($desc));
         }
     }

    public function render_field_requirements_table( $args ) {
        $options = get_option( $this->option_name, [] );
        $current_requirements = $options['field_requirements'] ?? [];
        $option_name_attr = esc_attr($this->option_name);

        echo '<table class="qcp-fields-table widefat fixed striped">'; // Added classes for styling
        echo '<thead><tr>';
        echo '<th scope="col" class="qcp-field-label-col">' . esc_html__( 'Form Field', 'quick-checkout-popup' ) . '</th>';
        echo '<th scope="col" class="qcp-field-required-col">' . esc_html__( 'Required?', 'quick-checkout-popup' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($this->managed_popup_fields as $key => $field_data) {
            $is_currently_required = $current_requirements[$key] ?? $field_data['default_required'];
            $checked_attr = checked( $is_currently_required, 1, false );
            $field_key_attr = esc_attr($key);
            $field_label = $this->get_translated_field_label($key, $field_data['label']);

            echo '<tr>';
            echo '<td class="qcp-field-label-col"><label for="qcp_req_' . $field_key_attr . '">' . esc_html($field_label) . '</label></td>';
            echo '<td class="qcp-field-required-col">';
            printf(
                '<input type="checkbox" id="qcp_req_%1$s" name="%2$s[field_requirements][%1$s]" value="1" %3$s />',
                $field_key_attr,
                $option_name_attr,
                $checked_attr
            );
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function get_translated_field_label($key, $default_label) {
         switch ($key) {
             case 'name': return __('Name', 'quick-checkout-popup');
             case 'phone': return __('Phone', 'quick-checkout-popup');
             case 'city': return __('City', 'quick-checkout-popup');
             case 'address': return __('Address', 'quick-checkout-popup');
             default: return $default_label;
         }
    }

    public function render_payment_gateway_select_field( $args ) {
        $options = get_option( $this->option_name, [] );
        $id = esc_attr($args['id']);
        $value = $options[$id] ?? ($args['default'] ?? 'cod');
        $desc = $args['desc'] ?? '';
        $option_name_attr = esc_attr($this->option_name);

        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

        if ( empty( $available_gateways ) ) {
            echo '<p>' . esc_html__( 'No payment gateways are currently available.', 'quick-checkout-popup' ) . '</p>';
            printf('<input type="hidden" name="%s[%s]" value="%s">', $option_name_attr, $id, esc_attr($value));
            return;
        }

        printf('<select id="%s" name="%s[%s]" %s>',
            $id, $option_name_attr, $id,
            $desc ? 'aria-describedby="' . $id . '-description"' : ''
        );

        foreach ( $available_gateways as $gateway_id => $gateway ) {
             printf( '<option value="%s" %s>%s</option>',
                 esc_attr( $gateway_id ),
                 selected( $value, $gateway_id, false ),
                 esc_html( $gateway->get_title() )
            );
        }
        echo '</select>';

        if ($desc) {
            printf('<p class="description" id="%s-description">%s</p>', $id, wp_kses_post($desc));
        }
    }
    // --- End Field Rendering Callbacks ---


     /**
      * Enqueue admin scripts and styles ONLY for the settings page.
      */
     public function enqueue_admin_assets( $hook_suffix ) {
        $settings_page_hook = 'woocommerce_page_' . $this->settings_page_slug;

        if ( $settings_page_hook === $hook_suffix ) {
            // CSS
            $css_file_path = QCP_PLUGIN_DIR . 'assets/css/admin.css';
            $css_version = QCP_VERSION . '.' . (file_exists($css_file_path) ? filemtime($css_file_path) : '0');
            wp_enqueue_style(
                'qcp-admin-style',
                QCP_PLUGIN_URL . 'assets/css/admin.css',
                array(), // No WP dependencies for basic CSS
                $css_version
            );

            // JavaScript for Tabs
            $js_file_path = QCP_PLUGIN_DIR . 'assets/js/admin.js';
            $js_version = QCP_VERSION . '.' . (file_exists($js_file_path) ? filemtime($js_file_path) : '0');
            wp_enqueue_script(
                'qcp-admin-script', // Handle
                QCP_PLUGIN_URL . 'assets/js/admin.js', // Source
                array('jquery'), // Depends on jQuery
                $js_version, // Version
                true // Load in footer
            );
        }
     }

} // End Class QCP_Admin