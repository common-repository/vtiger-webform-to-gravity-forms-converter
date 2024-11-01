<?php
/**
 * Class to convert a Vtiger Webform into a Gravity Forms form.
 *
 * @package VWTGF_CONVERTER\Actions
 */

namespace VWTGF_CONVERTER\Actions;

use DOMDocument;
use GFAPI;
use WP_Error;

/**
 * Class ConvertWebform
 */
class ConvertWebform {
	/**
	 * Init all filter and action hooks so that they can be used.
	 *
	 * @see https://wordpress.org/gutenberg/handbook/blocks/writing-your-first-block-type/#enqueuing-block-scripts
	 */
	public function init() {
		add_action( 'admin_post_vwtgf_converter_convert_form', [ $this, 'vwtgf_converter_convert_web_form' ] );
	}

	/**
	 * Convert the Vtiger Webform to a Gravity Form and check if the form already exists.
	 *
	 * @return void
	 */
	public function vwtgf_converter_convert_web_form() {
		/*
		 * Check if the nonce is valid.
		 */
		if ( ! isset( $_REQUEST['vwtgf_converter_convert_webform'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['vwtgf_converter_convert_webform'] ) ), 'vwtgf_converter_convert_webform' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vwtgf-converter&status=error' ) );
			exit;
		}

		/*
		 * Get the raw HTML from the request and unslash it.
		 * Remove all script tags to prevent XSS.
		 * HTML is not stored in the Database or outputted to the user.
		 */
		$raw_html = ( isset( $_REQUEST['webform'] ) && ! empty( $_REQUEST['webform'] ) ) ? preg_replace( '@<(script)[^>]*?>.*?</\\1>@si', '', wp_unslash( $_REQUEST['webform'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		/*
		 * Clean and santize the HTML.
		 */
		$clean_html = $this->vwtgf_converter_sanitize_webform_input( $raw_html );

		/*
		 * Check if form is set and not empty.
		 */
		if ( ! empty( $clean_html ) ) {
				/*
			 * Convert HTML to a nested array.
			 */
			$html_array = $this->vwtgf_converter_html_to_nested_array( $clean_html );

			/*
			 * Convert HTML Array to a Gravity Forms Form Object.
			 */
			$form_meta = [];
			$fieldid   = 1;
			$this->vwtgf_converter_create_gf_form_object( [ $html_array ], $form_meta, $fieldid );

			$form_id     = 0;
			$form_exists = $this->vwtgf_converter_check_given_form_meta( $form_meta, $form_id );

			if ( $form_exists ) {
				/*
				 * Update the existing form.
				 */
				$this->vwtgf_converter_update_existing_form( $form_meta, $form_id );

				/*
				 * Redirect to the admin page with the status updated.
				 */
				wp_safe_redirect( admin_url( 'admin.php?page=vwtgf-converter&status=updated' ) );
				exit;
			} else {
				/*
				 * Add the built Form Object to Gravity Forms.
				 */
				$form_meta['enableHoneypot'] = true;
				$form_meta['honeypotAction'] = 'spam';

				/**
				 * Filter the form meta before adding the form to Gravity Forms.
				 *
				 * @param array $form_meta The form meta.
				 */
				$form_meta = apply_filters( 'vwtgf_converter_form_meta', $form_meta );

				$status = GFAPI::add_form( $form_meta );
				if ( $status instanceof WP_Error ) {
					/*
					 * If an error occurred, redirect to the admin page with the status error.
					 */
					wp_safe_redirect( admin_url( 'admin.php?page=vwtgf-converter&status=error' ) );
					exit;
				}

				/*
				 * Redirect to the admin page with the status success.
				 */
				wp_safe_redirect( admin_url( 'admin.php?page=vwtgf-converter&status=success' ) );
				exit;
			}
		} else {
			/*
			 * If form is empty, redirect to the admin page with the status error.
			 */
			wp_safe_redirect( admin_url( 'admin.php?page=vwtgf-converter&status=error' ) );
			exit;
		}
	}

	/**
	 * Escape HTML content but keep the tags intact.
	 *
	 * @param string $html The HTML string to escape.
	 * @return string Escaped HTML.
	 */
	public function vwtgf_converter_sanitize_webform_input( $html ) {
		$allowed_tags = [
			'meta'     => [
				'http-equiv' => [],
				'content'    => [],
			],
			'form'     => [
				'id'             => [],
				'name'           => [],
				'action'         => [],
				'method'         => [],
				'accpet-charset' => [],
				'enctype'        => [],
			],
			'input'    => [
				'type'        => [],
				'name'        => [],
				'value'       => [],
				'placeholder' => [],
				'required'    => [],
				'multiple'    => [],
				'checked'     => [],
				'selected'    => [],
				'data-label'  => [],
			],
			'table'    => [],
			'tbody'    => [],
			'tr'       => [],
			'td'       => [],
			'th'       => [],
			'label'    => [
				'for' => [],
			],
			'textarea' => [
				'name'        => [],
				'placeholder' => [],
				'required'    => [],
			],

			'div'      => [
				'id'    => [],
				'class' => [],
			],
			'select'   => [
				'name'       => [],
				'id'         => [],
				'class'      => [],
				'data-label' => [],
				'hidden'     => [],

			],
			'option'   => [
				'value'    => [],
				'selected' => [],
			],
		];

		/**
		 * Filter the allowed tags for the webform sanitization.
		 *
		 * @param array $allowed_tags The allowed tags.
		 */
		$allowed_tags = apply_filters( 'vwtgf_converter_allowd_tags', $allowed_tags );

		/**
		 * Filter the allowed custom html for the webform sanitization.
		 *
		 * @param array $html The allowed tags.
		 */
		$html = apply_filters( 'vwtgf_converter_custom_webform_sanitization', $html );

		return wp_kses( $html, $allowed_tags );
	}

	/**
	 * Function to recursively build nested array
	 *
	 * @param object $element The element to build the array from.
	 *
	 * @return array
	 */
	public function vwtgf_converter_build_array_from_element( $element ) {
		$result = [];

		/*
		 * Get the tag name
		 */
		$tag_name = $element->tagName;

		/*
		 * Get the attributes.
		 */
		$attributes = [];
		foreach ( $element->attributes as $attribute ) {
			$attributes[ $attribute->name ] = $attribute->value;
		}

		/*
		 * Add tag name and attributes to the result.
		 */
		$result['tag']        = $tag_name;
		$result['attributes'] = $attributes;

		/*
		 * Check if the element has children.
		 */
		if ( $element->hasChildNodes() ) {
			/*
			 * Iterate through children.
			 */
			foreach ( $element->childNodes as $child ) {
				/*
				 * Check if the child is an element node.
				 */
				if ( XML_ELEMENT_NODE === $child->nodeType ) {
					/*
					 * Recursively build array for child element.
					 */
					$result['children'][] = $this->vwtgf_converter_build_array_from_element( $child );
				} elseif ( XML_TEXT_NODE === $child->nodeType ) {
					/*
					 * Add text content.
					 */
					$result['text'] = trim( $child->textContent );
				}
			}
		}

		return $result;
	}

	/**
	 * Converts an HTML String to an Array
	 *
	 * @param string $html_string The HTML String to convert.
	 *
	 * @return array
	 */
	public function vwtgf_converter_html_to_nested_array( $html_string ) {
		/*
		 * Create a DOMDocument object.
		 */
		$dom = new DOMDocument();

		/*
		 * Load HTML string into the DOMDocument.
		 */
		$dom->loadHTML( $html_string );

		/*
		 * Get the root element.
		 */
		$root_element = $dom->documentElement;

		/*
		 * Build array from root element.
		 */

		return $this->vwtgf_converter_build_array_from_element( $root_element );
	}

	/**
	 * Recursively Iterates through the nested HTML Array and adds the Fields to $form_meta
	 *
	 * @param array $html_array The HTML Array.
	 * @param array $form_meta The Form Meta Array.
	 * @param int   $fieldid The ID of the Field.
	 *
	 * @return void
	 */
	public function vwtgf_converter_create_gf_form_object( $html_array, &$form_meta, &$fieldid ) {
		/*
		 *  Switch for the different HTML Tags.
		 */
		foreach ( $html_array as $element ) {
			$input_type = 'hidden';
			switch ( $element['tag'] ) {
				case 'html':
				case 'body':
				case 'table':
				case 'tbody':
					/*
					 * Just go deeper Into the Array.
					 */
					$this->vwtgf_converter_create_gf_form_object( $element['children'], $form_meta, $fieldid );
					break;
				case 'form':
					/*
					 * Sets the GF title and adds the VTiger URL as a Hidden Field. Then go deeper into the Array.
					 */
					$form_meta['title'] = $element['attributes']['name'];

					$field = [
						'type'         => $input_type,
						'id'           => $fieldid ++,
						'label'        => 'vtiger_POST_url',
						'adminLabel'   => 'vtiger_POST_url',
						'defaultValue' => $element['attributes']['action'],
						'size'         => 'large',
					];

					$form_meta['fields'][] = $field;

					$this->vwtgf_converter_create_gf_form_object( $element['children'], $form_meta, $fieldid );
					break;
				case 'input':
					/*
					 * If the Input is a Submit Button, add the Button to the Form Meta.
					 */
					if ( 'submit' === $element['attributes']['type'] ) {
						$form_meta['button'] = [
							'type' => 'text',
							'text' => $element['attributes']['value'],
						];
						break;
					}

					/*
					 * If the Input is hidden, set the field type to hidden.
					 */
					$field = [
						'type'         => $input_type,
						'id'           => $fieldid ++,
						'label'        => $element['attributes']['name'],
						'adminLabel'   => $element['attributes']['name'],
						'defaultValue' => ! empty( $element['attributes']['value'] ) ? $element['attributes']['value'] : '',
						'isRequired'   => array_key_exists( 'required', $element['attributes'] ),
						'size'         => 'large',
					];

					$form_meta['fields'][] = $field;
					break;
				case 'tr':
					$label = '';
					$index = 0;
					$field = [];

					/*
					 *
					 * If the Input has a Label, set the Label.
					 */
					if ( count( $element['children'] ) === 2 ) {
						$label = $element['children'][0]['children'][0]['text'];
						$index = 1;
					}

					/*
					 * Sets the Array to the Element itself (not the Label Array).
					 */
					if ( count( $element['children'][ $index ]['children'] ) === 2 ) {
						$element = $element['children'][ $index ]['children'][1];
					} else {
						$element = $element['children'][ $index ]['children'][0];
					}

					/*
					 *  Input field.
					 */
					if ( 'input' === $element['tag'] ) {
						$choices = [];

						/*
						 * Check for special fields.
						 */
						if ( empty( $label ) ) {
							$input_type = 'hidden';
						} elseif ( 'email' === $element['attributes']['name'] ) {
							$input_type = 'email';
						} elseif ( 'mobile' === $element['attributes']['name'] || 'phone' === $element['attributes']['name'] || 'fax' === $element['attributes']['name'] ) {
							$input_type = 'phone';
						} elseif ( 'file' === $element['attributes']['type'] ) {
							$input_type = 'fileupload';
						} elseif ( 'checkbox' === $element['attributes']['type'] ) {
							$input_type = 'checkbox';
						} elseif ( 'date' === $element['attributes']['type'] ) {
							$input_type = 'date';
						} elseif ( 'website' === $element['attributes']['name'] ) {
							$input_type = 'website';
						} else {
							$input_type = 'text';
						}

						/*
						 * Checks the field type with the label (label empty = hidden field).
						 */
						$field = [
							'type'         => $input_type,
							'id'           => $fieldid ++,
							'label'        => $label ?? $element['attributes']['name'],
							'adminLabel'   => $element['attributes']['name'],
							'defaultValue' => ! empty( $element['attributes']['value'] ) ? $element['attributes']['value'] : '',
							'isRequired'   => array_key_exists( 'required', $element['attributes'] ),
							'size'         => 'large',
						];

						/*
						 * Checks if the field is an email field and add extra field attributes.
						 */
						if ( 'email' === $input_type ) {
							$field['placeholder'] = 'name@example.com';
						}

						/*
						 * Checks if the field is a phone field and add extra field attributes.
						 */
						if ( 'mobile' === $input_type || 'phone' === $input_type || 'fax' === $input_type ) {
							$field['placeholder'] = '+49 (0) 123 / 456 789 0';
						}

						/*
						 * Checks if the field is a website field and add extra field attributes.
						 */
						if ( 'website' === $input_type ) {
							$field['placeholder'] = 'https://example.com';
						}

						/*
						 * Checks if the field is a file upload field and add extra field attributes and remove some.
						 */
						if ( 'fileupload' === $input_type ) {
							$file_size = 5;

							/**
							 * Filter for the file size (in MB) for the file upload field.
							 *
							 * @param string $file_size The file size.
							 * @param array $field The field array.
							 */
							$file_size = apply_filters( 'vwtgf_converter_upload_file_size', $file_size, $field );

							$allowed_extensions = 'pdf,doc,docx,jpg,jpeg,png';

							/**
							 * Filter for the allowed file extensions for the file upload field.
							 *
							 * @param string $allowed_extensions The allowed file extensions.
							 * @param array $field The field array.
							 */
							$allowed_extensions = apply_filters( 'vwtgf_converter_upload_file_extensions', $allowed_extensions, $field );

							$field['multipleFiles']     = false;
							$field['maxFileSize']       = $file_size;
							$field['allowedExtensions'] = $allowed_extensions;
							unset( $field['size'] );
							unset( $field['defaultValue'] );
						}

						if ( 'date' === $input_type ) {
							$field['dateType']   = 'datedropdown';
							$field['dateFormat'] = 'ymd_dash';
						}

						if ( 'checkbox' === $input_type ) {
							$choices['text']     = $label;
							$choices['value']    = $label;
							$field['choices'][0] = $choices;
							unset( $field['label'] );
						}

						/*
						 * Select Field.
						 */
					} elseif ( 'textarea' === $element['tag'] ) {
							/*
							 * Check for special fields.
							 */
						if ( empty( $label ) ) {
							$input_type = 'hidden';
						} else {
							$input_type = 'textarea';
						}

							/*
							 * Checks the field type with the label (label empty = hidden field).
							 */
							$field = [
								'type'         => $input_type,
								'id'           => $fieldid ++,
								'label'        => $label ?? $element['attributes']['name'],
								'adminLabel'   => $element['attributes']['name'],
								'defaultValue' => ! empty( $element['attributes']['text'] ) ? $element['attributes']['text'] : '',
								'isRequired'   => array_key_exists( 'required', $element['attributes'] ),
								'size'         => 'large',
							];
					} elseif ( 'select' === $element['tag'] ) {
						$is_multiple = array_key_exists( 'multiple', $element['attributes'] );

						/*
						 * Checks if the Select Field is Hidden.
						 */
						if ( ! empty( $label ) ) {
							/*
							 * Visible Select Field.
							 */
							$choices = [];

							/*
							 *Iterates through all options and add them to the option Array.
							 */
							foreach ( $element['children'] as $option ) {
								$choices[] = [
									'text'       => $option['text'],
									'value'      => $option['attributes']['value'],
									'isSelected' => array_key_exists( 'selected', $option['attributes'] ),
								];
							}

							$input_type  = 'multiselect';
							$placeholder = '';

							if ( ! $is_multiple ) {
								$placeholder = $choices[0]['text'];
								array_shift( $choices );
								$input_type = 'select';
							}

							$field = [
								'type'        => $input_type,
								'id'          => $fieldid ++,
								'label'       => $label,
								'adminLabel'  => $element['attributes']['name'],
								'placeholder' => $placeholder,
								'choices'     => $choices,
								'isRequired'  => array_key_exists( 'required', $element['attributes'] ),
								'size'        => 'large',
							];
						} else {
							/*
							 * Hidden Select Field: Gets Converted to a Hidden Input Field.
							 */
							$select_value = '';

							/*
							 * Gets the Selected option.
							 */
							foreach ( $element['children'] as $option ) {
								if ( array_key_exists( 'selected', $option['attributes'] ) ) {
									$select_value = $option['attributes']['value'];
								}
							}
							$field = [
								'type'         => $input_type,
								'id'           => $fieldid ++,
								'label'        => $element['attributes']['name'],
								'adminLabel'   => $element['attributes']['name'],
								'defaultValue' => $select_value,
								'size'         => 'large',
							];
						}
					}

					/**
					 * Allow to modify the field before adding it to the form meta.
					 *
					 * @param array $field The field array.
					 * @param array  $element The element array.
					 * @param string $input_type The input type.
					 */
					$field = apply_filters( "vwtgf_converter_field_meta_{$input_type}", $field, $element );

					/**
					 * Allow to modify the field before adding it to the form meta.
					 *
					 * @param array  $field The field array.
					 * @param array $element The element array.
					 * @param string $input_type The input type.
					 */
					$field = apply_filters( 'vwtgf_converter_field_meta', $field, $element, $input_type );

					$form_meta['fields'][] = $field;

					break;
				default:
					break;
			}
		}
	}

	/**
	 * Check if the given form already exists in Gravity Forms.
	 *
	 * @param array $form_meta The Form Meta Array.
	 * @param int   $form_id The Form ID.
	 *
	 * @return bool
	 */
	public function vwtgf_converter_check_given_form_meta( $form_meta, &$form_id ) {
		$exists = false;
		$forms  = GFAPI::get_forms();

		/*
		 * Iterate through all forms and check if the form already exists.
		 */
		foreach ( $forms as $form ) {
			foreach ( $form['fields'] as $field ) {
				if ( 'publicid' === $field->adminLabel ) {
					$publicid = $field->defaultValue;
					foreach ( $form_meta['fields'] as $meta_field ) {
						if ( 'publicid' === $meta_field['adminLabel'] ) {
							if ( $publicid === $meta_field['defaultValue'] ) {
								$form_id = $form['id'];
								return true;
							}
						}
					}
				}
			}
		}
		return $exists;
	}

	/**
	 * Update the existing form with the new form meta.
	 *
	 * @param array $form_meta The Form Meta Array.
	 * @param int   $form_id The Form ID.
	 *
	 * @return void
	 */
	public function vwtgf_converter_update_existing_form( $form_meta, $form_id ) {
		$form           = GFAPI::get_form( $form_id );
		$form['fields'] = $form_meta['fields'];
		GFAPI::update_form( $form );
	}
}
