<?php
/**
 * Functions to integrate with Ultimate Membership
 * 
 * ## Where I left off
 * I found the hook in Ultimate Membership to tap into, has the first and last name I need to be able to send to MailChimp
 *  
 * ## Next Steps
 * - add checkbox for user to opt in/out of newsletter signup
 * - add WC notice with unsubscribe link when user signals intent to unsubscribe
 *
 * @author Chris Jangl <plugins@digitallycultured.com>
 * @since 0.2
 */

/**
 * This action gets called inside a callback on 'um_user_register' action 
 * 
 * @see um_after_insert_user() in wp-content/plugins/ultimate-member/includes/core/um-actions-register.php
 */
add_action( 'um_registration_complete', 'subscribe_new_user_to_mailchimp', 10, 2 );


/**
 * Hooking in after the user has been created, this function calls our singleton to 
 * upsert the user to MailChimp
 *
 * @param int $user_id
 * @param array $args
 *
 * @return void
 */
function subscribe_new_user_to_mailchimp( $user_id, $args ) {

    // TODO: Is there a way I can force this checkbox to appear in registration form?
    // check user meta if there's a value for our newsletter intent
    $wants_to_sign_up = true;
    // $wants_to_sign_up = get_user_meta( $user->id, 'dc_mc_sign_me_up', true );

    if ( $wants_to_sign_up ) {

        // get user info: email, first/last name
        $user = get_userdata( $user_id );

        $email = $user->user_email;
        // TODO: First & last name aren't added to the user yet...
        $first_name = $args['first_name'] ? $args['first_name'] : false;
        $last_name = $args['last_name'] ? $args['last_name'] : false;

        // initialize or get instance of MailChimp connection
        $mailchimp =  DC_mailchimp_singleton::get_instance();

        // find list id 
        $list_id = $mailchimp::get_general_user_list_id();
        
        $mailchimp->subscribe_user( $email, $first_name, $last_name, $list_id );

    }
}