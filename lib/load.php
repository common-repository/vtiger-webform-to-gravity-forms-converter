<?php
/**
 * Main plugin file to load other classes
 *
 * @package VWTGF_CONVERTER
 */

namespace VWTGF_CONVERTER;

use VWTGF_CONVERTER\Actions\ConvertWebform;
use VWTGF_CONVERTER\Actions\PostToVtiger;
use VWTGF_CONVERTER\Helpers\SettingsPage;

/**
 * Init function of the plugin
 */
function init() {
	/*
	 * Only initialize the plugin when GravityForms is active.
	 */
	if ( ! class_exists( 'GFCommon' ) ) {
		return;
	}

	/*
	 * Construct all modules to initialize.
	 */
	$modules = [
		'vwtgf_converter_settings_page'   => new SettingsPage(),
		'vwtgf_converter_convert_webform' => new ConvertWebform(),
		'vwtgf_converter_post_to_vtiger'  => new PostToVtiger(),
	];

	/*
	 * Initialize all modules.
	 */
	foreach ( $modules as $module ) {
		if ( is_callable( [ $module, 'init' ] ) ) {
			call_user_func( [ $module, 'init' ] );
		}
	}
}

add_action( 'plugins_loaded', 'VWTGF_CONVERTER\init' );
