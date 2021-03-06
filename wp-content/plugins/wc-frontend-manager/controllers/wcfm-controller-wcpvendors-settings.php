<?php
/**
 * WCFM plugin controllers
 *
 * Plugin WC Product Vendors Setings Controller
 *
 * @author 		WC Lovers
 * @package 	wcfm/controllers
 * @version   2.1.1
 */

class WCFM_Settings_WCPVendors_Controller {
	
	public function __construct() {
		global $WCFM;
		
		$this->processing();
	}
	
	public function processing() {
		global $WCFM, $wpdb, $_POST;
		
		$wcfm_settings_form_data = array();
	  parse_str($_POST['wcfm_settings_form'], $wcfm_settings_form);
	  
	  $vendor_data = WC_Product_Vendors_Utils::get_vendor_data_from_user();
	  
	  // sanitize
		$wcfm_settings_form = array_map( 'sanitize_text_field', $wcfm_settings_form );
		$wcfm_settings_form = array_map( 'stripslashes', $wcfm_settings_form );
		
		// sanitize html editor content
		$wcfm_settings_form['profile'] = ! empty( $_POST['profile'] ) ? wp_kses_post( stripslashes( $_POST['profile'] ) ) : '';
		
		// Set Product Featured Image
		$wp_upload_dir = wp_upload_dir();
		if(isset($wcfm_settings_form['logo']) && !empty($wcfm_settings_form['logo'])) {
			$featured_img = str_replace($wp_upload_dir['baseurl'], $wp_upload_dir['basedir'], $wcfm_settings_form['logo']);
			$wcfm_settings_form['logo'] = $this->wcfm_get_image_id($wcfm_settings_form['logo']);
		}
		
		// merge the changes with existing settings
		$wcfm_settings_form = array_merge( $vendor_data, $wcfm_settings_form );

		if ( update_term_meta( WC_Product_Vendors_Utils::get_logged_in_vendor(), 'vendor_data', $wcfm_settings_form ) ) {
			echo '{"status": true, "message": "' . __( 'Settings saved successfully', $WCFM->text_domain ) . '"}';
		} else {
			echo '{"status": false, "message": "' . __( 'Settings failed to save', $WCFM->text_domain ) . '"}';
		}
		 
		die;
	}
	
	function wcfm_get_image_id($image_url) {
		global $wpdb;
		$attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url )); 
		return $attachment[0]; 
	}
}