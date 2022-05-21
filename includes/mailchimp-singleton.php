<?php
/**
 * ## Next steps
 * - remove trade/retail list mentions
 * - messages being shown to subscriber should be customizable in WP Dashboard
 * - add WC notice with unsubscribe link when user signals intent to unsubscribe
 * - make sure subscribe_user() still works after adding option 4th parameter ($list_id)
 * - can we use vanilla JS instead of jQuery?
 * 
 */

require_once( 'MailChimp.php' );

class DC_mailchimp_singleton extends MailChimp {
    private static $api_key = null;
    private static $instance = null;
    private static $MailChimp = null;
    private static $general_user_list_id = null;
    
	public function __construct( $api_key ='' ) {

        require_once( 'MailChimp.php' );

        // add our actions
        add_action( 'init', array( $this, 'add_actions') );

        // if we're passed an API key, set it; 
        if ( $api_key ) {
            self::set_api_key( $api_key );
        }
    
    }

    function add_actions() {

        //AJAX handler
        add_action( 'wp_ajax_nopriv_dc_mc_post_subscriber',  array($this, 'post_subscriber') );
        add_action( 'wp_ajax_dc_mc_post_subscriber',  array($this, 'post_subscriber') );
    }
    
    public static function get_instance() {

        if ( is_null( self::$instance ) ) {
            self::$instance = new DC_mailchimp_singleton();
        }

        return self::$instance;
    }

    static function instantiate_mailchimp() {

        if ( ! is_null( self::$api_key ) ) {
            require_once( 'MailChimp.php' );
            self::$MailChimp = new MailChimp( self::$api_key );
        }
    }

    static function set_api_key( $api_key ) {
        
        self::$api_key = $api_key;

    }

    private static function get_api_key() {
        return self::$api_key;
    }

    private static function get_MailChimp_instance() {

        if ( is_null( self::$MailChimp ) ) {
    
            if ( !is_null( self::$api_key) )
            
            require_once( 'MailChimp.php' );
            self::$MailChimp = new MailChimp( self::$api_key );
        }
    
        $MailChimp = self::$MailChimp;

        return $MailChimp;
    }
    
    function get_lists() {

        $MailChimp = self::get_MailChimp_instance();
        
        $lists = $MailChimp->get('lists');
        return $lists['lists'];
        
    }

    function get_general_user_list_id() {

        // check if we already have the List ID set...
        if ( is_null( self::$general_user_list_id) ) {
       
            // if not, then grab it from the database...
            $list_id = get_option('dc_mc_general_user_list_id');
            
            if ( !is_null( $list_id ) && $list_id ) {

                // as long as there's something from the DB, assign it to our singleton instance
                self::set_general_user_list_id( $list_id );

            }
        }

        // and pass it back
        return self::$general_user_list_id;
    }

    function set_general_user_list_id( $list_id ) {
        if ( ! $list_id || is_null( $list_id ) ) {
            return false;
        } 
        
        self::$general_user_list_id = $list_id;
    }
    
    // halfway there towards proper usage of get_MailChimp_instance()
    function get_list_name( $list_id ) {
        
        $MailChimp = self::get_MailChimp_instance();

        if ( $MailChimp ) {
            
            // we have a successful connection to MailChimp,
            // let's do what we came here to do
            $list = $MailChimp->get('lists/'.$list_id);
            return $list['name'];

            
        } else  {

            // @todo: bail gracefully
            // something's wrong or missing, we can't continue

        }
    }

    static function contact_exists( $list_id, $email ) {

        $MailChimp = self::get_MailChimp_instance();

        if ( $MailChimp ) {

            $hashed_email = $MailChimp->subscriberHash($email);
            $result = $MailChimp->get("lists/$list_id/members/$hashed_email" );
            
            // contact doesn't exist
            if ( $result['status'] == 404 ) {
                return false;
            } 
            // contact is either subscribed, unsubscribed, cleaned or pending
            else if ( in_array( $result['status'], ['subscribed', 'unsubscribed', 'pending', 'cleaned'] ) ) {
                return $result['status'];
            }
        }
    }
    
    private static function add_subscriber( $list_id, $email, $first_name = '', $last_name = '' ) {

        $MailChimp = self::get_MailChimp_instance();

        if ( $MailChimp ) {
            
            $result = $MailChimp->post("lists/$list_id/members", [
                    'email_address' => $email,
                    'status'        => 'subscribed',
                    'merge_fields'  =>  [
                        // @todo: need to have these columns set dynamically
                        'FNAME'     =>  $first_name,
                        'LNAME'     =>  $last_name
                    ]
                ]);
            return $result;
            
        } else {
            
            // @todo: bail gracefully
            // something's wrong or missing, we can't continue

        }
    }

    // @todo: make sure this works for brand new email addresses as well
    private static function upsert_subscriber( $list_id, $email, $first_name='', $last_name='' ) {

        // get the instance of MailChimp
        $MailChimp = self::get_MailChimp_instance();

        if ( $MailChimp ) {

            $hashed_email = $MailChimp->subscriberHash($email);

            // build the body parameters array 
            $body_parameters = [
                'email_address' =>  $email,
                'status'        => 'subscribed',
            ];
            
            // build the merge fields array
            $merge_fields = [];

            // first name
            // TODO: Does empty() what I'm expecting it to?
            if ( $first_name && !empty( $first_name ) ) {
                $merge_fields['FNAME'] = $first_name;
            }
            
            // last name
            if ( $last_name && !empty( $last_name ) ) {
                $merge_fields['LNAME'] = $last_name;
            }

            // add the first or last name, if they exist
            if ( ! empty( $merge_fields ) ) {
                $body_parameters['merge_fields'] = $merge_fields;
            }

            // TODO: this doesn't work if we have our merge fields
            $result = $MailChimp->put("lists/$list_id/members/$hashed_email", $body_parameters);

            return $result;

        }
    }

    // @todo: create documentation
    function is_subscribed_to_list( $email, $list_id ) {

        $MailChimp = self::get_MailChimp_instance();
        
    }

    // @todo: create documentation
    function print_auto_subscribe_form( $list_id, $email = null, $first_name = null, $last_name = null ) {

        $email_input = ( is_null( $email ) ) ? 
            '<input name="EMAIL" type="hidden" id="dc-mc-email">' :
            '<input name="EMAIL" type="hidden" id="dc-mc-email" value="' . $email . '">';

        $first_name_input = ( is_null( $first_name ) ) ? 
            '<input name="first_name" type="hidden" id="dc-mc-first-name">' :
            '<input name="first_name" type="hidden" id="dc-mc-first-name" value="' . $first_name . '">';

        $last_name_input = ( is_null( $last_name ) ) ? 
            '<input name="last_name" type="hidden" id="dc-mc-last-name">' :
            '<input name="last_name" type="hidden" id="dc-mc-last-name" value="' . $last_name . '">';

        $list_id = ( is_null( $list_id ) ) ? 
        '<input name="dc-mc-list-id" type="hidden" id="dc-mc-list-id">' :
        '<input name="dc-mc-list-id" type="hidden" id="dc-mc-list-id" value="' . $list_id . '">';


        ob_start();
        ?>

        <form class="validate" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form">
                
            <?php echo $email_input; ?>
            <?php echo $first_name_input; ?>
            <?php echo $last_name_input; ?>
            <?php echo $list_id; ?>
            <input name="subscribe" type="submit" id="dc-mc-signup-submit" value="Sign Up!" />
            <div id="dc-mc-signup-response" style="margin: 1rem auto;"></div>
            <div class="loading" id="dc-mc-signup-loading" style="display: none; text-align: center; margin-top:15px;">
                <i class="fa fa-refresh fa-spin fa-3x fa-fw"></i>
                <span class="sr-only">Loading...</span>
            </div>
        
        </form>
        <script>
            jQuery('#dc-mc-signup-submit').click(function(event) {
                event.preventDefault();

                var loadingArea = jQuery('#dc-mc-signup-loading'),
                    responseArea = jQuery('#dc-mc-signup-response'),
                    email = jQuery('#dc-mc-email').val(),
                    firstName = jQuery('#dc-mc-first-name').val(),
                    lastName = jQuery('#dc-mc-last-name').val(),
                    listID = jQuery ('#dc-mc-list-id').val();

                if ( validateEmail( email ) ) {
                    responseArea.empty();
                    loadingArea.slideDown();
                    var data = {
                        'action': 'dc_mc_post_subscriber',
                        'list_id': listID,
                        'email': email,
                        'FNAME': firstName,
                        'LNAME': lastName
                    };

                    jQuery.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(response) {
                        loadingArea.slideUp();
                        responseArea.html(response).slideDown();

                    });
                } else {
                    responseArea.empty().html('That doesn\'t look to be a valid email address. Please double check your email address and try again.').slideDown();
                }

            });
            function validateEmail(sEmail) {
                var filter = /^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;
                if (filter.test(sEmail)) {
                    return true;
                }
                else {
                    return false;
                }
            }
        </script>

        <?php
        return ob_get_clean();
    }

    // @pickUp - this is the AJAX handler for auto subscribe form;
    // @todo: need to update the contact if it exists, and include the first/last name
    function post_subscriber( ) {
        
        $list_id = ( isset($_POST['list_id']) ) ? $_POST['list_id'] : null;
        $email = ( isset($_POST['email']) ) ? $_POST['email'] : null;
        $first_name = ( isset($_POST['FNAME']) ) ? $_POST['FNAME'] : null;
        $last_name = ( isset($_POST['LNAME']) ) ? $_POST['LNAME'] : null;

        // add the email address to the list
        $response = self::upsert_subscriber( $list_id, $email, $first_name, $last_name );
    
        $message = self::get_message( $response);
        
        echo $message;
        
        wp_die();
    }

    /**
     * Subscribes user to MailChimp list. List being subscribed to is 
     * determined by the corresponding user role & plugin settings. 
     * 
     * If 4th parameter not passed ($list_id), function will attempt to determine the list ID to use
     * based on results from self::get_list_id_for_user_account()
     * 
     * @uses self::get_list_id()
     * @uses self::upsert_subscriber()
     *
     * @param string $email
     * @param string $first_name
     * @param string $last_name
     * @param int|bool $list_id default=false.
     * @return void
     */
    function subscribe_user( $email, $first_name = null, $last_name = null, $list_id = false ) {

        // were we passed a list ID or do we need to figure it out?
        if ( ! $list_id ) {
            
            // If we weren't, use the general list ID we have in the singleton instance
            $list_id = self::get_general_user_list_id();

        }

        self::upsert_subscriber( $list_id, $email, $first_name, $last_name );
        
    }

    /**
     * get the appropriate message to show to user, based on response from MailChimp
     * 
     * TODO: these messages should be customizable in WP Dashboard
     * 
     * @param array $response - response array from MailChimp API call
     * 
     * @return string Our user friendly message describing the MailChimp response
     */
    static function get_message( $response ) {

        switch( $response['status'] ) {
            case 'subscribed' :
                return "Thanks! We've added you to our email list.";
                break;
            case '400':
                if ( $response['title'] == 'Member Exists' ){
                    return 'Looks like you\'re already on our mailing list. If you\'re not receiving emails, try checking your Junk or Spam folder. If you\'re still not receiving them, <a href="' . get_site_url() . '/contact/" data-remodal-target="dc-contact">get in touch with us</a>, and we\'ll see if we can\'t figure it out!';
                } else if ( $response['title'] == 'Invalid Resource' ) {
                    return "Whoops, looks like you may have entered an invalid email address - why don't you try again?";
                } else if ( $response['title'] == 'Forgotten Email Not Subscribed' ) {
                    return 'It looks like your email address was permanently deleted from our list and cannot be re-imported. <a href="' . get_site_url() . '/contact/" data-remodal-target="dc-contact">Get in touch with us</a>, and we\'ll get it straightened out';
                } 
                // default message if something went wrong
                else {
                    return 'Looks like something went wrong. <a href="' . get_site_url() . '/contact/" data-remodal-target="dc-contact">Get in touch with us</a>, and we\'ll get it straightened out.';
                }
                break;
        }
    }

    /**
     * get the message to prompt the user to sign up for the mailing list 
     * on Checkout page. By default returns prompt for retail users, but can 
     * return Trade prompt by passing 'trade'
     * 
     * TODO: the prompts should be set in WP Dashboard, per list
     *
     * @param string $user_role - default 'retail'
     * @return string newsletter sign up prompt text to be used on Checkout
     */
    function get_checkout_signup_prompt( $user_role = 'retail' ) {

        $trade_prompt = 'Join our Trade Mailing List for exclusive offers and updates';

        $retail_prompt = 'Join our Mailing List for all our latest updates and special offers';

        // return trade signup prompt if the user role is trade
        if ( 'trade' == $user_role ) {
            return $trade_prompt;
        }
        
        // otherwise, return the retail signup prompt
        else {
            return $retail_prompt;
        }
    }
}

new DC_mailchimp_singleton();