<?php
$startTime = microtime(true);
include_once('archive.php');

if(!$isAdmin){
	header('Location:.');
	exit;
}
function wipeBoxTemplate(){
	global $buildserver_path;
	$socket = socket_create(AF_UNIX,SOCK_STREAM,0);
	if(!@socket_connect($socket,$buildserver_path.'/socket.sock')){
		return false;
	}
	
	$s = json_encode(array(
		'type' => 'destroy_template'
	))."\n";
	socket_write($socket,$s,strlen($s));
	socket_close($socket);
	return true;
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
}elseif(isset($_GET['wipeBoxTemplate']) || isset($_GET['triggerBuilds'])){
	if(!wipeBoxTemplate()){
		$messages[] = "Couldn't connect to backend!";
	}else{
		$messages[] = 'Triggered wiping of the sandbox template!';
		if(isset($_GET['triggerBuilds'])){
			$res = $db->sql_query("SELECT ".FILE_EXTRA_FRAGMENT.",".FILE_SELECT);
			$i = 0;
			while($o = $db->sql_fetchrow($res)){
				$o['no_extra_queries'] = true;
				$f = new File($o,true);
				$f->build();
				$i++;
			}
			$db->sql_freeresult($res);
			$messages[] = 'Triggered building for '.$i.' files';
		}
	}
}
$t->messages = $messages;

$body_template->addChild($t);
$body_template->render();
