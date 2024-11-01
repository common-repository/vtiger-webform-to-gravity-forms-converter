<?php
/**
 * Class to post Gravity Forms form to Vtiger.
 *
 * @package VWTGF_CONVERTER\Actions
 */

namespace VWTGF_CONVERTER\Actions;

use GFFormsModel;

/**
 * Class PostToVtiger
 */
class PostToVtiger {

	/**
	 * Base path for API routes.
	 *
	 * @var string
	 */
	public static $vtiger_url;

	/**
	 * Init all filter and action hooks so that they can be used.
	 *
	 * @see https://wordpress.org/gutenberg/handbook/blocks/writing-your-first-block-type/#enqueuing-block-scripts
	 */
	public function init() {
		add_action( 'gform_after_submission', [ $this, 'vwtgf_converter_post_to_vtiger' ], 10, 2 );
		add_filter( 'http_request_host_is_external', [ $this, 'http_request_host_is_external' ], 10, 3 );
		add_filter( 'vwtgf_converter_request_args', [ $this, 'disable_ssl' ], 0, 1 );
	}

	/**
	 * Convert the Gravity Forms Post to a Vtiger Post and post it.
	 *
	 * @param array  $entry the entry.
	 * @param object $form The form object.
	 */
	public function vwtgf_converter_post_to_vtiger( $entry, $form ) {
		/*
		 * Check if the entry is spam.
		 */
		if ( rgar( $entry, 'status' ) === 'spam' ) {
			return;
		}

		$is_vtiger_webform = false;

		/*
		 * Check if it is a Vtiger Form.
		 */
		foreach ( $form['fields'] as $field ) {
			if ( 'vtiger_POST_url' === $field['adminLabel'] ) {
				$is_vtiger_webform = true;
				self::$vtiger_url  = $field['defaultValue'];
				break;
			}
		}

		if ( ! $is_vtiger_webform ) {
			return;
		}

		/*
		 *  Boundary-Token für multipart/form-data.
		 */
		$boundary = uniqid();

		/**
		 * Allow to modify the boundry.
		 *
		 * @param array $headers The headers to post to Vtiger.
		 * @param object $form The form object.
		 */
		$boundary = gf_apply_filters( [ 'vwtgf_converter_post_boundary', $form['id'] ], $boundary, $form );

		/*
		 * Build headers.
		 */
		$headers = [
			'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
		];

		/**
		 * Allow to modify the headers before posting it to Vtiger.
		 *
		 * @param array $headers The headers to post to Vtiger.
		 * @param object $form The form object.
		 */
		$headers = gf_apply_filters( [ 'vwtgf_converter_post_headers', $form['id'] ], $headers, $form );

		/*
		 * Create payload and post to Vtiger Post.
		 */
		$payload = '';
		foreach ( $form['fields'] as $field ) {
			if ( 'vtiger_POST_url' === $field['adminLabel'] ) {
				continue;
			}

			if ( 'date' === $field['type'] && ( ! empty( rgpost( 'input_' . $field['id'] )[0] ) ) ) {
				$date_array = wp_unslash( rgpost( 'input_' . $field['id'] ) );
				$date       = implode( '-', $date_array );
				$payload   .= '--' . $boundary . "\r\n";
				$payload   .= 'Content-Disposition: form-data; name="' . $field['adminLabel'] . '"' . "\r\n\r\n";
				$payload   .= $date . "\r\n";
			} elseif ( 'time' === $field['type'] ) {
				$time_array = wp_unslash( rgpost( 'input_' . $field['id'] ) );
				if ( isset( $time_array[2] ) && 'pm' === $time_array[2] ) {
					$time_array[0] += 12;
				}
				$time = sprintf( '%02d', $time_array[0] ) . ':' . sprintf( '%02d', $time_array[1] );

				$payload .= '--' . $boundary . "\r\n";
				$payload .= 'Content-Disposition: form-data; name="' . $field['adminLabel'] . '"' . "\r\n\r\n";
				$payload .= $time . "\r\n";
			} elseif ( 'checkbox' === $field['type'] ) {
				$payload .= '--' . $boundary . "\r\n";
				$payload .= 'Content-Disposition: form-data; name="' . $field['adminLabel'] . '"' . "\r\n\r\n";
				$payload .= ( ! empty( sanitize_text_field( wp_unslash( rgpost( 'input_' . $field['id'] . '_1' ) ) ) ) ? 1 : 0 ) . "\r\n";
			} elseif ( 'fileupload' === $field['type'] ) {
				$upload_path = GFFormsModel::get_upload_path( $entry['form_id'] );
				$upload_url  = GFFormsModel::get_upload_url( $entry['form_id'] );
				$filelink    = str_replace( $upload_path, $upload_url, $entry[ $field['id'] ] );

				if ( '' === $filelink ) {
					continue;
				}

				$file         = wp_safe_remote_get( $filelink );
				$file_content = wp_remote_retrieve_body( $file );

				$payload .= '--' . $boundary . "\r\n";
				$payload .= 'Content-Disposition: form-data; name="' . $field['adminLabel'] . '"; filename="' . basename( $filelink ) . '"' . "\r\n";
				$payload .= 'Content-Type: ' . $this->get_url_mimetype( $file_content ) . "\r\n\r\n";
				$payload .= $file_content . "\r\n";
			} else {
				$payload .= '--' . $boundary . "\r\n";
				$payload .= 'Content-Disposition: form-data; name="' . $field['adminLabel'] . '"' . "\r\n\r\n";
				$payload .= sanitize_text_field( wp_unslash( rgpost( 'input_' . $field['id'] ) ) ) . "\r\n";
			}
		}

		$payload .= '--' . $boundary . '--';

		$args = [
			'headers' => $headers,
			'method'  => 'POST',
			'body'    => $payload,
		];

		/**
		 * Allow to modify the args before posting it to Vtiger.
		 *
		 * @param array $args The args to post to Vtiger.
		 * @param object $form The form object.
		 */
		$args = gf_apply_filters( [ 'vwtgf_converter_request_args', $form['id'] ], $args, $form );

		/*
		 *  Post it to Vtiger.
		 */
		$response = wp_safe_remote_request( self::$vtiger_url, $args );
	}

	/**
	 * Get the mime type of a file.
	 *
	 * @param string $file_content The file content.
	 *
	 * @return false|string
	 */
	public function get_url_mimetype( $file_content ) {
		$finfo = new \finfo( FILEINFO_MIME_TYPE );
		return $finfo->buffer( $file_content );
	}

	/**
	 * Mark the api_url as an external URL.
	 *
	 * @param bool   $external Whether HTTP request is external or not.
	 * @param string $host Host name of the requested URL.
	 * @param string $url Requested URL.
	 *
	 * @return bool
	 */
	public function http_request_host_is_external( $external, $host, $url ) {
		return $external || $url === self::$vtiger_url;
	}

	/**
	 * Disable sslverify
	 *
	 * @param array $args The args array.
	 *
	 * @return array
	 */
	public function disable_ssl( $args ) {
		$args['sslverify'] = false;

		return $args;
	}
}
