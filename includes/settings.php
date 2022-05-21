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
    // general user role
    register_setting( 'dc_mc_mailchimp_api', 'dc_mc_general_user_list_id' );
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

  $list_id = get_option('dc_mc_general_user_list_id');
  $plugin_name = urlencode_deep( dc_mc_get_plugin_name() );
  $plugin_version = dc_mc_get_version();

  ?>

  <div>
    <h2>Mailchimp Integrations</h2>
    <form method="post" action="options.php">
      <?php settings_fields( 'dc_mc_mailchimp_api' ); ?>
      
      <p>Enter your MailChimp API key below. You can get your API Key <a href="http://admin.mailchimp.com/account/api-key-popup" target="_blank" >here</a>. After entering the key, you'll need to hit Save in order to grab the lists on your MailChimp account. After the widget refeshes, choose the list that you'll want to add subscribers to. Once you've filled out the rest of the info in this widget, hit save. If all went well, you'll see the List ID &amp; List Name populated below the dropdown menu.</p>
      <p><b>Need help?</b> This plugin is free, so I can't offer in-depth support (as of yet). If you'd like to ask me a question, I'll do my best to answer or point you in the right direction, but I can't guarantee I'll be able to resolve the issue. <a href="https://digitallycultured.com/plugins/help-with-a-plugin/?plugin_name=<?php echo $plugin_name; ?>&version=<?php echo $plugin_version; ?>">Click here</a> to send me a message.</p>
      <p>Interested in finding out when I publish updates? <a href="https://digitallycultured.com/stay-in-touch/">Click here</a> to stay informed on future releases!</p>
      <table>
        <tr valign="top">
          <th scope="row"><label for="dc_mc_slack_webhook_url">API Key:</label></th>
          <td>
            <input type="text" class="tinyfat" id="dc_mc_mailchimp_api_key" name="dc_mc_mailchimp_api_key" value="<?php echo $api_key; ?>" placeholder="Enter your API key"/>
          </td>
        </tr>
      </table>

      <h3>Auto-assign audience</h3>
      <!-- <p>If you'd like to assign a user to a specific MailChimp audience based on their user role, enter it below.</p> -->

      <p>
        <label for="dc_mc_general_user_list_id"><?php _e('List:'); ?></label> 
        <select id="dc_mc_general_user_list_id" name="dc_mc_general_user_list_id" >
            <option>------</option>
            <?php
            if ( $lists ) {
                    foreach ($lists as $key => $index ) {
                        $name = $index['name'];
                        $id = $index['id'];
                        echo "<option value=\"$id\" " . ($list_id == $id ? 'selected="selected"' : '' ) . ">$name</option> ";
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