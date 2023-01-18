<?php

//CHECK FOR UPDATES
require 'plugin-update-checker-master/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/course-flow/wordpress-plugin',
	__FILE__,
	'courseflow'
);

//Set the branch that contains the stable release.
$updateChecker->setBranch('main');
//$updateChecker->setAuthentication('your-token-here');
