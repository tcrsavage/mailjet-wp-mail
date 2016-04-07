<?php

/**
 * API Integration for Mailjet transactional emails.
 *
 * Class Mailjet_WP_Mail
 */
class Mailjet_WP_Mail {

	/**
	 * Mailjet base API URL
	 *
	 * @var string
	 */
	static $base_url = 'https://api.mailjet.com/v3/';

	/**
	 * Send an email via mailjet (accepts wp_mail style arguments)
	 *
	 * @param array $to                   Send to email can be comma separated list or array of emails
	 * @param string $subject             Subject string
	 * @param string $message             Message string (expects html markup)
	 * @param string $headers             Headers string (Reply-To, CC etc)
	 * @param array $attachments          Attachments array of file paths
	 * @param array $request_body         Override array to define explicit API call body params (overrides internal handling and filtering)
	 * @return bool
	 */
	static function send( $to, $subject, $message, $headers = '', $attachments = array(), $request_body = array() ) {

		$body = array(
			'Subject'     => $subject,
			'Html-part'   => $message,
		);

		$body     = static::parse_send_args( $to, $subject, $message, $headers, $attachments, $body );
		$body     = wp_parse_args( $request_body, $body );

		$response =  static::request( 'POST', 'send', $body );

		if ( is_wp_error( $response ) || empty( $response['response']['code'] ) || $response['response']['code'] > 200 ) {
			return false;
		}

		return true;
	}

	/**
	 * Make a request to the Mailjet v3 API
	 *
	 * @param $method
	 * @param $path
	 * @param $body
	 * @param array $headers
	 * @return bool
	 */
	static function request( $method, $path, $body, $headers = array() ) {

		$http = new \WP_Http();

		$response = $http->request( static::$base_url . $path, array(
			'method'       => $method,
			'timeout'      => 15,
			'httpversion'  => '1.1',
			'headers'      => wp_parse_args( $headers, array(
				'Content-Type'   => 'application/json',
				'Authorization'  => sprintf( 'Basic %s', base64_encode( sprintf( '%s:%s', MAILJET_API_KEY, MAILJET_SECRET_KEY ) ) )
			) ),
			'body' => json_encode( $body )
		) );

		return $response;
	}

	/**
	 * Parse wp_mail compatible args for use with the Mailjet API
	 *
	 * @param $to
	 * @param $subject
	 * @param $message
	 * @param $headers
	 * @param $attachments
	 * @param $body
	 * @return mixed|void
	 */
	protected static function parse_send_args( $to, $subject, $message, $headers, $attachments, $body ) {

		$body = static::parse_to_email( $to, $body );
		$body = static::parse_headers( $headers, $body );
		$body = static::parse_attachments( $attachments, $body );

		return apply_filters( 'mailjet_wp_mail_body', $body );
	}

	/**
	 * Parse wp_mail compatible to email for use with the Mailjet API
	 *
	 * @param $to
	 * @param $body
	 * @return mixed|void
	 */
	protected static function parse_to_email( $to, $body ) {

		if ( ! is_array( $to ) ) {
			$to = explode( ',', $to );
		}

		$to = apply_filters( 'mailjet_wp_mail_to_email', $to );

		$body['Recipients']  = array_map( function( $email ) {
			return (object) array( 'Email' => $email );
		}, $to );

		return $body;
	}

	/**
	 * Parse wp_mail compatible headers for use with the Mailjet API
	 *
	 * @param $headers
	 * @param $body
	 * @return array|mixed|void
	 */
	protected static function parse_headers( $headers, $body ) {

		if ( ! is_array( $body ) ) {
			return $body;
		}

		// Prepare the passed headers.
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}

		// Bail if we don't have any headers to work with.
		if ( empty( $headers ) ) {
			return $body;
		}

		foreach ( (array) $headers as $index => $header ) {

			if ( false === strpos( $header, ':' ) ) {
				continue;
			}

			// Explode them out
			list( $name, $content ) = explode( ':', trim( $header ), 2 );

			// Cleanup crew
			$name    = trim( $name );
			$content = trim( $content );

			switch ( strtolower( $name ) ) {

				// Mailjet handles these separately
				case 'subject':
				case 'to':
					unset( $headers[ $index ] );
					break;

				case 'from':

					$bracket_pos = strpos( $content, '<' );

					if ( $bracket_pos !== false ) {

						// Text before the bracketed email is the "From" name.
						if ( $bracket_pos > 0 ) {
							$from_name = trim( str_replace( '"', '', substr( $content, 0, $bracket_pos - 1 ) ) );
						}

						$from_email = trim( str_replace( '>', '', substr( $content, $bracket_pos + 1 ) ) );

						// Avoid setting an empty $from_email.
					} elseif ( '' !== trim( $content ) ) {

						$from_email = trim( $content );
					}

					break;

				default:
					// Add it to our grand headers array
					$body['Headers'][ trim( $name ) ] = trim( $content );
					break;
			}
		}

		// Need a default from email
		if ( ! isset( $from_email ) ) {

			// Get the site domain and get rid of www.
			$sitename   = strtolower( $_SERVER['SERVER_NAME'] );
			$sitename   = ( substr( $sitename, 0, 4 ) == 'www.' ) ? substr( $sitename, 4 ) : $sitename;
			$from_email = 'wordpress@' . $sitename;
		}

		// Need a default from name
		if ( ! isset( $from_name ) ) {
			$from_name  = 'Wordpress';
		}

		$body['FromName']  = apply_filters( 'wp_mail_from_name', $from_name );
		$body['FromEmail'] = apply_filters( 'wp_mail_from', $from_email );

		return $body;
	}

	/**
	 * Parse wp_mail compatible attachments for use with the Mailjet API
	 *
	 * @param $attachments
	 * @param $body
	 * @return mixed|void
	 */
	protected static function parse_attachments( $attachments, $body ) {

		// Ensure we are working with an array of file paths
		if ( ! is_array( $attachments ) ) {
			$attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );
		}

		// Create attachment objects from file paths
		$attachments = array_map( function( $attachment ) {

			// File not available, skip
			if ( ! is_readable( $attachment ) || ! mime_content_type( $attachment ) ) {
				return false;
			}

			// Get file name
			$name = end( ( explode( '/', str_replace( '\\', '/', $attachment ) ) ) );

			//Create object with content type, name and base64 encoded contents
			return array(
				'Content-type' => mime_content_type( $attachment ),
				'Filename'     => $name,
				'content'      => base64_encode( file_get_contents( $attachment ) )
			);

		}, $attachments );

		// Filter attachments which couldn't be parsed
		$attachments = array_filter( $attachments );

		// Add to send request body
		$body['Attachments'] = $attachments;

		return $body;
	}
}
