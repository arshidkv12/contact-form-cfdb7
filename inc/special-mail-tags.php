<?php
/**
 * CFDB7 Special Mail Tags
 *
 * Captures CF7 special mail tags used in form mail templates
 * and saves them alongside form submission data.
 *
 * @since 1.3.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get the list of CF7 special mail tags supported by CFDB7.
 *
 * @since 1.3.6
 * @return array
 */
function cfdb7_get_special_mail_tags() {
	return apply_filters( 'cfdb7_special_mail_tags', array(
		'_remote_ip', '_user_agent', '_url', '_date', '_time',
		'_post_id', '_post_name', '_post_title', '_post_url',
		'_post_author', '_post_author_email', '_serial_number',
		'_site_title', '_site_description', '_site_url', '_site_admin_email',
		'_user_login', '_user_email', '_user_url',
	) );
}

/**
 * Resolve a special mail tag value using CF7's own wpcf7_special_mail_tags filter.
 *
 * @since 1.3.6
 * @param string $tag_name The special tag name (e.g. '_remote_ip').
 * @return string The resolved value.
 */
function cfdb7_resolve_special_mail_tag( $tag_name ) {
	return apply_filters( 'wpcf7_special_mail_tags', '', $tag_name, false, null );
}

/**
 * Detect which special mail tags are used in the form's mail templates
 * and add their resolved values to the form data before saving.
 *
 * Hooked to cfdb7_before_save_data.
 *
 * @since 1.3.6
 * @param array $form_data The form data to be saved.
 * @return array Modified form data with special mail tags appended.
 */
function cfdb7_add_special_mail_tags( $form_data ) {
	$submission = WPCF7_Submission::get_instance();

	if ( ! $submission ) {
		return $form_data;
	}

	$contact_form = $submission->get_contact_form();

	if ( ! $contact_form ) {
		return $form_data;
	}

	$all_special_tags = cfdb7_get_special_mail_tags();

	// Collect all mail template content (mail + mail_2)
	$mail_properties = (array) $contact_form->prop( 'mail' );
	$mail_2 = (array) $contact_form->prop( 'mail_2' );

	if ( ! empty( $mail_2['active'] ) ) {
		$mail_properties = array_merge_recursive( $mail_properties, $mail_2 );
	}

	$mail_string = implode( ' ', array_map( function( $v ) {
		return is_array( $v ) ? implode( ' ', $v ) : (string) $v;
	}, $mail_properties ) );

	// Find which special tags are actually used in the mail templates
	$active_tags = array();
	foreach ( $all_special_tags as $tag ) {
		if ( strpos( $mail_string, '[' . $tag . ']' ) !== false ) {
			$active_tags[] = $tag;
		}
	}

	/**
	 * Filter the list of special mail tags that will be saved for this form.
	 *
	 * @since 1.3.6
	 * @param array              $active_tags   Tags detected in mail templates.
	 * @param WPCF7_ContactForm  $contact_form  The contact form instance.
	 */
	$active_tags = apply_filters( 'cfdb7_active_special_mail_tags', $active_tags, $contact_form );

	foreach ( $active_tags as $tag ) {
		if ( isset( $form_data[ $tag ] ) ) {
			continue;
		}

		// _serial_number is CFDB7-specific, not a native CF7 tag
		if ( $tag === '_serial_number' ) {
			global $wpdb;
			$cfdb       = apply_filters( 'cfdb7_database', $wpdb );
			$table_name = $cfdb->prefix . 'db7_forms';
			$count      = $cfdb->get_var(
				$cfdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE form_post_id = %d",
					$contact_form->id()
				)
			);
			$form_data[ $tag ] = (string) ( intval( $count ) + 1 );
			continue;
		}

		// Delegate to CF7's native special mail tag resolution
		$value = cfdb7_resolve_special_mail_tag( $tag );

		if ( $value !== '' && $value !== false && $value !== null ) {
			$form_data[ $tag ] = sanitize_text_field( (string) $value );
		}
	}

	return $form_data;
}

add_filter( 'cfdb7_before_save_data', 'cfdb7_add_special_mail_tags' );
