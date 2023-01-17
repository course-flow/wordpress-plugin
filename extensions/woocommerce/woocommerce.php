<?php

add_action( 'woocommerce_product_options_advanced', 'CF_WooCommerce::upsell_add_custom_inventory_fields' );
add_action( 'woocommerce_process_product_meta', 'CF_WooCommerce::upsell_add_custom_inventory_fields_save' );
add_action( 'woocommerce_order_status_completed', 'CF_WooCommerce::order_complete');
add_action('woocommerce_thankyou', 'CF_WooCommerce::redirect_thankyou_page');

class CF_WooCommerce extends courseflow
{

	public static function upsell_add_custom_inventory_fields() {
		global $woocommerce, $post;
		echo '<div class="options_group">';

		$options = array();

		$flows = self::getFlows();
		if($flows):
			$options[''] = __( 'No flow selected - Click here to select', 'woocommerce');


			foreach($flows as $flow):
				$options[$flow->ID] = $flow->post_title;
			endforeach;

		else:
			$options[''] = __( 'Please enter CourseFlow API settings to use this option', 'woocommerce');
		endif;

		// EAN Kode
		woocommerce_wp_select(
			array(
				'id'                => 'courseflowid',
				'class'             => 'select short',
				'name'              => 'courseflowid',
				'label'              => __('CourseFlow Flow', 'courseflow'),
				'desc_tip'          => false,
				'custom_attributes' => array(),
				'options' =>  $options,
			)
		);

		echo '</div>';

		echo '<div class="options_group">';

		// EAN Kode
		woocommerce_wp_checkbox( array(
			'id'            => 'redirect_activated',
			'label'         => __('Redirect user to flow', 'CourseFlow' ),
			'value'         => get_post_meta( $post->ID, 'redirect_activated', true ),
			'required'      => true
		) );

		echo '</div>';

//		self::payment_complete(547);

	}

	// Save Fields
	public static function upsell_add_custom_inventory_fields_save( $post_id ){
		$courseflowid = $_POST['courseflowid'];
		if(isset($courseflowid)){
			update_post_meta( $post_id, 'courseflowid', esc_attr( $courseflowid ));
		}

		$courseflowid = get_post_meta($post_id,'courseflowid', true);
		if (empty($courseflowid)){
			delete_post_meta($post_id,'courseflowid', '');
		}

		$redirect_activated = $_POST['redirect_activated'];
		if($_POST['redirect_activated']){
			update_post_meta( $post_id, 'redirect_activated', esc_attr( $redirect_activated ));
		}

		if (!isset($_POST['redirect_activated'])){
			delete_post_meta($post_id,'redirect_activated');
		}
	}

	public static function order_complete($order_id)
	{
		$order = wc_get_order( $order_id );
		$items = $order->get_items();

		$redirectActivated = false;
		$redirectURL = false;
		$redirectAfter = false;

		foreach($items as $key => $item):
			$product_id = $item['product_id'];
			$flowid = self::productHasCourseFlowSubscribeEndpoint($product_id);
			$redirectActivated = get_post_meta($product_id, 'redirect_activated', true);
			if($flowid):
				$email = $order->get_billing_email();
				$firstname = $order->get_billing_first_name();
				$lastname = $order->get_billing_last_name();
				$redirectURL = self::subscribeUserToFlow($order, $flowid, $email, $firstname, $lastname);
			endif;

			if($redirectActivated && $redirectURL):
				$redirectAfter = $redirectURL;
			endif;
		endforeach;

		if($redirectAfter):
			update_post_meta($order_id, 'CF_redirect_url', $redirectAfter);
		endif;

	}

	public static function productHasCourseFlowSubscribeEndpoint($product_id)
	{
		$courseflowid = get_post_meta($product_id, 'courseflowid', true);

		if($courseflowid && trim($courseflowid) != '' && intval($courseflowid) > 0):
			return $courseflowid;
		endif;

		return false;
	}

	public static function redirect_thankyou_page($order_id)
	{
		$url = get_post_meta($order_id, 'CF_redirect_url', true);
		if($url):
			echo '<div style="margin: 10px 0 30px 0">'.__('You will be redirected to the bought course in a few seconds...', 'courseflow').'</div>';
			echo '<script type="text/javascript">setTimeout(function(){window.location.href="'.$url.'"}, 4000)</script>';
		endif;
	}

}