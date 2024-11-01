<?php

/*
  Plugin Name: WooCommerce AvantGarde Safexpay
  Description: Extends WooCommerce with AvantGarde Safexpay Payment gateway. AJAX Version supported.
  Version: 1.0.1
  Requires at least: 4.0
  Tested up to: 4.9.4
  WC requires at least: 3.0.0
  WC tested up to: 3.2.6
  Author: Beta Soft Technology
  Author URI: https://www.betasofttechnology.com/
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$ag_base_dir = dirname(__FILE__);
if (!class_exists('AvantgardeLib')) {
    require_once($ag_base_dir . "/includes/avantgardelib.php");
}
if (!class_exists('Mobiledetect')) {
    require_once($ag_base_dir . "/includes/Mobiledetect.php");
}

add_action('plugins_loaded', 'woocommerce_avantgarde_init', 0);

if (!function_exists('woocommerce_avantgarde_init')) {

    function woocommerce_avantgarde_init() {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        /**
         * Localisation
         */
        load_plugin_textdomain('wc-avantgarde', false, dirname(plugin_basename(__FILE__)) . '/languages');

        if (isset($_GET['msg']) && $_GET['msg'] != '') {
            add_action('the_content', 'showMessage');
        }

        if (!function_exists('showMessage')) {

            function showMessage($content) {
                return '<div class="box ' . htmlentities($_GET['type']) . '-box">' . htmlentities(urldecode($_GET['msg'])) . '</div>' . $content;
            }

        }

        /**
         * Gateway class
         */
        class WC_Avantgarde extends WC_Payment_Gateway {

            protected $msg = array();

            public function __construct() {
                // Go wild in here
                $this->id = 'avantgarde';
                $this->method_title = __('AvantGarde', 'avantgarde');
                $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/avanthgarde.png';
                $this->has_fields = false;
                $this->init_form_fields();
                $this->init_settings();
                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];
                $this->merchant_id = $this->settings['merchant_id'];
                $this->merchant_key = $this->settings['merchant_key'];
                $this->aggregator_id = $this->settings['aggregator_id'];

                $this->domain_url = $this->settings['domain_url'];
                $this->transaction_method = $this->settings['transaction_method'];

                if (class_exists('Mobiledetect')) {
                    $mobiledetect = new Mobiledetect();
                    $this->channel = ($mobiledetect->isMobile()) ? 'MOBILE' : 'WEB';
                } else {
                    $this->channel = 'WEB';
                }
                if (isset($this->settings['redirect_page_id'])) {
                    $this->redirect_page_id = $this->settings['redirect_page_id'];
                } else {
                    $this->redirect_page_id = '';
                }

                $this->msg['message'] = "";
                $this->msg['class'] = "";
                add_action('init', array(&$this, 'check_avantgarde_response'));
                //update for woocommerce >2.0
                add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_avantgarde_response'));

                add_action('valid-avantgarde-request', array(&$this, 'SUCCESS'));
                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
                }
                add_action('woocommerce_receipt_avantgarde', array(&$this, 'receipt_page'));
                // add_action('woocommerce_thankyou_avantgarde',array(&$this, 'thankyou_page'));
            }

            function init_form_fields() {

                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'avantgarde'),
                        'type' => 'checkbox',
                        'label' => __('Enable AvantGarde Payment Module.', 'avantgarde'),
                        'default' => 'no'),
                    'title' => array(
                        'title' => __('Title:', 'avantgarde'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'avantgarde'),
                        'default' => __('AvantGarde', 'avantgarde')),
                    'description' => array(
                        'title' => __('Description:', 'avantgarde'),
                        'type' => 'textarea',
                        'description' => __('This controls the description which the user sees during checkout.', 'avantgarde'),
                        'default' => __('Pay securely by Credit or Debit card or net banking through AvantGarde Secure Servers.', 'avantgarde')),
                    'merchant_key' => array(
                        'title' => __('Merchant Encryption Key', 'avantgarde'),
                        'type' => 'text',
                        'description' => __('Merchant Encryption Key.', 'avantgarde')
                    ),
                    'merchant_id' => array(
                        'title' => __('Merchant ID', 'avantgarde'),
                        'type' => 'text',
                        'description' => __('Merchant ID.', 'avantgarde')
                    ),
                    'aggregator_id' => array(
                        'title' => __('Aggregator ID', 'avantgarde'),
                        'type' => 'text',
                        'description' => __('Aggregator ID.', 'avantgarde')
                    ),
                    'domain_url' => array(
                        'title' => __('Domain URL', 'avantgarde'),
                        'type' => 'text',
                        'description' => __('Domain URL.', 'avantgarde')
                    ),
                    'transaction_method' => array(
                        'title' => __('Transaction Type', 'avantgarde'),
                        'type' => 'select',
                        'options' => array("0" => "Select", "AUTH" => "Authorization", "SALE" => "Sale"),
                        'description' => __('Transaction Type.', 'avantgarde')
                    )
                );
            }

            /**
             * Admin Panel Options
             * - Options for bits like 'title' and availability on a country-by-country basis
             * */
            public function admin_options() {
                echo '<h3>' . __('AvantGarde Payment Gateway', 'avantgarde') . '</h3>';
                echo '<p>' . __('AvantGarde is most popular payment gateway for online shopping in India') . '</p>';
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            }

            /**
             *  There are no payment fields for AvantGarde, but we want to show the description if set.
             * */
            function payment_fields() {
                if ($this->description) {
                    echo wpautop(wptexturize($this->description));
                }
            }

            /**
             * Receipt Page
             * */
            function receipt_page($order) {
                echo '<p>' . __('Thank you for your order, please click the button below to pay with AvantGarde.', 'avantgarde') . '</p>';
                echo $this->generate_avantgarde_form($order);
            }

            /**
             * Process the payment and return the result
             * */
            function process_payment($order_id) {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg(
                            'order', $order->get_id(), add_query_arg(
                                    'key', $order->get_order_key(), ''
                                    //$order->get_checkout_payment_url() 
                                    //get_permalink(wc_get_page_id('pay'))
                            )
                    )
                );
            }

            /**
             * Check for valid AvantGarde server callback
             * */
            function check_AvantGarde_response() {

                global $woocommerce;
                ini_set("display_errors", 0);
                $post = $_POST;

                $post['txn_response'] = isset($post['txn_response']) ? $post['txn_response'] : '';
                $txn_response = AvantGardeLib::_AESDecryption($post['txn_response'], $this->merchant_key);

                $return_elements = array();
                $txn_response_arr = explode('|', $txn_response);
                $return_elements['txn_response']['ag_id'] = isset($txn_response_arr[0]) ? $txn_response_arr[0] : '';
                $return_elements['txn_response']['me_id'] = isset($txn_response_arr[1]) ? $txn_response_arr[1] : '';
                $return_elements['txn_response']['order_no'] = isset($txn_response_arr[2]) ? $txn_response_arr[2] : '';
                $return_elements['txn_response']['amount'] = isset($txn_response_arr[3]) ? $txn_response_arr[3] : '';
                $return_elements['txn_response']['country'] = isset($txn_response_arr[4]) ? $txn_response_arr[4] : '';
                $return_elements['txn_response']['currency'] = isset($txn_response_arr[5]) ? $txn_response_arr[5] : '';
                $return_elements['txn_response']['txn_date'] = isset($txn_response_arr[6]) ? $txn_response_arr[6] : '';
                $return_elements['txn_response']['txn_time'] = isset($txn_response_arr[7]) ? $txn_response_arr[7] : '';
                $return_elements['txn_response']['ag_ref'] = isset($txn_response_arr[8]) ? $txn_response_arr[8] : '';
                $return_elements['txn_response']['pg_ref'] = isset($txn_response_arr[9]) ? $txn_response_arr[9] : '';
                $return_elements['txn_response']['status'] = isset($txn_response_arr[10]) ? $txn_response_arr[10] : '';
                //$return_elements['txn_response']['txn_type'] 		= isset($txn_response_arr[11]) ? $txn_response_arr[11] : '';
                $return_elements['txn_response']['res_code'] = isset($txn_response_arr[11]) ? $txn_response_arr[11] : '';
                $return_elements['txn_response']['res_message'] = isset($txn_response_arr[12]) ? $txn_response_arr[12] : '';

                //Payment Gateway Details
                $post['pg_details'] = isset($post['pg_details']) ? $post['pg_details'] : '';
                $pg_details = AvantGardeLib::_AESDecryption($post['pg_details'], $this->merchant_key);
                $pg_details_arr = explode('|', $pg_details);
                $return_elements['pg_details']['pg_id'] = isset($pg_details_arr[0]) ? $pg_details_arr[0] : '';
                $return_elements['pg_details']['pg_name'] = isset($pg_details_arr[1]) ? $pg_details_arr[1] : '';
                $return_elements['pg_details']['paymode'] = isset($pg_details_arr[2]) ? $pg_details_arr[2] : '';
                $return_elements['pg_details']['emi_months'] = isset($pg_details_arr[3]) ? $pg_details_arr[3] : '';

                //Fraud Details
                $post['fraud_details'] = isset($post['fraud_details']) ? $post['fraud_details'] : '';
                $fraud_details = AvantGardeLib::_AESDecryption($post['fraud_details'], $this->merchant_key);
                $fraud_details_arr = explode('|', $fraud_details);
                $return_elements['fraud_details']['fraud_action'] = isset($fraud_details_arr[0]) ? $fraud_details_arr[0] : '';
                $return_elements['fraud_details']['fraud_message'] = isset($fraud_details_arr[1]) ? $fraud_details_arr[1] : '';
                $return_elements['fraud_details']['score'] = isset($fraud_details_arr[2]) ? $fraud_details_arr[2] : '';

                //Other Details
                $post['other_details'] = isset($post['other_details']) ? $post['other_details'] : '';
                $other_details = AvantGardeLib::_AESDecryption($post['other_details'], $this->merchant_key);
                $other_details_arr = explode('|', $other_details);
                $return_elements['other_details']['udf_1'] = isset($other_details_arr[0]) ? $other_details_arr[0] : '';
                $return_elements['other_details']['udf_2'] = isset($other_details_arr[1]) ? $other_details_arr[1] : '';
                $return_elements['other_details']['udf_3'] = isset($other_details_arr[2]) ? $other_details_arr[2] : '';
                $return_elements['other_details']['udf_4'] = isset($other_details_arr[3]) ? $other_details_arr[3] : '';
                $return_elements['other_details']['udf_5'] = isset($other_details_arr[4]) ? $other_details_arr[4] : '';


                $order_id = explode('_', $return_elements['txn_response']['order_no']);
                $order_id = (int) $order_id[0];    //get rid of time part
                $order = new WC_Order($order_id);
                if ($order_id != '') {
                    if (empty($return_elements['txn_response']['res_code']) || ($return_elements['txn_response']['res_code'] == 'Success')) {
                        try {

                            $txstatus = $return_elements['txn_response']['status'];
                            $txrefno = $return_elements['txn_response']['ag_ref'];
//                        $txmsg = $return_elements['txn_response']['res_message'];
//                        $peymentmode = $return_elements['pg_details']['paymode'];
//                        $amount = $return_elements['txn_response']['amount'];

                            $transauthorised = false;

                            if ($order->get_status() !== 'completed') {
                                //if(strcmp($txstatus, 'SUCCESS') == 0)
                                if ($txstatus == 'Successful' || $txstatus == 'successful') {

                                    $transauthorised = true;
                                    $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                    $this->msg['class'] = 'success';
                                    if ($order->get_status() == 'processing') {
                                        //do nothing
                                    } else {
                                        //complete the order
                                        $order->payment_complete();
                                        $order->add_order_note('AvantGarde Payment Gateway has processed the payment. Ref Number: ' . $txrefno);
                                        $order->add_order_note($this->msg['message']);
                                        $woocommerce->cart->empty_cart();
                                    }
                                } else {
                                    $this->msg['class'] = 'error';
                                    $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";

                                    //Here you need to put in the routines for a failed
                                    //transaction such as sending an email to customer
                                    //setting database status etc etc
                                }

                                if ($transauthorised == false) {
                                    $order->update_status('failed');
                                    $order->add_order_note('Failed');
                                    $order->add_order_note($this->msg['message']);
                                }
                                //removed for WooCOmmerce 2.0
                                //add_action('the_content', array(&$this, 'showMessage'));
                            }
                        } catch (Exception $e) {
                            // $errorOccurred = true;
                            //$msg = "Error " . $e->getMessage();
                            $this->msg['class'] = 'error';
                            $this->msg['message'] = $e->getMessage();
                        }
                    } else {
                        //Failure
                        $this->msg['class'] = 'error';
                        $this->msg['message'] = $return_elements['txn_response']['res_message'];
                    }
                } else {
                    //Failure
                    $this->msg['class'] = 'error';
                    $this->msg['message'] = "Attempt to forge transaction...";
                }

                //  $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                //For wooCoomerce 2.0
                // $redirect_url = add_query_arg( array('msg'=> urlencode($this -> msg['message']), 'type'=>$this -> msg['class']), $redirect_url );
                if (isset($txstatus) && ($txstatus == 'Successful' || $txstatus == 'successful')) {
                    $redirect_url = $order->get_checkout_order_received_url();
                } else {
                    $redirect_url = $order->get_cancel_order_url();
                }

                $redirect_url = add_query_arg(array('msg' => urlencode($this->msg['message']), 'type' => $this->msg['class']), $redirect_url);
                wp_redirect($redirect_url);
                exit;
            }

            /**
             * Generate AvantGarde button link
             * */
            public function generate_avantgarde_form($order_id) {

                global $woocommerce;
                $order = new WC_Order($order_id);
                $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);

                //For wooCoomerce 2.0
                $redirect_url = add_query_arg('wc-api', get_class($this), $redirect_url);
                $order_id = $order_id . '_' . date("ymds");

                //do we have a phone number?
                //get currency      
                $address = $order->get_billing_address_1();
                if ($order->get_billing_address_2() != "") {
                    $address = $address . ' ' . $order->get_billing_address_2();
                }
                $currencycode = get_woocommerce_currency();
                $merchantTxnId = $order_id;
                $orderAmount = $order->get_total();
                $action = $this->domain_url . 'agcore/payment';


                $success_url = $redirect_url;
                $failure_url = $redirect_url;

                $avantgarde_args['me_id'] = $this->merchant_id;

                $txn_details = $this->aggregator_id . '|' . $this->merchant_id . '|' . $merchantTxnId . '|' . $orderAmount . '|' . kia_convert_country_code($order->get_billing_country()) . '|' . $currencycode . '|' . $this->transaction_method . '|' . $success_url . '|' . $failure_url . '|' . $this->channel;
                $avantgarde_args['txn_details'] = AvantGardeLib::_AESEncryption($txn_details, $this->merchant_key);

                $pg_details = '|' . '|' . '|';
                $avantgarde_args['pg_details'] = AvantGardeLib::_AESEncryption($pg_details, $this->merchant_key);

                $card_details = '|' . '|' . '|' . '|';
                $avantgarde_args['card_details'] = AvantGardeLib::_AESEncryption($card_details, $this->merchant_key);

                $cust_details = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '|' . $order->get_billing_email() . '|' . $order->get_billing_phone() . '|' . '|';
                $avantgarde_args['cust_details'] = AvantGardeLib::_AESEncryption($cust_details, $this->merchant_key);

                $bill_details = $address . '|' . $order->get_billing_city() . '|' . $order->get_billing_state() . '|' . kia_convert_country_code($order->get_billing_country()) . '|' . $order->get_shipping_postcode();
                $avantgarde_args['bill_details'] = AvantGardeLib::_AESEncryption($bill_details, $this->merchant_key);

                $ship_details = $address . '|' . $order->get_shipping_city() . '|' . $order->get_shipping_state() . '|' . kia_convert_country_code($order->get_shipping_country()) . '|' . $order->get_shipping_postcode() . '|' . '|';
                $avantgarde_args['ship_details'] = AvantGardeLib::_AESEncryption($ship_details, $this->merchant_key);

                $item_details = '|' . '|';
                $avantgarde_args['item_details'] = AvantGardeLib::_AESEncryption($item_details, $this->merchant_key);

                $other_details = '|' . '|' . '|' . '|';
                $avantgarde_args['other_details'] = AvantGardeLib::_AESEncryption($other_details, $this->merchant_key);

                $avantgarde_args_array = array();
                foreach ($avantgarde_args as $key => $value) {
                    $avantgarde_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
                }
                return '<form action="' . $action . '" method="post" id="avantgarde_payment_form">
                ' . implode('', $avantgarde_args_array) . '
                <input type="submit" class="button-alt" id="submit_avantgarde_payment_form" value="' . 'Pay via AvantGarde' . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . 'Cancel order &amp; restore cart' . '</a>
                <script type="text/javascript">
        jQuery(function(){
        jQuery("body").block(
            {
                message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting\" style=\"float:left; margin-right: 10px;\" />' . 'Thank you for your order. We are now redirecting you to AvantGarde Payment Gateway to make payment.' . '",
                    overlayCSS:
            {
                background: "#fff",
                    opacity: 0.6
          },
        css: {
            padding:        20,
                textAlign:      "center",
                color:          "#555",
                border:         "3px solid #aaa",
                backgroundColor:"#fff",
                cursor:         "wait",
                lineHeight:"32px"
        }
        });
        jQuery("#submit_avantgarde_payment_form").click();

        });
      </script>
      </form>';
            }

            function get_pages($title = false, $indent = true) {
                $wp_pages = get_pages('sort_column=menu_order');
                $page_list = array();
                if ($title) {
                    $page_list[] = $title;
                }
                foreach ($wp_pages as $page) {
                    $prefix = '';
                    // show indented child pages?
                    if ($indent) {
                        $has_parent = $page->post_parent;
                        while ($has_parent) {
                            $prefix .= ' - ';
                            $next_page = get_page($has_parent);
                            $has_parent = $next_page->post_parent;
                        }
                    }
                    // add to page list array array
                    $page_list[$page->ID] = $prefix . $page->post_title;
                }
                return $page_list;
            }

        }

        /**
         * Add the Gateway to WooCommerce
         * */
        if (!function_exists('woocommerce_add_avantgarde_gateway')) {

            function woocommerce_add_avantgarde_gateway($methods) {
                $methods[] = 'WC_AvantGarde';
                return $methods;
            }

        }

        function kia_convert_country_code($country) {
            $countries = array(
                'AF' => 'AFG', //Afghanistan
                'AX' => 'ALA', //&#197;land Islands
                'AL' => 'ALB', //Albania
                'DZ' => 'DZA', //Algeria
                'AS' => 'ASM', //American Samoa
                'AD' => 'AND', //Andorra
                'AO' => 'AGO', //Angola
                'AI' => 'AIA', //Anguilla
                'AQ' => 'ATA', //Antarctica
                'AG' => 'ATG', //Antigua and Barbuda
                'AR' => 'ARG', //Argentina
                'AM' => 'ARM', //Armenia
                'AW' => 'ABW', //Aruba
                'AU' => 'AUS', //Australia
                'AT' => 'AUT', //Austria
                'AZ' => 'AZE', //Azerbaijan
                'BS' => 'BHS', //Bahamas
                'BH' => 'BHR', //Bahrain
                'BD' => 'BGD', //Bangladesh
                'BB' => 'BRB', //Barbados
                'BY' => 'BLR', //Belarus
                'BE' => 'BEL', //Belgium
                'BZ' => 'BLZ', //Belize
                'BJ' => 'BEN', //Benin
                'BM' => 'BMU', //Bermuda
                'BT' => 'BTN', //Bhutan
                'BO' => 'BOL', //Bolivia
                'BQ' => 'BES', //Bonaire, Saint Estatius and Saba
                'BA' => 'BIH', //Bosnia and Herzegovina
                'BW' => 'BWA', //Botswana
                'BV' => 'BVT', //Bouvet Islands
                'BR' => 'BRA', //Brazil
                'IO' => 'IOT', //British Indian Ocean Territory
                'BN' => 'BRN', //Brunei
                'BG' => 'BGR', //Bulgaria
                'BF' => 'BFA', //Burkina Faso
                'BI' => 'BDI', //Burundi
                'KH' => 'KHM', //Cambodia
                'CM' => 'CMR', //Cameroon
                'CA' => 'CAN', //Canada
                'CV' => 'CPV', //Cape Verde
                'KY' => 'CYM', //Cayman Islands
                'CF' => 'CAF', //Central African Republic
                'TD' => 'TCD', //Chad
                'CL' => 'CHL', //Chile
                'CN' => 'CHN', //China
                'CX' => 'CXR', //Christmas Island
                'CC' => 'CCK', //Cocos (Keeling) Islands
                'CO' => 'COL', //Colombia
                'KM' => 'COM', //Comoros
                'CG' => 'COG', //Congo
                'CD' => 'COD', //Congo, Democratic Republic of the
                'CK' => 'COK', //Cook Islands
                'CR' => 'CRI', //Costa Rica
                'CI' => 'CIV', //C�te d\'Ivoire
                'HR' => 'HRV', //Croatia
                'CU' => 'CUB', //Cuba
                'CW' => 'CUW', //Cura�ao
                'CY' => 'CYP', //Cyprus
                'CZ' => 'CZE', //Czech Republic
                'DK' => 'DNK', //Denmark
                'DJ' => 'DJI', //Djibouti
                'DM' => 'DMA', //Dominica
                'DO' => 'DOM', //Dominican Republic
                'EC' => 'ECU', //Ecuador
                'EG' => 'EGY', //Egypt
                'SV' => 'SLV', //El Salvador
                'GQ' => 'GNQ', //Equatorial Guinea
                'ER' => 'ERI', //Eritrea
                'EE' => 'EST', //Estonia
                'ET' => 'ETH', //Ethiopia
                'FK' => 'FLK', //Falkland Islands
                'FO' => 'FRO', //Faroe Islands
                'FJ' => 'FIJ', //Fiji
                'FI' => 'FIN', //Finland
                'FR' => 'FRA', //France
                'GF' => 'GUF', //French Guiana
                'PF' => 'PYF', //French Polynesia
                'TF' => 'ATF', //French Southern Territories
                'GA' => 'GAB', //Gabon
                'GM' => 'GMB', //Gambia
                'GE' => 'GEO', //Georgia
                'DE' => 'DEU', //Germany
                'GH' => 'GHA', //Ghana
                'GI' => 'GIB', //Gibraltar
                'GR' => 'GRC', //Greece
                'GL' => 'GRL', //Greenland
                'GD' => 'GRD', //Grenada
                'GP' => 'GLP', //Guadeloupe
                'GU' => 'GUM', //Guam
                'GT' => 'GTM', //Guatemala
                'GG' => 'GGY', //Guernsey
                'GN' => 'GIN', //Guinea
                'GW' => 'GNB', //Guinea-Bissau
                'GY' => 'GUY', //Guyana
                'HT' => 'HTI', //Haiti
                'HM' => 'HMD', //Heard Island and McDonald Islands
                'VA' => 'VAT', //Holy See (Vatican City State)
                'HN' => 'HND', //Honduras
                'HK' => 'HKG', //Hong Kong
                'HU' => 'HUN', //Hungary
                'IS' => 'ISL', //Iceland
                'IN' => 'IND', //India
                'ID' => 'IDN', //Indonesia
                'IR' => 'IRN', //Iran
                'IQ' => 'IRQ', //Iraq
                'IE' => 'IRL', //Republic of Ireland
                'IM' => 'IMN', //Isle of Man
                'IL' => 'ISR', //Israel
                'IT' => 'ITA', //Italy
                'JM' => 'JAM', //Jamaica
                'JP' => 'JPN', //Japan
                'JE' => 'JEY', //Jersey
                'JO' => 'JOR', //Jordan
                'KZ' => 'KAZ', //Kazakhstan
                'KE' => 'KEN', //Kenya
                'KI' => 'KIR', //Kiribati
                'KP' => 'PRK', //Korea, Democratic People\'s Republic of
                'KR' => 'KOR', //Korea, Republic of (South)
                'KW' => 'KWT', //Kuwait
                'KG' => 'KGZ', //Kyrgyzstan
                'LA' => 'LAO', //Laos
                'LV' => 'LVA', //Latvia
                'LB' => 'LBN', //Lebanon
                'LS' => 'LSO', //Lesotho
                'LR' => 'LBR', //Liberia
                'LY' => 'LBY', //Libya
                'LI' => 'LIE', //Liechtenstein
                'LT' => 'LTU', //Lithuania
                'LU' => 'LUX', //Luxembourg
                'MO' => 'MAC', //Macao S.A.R., China
                'MK' => 'MKD', //Macedonia
                'MG' => 'MDG', //Madagascar
                'MW' => 'MWI', //Malawi
                'MY' => 'MYS', //Malaysia
                'MV' => 'MDV', //Maldives
                'ML' => 'MLI', //Mali
                'MT' => 'MLT', //Malta
                'MH' => 'MHL', //Marshall Islands
                'MQ' => 'MTQ', //Martinique
                'MR' => 'MRT', //Mauritania
                'MU' => 'MUS', //Mauritius
                'YT' => 'MYT', //Mayotte
                'MX' => 'MEX', //Mexico
                'FM' => 'FSM', //Micronesia
                'MD' => 'MDA', //Moldova
                'MC' => 'MCO', //Monaco
                'MN' => 'MNG', //Mongolia
                'ME' => 'MNE', //Montenegro
                'MS' => 'MSR', //Montserrat
                'MA' => 'MAR', //Morocco
                'MZ' => 'MOZ', //Mozambique
                'MM' => 'MMR', //Myanmar
                'NA' => 'NAM', //Namibia
                'NR' => 'NRU', //Nauru
                'NP' => 'NPL', //Nepal
                'NL' => 'NLD', //Netherlands
                'AN' => 'ANT', //Netherlands Antilles
                'NC' => 'NCL', //New Caledonia
                'NZ' => 'NZL', //New Zealand
                'NI' => 'NIC', //Nicaragua
                'NE' => 'NER', //Niger
                'NG' => 'NGA', //Nigeria
                'NU' => 'NIU', //Niue
                'NF' => 'NFK', //Norfolk Island
                'MP' => 'MNP', //Northern Mariana Islands
                'NO' => 'NOR', //Norway
                'OM' => 'OMN', //Oman
                'PK' => 'PAK', //Pakistan
                'PW' => 'PLW', //Palau
                'PS' => 'PSE', //Palestinian Territory
                'PA' => 'PAN', //Panama
                'PG' => 'PNG', //Papua New Guinea
                'PY' => 'PRY', //Paraguay
                'PE' => 'PER', //Peru
                'PH' => 'PHL', //Philippines
                'PN' => 'PCN', //Pitcairn
                'PL' => 'POL', //Poland
                'PT' => 'PRT', //Portugal
                'PR' => 'PRI', //Puerto Rico
                'QA' => 'QAT', //Qatar
                'RE' => 'REU', //Reunion
                'RO' => 'ROU', //Romania
                'RU' => 'RUS', //Russia
                'RW' => 'RWA', //Rwanda
                'BL' => 'BLM', //Saint Barth&eacute;lemy
                'SH' => 'SHN', //Saint Helena
                'KN' => 'KNA', //Saint Kitts and Nevis
                'LC' => 'LCA', //Saint Lucia
                'MF' => 'MAF', //Saint Martin (French part)
                'SX' => 'SXM', //Sint Maarten / Saint Matin (Dutch part)
                'PM' => 'SPM', //Saint Pierre and Miquelon
                'VC' => 'VCT', //Saint Vincent and the Grenadines
                'WS' => 'WSM', //Samoa
                'SM' => 'SMR', //San Marino
                'ST' => 'STP', //S&atilde;o Tom&eacute; and Pr&iacute;ncipe
                'SA' => 'SAU', //Saudi Arabia
                'SN' => 'SEN', //Senegal
                'RS' => 'SRB', //Serbia
                'SC' => 'SYC', //Seychelles
                'SL' => 'SLE', //Sierra Leone
                'SG' => 'SGP', //Singapore
                'SK' => 'SVK', //Slovakia
                'SI' => 'SVN', //Slovenia
                'SB' => 'SLB', //Solomon Islands
                'SO' => 'SOM', //Somalia
                'ZA' => 'ZAF', //South Africa
                'GS' => 'SGS', //South Georgia/Sandwich Islands
                'SS' => 'SSD', //South Sudan
                'ES' => 'ESP', //Spain
                'LK' => 'LKA', //Sri Lanka
                'SD' => 'SDN', //Sudan
                'SR' => 'SUR', //Suriname
                'SJ' => 'SJM', //Svalbard and Jan Mayen
                'SZ' => 'SWZ', //Swaziland
                'SE' => 'SWE', //Sweden
                'CH' => 'CHE', //Switzerland
                'SY' => 'SYR', //Syria
                'TW' => 'TWN', //Taiwan
                'TJ' => 'TJK', //Tajikistan
                'TZ' => 'TZA', //Tanzania
                'TH' => 'THA', //Thailand    
                'TL' => 'TLS', //Timor-Leste
                'TG' => 'TGO', //Togo
                'TK' => 'TKL', //Tokelau
                'TO' => 'TON', //Tonga
                'TT' => 'TTO', //Trinidad and Tobago
                'TN' => 'TUN', //Tunisia
                'TR' => 'TUR', //Turkey
                'TM' => 'TKM', //Turkmenistan
                'TC' => 'TCA', //Turks and Caicos Islands
                'TV' => 'TUV', //Tuvalu     
                'UG' => 'UGA', //Uganda
                'UA' => 'UKR', //Ukraine
                'AE' => 'ARE', //United Arab Emirates
                'GB' => 'GBR', //United Kingdom
                'US' => 'USA', //United States
                'UM' => 'UMI', //United States Minor Outlying Islands
                'UY' => 'URY', //Uruguay
                'UZ' => 'UZB', //Uzbekistan
                'VU' => 'VUT', //Vanuatu
                'VE' => 'VEN', //Venezuela
                'VN' => 'VNM', //Vietnam
                'VG' => 'VGB', //Virgin Islands, British
                'VI' => 'VIR', //Virgin Island, U.S.
                'WF' => 'WLF', //Wallis and Futuna
                'EH' => 'ESH', //Western Sahara
                'YE' => 'YEM', //Yemen
                'ZM' => 'ZMB', //Zambia
                'ZW' => 'ZWE', //Zimbabwe
            );
            $iso_code = isset($countries[$country]) ? $countries[$country] : $country;
            return $iso_code;
        }

        add_filter('woocommerce_payment_gateways', 'woocommerce_add_avantgarde_gateway');

        /**
         * Show action links on the plugin screen.
         *
         * @param   mixed $links Plugin Action links.
         * @return  array
         */
        if (!function_exists('plugin_action_links')) {

            function plugin_action_links($links) {
                $action_links = array(
                    'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=avantgarde') . '" aria-label="' . esc_attr__('View AvantGarde settings', 'avantgarde') . '">' . esc_html__('Settings', 'avantgarde') . '</a>',
                );

                return array_merge($action_links, $links);
            }

        }
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugin_action_links');
    }

}
?>
