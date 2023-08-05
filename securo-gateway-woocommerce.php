<?php
class cwoa_SECURO_GATEWAY extends WC_Payment_Gateway {
  function __construct() {
    // global ID
    $this->id = "cwoa_securo_gateway";
    // Show Title
    $this->method_title = __( "Securo Gateway", 'cwoa-securo-gateway' );
    // Show Description
    $this->method_description = __( "Securo Gateway Payment Gateway Plug-in for WooCommerce", 'cwoa-securo-gateway' );
    // vertical tab title
    $this->title = __( "Securo Gateway", 'cwoa-securo-gateway' );
    $this->icon = null;
    $this->has_fields = false;
    // support default form with credit card
    //$this->supports = array( 'default_credit_card_form' );
    // setting defines
    $this->init_form_fields();
    // load time variable setting
    $this->init_settings();
    
    // Turn these settings into variables we can use
    foreach ( $this->settings as $setting_key => $value ) {
      $this->$setting_key = $value;
    }
    
    // further check of SSL if you want
    add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );
    
    // Save settings
    if ( is_admin() ) {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }    
  } // Here is the  End __construct()
  // administration fields for specific Gateway
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title'    => __( 'Enable / Disable', 'cwoa-securo-gateway' ),
        'label'    => __( 'Enable this payment gateway', 'cwoa-securo-gateway' ),
        'type'    => 'checkbox',
        'default'  => 'no',
      ),
      'merchant_id' => array(
        'title'    => __( 'Merchant ID', 'cwoa-securo-gateway' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Merchant ID to identify the merchant', 'cwoa-securo-gateway' ),
      ),
      'secret_key' => array(
        'title'    => __( 'Secret Key', 'cwoa-securo-gateway' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Secret key of the merchant.', 'cwoa-securo-gateway' ),
      ),
      'api_username' => array(
        'title'    => __( 'API Username', 'cwoa-securo-gateway' ),
        'type'    => 'text',
        'desc_tip'  => __( 'API Username of the merchant.', 'cwoa-securo-gateway' ),
      ),
      'api_password' => array(
        'title'    => __( 'API Password', 'cwoa-securo-gateway' ),
        'type'    => 'password',
        'desc_tip'  => __( 'API Password of the merchant', 'cwoa-securo-gateway' ),
      ),
      'store_id' => array(
        'title'    => __( 'Store ID', 'cwoa-securo-gateway' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Store ID of the merchant', 'cwoa-securo-gateway' ),
      ),
      'terminal_key' => array(
        'title'    => __( 'Terminal Key', 'cwoa-securo-gateway' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Terminal Key of the merchant', 'cwoa-securo-gateway' ),
      ),
      'environment' => array(
        'title'    => __( 'Securo Gateway Test Mode', 'cwoa-securo-gateway' ),
        'label'    => __( 'Enable Test Mode', 'cwoa-securo-gateway' ),
        'type'    => 'checkbox',
        'description' => __( 'This is the test mode of gateway.', 'cwoa-securo-gateway' ),
        'default'  => 'no',
      )
    );    
  }
  
  // Response handled for payment gateway
  public function process_payment( $order_id ) {
    global $woocommerce;
    $customer_order = new WC_Order( $order_id );
    
    // checking for transiction
    $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';
    // Decide which URL to post to
    $environment_url = ( "FALSE" == $environment ) 
                               ? 'https://api.securogate.com/api/v1/gateway/transaction/init'
               : 'https://api.securogate.com/api/v1/gateway/transaction/init';
    
    $token = $this->getAuthToken();
    
    $itemAmount = (int)$customer_order->order_total;

    $signature = strtoupper($this->merchant_id).';'
                .$customer_order->get_order_number().';;;'
                .($itemAmount*100).';'
                .'USD'.';'
                .$this->terminal_key.';'
                .strtoupper($customer_order->billing_email).';'
                .strtoupper($customer_order->billing_first_name).';'
                .strtoupper($customer_order->billing_last_name).';'
                .$this->secret_key;
    //SHA512(<merchant id>;<order id>;<amount>;<currency>;<key>;<email>;<fname>;<lname>;<secret>)
    
    $signature = hash("sha512", $signature);
    
    $payload = array (
      'StoreId' => $this->store_id,
      'TransactionDetails' => array (
        'OrderId' => $customer_order->get_order_number(),
        'ServiceName' => 'Test service name 1',
        'OriginalCurrency' => 'USD',
        'OriginalAmount' => $itemAmount*100,
        'TerminalKey' => $this->terminal_key,
      ),
      'PayerDetails' => array (
        'FirstName' => $customer_order->billing_first_name,
        'LastName' => $customer_order->billing_last_name,
        'Email' => $customer_order->billing_email,
        'Phone' => $customer_order->billing_phone,
      ),
      'PayerDevice' => array (
        'Ip' => $_SERVER['REMOTE_ADDR'],
      ),
      'BillingAddress' => array (
        'Street' => $customer_order->billing_address_1,
        'City' => $customer_order->billing_city,
        'Zip' => $customer_order->billing_postcode,
        'State' => $customer_order->billing_state,
        'Country' => $customer_order->billing_country,
      ),
      /*'CardDetails' => array (
        'Number' => $cardNo,
        'CVV' => ( isset( $_POST['cwoa_securo_gateway-card-cvc'] ) ) ? $_POST['cwoa_securo_gateway-card-cvc'] : '',
        'ExpirationMonth' => str_replace( ' ', '', $cardExpiry[0] ),
        'ExpirationYear' => str_replace( ' ', '', $cardExpiry[1] ),
        'NameOnCard' => $customer_order->billing_first_name,
      ),*/
      'Signature' => strtoupper($signature),
    );
    
    // Send this payload to Securo Gateway for processing
    $response = wp_remote_post( $environment_url, array(
      'method'    => 'POST',
      'headers'   => [ 
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
      ],
      'body'      => json_encode( $payload ),
      'timeout'   => 90,
      'sslverify' => false,
    ) );
    
    if ( is_wp_error( $response ) ) 
      throw new Exception( __( 'There is issue for connection payment gateway. Sorry for the inconvenience.', 'cwoa-securo-gateway' ) );
    if ( empty( $response['body'] ) )
      throw new Exception( __( 'Securo Gateway Response was not get any data.', 'cwoa-securo-gateway' ) );
      
    // get body response while get not error
    $response_body = json_decode(wp_remote_retrieve_body( $response ));
    
    if($response_body->errorMessage == "PENDING") {
        /*// Payment successful
        $customer_order->add_order_note( __( 'Securo complete payment.', 'cwoa-securo-gateway' ) );
                             
        // paid order marked
        $customer_order->payment_complete();
        // this is important part for empty cart
        $woocommerce->cart->empty_cart();*/
        // Redirect to thank you page
        
        $return_url = add_query_arg(
            array(
                "returnUrl" => $this->get_return_url( $customer_order ),
                "notificationUrl" => $this->get_return_url( $customer_order ),
            ),
            $response_body->payeerRedirectUrl
        );
        
        return array(
            'result'   => 'success',
            "redirect" => $return_url
            //'redirect' => $this->get_return_url( $customer_order ),
        );

        $response_body->payeerRedirectUrl;
    } else {
        //transaction fail
        wc_add_notice( $response_body->errorMessage, 'error' );
        $customer_order->add_order_note( 'Error: '. $response_body->errorMessage );
    }
  }
  
  // Validate fields
  public function validate_fields() {
    return false;
  }
  public function do_ssl_check() {
    if( $this->enabled == "yes" ) {
      if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
        echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";  
      }
    }    
  }
  
  public function getAuthToken() {
    $authorization_url = ( "FALSE" == $environment ) 
                               ? 'https://api.securogate.com/api/v1/gateway/authorization'
               : 'https://api.securogate.com/api/v1/gateway/authorization';
               
    $authorize_payload = array(
        "MerchantLongId" => $this->merchant_id,
        "ApiUsername" => $this->api_username,
        "ApiPassword" => $this->api_password
    );
    
    // Send this payload to Securo Gateway for processing
    $authorizationResponse = wp_remote_post( $authorization_url, array(
        'method'    => 'POST',
        'headers'   => [ 'Content-Type' => 'application/json' ],
        'body'      => json_encode( $authorize_payload ),
        'timeout'   => 90,
        'sslverify' => false,
    ));
    
    if ( is_wp_error( $authorizationResponse ) ) 
      throw new Exception( __( 'There is issue for connection payment gateway. Sorry for the inconvenience.', 'cwoa-securo-gateway' ) );
    if ( empty( $authorizationResponse['body'] ) )
      throw new Exception( __( 'Securo Gateway Response was not get any data.', 'cwoa-securo-gateway' ) );
      
    // get body response while get not error
    $response_body = wp_remote_retrieve_body( $authorizationResponse );
    $response_body = json_decode($response_body);
    
    
    if(!empty($response_body->ErrorMessage))
        throw new Exception(__( $response_body->ErrorMessage, 'cwoa-securo-gateway' ));
    
    return $response_body->token;
  }
}