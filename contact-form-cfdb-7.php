<?php
/*
Plugin name: Contact Form CFDB7
Plugin URI: https://ciphercoin.com/
Description: Save and manage Contact Form 7 messages. Never lose important data. Contact Form CFDB7 plugin is an add-on for the Contact Form 7 plugin.
Author: Arshid
Author URI: http://ciphercoin.com/
Text Domain: contact-form-cfdb7
Domain Path: /languages/
Version: 1.2.4.11
*/

function cfdb7_create_table(){

    global $wpdb;
    $cfdb       = apply_filters( 'cfdb7_database', $wpdb );
    $table_name = $cfdb->prefix.'db7_forms';

    if( $cfdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {

        $charset_collate = $cfdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            form_id bigint(20) NOT NULL AUTO_INCREMENT,
            form_post_id bigint(20) NOT NULL,
            form_value longtext NOT NULL,
            form_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (form_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    $upload_dir    = wp_upload_dir();
    $cfdb7_dirname = $upload_dir['basedir'].'/cfdb7_uploads';
    if ( ! file_exists( $cfdb7_dirname ) ) {
        wp_mkdir_p( $cfdb7_dirname );
    }
    add_option( 'cfdb7_view_install_date', date('Y-m-d G:i:s'), '', 'yes');

}

function cfdb7_on_activate( $network_wide ){

    global $wpdb;
    if ( is_multisite() && $network_wide ) {
        // Get all blogs in the network and activate plugin on each one
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            cfdb7_create_table();
            restore_current_blog();
        }
    } else {
        cfdb7_create_table();
    }

	// Add custom capability
	$role = get_role( 'administrator' );
	$role->add_cap( 'cfdb7_access' );
}

register_activation_hook( __FILE__, 'cfdb7_on_activate' );


function cfdb7_on_deactivate() {

	// Remove custom capability from all roles
	global $wp_roles;

	foreach( array_keys( $wp_roles->roles ) as $role ) {
		$wp_roles->remove_cap( $role, 'cfdb7_access' );
	}
}

register_deactivation_hook( __FILE__, 'cfdb7_on_deactivate' );


function cfdb7_before_send_mail( $form_tag ) {

    global $wpdb;
    $cfdb          = apply_filters( 'cfdb7_database', $wpdb );
    $table_name    = $cfdb->prefix.'db7_forms';
    $upload_dir    = wp_upload_dir();
    $cfdb7_dirname = $upload_dir['basedir'].'/cfdb7_uploads';
    $time_now      = time();

    $form = WPCF7_Submission::get_instance();

    if ( $form ) {

        $black_list   = array('_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag',
        '_wpcf7_is_ajax_call','cfdb7_name', '_wpcf7_container_post','_wpcf7cf_hidden_group_fields',
        '_wpcf7cf_hidden_groups', '_wpcf7cf_visible_groups', '_wpcf7cf_options','g-recaptcha-response');

        $data           = $form->get_posted_data();
        $files          = $form->uploaded_files();
        $uploaded_files = array();

        $rm_underscore  = apply_filters('cfdb7_remove_underscore_data', true); 

        foreach ($_FILES as $file_key => $file) {
            array_push($uploaded_files, $file_key);
        }

        foreach ($files as $file_key => $file) {
            copy($file, $cfdb7_dirname.'/'.$time_now.'-'.basename($file));
        }

        $form_data   = array();

        $form_data['cfdb7_status'] = 'unread';
        foreach ($data as $key => $d) {
            
            $matches = array();
            if( $rm_underscore ) preg_match('/^_.*$/m', $key, $matches);

            if ( !in_array($key, $black_list ) && !in_array($key, $uploaded_files ) && empty( $matches[0] ) ) {

                $tmpD = $d;

                if ( ! is_array($d) ){

                    $bl   = array('\"',"\'",'/','\\','"',"'");
                    $wl   = array('&quot;','&#039;','&#047;', '&#092;','&quot;','&#039;');

                    $tmpD = str_replace($bl, $wl, $tmpD );
                }

                $form_data[$key] = $tmpD;
            }
            if ( in_array($key, $uploaded_files ) ) {
                $file_name = isset( $files[ $key ] ) ? $time_now.'-'.basename( $files[ $key ])  : ''; 
                $form_data[$key.'cfdb7_file'] = $file_name;
            }
        }

        /* cfdb7 before save data. */
        $form_data = apply_filters('cfdb7_before_save_data', $form_data);

        do_action( 'cfdb7_before_save', $form_data );

        $form_post_id = $form_tag->id();
        $form_value   = serialize( $form_data );
        $form_date    = current_time('Y-m-d H:i:s');

        $cfdb->insert( $table_name, array(
            'form_post_id' => $form_post_id,
            'form_value'   => $form_value,
            'form_date'    => $form_date
        ) );

        /* cfdb7 after save data */
        $insert_id = $cfdb->insert_id;
        do_action( 'cfdb7_after_save_data', $insert_id );
    }

}

add_action( 'wpcf7_before_send_mail', 'cfdb7_before_send_mail' );


add_action( 'init', 'cfdb7_init');

/**
 * CFDB7 cfdb7_init and cfdb7_admin_init
 * Admin setting
 */
function cfdb7_init(){

    do_action( 'cfdb7_init' );

    if( is_admin() ){

        require_once 'inc/admin-mainpage.php';
        require_once 'inc/admin-subpage.php';
        require_once 'inc/admin-form-details.php';
        require_once 'inc/export-csv.php';

        do_action( 'cfdb7_admin_init' );

        $csv = new Export_CSV();
        if( isset($_REQUEST['csv']) && ( $_REQUEST['csv'] == true ) && isset( $_REQUEST['nonce'] ) ) {

            $nonce  = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_STRING );

            if ( ! wp_verify_nonce( $nonce, 'dnonce' ) ) wp_die('Invalid nonce..!!');

            $csv->download_csv_file();
        }
        new Cfdb7_Wp_Main_Page();
    }
}


add_action( 'admin_notices', 'cfdb7_admin_notice' );
add_action('admin_init', 'cfdb7_view_ignore_notice' );

function cfdb7_admin_notice() {

    $install_date = get_option( 'cfdb7_view_install_date', '');
    $install_date = date_create( $install_date );
    $date_now     = date_create( date('Y-m-d G:i:s') );
    $date_diff    = date_diff( $install_date, $date_now );

    if ( $date_diff->format("%d") < 7 ) {

        return false;
    }

    if ( ! get_option( 'cfdb7_view_ignore_notice' ) ) {

        echo '<div class="updated"><p>';

        printf(__( 'Awesome, you\'ve been using <a href="admin.php?page=cfdb7-list.php">Contact Form CFDB7</a> for more than 1 week. May we ask you to give it a 5-star rating on WordPress? | <a href="%2$s" target="_blank">Ok, you deserved it</a> | <a href="%1$s">I already did</a> | <a href="%1$s">No, not good enough</a>', 'contact-form-cfdb7' ), '?cfdb7-ignore-notice=0',
        'https://wordpress.org/plugins/contact-form-cfdb7/');
        echo "</p></div>";
    }
}

function cfdb7_view_ignore_notice() {

    if ( isset($_GET['cfdb7-ignore-notice']) && '0' == $_GET['cfdb7-ignore-notice'] ) {

        update_option( 'cfdb7_view_ignore_notice', 'true' );
    }
}

/**
 * Plugin settings link
 * @param  array $links list of links
 * @return array of links
 */
function cfdb7_settings_link( $links ) {
    $forms_link = '<a href="admin.php?page=cfdb7-list.php">Contact Forms</a>';
    array_unshift($links, $forms_link);
    return $links;
}

$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'cfdb7_settings_link' );


/**
 * Load language files to enable plugin translation
 *
 * @since 1.2.4.1
 */
function cfdb7_load_textdomain() {
	load_plugin_textdomain( 'contact-form-cfdb7', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'cfdb7_load_textdomain' );
