<?php
include_once('archive.php');

function panic(){
	header('Location:index.php?error');
	die();
}

function getDlFiles($info){
	switch($info['type']){
		case 'zip':
			$zip = new ZipArchive();
			if(!$zip->open(Upload::getZipName($info['id']))){
				return false;
			}
			$a = array();
			$allFiles = array();
			if($s = $zip->getFromName('download.txt')){
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
			foreach($a as &$i){
				$i = array(
					'in' => $i,
					'out' => $i
				);
			}
			return array(
				'type' => 'zip',
				'name' => Upload::getZipName($info['id']),
				'files' => $a
			);
		case 'build':
			$folder = '';
			if(!is_dir('files/gb1/'.$info['id'])){
				return false;
			}
			if(isset($_GET['time']) && is_numeric($_GET['time'])){
				$folder = 'files/gb1/'.$info['id'].'/'.$_GET['time'];
			}else{
				$latest = 0;
				foreach(scandir('files/gb1/'.$info['id']) as $ts){
					if(is_numeric($ts) && $ts > $latest){
						$latest = $ts;
					}
				}
				if(!$latest){
					return false;
				}
				$folder = 'files/gb1/'.$info['id'].'/'.$latest;
			}
			if(!$folder || !is_dir($folder)){
				return false;
			}
			$folder = realpath($folder);
			
			$a = array();
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($folder),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach($files as $file){
				if(!$file->isDir()){
					$filePath = $file->getRealPath();
					$relativePath = substr($filePath,strlen($folder)+1);
					$a[] = array(
						'in' => $filePath,
						'out' => $relativePath
					);
				}
			}
			return array(
				'type' => 'files',
				'files' => $a
			);
	}
	return false;
}
function _populateZip_inner($zip,$pattern){
	switch($pattern['type']){
		case 'zip':
			$oldzip = new ZipArchive();
			if(!$oldzip->open($pattern['name'])){
				return;
			}
			foreach($pattern['files'] as $f){
				if(!$f['out']){
					continue;
				}
				$zip->addFromString($f['out'],$oldzip->getFromName($f['in']));
			}
			return;
		case 'files':
			foreach($pattern['files'] as $f){
				if(!$f['out']){
					continue;
				}
				$zip->addFile($f['in'],$f['out']);
			}
			return;
	}
}
function populateZip($pattern){
	$zippath = 'tmp/'.generateRandomString().time().'.zip';
	$zip = new ZipArchive();
	if(!$zip->open($zippath, ZIPARCHIVE::CREATE)){
		return false;
	}
	if(count(array_filter(array_keys($pattern),'is_string'))==0){
		foreach($pattern as $p){
			_populateZip_inner($zip,$p);
		}
	}else{
		_populateZip_inner($zip,$pattern);
	}
	$zip->close();
	return $zippath;
}

function dlZip($zippath,$name = 'gamebuino.zip'){
	if(!$zippath || !file_exists($zippath)){
		return false;
	}
	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename='.$name);
	header('Content-length: '.filesize($zippath));
	header('Proagma: no-cache');
	header('Expires: 0');
	readfile($zippath);
	return true;
}
if(request_var('id',false)){
	$f = new File(request_var('id','invalid'),true);
	
	$i = $f->downloadInfo();
	
	($i['type'] != 'invalid' && $i['filename']) or panic();
	
	
	
	$zippath = '';
	$delzip = false;
	if(isset($_GET['all'])){
		$zippath = Upload::getZipName($i['id']);
	}else{
		$dlFiles = getDlFiles($i) or panic();
		$zippath = populateZip($dlFiles) or panic();
		$delzip = true;
	}
	dlZip($zippath,$i['filename']) or panic();
	
	if($delzip){
		unlink($zippath);
	}
	$f->download();
}elseif(request_var('mult',false)){
	$s = request_var('mult','invalid');
	preg_match('/^[0-9]+(|,[0-9]+)+$/',$s) or panic();
	$fids = array_map('intval',explode(',',$s)); // we don't know yet if they actually exist
	sort($fids);
	
	$res = $db->sql_query("SELECT ".FILE_EXTRA_FRAGMENT.",".FILE_SELECT." WHERE t1.`id` IN (".implode(',',$fids).") ORDER BY t1.`id` ASC");
	$files = array();
	while($o = $db->sql_fetchrow($res)){
		$files[] = new File($o,true);
	}
	$db->sql_freeresult($res);
	$dlFiles = array();
	$knownFiles = array();
	foreach($files as $f){
		$d = getDlFiles($f->downloadInfo()) or panic();
		foreach($d['files'] as &$df){
			if(in_array($df['out'],$knownFiles)){
				if(preg_match('#([ !\#$%\'()+-.\d;=@-\[\]-{}~]+)\.(\w+)$#',$df['out'],$name)){
					for ($i = 0; in_array($name[0],$knownFiles); $name[0] = $name[1] . '-' . (++$i) . '.' . $name[2]);
					$df['out'] = $name[0];
				}else{
					$df['out'] = '';
					continue;
				}
			}
			$knownFiles[] = $df['out'];
		}
		$dlFiles[] = $d;
	}
	
	$zippath = populateZip($dlFiles) or panic();
	dlZip($zippath) or panic();
	unlink($zippath);
	foreach($files as $f){
		$f->download();
	}
}
