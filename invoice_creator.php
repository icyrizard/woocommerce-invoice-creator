<?php

/* Plugin Name: woocommerce-invoice-creator
* Plugin URI:
* Description: Sends invoice to invoice websites
* Version: 1.0
* Author: Richard Torenvliet
* Author URI: http://www.sponiza.nl
* License: GPLv2 or later
*/

/**
 * Check if WooCommerce is active
 **/
if (in_array('woocommerce' . DIRECTORY_SEPARATOR.
        'woocommerce.php',  apply_filters('active_plugins',
            get_option('active_plugins')))) {

    /** This global variable needs to be set to the settings file of
     * your api in the directory apifiles.
     * Note that the location really doesn't matter just that the globals
     * are set correctly */
    global $SETTINGS_FILE;
    $SETTINGS_FILE = "apifiles/factuursturen/settings.php";

    include_once('invoice_creator-hooks.php');
    function invoice_creator_activation() {
        global $SETTINGS_FILE;
        include_once($SETTINGS_FILE);

        /* settings file */
        global $wpdb, $API_NAME, $TABLE_NAME,
            $TABLE_FIELDS, $FUNCTIONS_FILE, $API_FILES_LOCATION;

        /* hook to wp_admin settings */
        add_action('admin_notices', 'invoice_creator_admin_notices');

        /* included to set activation admin notice */
        $table_name = $wpdb->prefix . "invoice_creator";
        $fields = "";

        /* get fields from settings */
        foreach($TABLE_FIELDS as $name => $additional){
            $fields .= $name ." ". $additional . ',';
        }

        /* create settings table */
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            api_name VARCHAR(128) DEFAULT '$API_NAME' NOT NULL,
            $fields
            exclude_custom_fields VARCHAR(64) DEFAULT '',
            textinvoice VARCHAR(128) DEFAULT 'Thanks for purchasing, this is your invoice' NOT NULL,
            UNIQUE KEY id (id)
        );";
        error_log($sql);

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
        /* insert one row */
        if (!$wpdb->get_var( "SELECT COUNT(*) FROM $table_name"))
            $rows_affected = $wpdb->insert($table_name, array('exclude_custom_fields' => ''));

        /* add action */
        add_action('wp_head', 'call_send_invoice');
    }

    function call_send_invoice($order_id){

        global $SETTINGS_FILE;
        include_once($SETTINGS_FILE);
        global $API_NAME, $FUNCTIONS_FILE, $API_FILES_LOCATION;
        /* include apifiles functions file */
        include_once(strtolower($API_FILES_LOCATION).
            DIRECTORY_SEPARATOR
            . $FUNCTIONS_FILE);
        invoice_creator_send_invoice($order_id);
    }

    function invoice_creator_deactivation() {
        global $wpdb;
        $table = $wpdb->prefix . 'invoice_creator';
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    register_activation_hook(__FILE__, 'invoice_creator_activation');
    register_uninstall_hook(__FILE__, 'invoice_creator_deactivation');
    register_deactivation_hook( __FILE__, 'invoice_creator_deactivation' );
    add_action('woocommerce_order_status_completed', 'call_send_invoice');
}

?>
