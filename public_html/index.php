<?php
$startTime = microtime(true);
include_once('archive.php');

function panic(){
	header('Location:?error');
	die();
}


$templates = array();
$body_template = new Template('body.inc');
$body_template->title = '';
$body_template->startTime = $startTime;

if(request_var('file',false)){
	$fid = request_var('file','invalid');
	$f = new File($fid,true);
	$f->visit();
	$body_template->title = 'File not found';
	$t = $f->template();
	if($f->exists()){
		$body_template->title = $t->name;
	}
	if($t->zip){
		$zip = new ZipArchive();
		if($zip->open($t->zip)){
			$z = array();
			for($i = 0;$i < $zip->numFiles;$i++){
				$z[] = $zip->getNameIndex($i);
			}
			$t->zip = $z;
		}else{
			$t->zip = '';
		}
	}
	
	$templates[] = $t;
}elseif(request_var('author',false)){
	$aid = request_var('author','invalid');
	$html = '<b>Error: author not found</b>';
	$title = 'Author not found';
	
	$body_template->title = 'Recent Files';
	$a = new Author($aid);
	
	$at = $a->template();
	$at->url = '?author='.$at->id;
	$templates[] = $at;
	$body_template->title = $at->name;
	
	if($at->exists){
		$f = new Files("t1.`author`=".$at->id);
		$ft = $f->template();
		$ft->url = '?author='.$at->id;
		$templates[] = $ft;
	}else{
		$body_template->title = 'Invalid author';
	}
}elseif(request_var('rate',false)){
	$fid = request_var('rate','invalid');
	$f = new File($fid,true);
	$res = $f->rate((int)request_var('dir',0));
	if(isset($_GET['json'])){
		header('Content-Type:application/json');
		echo json_encode($res);
		exit;
	}
	header('Location: ?file='.$res['id']);
	exit;
}elseif(request_var('edit',false)){
	$f = new File(request_var('edit','invalid'),true);
	$t = $f->template_edit();
	if($t->exists){
		$body_template->title = $t->name;
	}else{
		$body_template->title = 'Error';
	}
	$templates[] = $t;
}elseif(request_var('edit_authorcheck',false)){
	$result = $db->sql_query(query_escape("SELECT `username`,`user_id` AS `id` FROM ".USERS_TABLE." WHERE `username_clean` LIKE '%s' LIMIT 1",strtolower(request_var('edit_authorcheck','INVALID')).'%'));
	header('Content-Type: application/json');
	if($u = $db->sql_fetchrow($result)){
		echo json_encode($u);
	}else{
		echo json_encode(array(
			'id' => -1,
			'username' => ''
		));
	}
	$db->sql_freeresult($result);
}elseif(request_var('get_build',false)){
	global $db;
	$result = $db->sql_query(query_escape("SELECT `id`,`status`,UNIX_TIMESTAMP(`ts`) AS `ts`,`file` FROM `archive_queue` WHERE `type`=0 AND `id`=%d",(int)request_var('get_build','invalid')));
	header('Content-Type: application/json');
	if($b = $db->sql_fetchrow($result)){
		$f = new File($b['file']);
		if($f->canEdit()){
			$t = new Template('edit_build.inc');
			$t->id = (int)$b['id'];
			$t->status = (int)$b['status'];
			$t->ts = (int)$b['ts'];
			
			ob_start();
			$t->render();
			$t = ob_get_contents();
			ob_end_clean();
			echo json_encode(array(
				'success' => true,
				'pending' => ($b['status'] == 1 || $b['status'] == 2),
				'html' => $t
			));
		}else{
			echo json_encode(array(
				'success' => false
			));
		}
	}else{
		echo json_encode(array(
			'success' => false
		));
	}
	$db->sql_freeresult($result);
}elseif(request_var('delete_build',false)){
	global $db;
	$result = $db->sql_query(query_escape("SELECT `id`,`file`,`status` FROM `archive_queue` WHERE `type`=0 AND `id`=%d",(int)request_var('delete_build','invalid')));
	$success = false;
	if(($b = $db->sql_fetchrow($result)) && ($b['status'] == 0 || $b['status'] == 4)){
		$f = new File($b['file']);
		if($f->canEdit()){
			$db->sql_query(query_escape("DELETE FROM `archive_queue` WHERE `id`=%d",(int)$b['id']));
			$success = true;
		}
	}
	$db->sql_freeresult($result);
	header('Content-Type: text/javascript');
	echo json_encode(array(
		'success' => $success
	));
}elseif(request_var('save',false)){
	$body_template->title = 'Saving';
	$f = new File(request_var('save','invalid'));
	$templates[] = $f->save();
}elseif(request_var('build',false)){
	global $db;
	$f = new File(request_var('build','invalid'));
	header('Content-Type: application/json');
	$id = $f->build();
	echo json_encode(array(
		'success' => $id != -1,
		'id' => $id
	));
}elseif(request_var('build_message',false)){
	header('Content-Type: text/plain');
	echo getBuildOutputMessage(request_var('build_message','invalid'));
}elseif(request_var('getBuildVars',false)){
	$f = new File(request_var('getBuildVars','invalid'));
	$j = $f->json_edit();
	header('Content-Type: application/json');
	echo json_encode(array(
		'build_path' => $j['build_path'],
		'build_command' => $j['build_command'],
		'build_makefile' => $j['build_makefile'],
		'build_filename' => $j['build_filename'],
		'build_movepath' => $j['build_movepath']
	));
}elseif(isset($_GET['error'])){
	$body_template->title = 'Error';
	$templates[] = 'Something went wrong! Be sure to <a href="https://github.com/Sorunome/gamebuino-archives/issues" target="_blank">report the issue</a>!';
}else{
	$body_template->title = 'Recent Files';
	$files = new Files('',true);
	$templates[] = $files->template();
}
if(sizeof($templates) > 0){
	if($body_template->title != ''){
		$body_template->addChildren($templates);
		$body_template->render();
	}else{
		foreach($templates as $t){
			if(is_string($t)){
				echo $t;
			}else{
				$t->render();
			}
		}
	}
}
