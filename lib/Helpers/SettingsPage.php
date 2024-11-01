<?php
/**
 * Create the Settings Page for the plugin.
 *
 * @package VWTGF_CONVERTER\Helpers
 */

namespace VWTGF_CONVERTER\Helpers;

/**
 * Class SettingsPage
 */
class SettingsPage {

	/**
	 * Init all filter and action hooks so that they can be used.
	 *
	 * @see https://wordpress.org/gutenberg/handbook/blocks/writing-your-first-block-type/#enqueuing-block-scripts
	 */
	public function init() {
		add_action( 'admin_notices', [ $this, 'vwtgf_converter_admin_notice' ] );
		add_filter( 'gform_addon_navigation', [ $this, 'vwtgf_converter_add_admin_page' ] );
	}

	/**
	 * Hook into Gravity Forms menu and add "Vtiger Webform Converter" as a submenu item.
	 *
	 * @param mixed $addon_menus The current menu items.
	 */
	public function vwtgf_converter_add_admin_page( $addon_menus ) {
		$menu = [
			'label'      => __( 'Vtiger Webform Converter', 'vtiger-webform-to-gravity-forms-converter' ),
			'permission' => 'manage_options',
			'name'       => 'vwtgf-converter',
			'callback'   => [ $this, 'vwtgf_converter_admin_page_html' ],
		];

		$addon_menus[] = $menu;

		return $addon_menus;
	}


	/**
	 * Add settings page.
	 */
	public function vwtgf_converter_admin_page_html() {
		echo '<div class="wrap vwtgf_converter_converter">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<div class="tab-content">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="vwtgf_converter_convert_form">';
		echo '<label for="webform">Vtiger Webform</label><br>';
		echo '<textarea id="webform" name="webform" rows="30" cols="100"></textarea><br>';
		echo sprintf( '<input type="submit" value="%1$s" name="webform-button" class="button button-primary">', esc_html__( 'Convert', 'vtiger-webform-to-gravity-forms-converter' ) );
		wp_nonce_field( 'vwtgf_converter_convert_webform', 'vwtgf_converter_convert_webform' );
		echo '</form></div></div>';
	}

	/**
	 * Add admin notice.
	 */
	public function vwtgf_converter_admin_notice() {
		global $wp_version;

		/*
		 * Get the current screen object.
		 */
		$screen = get_current_screen();

		/*
		 * Check if the current screen is the settings page.
		 */
		if ( 'gf_edit_forms' !== $screen->parent_base ) {
			return;
		}

		// phpcs:disable
		if ( isset( $_GET['status'] ) ) {
			$status = sanitize_text_field( wp_unslash( $_GET['status'] ) );
			if ( '6.4.0' > $wp_version) {
				echo '<div class="notice notice-' . esc_attr( $status ) . ' is-dismissible">';
				echo '<p>';
				if ( 'success' === $status ) {
					echo esc_html__( 'Form successfully created.', 'vtiger-webform-to-gravity-forms-converter' );
				} elseif ( 'error' === $status ) {
					echo esc_html__( 'There was an error while creating the form!', 'vtiger-webform-to-gravity-forms-converter' );
				}
				elseif ( 'updated' === $status ) {
					echo esc_html__( 'Form successfully updated!', 'vtiger-webform-to-gravity-forms-converter' );
				}
				echo '</p></div>';
			} else {
				if ( 'success' === $status ) {
					wp_admin_notice( esc_html__( 'Form successfully created.', 'vtiger-webform-to-gravity-forms-converter' ), [ 'type' => 'success', 'dismissible' => true ] );
				} elseif ( 'error' === $status ) {
					wp_admin_notice( esc_html__( 'There was an error while creating the form!', 'vtiger-webform-to-gravity-forms-converter' ), [ 'type' => 'error', 'dismissible' => true ] );
				} elseif ( 'updated' === $status ) {
					wp_admin_notice( esc_html__( 'Form successfully updated!', 'vtiger-webform-to-gravity-forms-converter' ), [ 'type' => 'success', 'dismissible' => true ] );
				}
			}
		}
		// phpcs:enable
	}
}
