<?php
/**
 * Plugin Name:       Social Message Notifications for WooCommerce
 * Plugin URI:        http://joydevs.com/
 * Description:       Sends whatsapp SMS notifications to your clients for order status changes. You can also receive an SMS message when a new order is received.
 * Version:           2.00
 * Author:            Abdur Rahim
 * Author URI:        https://joydevs.com/
 * License:           GPL v2 or later
 * Text Domain:       social-message-notify
 * Domain Path:       /languages/
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// here define plugin path and url
define( 'wmnw_plugin_path', plugin_dir_path( __FILE__ ) );
define( 'wmnw_plugin_url', plugin_dir_url( __FILE__ ) );
define( 'wmnw_version', '1.00' );
define( 'wmnw_prefix', 'wmnw_' );


// Check if WooCommerce is active
if ( ! function_exists( 'is_plugin_active' ) ) {
    require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
}

if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) )
{
    add_action( 'admin_notices', 'wmnw_woocommerce_missing' );
    return;
}

function wmnw_woocommerce_missing() {
    $translators_text = sprintf( '%s <a href="https://woocommerce.com/" target="_blank">%s</a> here.', __( "WooCommerce WhatsApp Message Notifications requires WooCommerce to be installed and active. You can download", "social-message-notify" ), __( "WooCommerce", "social-message-notify" ) );
    /* translators: 1. URL link. */
    echo '<div class="error"><p><strong>' . $translators_text . '</strong></p></div>';
}

// plugin init code
if ( ! class_exists( 'WcWhatsAppMessageNotify' ) ){

    class WcWhatsAppMessageNotify {

        function __construct() {
            add_action( 'plugins_loaded' , array( $this, 'plugins_loaded_text_domain' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'front_end_enqueue_script' ) );
            add_action( 'woocommerce_after_checkout_form', array( $this, 'ccs_enable_on_woocomerce' ) );
            add_action( 'woocommerce_checkout_fields', array( $this, 'change_phone_number_label' ) );
            $this->includes();
        }
        public function change_phone_number_label( $fields ){
            $fields['billing']['billing_phone']['label'] = get_option( 'wmnw_whatsapp_number_label' );
            return $fields;
        }
        public function plugins_loaded_text_domain() {
            load_plugin_textdomain( 'social-message-notify', false, wmnw_plugin_path . 'languages/' );
        }

        public function street_address_pos_change( $fields ){
            $fields['billing']['billing_country']['priority'] = 61;
            $fields['shipping']['shipping_country']['priority'] = 61;
            return $fields;
        }

        public function enqueue_script() {
            wp_enqueue_style( 'wmnw-notify-backend',wmnw_plugin_url . 'assets/css/backend.css',  '', wmnw_version );
            wp_enqueue_script( 'wmnw-notify-backend', wmnw_plugin_url . 'assets/js/backend.js', array( 'jquery' ), wmnw_version, true);
        }

        public function front_end_enqueue_script() {
            if( is_plugin_active('woocommerce/woocommerce.php') && is_checkout() ) {
                wp_enqueue_style( 'wmnw-notify-front-end',wmnw_plugin_url . 'assets/css/country-code-selector-public.css',  '', wmnw_version );
                wp_enqueue_script( 'wmnw-notify-front-end', wmnw_plugin_url . 'assets/js/country-code-selector-public.js', array( 'jquery' ), wmnw_version, false);
            }
        }

        public function ccs_enable_on_woocomerce() {
            if( is_plugin_active('woocommerce/woocommerce.php') && is_checkout() ) {
                ?>
                <script>
                    var selection = document.querySelector("#billing_phone") !== null;
                    if(selection){
                        var input = document.querySelector("#billing_phone");
                        // initialise plugin
                        var iti = window.intlTelInput(input, {
                            hiddenInput: "billing_phone",
                            separateDialCode: true,
                            formatOnDisplay: true,
                            initialCountry:"",
                            onlyCountries: [],
                            utilsScript: "<?php echo esc_url( plugin_dir_url( __FILE__ ) .'assets/js/wc-utils.js' );?>",
                        });
                        $(input).intlTelInput();
                    }
                </script>

                <script>
                    var input = document.querySelector("#billing_phone");

                    var errorMap = [
                        "<?php _e( 'Invalid number', 'social-message-notify' ); ?>",
                        "<?php _e( 'Invalid country code', 'social-message-notify' ); ?>",
                        "<?php _e( 'Too short', 'social-message-notify' ); ?>",
                        "<?php _e( 'Too long', 'social-message-notify' ); ?>"
                    ];

                    var preventAlert = true;

                    var reset = function() {
                        preventAlert = true;
                    };

                    input.addEventListener('blur', function() {
                        if (input.value.trim()) {
                            if (iti.isValidNumber()) {
                                document.getElementById("place_order").disabled = false;
                            } else {
                                var errorCode = iti.getValidationError();

                                if(preventAlert){
                                    document.getElementById("place_order").disabled = true;
                                    alert(errorMap[errorCode]);
                                    preventAlert = false;
                                }
                            }
                        }
                    });

                    // on keyup / change flag: reset
                    input.addEventListener('change', reset);
                    input.addEventListener('keyup', reset);

                    jQuery('select#billing_country').on( 'change', function (){
                        var billingCountry = jQuery('#billing_country :selected').val();
                        iti.setCountry(billingCountry.toLowerCase());
                    });

                    input.addEventListener("countrychange", function() {
                        var country_data = iti.getSelectedCountryData();
                        // alert(country_data.name+'-'+country_data.iso2+'-'+country_data.dialCode);
                        jQuery('input#billing_phone').val('');
                        jQuery('select#billing_country').val(country_data.iso2.toUpperCase()).trigger('change');
                    });
                </script>
                <?php
            }
        }

        public function includes() {

            if ( is_admin() ) {
                require_once wmnw_plugin_path . 'includes/class-wmnw-admin-menu.php';
            }

            require_once wmnw_plugin_path . 'includes/class-wmnw-sent-massages.php';
        }

    }

    new WcWhatsAppMessageNotify();
}



register_activation_hook( __FILE__, 'wmnw_activation' );

function wmnw_activation() {
    update_option( 'wmnw_whatsapp_number_label', 'WhatsApp Number' );
}