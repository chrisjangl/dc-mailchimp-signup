<?php
/*
Plugin Name: MailChimp Signup
Plugin URI:  https://github.com/chrisjangl/dc-mailchimp-signup
Description: MailChimp integration for your website. Add widget for users to signup for your newsletter; automatically send newly registered user to a custom MailChimp audience; integrates with WooCommerce & Ultimate Member plugins.
Version:     0.3.0
Author:      Digitally Cultured
Author URI:  https://digitallycultured.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

function dc_mc_get_plugin_name() {
    return "MailChimp Signup";
}

function dc_mc_get_version() {
    return "0.3.0";
}

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

// Ultimate Membership
include( 'includes/ultimate-membership-integrations.php' );

// instantiate on front end
add_action( 'plugins_loaded', 'instantiate_front_end');
function instantiate_front_end() {

    // check if we have an API key set
    $api_key = get_option('dc_mc_mailchimp_api_key');

    // if so, create instance of singleton
    $mailchimp = DC_mailchimp_singleton::get_instance();

    $mailchimp::set_api_key( $api_key );
}