<?php

class wmnwSentMassages{

    function __construct()
    {
        add_action( 'woocommerce_order_status_changed', array( $this, 'process_status' ), 10, 3 );
        add_action( 'woocommerce_new_order', array( $this, 'new_order' ), 20 );
    }

    public function process_status( $order_id, $old_status, $status ){

        $order = new \WC_Order( $order_id );
        $shipping_phone = false;
        $phone = $order->get_billing_phone();

        //Remove old 'wc-' prefix from the order status
        $status = str_replace( 'wc-', '', $status );

        $template = "";

        switch ( $status ) {
            case 'on-hold':
                if ( get_option( wmnw_prefix . 'check_msg_on_hold' ) ) {
                    $massage =  get_option( wmnw_prefix . 'msg_on_hold' );
                    $template = $this->process_variables( $massage, $order );
                }
                break;

            case 'processing':
                if ( get_option( wmnw_prefix . 'check_msg_processing' ) ) {
                    $massage =  get_option( wmnw_prefix . 'msg_processing' );
                    $template = $this->process_variables( $massage, $order );
                }
                break;

            case 'completed':
                if ( get_option( wmnw_prefix . 'check_msg_completed' ) ) {
                    $massage =  get_option( wmnw_prefix . 'msg_completed' );
                    $template = $this->process_variables( $massage, $order );
                }
                break;

            case 'cancelled':
                if ( get_option( wmnw_prefix . 'check_msg_cancelled' ) ) {
                    $massage =  get_option( wmnw_prefix . 'msg_cancelled' );
                    $template = $this->process_variables( $massage, $order );
                }
                break;

            case 'refunded':
                if ( get_option(wmnw_prefix . 'check_msg_refunded' ) ) {
                    $massage =  get_option( wmnw_prefix . 'msg_refunded' );
                    $template = $this->process_variables( $massage, $order );
                }
                break;

            case 'failed':
                if ( get_option( wmnw_prefix . 'check_msg_failure') ) {
                    $massage =  get_option( wmnw_prefix . 'msg_failure' );
                    $template = $this->process_variables( $massage, $order );
                }
                break;

            default:
                if ( get_option( wmnw_prefix . 'check_msg_custom') ) {
                    $massage =  get_option( wmnw_prefix . 'msg_custom' );
                    $template = $this->process_variables( $massage, $order );
                }
        }

        $phone_process = $this->process_phone( $order, $phone );

        if ( ! empty( $template ) ) {
            $this->whatsapp_massage_sent( $phone_process, $template );
        }

    }

    public function new_order( $order_id ) {

        $order = new \WC_Order( $order_id );
        $phone = $order->get_billing_phone();

        //customer new order massage
        if ( get_option( wmnw_prefix . 'msg_new_order' ) ) {

            $massage =  get_option( wmnw_prefix . 'msg_new_order' );
            $process_massage = $this->process_variables( $massage, $order );
            $phone_process = $this->process_phone( $order, $phone );
            $this->whatsapp_massage_sent( $phone_process, $process_massage );

        }

        //admin new order massage
        if ( get_option( wmnw_prefix . 'check_admin_msg_new_order' ) ) {

            $massage =  get_option( wmnw_prefix . 'admin_msg_new_order' );
            $process_massage = $this->process_variables( $massage, $order );
            $phone = get_option( wmnw_prefix . 'admin_phone' );
            $this->whatsapp_massage_sent( $phone, $process_massage );

        }

    }

    public function whatsapp_massage_sent( $phone, $massage ) {
        // get access token
        $token = get_option( wmnw_prefix . "api" ) ? get_option( wmnw_prefix . "api" ) : '';

        //get instanceId
        $instanceId = get_option( wmnw_prefix . "instance" ) ? get_option( wmnw_prefix . "instance" ) : '';

        $data = array(
            'instance_id' => $instanceId, // Your instance id
            'massage'     => $massage, // Message
            'number'      => $phone, // Receivers phone
            'token_key'   => $token, // Token key
        );

        $query = http_build_query($data);
        // request url
        $url = 'https://chatappbot.com/api/massage?' . $query;
        wp_remote_get($url);
        return ;
    }

    public function process_variables( $message, $order = null, $additional_data = [] ) {

        //template customize variables here
        $sms_strings = array( 'id', 'status', 'prices_include_tax', 'tax_display_cart', 'display_totals_ex_tax', 'display_cart_ex_tax', 'order_date', 'modified_date', 'customer_message', 'customer_note', 'post_status', 'shop_name', 'note', 'order_product' );
        $wc_whatsapp_notify_variables = array( 'order_key', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_country', 'billing_state', 'billing_email', 'billing_phone', 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_postcode', 'shipping_country', 'shipping_state', 'shipping_method', 'shipping_method_title', 'payment_method', 'payment_method_title', 'order_discount', 'cart_discount', 'order_tax', 'order_shipping', 'order_shipping_tax', 'order_total', 'order_currency' );
        $specials = array( 'order_date', 'modified_date', 'shop_name', 'id', 'order_product', 'signature' );

        $order_variables = $order ? get_post_custom( $order->get_id() ) : []; //WooCommerce 2.1
        $custom_variables = explode( "\n", str_replace( array( "\r\n", "\r" ),  "\n", $this->wc_whatsapp_notify_field( 'variables' ) ) );

        //template customize additional variables
        $additional_variables = array_keys( $additional_data );

        if ( empty( $order ) ) {
            $order = new WC_Order();
        }

        //find variable form string
        preg_match_all("/%(.*?)%/", $message,  $search );

        //This will bring out a variable through the loop and extract the data from woocommerce
        foreach ( $search[1] as $variable ) {
            $variable = strtolower( $variable );

            if ( ! in_array( $variable, $sms_strings ) && ! in_array( $variable, $wc_whatsapp_notify_variables ) && ! in_array( $variable, $specials ) && ! in_array( $variable, $custom_variables ) && ! in_array( $variable, $additional_variables ) ) {
                continue;
            }

            if ( ! in_array( $variable, $specials ) ) {

                if ( in_array( $variable, $sms_strings ) ) {
                    $message = str_replace( "%" . $variable . "%", $order->$variable, $message ); //Standard fields
                }
                elseif ( in_array( $variable, $wc_whatsapp_notify_variables ) ) {
                    $message = str_replace("%" . $variable . "%", $order_variables["_" . $variable][0], $message ); //Meta fields
                }
                elseif ( in_array( $variable, $custom_variables ) && isset( $order_variables[ $variable ] ) ) {
                    $message = str_replace( "%" . $variable . "%", $order_variables[ $variable][0] , $message );
                }
                elseif ( in_array( $variable, $additional_variables ) && isset( $additional_data[$variable] ) ) {
                    $message = str_replace("%" . $variable . "%", $additional_data[$variable], $message );
                }

            }
            elseif ( $variable === "order_date" || $variable === "modified_date" ) {
                $message = str_replace("%" . $variable . "%", date_i18n( woocommerce_date_format(), strtotime( $order->$variable ) ), $message );
            }
            elseif ( $variable === "shop_name" ) {
                $message = str_replace("%" . $variable . "%", get_bloginfo(' name' ), $message );
            }
            elseif ( $variable === "id" ) {
                $message = str_replace("%" . $variable . "%", $order->get_order_number(), $message );
            }
            elseif ( $variable === "order_product" ) {

                $products = $order->get_items();
                $quantity = $products[ key( $products ) ]['name'];

                if ( strlen( $quantity ) > 10 ) {
                    $quantity = substr( $quantity,  0, 10 ) . "...";
                }

                if ( count( $products ) > 1 ) {
                    $quantity .= " (+" . ( count( $products ) - 1 ) . ")";
                }

                $message = str_replace("%" . $variable . "%", $quantity, $message);
            }

            elseif ( $variable === "signature" ) {
                $message = str_replace("%" . $variable . "%", $this->wc_whatsapp_notify_field( 'signature' ), $message );
            }

        }
        return $message;
    }

    public function wc_whatsapp_notify_field( $var ) {
        global $wc_whatsapp_notify_settings;

        if ( $wc_whatsapp_notify_settings[ $var ] ) {
            return $wc_whatsapp_notify_settings[ $var ];
        }else{
            return ;
        }

    }

    public function process_phone( $order, $phone, $shipping = false, $owners_phone = false ) {
        //Sanitize phone number
        $phone = str_replace( array('+', '-' ), '', filter_var( $phone, FILTER_SANITIZE_NUMBER_INT ) );
        return $phone;
    }
     
}

new wmnwSentMassages();