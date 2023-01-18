<?php

// Plugin Name: CourseFlow
// Plugin Author: Jerry Tieben
// Description: With this plugin you can add your CourseFlow shop to your website and connect woocommerce to your CourseFlow account
// Version: 1.02


include('extensions/woocommerce/woocommerce.php');
include('update.php');

add_action( 'admin_menu', 'courseflow::menu' );
add_action('admin_head', 'courseflow::save');
add_action('wp_enqueue_scripts', 'courseflow::scripts');
add_action('admin_enqueue_scripts', 'courseflow::scripts');
add_action('init', 'courseflow::language' );
add_action('wp_head', 'courseflow::forceRefresh');

add_shortcode('courseflowShop', 'courseflow::integration');
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'courseflow::page_settings_link');


class courseflow {

	public static function scripts()
	{
		wp_enqueue_script('jquery');
		wp_enqueue_style( 'courseflowCSS', plugins_url().'/courseflow/style.css' );
	}



	public static function language()
	{
		load_plugin_textdomain( 'courseflow', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public static function menu()
	{
		add_options_page( 'CourseFlow', 'CourseFlow', 'manage_options', 'courseFlowShop', 'courseflow::courseFlowSettingsPage' );
	}

	public static function save()
	{
		if(is_user_logged_in() && isset($_POST['updateCourseFlow']) && trim($_POST['courseflowApi'][0]) !== '' && trim($_POST['courseflowApi'][1]) !== ''):
			update_option('courseflowApi', $_POST['courseflowApi']);
			delete_option('courseFlowShopContent');
			delete_option('courseFlowShop_refreshed');
			self::refresh();
		elseif(is_user_logged_in() && isset($_POST['updateCourseFlow'])):
			delete_option('courseFlowShopContent');
			delete_option('courseFlowShop_refreshed');
		endif;
	}

	public static function settingsSet()
	{

	}

	public static function refresh()
	{
		global $CFerror;
		$api = get_option('courseflowApi');

		if(is_array($api) && trim($api[0]) !== '' && trim($api[1])):
			$content = file_get_contents('https://eu1.course-flow.com/api/?apikey='.$api[0].'&apisecret='.$api[1].'&action=getShopPage');

		if(trim($content) !== '' ):
			update_option('courseFlowShopContent', $content);
			update_option('courseFlowShop_refreshed', time());
		else:
			$CFerror =  __('Error: Something went wrong, please check your settings', 'courseflow');
		endif;


		endif;


	}

	public static function courseFlowSettingsPage()
	{
		global $CFerror;
		echo '<h1 style="text-align: center;margin-bottom: 30px">'.__('CourseFlow Shop Settings', 'courseflow').'</h1>';
		$api = get_option('courseflowApi');
		$content = get_option('courseFlowShopContent');

		echo '<courseFlowWrapper>';

		if(!is_array($api) ||  $api[0] == '' || $api[1] == '' || !$content || trim($content) == '' || isset($_GET['credentials'])):

			echo '<form action="?page=courseFlowShop" method="POST" id="credentials">';
				if($CFerror):
						echo '<div class="error">'.$CFerror.'</div>';
				endif;
			echo '<label>'.__('CourseFlow API key', 'courseflow').'</label>';
			echo '<input name="courseflowApi[]" type="text" value="'.@$api[0].'" />';

			echo '<label>'.__('CourseFlow API Secret', 'courseflow').'</label>';
			echo '<input name="courseflowApi[]" type="text" value="'.@$api[1].'" />';

			echo '<input name="updateCourseFlow" type="submit" value="'.__('Connect', 'courseflow').'"/>';
			echo '</form>';

		else:
			echo '<div id="connected">';
			echo '<div class="successMessage"><span class="check">✓</span>'.__('Connection successfully made', 'courseflow').'</div>';
			echo '<div class="changeCredentials" onclick="window.location.href=(\'?page=courseFlowShop&credentials\')">'.__('Click here to change your API settings', 'courseflow').'</div>';
			echo '<div class="steptwo">'.__('Now add the shortcode <em>[courseflowShop]</em> to any page where you want to display your CourseFlow Shop page:', 'courseflow').'</div>';
			echo '</div>';

			echo self::integration(true);
		endif;

		echo '</courseFlowWrapper>';
	}

	public static function integration($refresh=false)
	{
		if($refresh || (time() - get_option('courseFlowShop_refreshed') > 86400)):
			self::refresh();
			return get_option('courseFlowShopContent');
		else:
			return get_option('courseFlowShopContent');
		endif;

	}

	public static function page_settings_link($links){
		$links[] = '<a href="' .
		           admin_url( 'options-general.php?page=courseFlowShop' ) .
		           '">' . __('Settings') . '</a>';
		return $links;
	}

	public static function forceRefresh()
	{
		if(isset($_GET['refreshCourseFlowShop'])):
			if(time() - get_option('courseFlowShop_refreshed') > 60):
				self::refresh();
			endif;
		endif;
	}

	public static function getFlows()
	{
		$cache = get_option('CF_flows');
		$lastCache = get_option('CF_flows_last_update');

		if(time() - intval($lastCache) < 3600):
			return $cache;
		endif;

		$api = get_option('courseflowApi');
		if(is_array($api) && trim($api[0]) !== '' && trim($api[1])):
			$url = 'https://eu1.course-flow.com/api/?apikey='.$api[0].'&apisecret='.$api[1].'&action=getFlowsAndNumberSubscribers';
			$flows = wp_remote_get($url, array( 'timeout' => 120));
			$flows = json_decode($flows['body']);
			update_option('CF_flows', $flows);
			update_option('CF_flows_last_update', time());
			return $flows;
		else:
			return false;
		endif;
	}

	public static function subscribeUserToFlow($order, $flowid, $email, $firstname, $lastname)
	{

		$api = get_option('courseflowApi');
		if(is_array($api) && trim($api[0]) !== '' && trim($api[1])):
			$url = 'https://eu1.course-flow.com/api/?apikey='.$api[0].'&apisecret='.$api[1].'&action=getTempHandshake';
			$handshake = json_decode(wp_remote_get($url, array( 'timeout' => 120))['body']);
			$url = 'https://eu1.course-flow.com/api/';
			$args = array('method' => 'POST',
			              'body' => array(
				              'apikey' => $api[0],
				              'apisecret' => $api[1],
				              'action' => 'addSubscriber',
				              'flowid' => $flowid,
				              'email' => $email,
				              'firstname' => $firstname,
				              'lastname' => $lastname,
				              'handshake' => $handshake->handshake,
				              'password' => substr(md5(uniqid()),0,8)
			              ));
			$response = wp_remote_post($url, $args);
			$response = json_decode($response['body']);

			if(isset($response->directLoginURL)):
				$order->add_order_note(sprintf(__('User added to CourseFlow Flow %d', 'courseflow'), $flowid));
				return $response->directLoginURL;
			else:
				$order->add_order_note(sprintf(__('Tried adding user to flow, but didn\'t succeed. Reason: %s', 'courseflow'), $response->message));
			endif;
		endif;
	}

}



?>