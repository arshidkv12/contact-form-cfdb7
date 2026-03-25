<?php
/*
Plugin name: Contact Form CFDB7
Plugin URI: https://ciphercoin.com/
Description: Save and manage Contact Form 7 messages. Never lose important data. Contact Form CFDB7 plugin is an add-on for the Contact Form 7 plugin.
Author: Arshid
Author URI: http://ciphercoin.com/
Text Domain: contact-form-cfdb7
License: GPL v2 or later
Domain Path: /languages/
Version: 1.3.5
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
        $fp = fopen( $cfdb7_dirname.'/index.php', 'w');
        fwrite($fp, "<?php \n\t // Silence is golden.");
        fclose( $fp );
    }
    add_option( 'cfdb7_view_install_date', date('Y-m-d G:i:s'), '', 'yes');

}

function cfdb7_on_activate( $network_wide ){

    global $wpdb;
    if ( is_multisite() && $network_wide ) {
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            cfdb7_create_table();
            restore_current_blog();
        }
    } else {
        cfdb7_create_table();
    }

	$role = get_role( 'administrator' );
	$role->add_cap( 'cfdb7_access' );
}

register_activation_hook( __FILE__, 'cfdb7_on_activate' );


function cfdb7_upgrade_function( $upgrader_object, $options ) {

    $upload_dir    = wp_upload_dir();
    $cfdb7_dirname = $upload_dir['basedir'].'/cfdb7_uploads';

    if ( file_exists( $cfdb7_dirname.'/index.php' ) ) return;
        
    if ( file_exists( $cfdb7_dirname ) ) {
        $fp = fopen( $cfdb7_dirname.'/index.php', 'w');
        fwrite($fp, "<?php \n\t // Silence is golden.");
        fclose( $fp );
    }

}

add_action( 'upgrader_process_complete', 'cfdb7_upgrade_function',10, 2);



function cfdb7_on_deactivate() {

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
    $bytes         = random_bytes(5);
    $time_now      = time().bin2hex($bytes);

    $submission   = WPCF7_Submission::get_instance();
    $contact_form = $submission->get_contact_form();
    $tags_names   = array();
    $strict_keys  = apply_filters('cfdb7_strict_keys', false);  

    if ( $submission ) {

        $allowed_tags = array();
        $bl   = array('\"',"\'",'/','\\','"',"'");
        $wl   = array('&quot;','&#039;','&#047;', '&#092;','&quot;','&#039;');

        if( $strict_keys ){
            $tags  = $contact_form->scan_form_tags();
            foreach( $tags as $tag ){
                if( ! empty($tag->name) ) $tags_names[] = $tag->name;
            }
            $allowed_tags = $tags_names;
        }

        $not_allowed_tags = apply_filters( 'cfdb7_not_allowed_tags', array( 'g-recaptcha-response' ) );
        $allowed_tags     = apply_filters( 'cfdb7_allowed_tags', $allowed_tags );
        $data             = $submission->get_posted_data();
        $files            = $submission->uploaded_files();
        $uploaded_files   = array();


        foreach ($_FILES as $file_key => $file) {
            array_push($uploaded_files, $file_key);
        }
        
        /**
         * Filters the uploaded files array before copying to cfdb7_uploads.
         *
         * Return an empty array to prevent all files from being copied.
         *
         * @since 1.3.6
         * @param array $files Uploaded files from the CF7 submission.
         */
        $files = apply_filters( 'cfdb7_before_file_copy', $files );

        foreach ($files as $file_key => $file) {
            $file = is_array($file) ? reset($file) : $file;
            if (empty($file)) continue;
            copy($file, $cfdb7_dirname . '/' . $time_now . '-' . $file_key . '-' . basename($file));
        }

        $form_data   = array();

        $form_data['cfdb7_status'] = 'unread';
        foreach ($data as $key => $d) {
            
            if( $strict_keys && !in_array($key, $allowed_tags) ) continue;

            if ( !in_array($key, $not_allowed_tags ) && !in_array($key, $uploaded_files )  ) {

                $tmpD = $d;

                if ( ! is_array($d) ){
                    $tmpD = str_replace($bl, $wl, $tmpD );
                }else{
                    $tmpD = array_map(function($item) use($bl, $wl){
                               return str_replace($bl, $wl, $item ); 
                            }, $tmpD);
                }

                $key = sanitize_text_field( $key );
                $form_data[$key] = $tmpD;
            }
            if ( in_array($key, $uploaded_files ) ) {
                $file = is_array( $files[ $key ] ) ? reset( $files[ $key ] ) : $files[ $key ];
                $file_name = empty( $file ) ? '' : $time_now.'-'.$key.'-'.basename( $file ); 
                $key = sanitize_text_field( $key );
                $form_data[$key.'cfdb7_file'] = sanitize_text_field($file_name);
            }
        }

        /**
         * Capture CF7 special mail tags used in the form's mail templates.
         * Only saves tags that are actually referenced in the mail body/subject/headers.
         *
         * @since 1.3.6
         */
        $all_special_tags = cfdb7_get_special_mail_tags();

        $mail_properties = (array) $contact_form->prop( 'mail' );
        $mail_2 = (array) $contact_form->prop( 'mail_2' );
        if ( ! empty( $mail_2['active'] ) ) {
            $mail_properties = array_merge_recursive( $mail_properties, $mail_2 );
        }
        $mail_string = implode( ' ', array_map( function( $v ) {
            return is_array( $v ) ? implode( ' ', $v ) : (string) $v;
        }, $mail_properties ) );

        $special_mail_tags = array();
        foreach ( $all_special_tags as $tag ) {
            if ( strpos( $mail_string, '[' . $tag . ']' ) !== false ) {
                $special_mail_tags[] = $tag;
            }
        }

        $special_mail_tags = apply_filters( 'cfdb7_active_special_mail_tags', $special_mail_tags, $contact_form );

        $meta_map = array(
            '_remote_ip'   => 'remote_ip',
            '_user_agent'  => 'user_agent',
            '_url'         => 'url',
            '_date'        => 'timestamp',
            '_time'        => 'timestamp',
        );

        foreach ( $special_mail_tags as $tag ) {
            if ( isset( $form_data[$tag] ) ) continue;

            $value = '';

            if ( isset( $meta_map[$tag] ) ) {
                $meta_value = $submission->get_meta( $meta_map[$tag] );
                if ( $tag === '_date' && $meta_value ) {
                    $value = wp_date( get_option( 'date_format' ), $meta_value );
                } elseif ( $tag === '_time' && $meta_value ) {
                    $value = wp_date( get_option( 'time_format' ), $meta_value );
                } else {
                    $value = $meta_value ? $meta_value : '';
                }
            } elseif ( $tag === '_post_id' ) {
                $value = $submission->get_meta( 'container_post_id' );
            } elseif ( $tag === '_post_name' && $submission->get_meta( 'container_post_id' ) ) {
                $post = get_post( $submission->get_meta( 'container_post_id' ) );
                $value = $post ? $post->post_name : '';
            } elseif ( $tag === '_post_title' && $submission->get_meta( 'container_post_id' ) ) {
                $value = get_the_title( $submission->get_meta( 'container_post_id' ) );
            } elseif ( $tag === '_post_url' && $submission->get_meta( 'container_post_id' ) ) {
                $value = get_permalink( $submission->get_meta( 'container_post_id' ) );
            } elseif ( $tag === '_post_author' && $submission->get_meta( 'container_post_id' ) ) {
                $post = get_post( $submission->get_meta( 'container_post_id' ) );
                $value = $post ? get_the_author_meta( 'display_name', $post->post_author ) : '';
            } elseif ( $tag === '_post_author_email' && $submission->get_meta( 'container_post_id' ) ) {
                $post = get_post( $submission->get_meta( 'container_post_id' ) );
                $value = $post ? get_the_author_meta( 'user_email', $post->post_author ) : '';
            } elseif ( $tag === '_site_title' ) {
                $value = get_bloginfo( 'name' );
            } elseif ( $tag === '_site_url' ) {
                $value = get_bloginfo( 'url' );
            } elseif ( $tag === '_site_admin_email' ) {
                $value = get_option( 'admin_email' );
            } elseif ( $tag === '_user_login' && is_user_logged_in() ) {
                $user  = wp_get_current_user();
                $value = $user->user_login;
            } elseif ( $tag === '_user_email' && is_user_logged_in() ) {
                $user  = wp_get_current_user();
                $value = $user->user_email;
            } elseif ( $tag === '_user_url' && is_user_logged_in() ) {
                $user  = wp_get_current_user();
                $value = $user->user_url;
            } elseif ( $tag === '_serial_number' ) {
                $value = $cfdb->get_var(
                    $cfdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE form_post_id = %d",
                        $form_tag->id()
                    )
                );
                $value = intval( $value ) + 1;
            }

            if ( $value !== '' && $value !== false && $value !== null ) {
                $form_data[$tag] = sanitize_text_field( (string) $value );
            }
        }

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

        $insert_id = $cfdb->insert_id;
        do_action( 'cfdb7_after_save_data', $insert_id, $form_data );
    }

}

add_action( 'wpcf7_before_send_mail', 'cfdb7_before_send_mail' );


/**
 * Get the list of CF7 special mail tags saved by CFDB7.
 *
 * @since 1.3.6
 * @return array
 */
function cfdb7_get_special_mail_tags() {
    return apply_filters( 'cfdb7_special_mail_tags', array(
        '_remote_ip', '_user_agent', '_url', '_date', '_time',
        '_post_id', '_post_name', '_post_title', '_post_url',
        '_post_author', '_post_author_email', '_serial_number',
        '_site_title', '_site_url', '_site_admin_email',
        '_user_login', '_user_email', '_user_url',
    ) );
}


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

        $csv = new CFDB7_Export_CSV();
        if( isset($_REQUEST['csv']) && ( $_REQUEST['csv'] == true ) && isset( $_REQUEST['nonce'] ) ) {

            $nonce  = $_REQUEST['nonce'];

            if ( ! wp_verify_nonce( $nonce, 'dnonce' ) ) wp_die('Invalid nonce..!!');

            $csv->download_csv_file();
        }
        new Cfdb7_Wp_Main_Page();
    }
}


add_action( 'admin_notices', 'cfdb7_admin_notice' );
add_action('admin_init', 'cfdb7_view_ignore_notice' );

function cfdb7_admin_notice() {

    if( get_option('cfdb7_view_ignore_notice') ){
        return;
    }

    if( empty($_GET['page']) || empty($_GET['fid']) || $_GET['page'] != 'cfdb7-list.php'){
        return;
    }

    global $wpdb;

    $form_post_id  = (int) $_GET['fid'];
    $cfdb          = apply_filters( 'cfdb7_database', $wpdb );
    $table_name    = $cfdb->prefix.'db7_forms';
    $totalItems    = $cfdb->get_var("SELECT COUNT(*) FROM $table_name WHERE form_post_id = '$form_post_id'");

    if($totalItems < 1){
        return;
    }

    $message = _n(
        '🎉 Nice! CFDB7 has successfully saved your form entry.',
        '🎉 Nice! CFDB7 has successfully saved your form entries.',
        $totalItems,
        'contact-form-cfdb7'
    );

    echo '<div class="notice notice-success is-dismissible"><p>';

    printf(
        __(
            '%1$s If it\'s helpful, would you consider leaving a <strong>5-star review</strong> on WordPress?<br><br>
            <a href="%3$s" class="button button-primary" target="_blank">⭐ Leave a review</a>
            <a href="%2$s" class="button-link-delete">I already did</a> |
            <a href="%2$s" class="button-link-delete">No, thanks</a>',
            'contact-form-cfdb7'
        ),
        esc_html( $message ),
        esc_url( add_query_arg(
                [
                    'page'                => 'cfdb7-list.php',
                    'fid'                 => (int) $form_post_id,
                    'cfdb7-ignore-notice' => 1,
                ],
                admin_url( 'admin.php' )
        ) ),
        esc_url( 'https://wordpress.org/plugins/contact-form-cfdb7/' )
    );

    echo '</p></div>';
    
}

function cfdb7_view_ignore_notice() {

    if ( isset($_GET['cfdb7-ignore-notice']) && '1' == $_GET['cfdb7-ignore-notice'] ) {

        update_option( 'cfdb7_view_ignore_notice', 'true' );
    }
}

/**
 * Plugin settings link
 * @param  array $links list of links
 * @return array of links
 */
function cfdb7_settings_link( $links ) {
    $forms_link = '<a href="admin.php?page=cfdb7-list.php">' . __( 'Submissions', 'contact-form-cfdb7' ) . '</a>';
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