<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'LLMS_Admin_Assets' ) ) :

/**
* Admin Assets Class
*
* Sets up the enqueue scripts and styles for the Admin pages.
* TODO: register scripts. make page ids a db option. 
*
* @version 1.0
* @author codeBOX
* @project lifterLMS
*/
class LLMS_Admin_Assets {

	/**
	* allows injecting "min" in file name suffix.
	* @access public
	* @var string
	*/
	public static $min = ''; //'.min';

	/**
	* Constructor
	*
	* executes enqueue functions on admin_enqueue_scripts
	*/
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	/**
    * Returns array of the page ids we want to enqueue scripts on.
	*
	* @return array
	*/
	public function get_llms_admin_page_ids() {
		$screen_id = 'lifterlms';

	    return apply_filters( 'lifterlms_admin_page_ids', array(
	    	$screen_id . '_page_llms-settings',
	    	'course',
	    	'edit-course',
	    	'edit-course_cat',

	    	'llms_certificate',
	    	'edit-llms_certificate',

	    	'llms_engagement',
	    	'edit-llms_engagement',

	    	'llms_achievement',
	    	'edit-llms_achievement',

	    	'llms_membership',
	    	'edit-llms_membership',
	    ));
	}

	/**
	* Enqueue stylesheets
	*
	* @return void
	*/
	public function admin_styles() {

			wp_enqueue_style( 'admin-styles', plugins_url( '/assets/css/admin' . LLMS_Admin_Assets::$min . '.css', LLMS_PLUGIN_FILE ) );
			wp_enqueue_style( 'chosen-styles', plugins_url( '/assets/chosen/chosen' . LLMS_Admin_Assets::$min . '.css', LLMS_PLUGIN_FILE ) );
	}

	/**
	* Enqueue scripts
	*
	* @return void
	*/
	public function admin_scripts() {
		global $post_type;
		$screen = get_current_screen();

		if ( in_array( $screen->id, LLMS_Admin_Assets::get_llms_admin_page_ids() ) ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_register_style('jquery-ui', 'https://code.jquery.com/ui/1.11.2/themes/flick/jquery-ui.css');
			wp_enqueue_style( 'jquery-ui' );

			wp_enqueue_script( 'chosen-jquery', plugins_url( 'assets/chosen/chosen.jquery' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
			

			wp_enqueue_script( 'llms-ajax', plugins_url(  '/assets/js/llms-ajax' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
			wp_enqueue_script( 'llms-metabox', plugins_url(  '/assets/js/llms-metabox' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);

			wp_enqueue_media();

			if( 'course' == $post_type ) {
				wp_enqueue_script( 'llms-metabox-syllabus', plugins_url(  '/assets/js/llms-metabox-syllabus' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
				wp_enqueue_script( 'llms-metabox-data', plugins_url(  '/assets/js/llms-metabox-data' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
				wp_enqueue_script( 'llms-metabox-fields', plugins_url(  '/assets/js/llms-metabox-fields' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
			}
			if( 'llms_certificate' == $post_type ) {

				wp_enqueue_script( 'llms-metabox-certificate', plugins_url(  '/assets/js/llms-metabox-certificate' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
			}
			if( 'llms_achievement' == $post_type ) {

				wp_enqueue_script( 'llms-metabox-achievement', plugins_url(  '/assets/js/llms-metabox-achievement' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
			}
			if( 'llms_engagement' == $post_type ) {

				wp_enqueue_script( 'llms-metabox-engagement', plugins_url(  '/assets/js/llms-metabox-engagement' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
			}
			if( 'llms_membership' == $post_type ) {
				wp_enqueue_script( 'llms-metabox-data', plugins_url(  '/assets/js/llms-metabox-data' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
				wp_enqueue_script( 'llms-metabox-fields', plugins_url(  '/assets/js/llms-metabox-fields' . LLMS_Admin_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
			}


		}
	}

}

endif;

return new LLMS_Admin_Assets;