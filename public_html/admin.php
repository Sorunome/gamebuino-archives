<?php
$startTime = microtime(true);
include_once('archive.php');

if(!$isAdmin){
	header('Location:.');
	exit;
}

$templates = array();
$body_template = new Template('body.inc');
$body_template->title = '';
$body_template->startTime = $startTime;
$body_template->title = 'Admin Panel';
$t = new Template('admin.inc');
$messages = array();
if(isset($_GET['clearTemplateCache'])){
	foreach(scandir('cache') as $c){
		if(substr($c,-4) == '.inc'){
			unlink('cache/'.$c);
		}
	}
	$messages[] = 'Cleared the template file cache!';
}
$t->messages = $messages;

$body_template->addChild($t);
$body_template->render();
