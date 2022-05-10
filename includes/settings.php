<?php

/**
 * registers settings for plugin's Options page
 * 
 * TODO: allow for dynamic creation of lists/roles
 *
 * @return void
 */
function dc_mc_register_settings() {
    add_option( 'dc_mc_mailchimp_api_key' );
    
    register_setting( 'dc_mc_mailchimp_api', 'dc_mc_mailchimp_api_key' );
    // retail user role
    register_setting( 'dc_mc_mailchimp_api', 'dc_mc_mailchimp_audience_rule_one_user_role' );
    register_setting( 'dc_mc_mailchimp_api', 'dc_mc_mailchimp_audience_retail_list_id' );
    // trade member user role
    register_setting( 'dc_mc_mailchimp_api', 'dc_mc_mailchimp_audience_rule_two_user_role' );
    register_setting( 'dc_mc_mailchimp_api', 'dc_mc_mailchimp_audience_trade_list_id' );
}
add_action( 'admin_init', 'dc_mc_register_settings' );

/**
 * registers the Options page for plugin
 *
 * 
 */
function dc_mc_add_options_page() {

  add_options_page( 
      __( 'Mailchimp Integration Settings', 'dc_mc' ),  // page_title
      __( 'Mailchimp Integrations Settings', 'dc_mc' ),   // menu_title
      'manage_options',   // capability
      'mailchimp-integrations.php', // menu_slug
      'dc_mc_build_options_page' // callback to build the page
  );

}
add_action('admin_menu', 'dc_mc_add_options_page');

/** 
 * contains HTML to build the plugin's Options page 
 */
function dc_mc_build_options_page() {
  $api_key = get_option('dc_mc_mailchimp_api_key');

  if ( $api_key ) {
    $mailchimp = new DC_mailchimp_singleton( $api_key );
    $lists = $mailchimp->get_lists();
  }

  $rule_one = array(
      'user_role'   =>  'dc_mc_mailchimp_audience_rule_one_user_role',
      'list_id'     =>  'dc_mc_mailchimp_audience_retail_list_id'
  );
  $rule_two = array(
    'user_role'   =>  'dc_mc_mailchimp_audience_rule_two_user_role',
    'list_id'     =>  'dc_mc_mailchimp_audience_wholesale_list_id'
  );
  $retail_list_id = get_option('dc_mc_mailchimp_audience_retail_list_id');
  $trade_list_id = get_option('dc_mc_mailchimp_audience_trade_list_id');
  ?>

  <div>
    <h2>Mailchimp Integrations</h2>
    <form method="post" action="options.php">
      <?php settings_fields( 'dc_mc_mailchimp_api' ); ?>
      
      <p>Enter your MailChimp API key below. You can get your API Key <a href="http://admin.mailchimp.com/account/api-key-popup" target="_blank" >here</a>. After entering the key, you'll need to hit Save in order to grab the lists on your MailChimp account. After the widget refeshes, choose the list that you'll want to add subscribers to. Once you've filled out the rest of the info in this widget, hit save. If all went well, you'll see the List ID &amp; List Name populated below the dropdown menu.</p>
      <table>
        <tr valign="top">
          <th scope="row"><label for="dc_mc_slack_webhook_url">API Key:</label></th>
          <td>
            <input type="text" class="tinyfat" id="dc_mc_mailchimp_api_key" name="dc_mc_mailchimp_api_key" value="<?php echo $api_key; ?>" placeholder="Enter your API key"/>
          </td>
        </tr>
      </table>

      <h3>Auto-assign audience</h3>
      <p>If you'd like to assign a user to a specific MailChimp audience based on their user role, enter it below.</p>

      <p>Retail</p>
      <p>
        <label for="dc_mc_mailchimp_audience_retail_list_id"><?php _e('List:'); ?></label> 
        <select id="dc_mc_mailchimp_audience_retail_list_id" name="dc_mc_mailchimp_audience_retail_list_id" >
            <option>------</option>
            <?php
            if ( $lists ) {
                    foreach ($lists as $key => $index ) {
                        $name = $index['name'];
                        $id = $index['id'];
                        echo "<option value=\"$id\" " . ($retail_list_id == $id ? 'selected="selected"' : '' ) . ">$name</option> ";
                    }
            } ?>
            <hr />
        </select>
       </p>
       
      <p>Trade</p>
      <p>
        <label for="dc_mc_mailchimp_audience_trade_list_id"><?php _e('List:'); ?></label> 
        <select id="dc_mc_mailchimp_audience_trade_list_id" name="dc_mc_mailchimp_audience_trade_list_id" >
            <option>------</option>
            <?php
            if ( $lists ) {
                    foreach ($lists as $key => $index ) {
                        $name = $index['name'];
                        $id = $index['id'];
                        echo "<option value=\"$id\" " . ($trade_list_id == $id ? 'selected="selected"' : '' ) . ">$name</option> ";
                    }
            } ?>
            <hr />
        </select>
      </p>

      <?php  submit_button(); ?>
    </form>
  </div>
  <?php
} 