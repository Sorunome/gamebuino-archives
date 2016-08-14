<?php
if(!defined('IN_PHPBB')){
	exit;
}
if(empty($lang) || !is_array($lang)){
	$lang = array();
}
$lang = array_merge($lang,array(
	'ARCHIVES_CLI_BUILD' => 'Create a notification for a build based on a query ID',
	'ARCHIVES_CLI_QID' => 'Query ID of the effected file, should be unique'
));
