<?php
/*
 * Plugin Name: Securo Payment Gateway
 * Plugin URI: https://github.com/kamrankb/securo-gateway.git
 * Description: Payment through Securo Payment Gateway
 * Author: Kamran KB
 * Author URI: https://stackoverflow.com/users/10463498/kamran-allana
 * Version: 1.0.1
 */
 
add_action( 'plugins_loaded', 'securo_gateway_init', 0 );
function securo_gateway_init() {
    //if condition use to do nothin while WooCommerce is not installed
  if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
  include_once( 'securo-gateway-woocommerce.php' );
  // class add it too WooCommerce
  add_filter( 'woocommerce_payment_gateways', 'cwoa_add_securo_gateway' );
  function cwoa_add_securo_gateway( $methods ) {
    $methods[] = 'cwoa_SECURO_GATEWAY';
    return $methods;
  }
}
// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cwoa_securo_gateway_action_links' );
function cwoa_securo_gateway_action_links( $links ) {
  $plugin_links = array(
    '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'cwoa-securo-gateway' ) . '</a>',
  );
  return array_merge( $plugin_links, $links );
}


// Add a custom webhook handler function
add_action('woocommerce_api_securo_payment_notify', 'securo_payment_notify');

function securo_payment_notify($data) {
    // Get the order ID from the webhook data
    $order_id = $data['resource_id'];
    
    // Get the order object
    $order = wc_get_order($order_id);
    
	if(!empty($data['status']) && ($data['status'] == 'completed')) {
		// Update payment status
		$order->update_status('completed', __('Payment received via Securo', 'woocommerce'));

		// Add a note to the order
		$order->add_order_note(__('Webhook: Payment status updated', 'woocommerce'));
	} else {
		// Update payment status
		$order->update_status('failed', __('Payment failed via Securo', 'woocommerce'));

		// Add a note to the order
		$order->add_order_note(__('Webhook: Payment status updated', 'woocommerce'));
	}
    
}