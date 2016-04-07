<?php
/**
 * Plugin Name:  Mailjet wp_mail
 * Plugin URI:   https://github.com/tcrsavage/mailjet-wp-mail
 * Description:  Drop-in replacement for standard wp_mail function using mailjet API integration
 * Version:      0.0.1
 * Author:       tcrsavage, sanchothefat
 * Author URI:   https://github.com/tcravage
 * License:      GPL-2.0+
 * License URI:  http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'MAILJET_API_KEY' ) || ! defined( 'MAILJET_SECRET_KEY' ) ) {
	return;
}

require_once __DIR__ . '/inc/classes/class-mailjet-wp-mail.php';

/**
 * Override WordPress' default wp_mail function with one that sends email
 * using Mailjet's API.
 *
 * Note that this function requires the MAILJET_API_KEY and MAILJET_SECRET_KEY constants to be defined
 * in order for it to work. The easiest place to define this is in wp-config.
 *
 * @since  0.0.1
 * @access public
 * @param  string $to
 * @param  string $subject
 * @param  string $message
 * @param  mixed $headers
 * @param  array $attachments
 * @return bool true if mail has been sent, false if it failed
 */
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {

	return Mailjet_WP_Mail::send( $to, $subject, $message, $headers, $attachments );
}
