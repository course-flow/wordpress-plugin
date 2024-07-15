<?php
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
	die;
}

$option_name = 'wporg_option';

delete_option('courseFlowShopContent');
delete_option('courseFlowShop_refreshed');
delete_option('courseflowApi');