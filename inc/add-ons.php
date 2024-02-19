<?php

add_submenu_page('cfdb7-list.php', __( 'Extensions', 'contact-form-cfdb7' ), __( 'Extensions', 'contact-form-cfdb7' ), 'manage_options', 'cfdb7-extensions',  'cfdb7_extensions' );

/**
 * Extensions page
 */
function cfdb7_extensions(){
    ?>
    <div class="wrap">
        <h2><?php _e( 'Extensions for CFDB7', 'contact-form-cfdb7' ); ?>
            <span>
                <a class="button-primary" href="https://ciphercoin.com/contact-form-7-database-cfdb7-add-ons/"><?php _e( 'Browse All Extensions', 'contact-form-cfdb7' ); ?></a>
            </span>
        </h2>
        <p><?php _e( 'These extensions <strong>add functionality</strong> to CFDB7', 'contact-form-cfdb7' ); ?></p>
        <?php echo cfdb7_add_ons_get_feed(); ?>
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
// delete_transient('cfdb7_add_ons_feed');