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
