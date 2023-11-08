<?php
/**
 * Plugin Name: Beaver Builder Flodesk
 * Plugin URI: https://zackeryfretty.com
 * Description: Adds Flodesk as an integration option when using the Subscribe Form module in Beaver Builder.
 * Version: 1.0
 * Author: Zackery Fretty
 * Author URI: https://zackeryfretty.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'BB_FLODESK_DIR', plugin_dir_path( __FILE__ ) );
define( 'BB_FLODESK_URL', plugins_url( '/', __FILE__ ) );

// Check for Beaver Builder
function zf_bbflo_check_for_beaver_builder() {
  if ( class_exists( 'FLBuilder' ) ) {
        // If Beaver Builder is installed, load the class into the sub module.
        function zf_bbflo_add_to_sub_module( $services ) {
            $services['flodesk'] = array(
                'type'  => 'autoresponder',
                'name'  => 'Flodesk',
                'class' => 'FLBuilderServiceFlodesk',
                'file'  => BB_FLODESK_DIR .'classes/class-fl-builder-service-flodesk.php',
            );
            // Restore alphabetical listing
            ksort($services);
        return $services;
      }
      add_filter( 'fl_builder_subscribe_form_services', 'zf_bbflo_add_to_sub_module' );
  }
}
add_action( 'init', 'zf_bbflo_check_for_beaver_builder' );