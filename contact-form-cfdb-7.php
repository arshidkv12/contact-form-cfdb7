<?php
/*
Plugin name: Contact Form CFDB7
Plugin URI: http://ciphercoin.com/
Description: Save and manage Contact Form 7 messages. Never lose important data. Contact Form CFDB7 plugin is an add-on for the Contact Form 7 plugin.
Author: Arshid
Author URI: http://ciphercoin.com/
Text Domain: contact-form-cfdb7
Version: 1.1.6
*/


register_activation_hook( __FILE__, 'cfdb7_pugin_activation' );
function cfdb7_pugin_activation(){

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
        '_wpcf7cf_hidden_groups', '_wpcf7cf_visible_groups', '_wpcf7cf_options');

        $data           = $form->get_posted_data();
        $files          = $form->uploaded_files();
        $uploaded_files = array();

        foreach ($files as $file_key => $file) {
            array_push($uploaded_files, $file_key);
            copy($file, $cfdb7_dirname.'/'.$time_now.'-'.basename($file));
        }

        $form_data   = array();

        $form_data['cfdb7_status'] = 'unread';
        foreach ($data as $key => $d) {
            if ( !in_array($key, $black_list ) && !in_array($key, $uploaded_files ) ) {

                $tmpD = $d;

                if ( ! is_array($d) ){

                    $bl   = array('\"',"\'",'/','\\');
                    $wl   = array('&quot;','&#039;','&#047;', '&#092;');

                    $tmpD = str_replace($bl, $wl, $tmpD );
                }

                $form_data[$key] = $tmpD;
            }
            if ( in_array($key, $uploaded_files ) ) {
                $form_data[$key.'cfdb7_file'] = $time_now.'-'.$d;
            }
        }

        /* cfdb7 before save data. */
        $form_data = apply_filters('cfdb7_before_save_data', $form_data); //Combine form data with any external hooks
        do_action( 'cfdb7_before_save_data', $form_data );

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

        $csv = new Expoert_CSV();
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

    global $current_user ;
    $user_id = $current_user->ID;

    if ( ! get_user_meta($user_id, 'cfdb7_view_ignore_notice' ) ) {

        echo '<div class="updated"><p>';

        printf(__('Awesome, you\'ve been using <a href="admin.php?page=cfdb7-list.php">Contact Form CFDB7</a> for more than 1 week. May we ask you to give it a 5-star rating on WordPress? | <a href="%2$s" target="_blank">Ok, you deserved it</a> | <a href="%1$s">I already did</a> | <a href="%1$s">No, not good enough</a>'), '?cfdb7-ignore-notice=0',
        'https://wordpress.org/plugins/contact-form-cfdb7/');
        echo "</p></div>";
    }
}

function cfdb7_view_ignore_notice() {
    global $current_user;
    $user_id = $current_user->ID;

    if ( isset($_GET['cfdb7-ignore-notice']) && '0' == $_GET['cfdb7-ignore-notice'] ) {

        add_user_meta($user_id, 'cfdb7_view_ignore_notice', 'true', true);
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
