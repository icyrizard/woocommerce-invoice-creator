<?php
/**
* The hooks of the plugin, hook into the admin page.
*/
include_once($SETTINGS_FILE);
include_once("invoice_creator-common.php");

add_action( 'wp_router_generate_routes', 'bl_add_routes', 20);

/* add to admin_menu */
add_action('admin_menu', 'invoice_creator');

/* callback to process POST request invoice */
add_action('admin_post_invoice', 'process_invoice_options' );

/**
 * Processes the Plugin-Options Post requests.
 * The settings.php file is used to find the name and fields defined in
 * the database.
 */
function process_invoice_options(){
    global $wpdb, $TABLE_FIELDS, $API_NAME;

    if (!current_user_can('manage_options')){
        wp_die('You are not allowed to be on this page.');
    }

    /* supported fields, default fields */
    $options = array(
        "textinvoice" => '',
        "exclude_custom_fields" => '',
        "api_name" => '',
    );

    /* load fields from settings table */
    foreach($TABLE_FIELDS as $name => $additional){
        $options[$name] = '';
    }

    /* gather information from the post data and sanatize */
    foreach($options as $k => $v){
        if (isset ($_POST[$k])){
            $options[$k] = sanitize_text_field($_POST[$k]);
        } else {
            unset($options[$k]);
        }
    }

    /* update url */
    $wpdb->update($wpdb->prefix .
        "invoice_creator", $options, array("api_name" => $API_NAME));

    /* redirect to previous page */
    wp_redirect($_SERVER['HTTP_REFERER']);
}

/* Adds Plugin Options page */
function invoice_creator(){
    global $API_NAME;

    $hook_suffix = add_options_page("$API_NAME Options",
        "$API_NAME Settings", 'manage_options',
        'invoice_creator-plugin-page', 'invoice_creator_options' );

    /* Use the hook suffix to compose the hook and register an action executed
     * when plugin's options page is loaded */
    add_action('load-' . $hook_suffix, 'invoice_creator_load_function');
}

/**
* Current admin page is the options page for our plugin, so do not display
* the notice. (remove the action responsible for this)
*/
function invoice_creator_load_function() {
    remove_action('admin_notices', 'invoice_creator_admin_notices');
}

/* Update information */
function invoice_creator_admin_notices(){
    echo "<div id='notice' class='updated fade'><p>Acumulus plugin is not configured yet, please do this (add API KEY and personal settings)</p></div>\n";
}

/**
 * Invoice creator options, see settings.php to set the global
 * information(TABLE_NAME etc.)
 */
function invoice_creator_options() {
    global $wpdb, $TABLE_FIELDS, $API_NAME, $TABLE_NAME;
    /* check if user is allowd to access */
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    /* get available options/credentials */
    $options = invc_get_credentials();

    /* start settings table */
    echo '<div class="wrap">';
    echo "<h2>$API_NAME plugin settings</h2>";
    echo "<p>Place your credentials obtained from $API_NAME
    the api-version and api-url are already filled in. Change them if needed.</p>";

    /* call render settings from options */
    echo render_settings($options);

    echo '</div>';
}
?>
