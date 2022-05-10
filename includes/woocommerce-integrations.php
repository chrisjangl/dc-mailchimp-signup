<?php
/**
 * Functions to integrate with WooCommerce
 * 
 * TODO: remove all static references to trade/retail
 * 
 * ## Next Steps
 * - add WC notice with unsubscribe link when user signals intent to unsubscribe
 *
 * @author Chris Jangl <plugins@digitallycultured.com>
 * @since 0.2
 */

 function dc_mc_maybe_show_signup( ) {

    // @todo: this can be replaced with dc_mc_get_user_role()
    // get the current user & it's role
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    $first_name = $current_user->first_name;
    $last_name = $current_user->last_name;
    $roles = $current_user->roles;
    $user_role = $roles[0];

    // trade or retail customer? Set the newsletter sign up prompt based on user role
    // we'll also be using the $customer_type to find the MailChimp list to send to
    if ( $user_role == 'default_wholesaler' ) {

        $customer_type = 'trade';
        $signup_prompt = "Join our Trade Mailing List for exclusive offers and updates";

    } else {

        $customer_type = 'retail';
        $signup_prompt = "Join our Mailing List for all our latest updates and special offers";

    }

    // initialize or get instance of MailChimp connection
    $mailchimp =  DC_mailchimp_singleton::get_instance();
    
    // is the customer on mailing list?...
    
    // ...first get the list approriate list (trade or retail)...
    $list_ID = $mailchimp::get_list_id_for_user_role( $customer_type );

    // create the sign up prompt, and button (form, really) that signs 
    // the customer up to proper mailing list:

    // 1st, the sign up prompt; text conditionally chosen based on customer's user role
    $signupCTA = "<p style=\"font-style: italic;\">$signup_prompt</p>";
    
    // and then the sign up button (and form)
    $signup_button =  $mailchimp->print_auto_subscribe_form( $list_ID, $user_email, $first_name, $last_name );

    // put it all together, now
    $prompt_and_signup_button = $signupCTA . $signup_button;
    
    // ...check if the customer already on that list...
    if ( $contact_status = $mailchimp::contact_exists( $list_ID, $user_email ) ) {
        
        // is the contact subscribed?
        switch ( $contact_status ) {
            case 'cleaned':
            case 'subscribed':
                return false;
            break;
            case 'unsubscribed':

                echo $prompt_and_signup_button;
                
            break;
            
            case 'pending':

                // instruct the user to confirm their subscription
                echo "<p>It looks like you signed up for our newletter, but we don't have you confirmed. Check your Inbox & Junk / Spam folder for an email from us. You'll have to follow the instructions in there before getting our newsletter.</p>";
            break;

        }
        return false;

    } else {
        
        // if not, show CTA & button
        echo $prompt_and_signup_button;

    }
}

/**
 * adds checkbox to edit account form in WooCommerce My Account page
 * to add user to MailChimp list
 * 
 * @todo: want to check if user is on MailChimp and have the checkbox reflect this
 *
 * @return void
 */
function dc_mc_add_mailchimp_signup_checkbox() {

    // does user want to be on newsletter?
    $current_user = wp_get_current_user();

    $user_ID = $current_user->ID;
    $user_wants_newsletter = get_user_meta( $user_ID, 'dc_mc_wants_newsletter', true );
    ?>

    <style>
        p.woocommerce-form-row.dc-mc-signup {
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        p.woocommerce-form-row.dc-mc-signup input {
            margin-top: 0;
            margin-right: 1rem;
        }
    </style>

    <p class="dc-mc-signup woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <input type="checkbox" class="woocommerce-Input" name="dc_mc_sign_me_up" id="dc_mc_sign_me_up" value="1" <?php checked( $user_wants_newsletter ); ?> />
        <label for="dc_mc_sign_me_up"><?php esc_html_e( 'I\'d like to be on your newsletter!', 'woocommerce' ); ?></label>
    </p>

    <?php
}
// NB: turning this off because I can't find a clean way to unsubscribe a user if they uncheck the box
// add_action( 'woocommerce_edit_account_form', 'dc_mc_add_mailchimp_signup_checkbox' );

/**
 * saves the users intent to subscribe to newsletter when editing their account
 * details in WooCommerce My Account page
 *
 * @param int $user_ID
 * @return void
 */
function dc_mc_signup_save_signup_intent( $user_ID ) {

    // @todo: really only want to inititate talking to MailChimp if there's a CHANGE in value

    // check incoming data to see what the user said
    if ( isset( $_POST['dc_mc_sign_me_up'] ) && $_POST['dc_mc_sign_me_up'] ) {

        // if they have the box checked, first update their user meta...
        $update_user_meta = update_user_meta( $user_ID, 'dc_mc_wants_newsletter', true );

        // ...then actually sign them up via MailChimp
        // grab first name
        $first_name = isset( $_POST['account_first_name'] ) ? $_POST['account_first_name'] : null;
        // grab last name
        $last_name = isset( $_POST['account_last_name'] ) ? $_POST['account_last_name'] : null;
        // grab email
        $email = isset( $_POST['account_email'] ) ? $_POST['account_email'] : null;
        
        // initialize or get instance of MailChimp connection
        $mailchimp =  DC_mailchimp_singleton::get_instance();
        
        $mailchimp->subscribe_user( $email, $first_name, $last_name );
        
        // extra points if we use the TechCrunch method of sending the request after this load
        
    } else {

        // intent is turned off, but did it used to be turned on?
        if ( get_user_meta( $user_ID, 'dc_mc_wants_newsletter', true ) ) {
            wc_add_notice( 'I see changes', 'notice' );

            // MailChimp doesn't let us unsubscribe via API; maybe we tell the user?
            // grab email
            $email = isset( $_POST['account_email'] ) ? $_POST['account_email'] : null;
    
            // initialize or get instance of MailChimp connection
            $mailchimp =  DC_mailchimp_singleton::get_instance();

            // get the (probable) unsubscribe link based on user role
            $probable_list_id = $mailchimp::get_list_id_for_user_account( $email );

            // user wants off the newsletter, so update the user meta to reflect this...
            $update_user_meta = update_user_meta( $user_ID, 'dc_mc_wants_newsletter', false );

            // ...and remove them from MailChimp, if they are subscribed
            $mailchimp->unsubscribe_user( $email );
            
            // extra points if we can use the TechCrunch method of sending the request after this load

        }
    }
}
// NB: turning this off; since the above is turned off, no reason to save the intent.
// add_action( 'woocommerce_save_account_details', 'dc_mc_signup_save_signup_intent', 10, 1 );

/**
 * Add WooCommerce Checkbox checkout
 * 
 * @uses DC_mailchimp_singleton->get_checkout_signup_prompt()
 * @uses woocommerce_form_field()
 * 
 */
function dc_mc_add_checkout_checkbox() {

    // get MailChimp instance
    $mailchimp = DC_mailchimp_singleton::get_instance();
    
    // check if customer is logged in & whether they're a trade member
    if ( 'trade' ==  dc_mc_get_customer_type() ) {
        
        // set checkbox key
        $checkbox_key = "dc-mc-trade-signup";
        
        // get the message to prompt Trade Member to sign up for newsletter, 
        $newsletter_prompt = $mailchimp->get_checkout_signup_prompt( 'trade' );
        
    } else {
        // otherwise, treat them as retail:

        // retail user checkbox key
        $checkbox_key = "dc-mc-retail-signup";
        
        // get the message to prompt retail customer to sign up for newsletter, 
        $newsletter_prompt = $mailchimp->get_checkout_signup_prompt();
    }    

    $checkbox_args = array( 
        'type'          => 'checkbox',
        'class'         => array('form-row mycheckbox'), // CSS Class
        'label_class'   => array('woocommerce-form__label woocommerce-form__label-for-checkbox checkbox'),
        'input_class'   => array('woocommerce-form__input woocommerce-form__input-checkbox input-checkbox'),
        // 'required'      => true, // Mandatory or Optional
        'label'         => $newsletter_prompt, 
    );

    // create form field specific to either retail or trade, based on above
    woocommerce_form_field( $checkbox_key, $checkbox_args, 1 );    
}
// priority 20 will place it *after* billing fields
add_action( 'woocommerce_checkout_billing', 'dc_mc_add_checkout_checkbox', 20 );

/**
 * check whether user had newsletter subscribe checked from checkout
 * 
 * @uses DC_mailchimp_Singleton->get_list_id_for_user_role()
 * @uses DC_Mailchimp_singleton->subscribe_user()
 * 
 */
function dc_mc_checkout_subscribe_intent() {

    // check if the user checked the retail signup box
    if ( (int) isset( $_POST['dc-mc-retail-signup'] ) ) {

        // get first name
        $first_name = isset( $_POST['billing_first_name'] ) ? $_POST['billing_first_name'] : '';

        // get last name
        $last_name = isset( $_POST['billing_last_name'] ) ? $_POST['billing_last_name'] : '';
        
        // get email address
        $email = isset( $_POST['billing_email'] ) ? $_POST['billing_email'] : null;
        
        // initialize or get instance of MailChimp connection
        $mailchimp =  DC_mailchimp_singleton::get_instance();

        // find list id for retail customers
        $list_id = $mailchimp::get_list_id_for_user_role('retail');
        
    } else 
        // or check if the user checked the Trade signup box
    if ( (int) isset( $_POST['dc-mc-trade-signup'] ) ) {
        
        // get first name
        $first_name = isset( $_POST['billing_first_name'] ) ? $_POST['billing_first_name'] : '';

        // get last name
        $last_name = isset( $_POST['billing_last_name'] ) ? $_POST['billing_last_name'] : '';
        
        // get email address
        $email = isset( $_POST['billing_email'] ) ? $_POST['billing_email'] : '';
        
        // initialize or get instance of MailChimp connection
        $mailchimp =  DC_mailchimp_singleton::get_instance();

        // find list id for trade customers
        $list_id = $mailchimp::get_list_id_for_user_role('trade');

    }

    // if we have an email address & a list ID, subscribe the user
    if ( !is_null( $email ) && !is_null( $list_id ) ) {
        $mailchimp->subscribe_user( $email, $first_name, $last_name, $list_id );
    }

}
add_action( 'woocommerce_checkout_process', 'dc_mc_checkout_subscribe_intent' );