<?php
/*
Plugin Name: MailChimp Signup
Plugin URI:  https://digitallycultured.com
Description: Adds a widget for you to choose a MailChimp list to add subscribers to, based on their user role.
Version:     0.2
Author:      Digitally Cultured
Author URI:  https://digitallycultured.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

include( 'includes/mailchimp-singleton.php' );

/**
 * Build settings page
 */
include( 'includes/settings.php');
add_action( 'admin_init', 'dc_mc_register_settings' );

include('includes/widget.php');

// WooCommerce integrations
include( 'includes/woocommerce-integrations.php' );

// Wholesale for WooCommerce integrations
include( 'includes/wholesale-woocommerce-integrations.php' );

// instantiate on front end
add_action( 'plugins_loaded', 'instantiate_front_end');
function instantiate_front_end() {

    // check if we have an API key set
    $api_key = get_option('dc_mc_mailchimp_api_key');

    // if so, create instance of singleton
    $mailchimp = DC_mailchimp_singleton::get_instance();

    $mailchimp::set_api_key( $api_key );
    // if ( )
}