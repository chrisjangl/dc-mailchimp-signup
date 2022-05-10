<?php
/**
 * Functions to integrate with Wholesale for WooCommerce
 * 
 * TODO: remove all static references to trade/retail lists
 * 
 * ## Next Steps
 *
 * @author Chris Jangl <plugins@digitallycultured.com>
 * @since 0.2
 */

 function dc_subscribe_upon_trade_member_request( $user_id, $from_our_override=false ) {

    // if there's no second parameter, our override has been overridden...
    if ( !$from_our_override ) {

        // so let's log that...
        error_log( 'DC MailChimp Signup: looks like the Trade Member registration override is no longer working.' );

        // and that's all. No need to check the user meta, because we probably don't have it
        // we're not using our override

    } else {
        
        // check user meta if there's a value for our newsletter intent
        $wants_to_sign_up = get_user_meta( $user_id, 'dc_mc_sign_me_up', true );
    
        if ( $wants_to_sign_up ) {

            // get user info: email, first/last name
            $user = get_userdata( $user_id );
            $email = $user->user_email;
            // looks like the previous function doesn't set that...
            $first_name = $user->first_name;
            $last_name = $user->last_name;
    
            // initialize or get instance of MailChimp connection
            $mailchimp =  DC_mailchimp_singleton::get_instance();

            // find list id for Trade Program members
            $trade_list = $mailchimp::get_list_id_for_user_role('trade');
            
            $mailchimp->subscribe_user( $email, $first_name, $last_name, $trade_list );
    
        }
    }

 }
 add_action( 'wwp_wholesale_new_request_submitted', 'dc_subscribe_upon_trade_member_request', 10, 2 );

 /**
 * Gets the user role of a user by their email address; if no email passed, 
 * we try to get the role of the currently logged in user
 * 
 * @todo: move this function to a more appropriate file
 * 
 * @uses wp_get_current_user()
 * @uses get_user_by()
 *
 * @param string optional $email the email address to check
 * @return string|false the WP User Role, false on failure
 */
function dc_mc_get_user_role( $email = null ) {

    // check if we were passed an email address
    if ( is_null( $email ) ) {
        
        // if not, see if there's a user logged in
        if ( wp_get_current_user() ) {

            $user = wp_get_current_user();
            
        } else { 
            // if no user logged in, there's not much for us to do. Bailing out...
            return false;
        }
        
    } else {
        
        // we were passed an email address, so let's work with that
        $user = get_user_by( 'email', $email );
        
    }

    // get the user role for the email address
    if ( $user ) {

        $roles = $user->roles;
        $user_role = $roles[0];
        
        return $user_role;
    } else {
        return false;
    }

}

 /**
 * Gets the type of customer by their email address; if no email passed, 
 * we try to use the currently logged in user
 * 
 * @uses dc_mc_get_user_role()
 * 
 * @param string optional $email the email address to check
 * @return string|false the customer type, false on failure
 */
function dc_mc_get_customer_type( $email = null ) {

    // check if we were passed an email address, or if we have to figure it out
    if ( is_null( $email ) ) {
        
        // if not, see if there's a user logged in
        if ( wp_get_current_user() ) {

            $user = wp_get_current_user();
            $email = $user->user_email;
            
        } else { 
            // if no user logged in, there's not much for us to do. Bailing out...
            return false;
        }
    } 

    // if we got this far, we have an email to use. 
    // get the user role for the email address, if one exists
    $user_role = dc_mc_get_user_role( $email );

    // Trade Member has a WP User Role of 'default_wholesaler'
    if ( $user_role == 'default_wholesaler' ) {

        return 'trade';

    } else {
        
        // otherwise, treat them as retail customer, even if they don't have an account
        return 'retail';
        
    }
}