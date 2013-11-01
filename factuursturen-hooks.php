<?php

add_action('admin_menu', 'factuursturen');
add_action('admin_post_factuursturen', 'process_factuursturen_options' );

function process_factuursturen_options(){
    global $wpdb;
    if ( !current_user_can( 'manage_options' ) ){
        wp_die( 'You are not allowed to be on this page.' );
    }


    /* supported fields, #TODO set in settings file */
    $options = array(
        "api_key" => '',
        "api_url" => '',
        "api_version" => '',
        "username" => '',
        "textinvoice" => '',
    );
    error_log(var_export($options,true), 0);

    /* gather information from the post data and sanatize */
    foreach($options as $k => $v){
        error_log($k, 0);
        error_log($v, 0);
        if (isset ($_POST[$k])){
            $options[$k] = sanitize_text_field($_POST[$k]);
        } else {
            unset($options[$k]);
        }
    }

    /* update url */
    $wpdb->update($wpdb->prefix . 'factuursturen_settings', $options, array("id" => 1));

    wp_redirect($_SERVER['HTTP_REFERER']);
}

function factuursturen(){
    $hook_suffix = add_options_page( 'Factuursturen Options', 'Factuur Sturen',
        'manage_options', 'factuursturen-options-plugin-page',
        'factuursturenplugin_options' );

    /* Use the hook suffix to compose the hook and register an action executed
     * when plugin's options page is loaded */
    add_action( 'load-' . $hook_suffix , 'factuursturen_load_function' );
}

function factuursturen_load_function() {
    /* Current admin page is the options page for our plugin, so do not display
    /* the notice. (remove the action responsible for this)*/
    remove_action( 'admin_notices', 'factuursturen_admin_notices' );
}

function factuursturen_admin_notices(){
    echo "<div id='notice' class='updated fade'><p>Factuursturen plugin is not configured yet, please do this (add API KEY and personal settings)</p></div>\n";
}

/** Step 3. */
function factuursturenplugin_options() {
    global $wpdb;
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    $options = $wpdb->get_row( $wpdb->prepare("
        SELECT 	    *
        FROM {$wpdb->prefix}factuursturen_settings;")
    );

        echo '<div class="wrap">';
        echo '<h2>Factuursturen.nl plugin settings</h2>';
        echo '<p>Place your credentials obtained from factuursturen.nl,
        the api-version and api-url are already filled in. Change them if needed.</p>';

    ?>

    <form name='factuursturen-options' method='post'
          action='admin-post.php'>
        <input type="hidden" name="action" value="factuursturen" />
        <label style="width:8em; display: block; float: left; line-height:
                1.8em;">API key</label>
        <input type='text' name='api_key' size="64" value='<?php echo
                esc_html($options->api_key); ?>'/></br>
        <label style="width:8em; display: block; float: left; line-height:
                1.8em;" >API url</label>
        <input type='text' name='api_url' size="64" value='<?php echo
                esc_html($options->api_url); ?>' /> </br>
        <label style="width:8em; display: block; float: left; line-height:
                1.8em;"  >API username</label>
        <input type='text' name='username' size="32" value='<?php echo
                esc_html($options->username); ?>'/></br>
        <label style="width:8em; display: block; float: left; line-height:
                1.8em;" >API version</label>
        <input type='text' name='api_version' size="16" value='<?php echo
                esc_html($options->api_version); ?>'/></br>
        <label style="width:8em; display: block; float: left; line-height:
                1.8em;" >Text Invoice</label>
        <textarea name='textinvoice' rows="3" cols="32 "size="128"
            value=''><?php echo esc_html($options->textinvoice);?></textarea>
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
        </p>
    </form>
<?php
    echo '</div>';
}

?>
