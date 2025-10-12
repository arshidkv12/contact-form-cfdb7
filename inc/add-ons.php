<?php

/**
 * CFDB7 Add-ons Submenu under Contact Form 7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register "CFDB7 Extensions" submenu under Contact Form 7
 */
function cfdb7_register_extensions_submenu() {

    // Ensure CF7 is active
    if ( ! class_exists( 'WPCF7' ) ) {
        add_action( 'admin_notices', 'cfdb7_cf7_required_notice' );
        return;
    }

    // Capability check
    $cfdb7_cap = ( current_user_can( 'cfdb7_access' ) ) ? 'cfdb7_access' : 'manage_options';

    // Register submenu under CF7
    add_submenu_page(
        'wpcf7', // Parent slug
        __( 'CFDB7 Extensions', 'contact-form-cfdb7' ), // Page title
        __( 'CFDB7 Extensions', 'contact-form-cfdb7' ), // Menu title
        $cfdb7_cap,
        'cfdb7-extensions',
        function () { // Inline callback avoids dependency timing issues
            ?>
            <div class="wrap">
                <h1>
                    <?php esc_html_e( 'Extensions for CFDB7', 'contact-form-cfdb7' ); ?>
                    <a class="page-title-action" href="https://ciphercoin.com/contact-form-7-database-cfdb7-add-ons/" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'Browse All Extensions', 'contact-form-cfdb7' ); ?>
                    </a>
                </h1>
                <p><?php esc_html_e( 'Add extra features to CFDB7 with these extensions.', 'contact-form-cfdb7' ); ?></p>
                <?php echo wp_kses_post( cfdb7_add_ons_get_feed() ); ?>
            </div>
            <?php
        }
    );
}
add_action( 'admin_menu', 'cfdb7_register_extensions_submenu', 99 );

/**
 * Admin notice shown when CF7 is not active
 */
function cfdb7_cf7_required_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            echo wp_kses_post(
                __( 'CFDB7 requires <a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">Contact Form 7</a> to be active.', 'contact-form-cfdb7' )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Add-ons Get Feed
 *
 * Gets the add-ons page feed.
 *
 * @since 1.0
 * @return void
 */
function cfdb7_add_ons_get_feed(){
	$cache = get_transient( 'cfdb7_add_ons_feed' );
	if ( false === $cache ) {
		$url = 'https://ciphercoin.com/cfdb7/?feed=true';
		$feed = wp_remote_get( esc_url_raw( $url ), array(
					'sslverify' => false,
					'timeout'     => 30,
				) );
		
		if ( ! is_wp_error( $feed ) ) {
			if ( isset( $feed['body'] ) && strlen( $feed['body'] ) > 0 ) {
				$cache = wp_remote_retrieve_body( $feed );
				set_transient( 'cfdb7_add_ons_feed', $cache, 3600 );
			}
		} else {
			$cache = '<div class="error"><p>' . __( 'There was an error retrieving the extensions list from the server. Please try again later.', 'contact-form-cfdb7' ) . '</div>';
		}
	}
	return $cache;
}
delete_transient('cfdb7_add_ons_feed');