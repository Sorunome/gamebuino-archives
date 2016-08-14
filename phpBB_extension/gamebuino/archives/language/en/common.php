<?php
if(!defined('IN_PHPBB')){
	exit;
}
if(empty($lang) || !is_array($lang)){
	$lang = array();
}
$lang = array_merge($lang,array(
	'ARCHIVES_NOTIFICATION_GROUP' => 'Archives Notifications',
	
	'ARCHIVES_NG_BUILDSUCCESS' => 'Successful build',
	'ARCHIVES_SUCCESS_BUILD' => 'Building of file %s succeeded!',
	
	'ARCHIVES_NG_BUILDFAIL' => 'Failed build',
	'ARCHIVES_FAILED_BUILD' => 'Building of file %s failed!',
));
