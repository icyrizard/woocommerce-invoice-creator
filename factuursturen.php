<?php
/* Plugin Name: factuursturen
Plugin URI:
Description: Sends invoice to factuursturen.nl
Version: 5.1
Author URI: http://www.sponiza.nl
License: GPLv2 or later
*/

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    include_once('factuursturen-functions.php');
    include_once('factuursturen-hooks.php');

    function factuursturen_activation() {
        global $wpdb;
        error_log("factuursturen activation", 0);
        add_action('admin_notices', 'factuursturen_admin_notices');

        /* included to set activation admin notice */
        include_once('factuursturen-hooks.php');

        #TODO set factuursturen_settings table name somewhere globally e.g.
        # settings file
        $table_name = $wpdb->prefix . "factuursturen_settings";

        /* create settings table */
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            api_key VARCHAR(42) DEFAULT '' NOT NULL,
            api_version VARCHAR(16) DEFAULT 'v0' NOT NULL,
            api_url VARCHAR(256) DEFAULT 'https://www.factuursturen.nl/api/' NOT NULL,
            username VARCHAR(32) DEFAULT '' NOT NULL,
            exclude_custom_fields VARCHAR(64) DEFAULT '',
            textinvoice VARCHAR(128) DEFAULT 'Thanks for purchasing, this is your invoice' NOT NULL,
            UNIQUE KEY id (id)
            );";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        error_log("factuursturen activation", 0);

        /* insert one row */
        $rows_affected = $wpdb->insert( $table_name, array('api_key' => ''));

    }

    function factuursturen_deactivation() {
        global $wpdb;
        $table = $wpdb->prefix."factuursturen_settings";

        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    register_activation_hook(__FILE__, 'factuursturen_activation');
    register_deactivation_hook(__FILE__, 'factuursturen_deactivation');
}
?>
