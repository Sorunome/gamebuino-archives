<?php
$startTime = microtime(true);
include_once('archive.php');

function panic(){
	header('Location:?error');
	die();
}

function getDlFiles($fid){
	$zip = new ZipArchive();
	if($zip->open(Upload::getZipName($fid)) !== false){
		$a = array();
		$allFiles = array();
		if(($s = $zip->getFromName('download.txt')) !== false){
			$searchArray = explode("\n",$s);
			for($i = 0;$i < $zip->numFiles;$i++){
				$n = $zip->getNameIndex($i);
				$allFiles[] = $n;
				if(in_array($n,$searchArray)){
					$a[] = $n;
				}
			}
		}else{
			for($i = 0;$i < $zip->numFiles;$i++){
				$n = $zip->getNameIndex($i);
				$allFiles[] = $n;
				if(preg_match('@^([^/]+\.(HEX|INF))$@i',$n,$name)){
					$a[] = $name[0];
				}
			}
		}
		$zip->close();
		if(sizeof($a) == 0){
			$a = $allFiles;
		}
		return $a;
	}else{
		return false;
	}
}
function getDlFilesMult($fids){
	$rename = 0;
	$curFilenames = array();
	$dlChangeNames = array();
	foreach($fids as $fid){
		$fdl = getDlFiles($fid) or panic();
		$dlChangeNames[$fid] = array();
		foreach($fdl as $f){
			$nf = $f;
			$dofile = true;
			if(in_array($f,$curFilenames)){
				$dofile = false;
				if($rename < 1){
					$rename = 1;
				}
				if(preg_match('#([ !\#$%\'()+-.\d;=@-\[\]-{}~]+)\.(\w+)$#',$f,$name)){
					$dofile = true;
					for ($i = 0; in_array($name[0],$curFilenames); $name[0] = $name[1] . '-' . (++$i) . '.' . $name[2]);
					$nf = $name[0];
				}elseif($rename < 2){
					$rename = 2;
				}
			}
			if($dofile){
				$curFilenames[] = $nf;
				$dlChangeNames[$fid][$f] = $nf;
			}
		}
	}
	return array($rename,$dlChangeNames);
}

$templates = array();
$body_template = new Template('body.inc');
$body_template->title = '';
$body_template->startTime = $startTime;

if(request_var('file',false)){
	$fid = request_var('file','invalid');
	$f = new File($fid,true);
	$body_template->title = 'File not found';
	$t = $f->template();
	if($f->exists()){
		$body_template->title = $t->name;
	}
	$templates[] = $t;
/*		if($file->exists()){
		$file->visit();
		$html = $file->html();
		
	}*/
	
		/*
		$dlFiles = getDlFiles($fid) or panic();
		$zip = new ZipArchive();
		if($zip->open($upload->getZipName($fid))){
			$html .= '<div id="zipcontentswrap"><div id="zipcontents">
				<div id="zipcontentsheader">Archive contents ( <a href="?dl='.$fid.'&all" download>Download all</a> )</div>';
			for($i = 0;$i < $zip->numFiles;$i++){
				$name = $zip->getNameIndex($i);
				$html .= '<div class="zipcontentsitem'.(in_array($name,$dlFiles)?' dlfile':'').'">'.htmlentities($name).'</div>';
			}
			$html .= '</div></div>';
			$zip->close();
		}else{
			$html .= '<b>Couldn\'t open zip archive!</b>';
		}*/
	
}elseif(request_var('dl',false)){
	$fid = request_var('dl','invalid');
	if((int)$fid == $fid){
		$result = $db->sql_query(query_escape("SELECT `filename` FROM `archive_files` WHERE `id`=%d",$fid));
		if($gamefile = $db->sql_fetchrow($result)){
			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename='.$gamefile['filename']);
			if(isset($_GET['all'])){
				$realzip = Upload::getZipName($fid);
			}else{
				$dlFiles = getDlFiles($fid) or panic();
				$newzip = new ZipArchive();
				$oldzip = new ZipArchive();
				$realzip = 'tmp/'.generateRandomString().time().'.zip';
				if($oldzip->open(Upload::getZipName($fid))){
					if($newzip->open($realzip, ZIPARCHIVE::CREATE)){
						foreach($dlFiles as $f){
							$newzip->addFromString($f,$oldzip->getFromName($f));
						}
						$oldzip->close();
						$newzip->close();
					}else{
						panic();
					}
				}else{
					panic();
				}
			}
			header('Content-length: '.filesize($realzip));
			header('Proagma: no-cache');
			header('Expires: 0');
			readfile($realzip);
			$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `downloads`=`downloads`+1 WHERE `id`=%d",$fid)));
			if(!isset($_GET['all'])){ // we only had a temp file, delete it
				unlink($realzip);
			}
		}else{
			panic();
		}
		$db->sql_freeresult($result);
	}else{
		panic();
	}
}elseif(request_var('dlmult',false)){
	$s = request_var('dlmult','invalid');
	if(preg_match('/^[0-9]+(|,[0-9]+)+$/',$s)){
		$preFids = explode(',',$s); // we don't know yet if they actually exist
		$fids = array();
		foreach($preFids as $fid){
			// no need to check if $fid is and int as that is implied with the regex
			$result = $db->sql_query(query_escape("SELECT `filename` FROM `archive_files` WHERE `id`=%d",$fid));
			if($gamefile = $db->sql_fetchrow($result)){
				$fids[] = $fid;
				if(!isset($_GET['info'])){
					$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `downloads`=`downloads`+1 WHERE `id`=%d",$fid)));
				}
			}
			$db->sql_freeresult($result);
		}
		$dlFiles = getDlFilesMult($fids);
		
		if(isset($_GET['info'])){
			header('Content-Type:application/json');
			echo json_encode(array('stability' => $dlFiles[0]));
		}else{
			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename=gamebuino_games.zip');
			$newzip = new ZipArchive();
			$oldzip = new ZipArchive();
			$realzip = 'tmp/'.generateRandomString().time().'.zip';
			if($newzip->open($realzip, ZIPARCHIVE::CREATE)){
				foreach($dlFiles[1] as $fid => $files){
					if($oldzip->open(Upload::getZipName($fid))){
						foreach($files as $oldf => $newf){
							$newzip->addFromString($newf,$oldzip->getFromName($oldf));
						}
						$oldzip->close();
					}else{
						panic();
					}
				}
				$newzip->close();
			}else{
				panic();
			}
			header('Content-length: '.filesize($realzip));
			header('Proagma: no-cache');
			header('Expires: 0');
			readfile($realzip);
			unlink($realzip);
		}
	}else{
		panic();
	}
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
}elseif(request_var('edit_builds',false)){
	$f = new File(request_var('edit_builds','invalid'));
	header('Content-Type: text/html');
	foreach($f->template_builds() as $b){
		$b->render();
	}
}elseif(request_var('save',false)){
	$body_template->title = 'Saving';
	$f = new File(request_var('save','invalid'));
	$templates[] = $f->save();
}elseif(request_var('build',false)){
	$f = new File(request_var('build','invalid'));
	header('Content-Type: application/json');
	echo json_encode(array(
		'success' => $f->build()
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
