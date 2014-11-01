<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'LLMS_Admin_Post_Types' ) ) :

/**
* Admin Post Types Class
*
* Sets up post type custom messages and includes base metabox class
*
* @version 1.0
* @author codeBOX
* @project lifterLMS
*/
class LLMS_Admin_Post_Types {

	/**
	* Constructor
	*
	* Adds functions to actions and sets filter on post_updated_messages
	*/
	public function __construct() {
		add_action( 'admin_init', array( $this, 'include_post_type_metabox_class' ) );
		add_filter( 'post_updated_messages', array( $this, 'llms_post_updated_messages' ) );

		add_filter( 'manage_order_posts_columns', array($this, 'llms_add_order_columns' ), 10, 1 );
		add_action( 'manage_order_posts_custom_column', array($this, 'llms_manage_order_columns' ), 10, 2 );
		add_filter( 'manage_edit-order_sortable_columns', array($this, 'llms_order_sortable_columns') );

		add_action( 'load-edit.php', array($this,'llms_edit_order_load' ) );
		//add_filter( 'request', array($this, 'llms_sort_orders') );

	}

	/**
	* Admin Menu
	*
	* Includes base metabox class
	*
	* @return void
	*/
	public function include_post_type_metabox_class() {
		include( 'post-types/class.llms.meta.boxes.php' );
	}

	/**
	* Customize post type messages.
	*
	* TODO: Tidy up post types array and make a db option. Allow users to customize them. 
	*
	* @return array $messages
	*/
	public function llms_post_updated_messages() {
		global $post, $post_ID;

		$llms_post_types = array(
			'course'			=> 'Course',
			'section' 			=> 'Section',
			'lesson' 			=> 'Lesson',
			'order'	 			=> 'Order',
			'llms_email'		=> 'Email',
			'llms_email'		=> 'Email',
			'llms_certificate' 	=> 'Certificate',
			'llms_achievement' 	=> 'Achievement',
			'llms_engagement' 	=> 'Engagement',

		);

		foreach( $llms_post_types as $type => $title ) {

			$messages[$type] = array(
				0 => '',
				1 => sprintf( __( $title . ' updated. <a href="%s">View ' . $title . '</a>', 'lifterlms' ), esc_url( get_permalink($post_ID) ) ),
				2 => __( 'Custom field updated.', 'lifterlms' ),
				3 => __( 'Custom field deleted.', 'lifterlms' ),
				4 => __( $title . ' updated.', 'lifterlms' ),
				5 => isset($_GET['revision']) ? sprintf( __( $title .' restored to revision from %s', 'lifterlms' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6 => sprintf( __( $title . ' published. <a href="%s">View ' . $title .'</a>', 'lifterlms' ), esc_url( get_permalink($post_ID) ) ),
				7 => __( $title . ' saved.', 'lifterlms' ),
				8 => sprintf( __( $title . ' submitted. <a target="_blank" href="%s">Preview ' . $title . '</a>', 'lifterlms' ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
				9 => sprintf( __( $title . ' scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview ' . $title . '</a>', 'lifterlms' ),
					date_i18n( __( 'M j, Y @ G:i', 'lifterlms' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
				10 => sprintf( __( $title . ' draft updated. <a target="_blank" href="%s">Preview ' . $title . '</a>', 'lifterlms' ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
			);


		}

		return $messages;
	}

	public function llms_add_order_columns($columns) {
	    $columns = array(
		'cb' => '<input type="checkbox" />',
		'title' => __( 'Order' ),
		'product' => __( 'Product' ),
		'total' => __( 'Total' ),
		'order_date' => __( 'Date' )
	);

	return $columns;
	}

	public function llms_manage_order_columns( $column, $post_id ) {
		global $post;

		switch( $column ) {

			case 'product' :

				$product_title = get_post_meta( $post_id, '_llms_product_title', true );

				if ( empty( $product_title ) )
					echo __( 'Unknown' );

				else
					printf( __( '%s' ), $product_title );

				break;

			case 'total' :

				$order_total = get_post_meta( $post_id, '_llms_order_total', true );

				if ( empty( $order_total ) )
					printf( __( '%s%0.2f' ), get_lifterlms_currency_symbol(), $order_total);

				else
					printf( __( '%s%0.2f' ), get_lifterlms_currency_symbol(), $order_total);

				break;

			case 'order_date' :

				$order_total = get_post_meta( $post_id, '_llms_order_date', true );

				if ( empty( $order_date ) )
					echo __( 'Unknown' );

				else
					printf( __( '%s' ), $order_date);

				break;

			default :
				break;
		}
	}

	function llms_order_sortable_columns( $columns ) {

		$columns['product'] = 'product';

		return $columns;
	}

	function llms_edit_order_load() {
		add_filter( 'request', array($this,'llms_sort_orders') );
	}

	public function llms_sort_orders( $vars ) {

		if ( isset( $vars['post_type'] ) && 'order' == $vars['post_type'] ) {


			if ( isset( $vars['orderby'] ) && 'product' == $vars['orderby'] ) {

				$vars = array_merge(
					$vars,
					array(
						'meta_key' => '_llms_product_title',
						'orderby' => 'meta_value'
					)
				);
			}
		}

		return $vars;
	}

}

endif;

return new LLMS_Admin_Post_Types();