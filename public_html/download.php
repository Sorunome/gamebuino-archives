<?php
include_once('archive.php');

function panic(){
	header('Location:index.php?error');
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
if(request_var('id',false)){
	$f = new File(request_var('id','invalid'),true);
	
	$i = $f->downloadInfo();
	
	($i['type'] != 'invalid' && $i['filename']) or panic();
	
	
	
	$zippath = '';
	$delzip = false;
	if($i['type'] == 'zip'){
		if(isset($_GET['all'])){
			$zippath = Upload::getZipName($i['id']);
		}else{
			$dlFiles = getDlFiles($i['id']) or panic();
			$newzip = new ZipArchive();
			$oldzip = new ZipArchive();
			$zippath = 'tmp/'.generateRandomString().time().'.zip';
			($oldzip->open(Upload::getZipName($i['id'])) && $newzip->open($zippath, ZIPARCHIVE::CREATE)) or panic();
			foreach($dlFiles as $f){
				$newzip->addFromString($f,$oldzip->getFromName($f));
			}
			$oldzip->close();
			$newzip->close();
			
			$delzip = true;
		}
	}elseif($i['type'] == 'build'){
		$folder = '';
		is_dir('files/gb1/'.$i['id']) or panic();
		if(isset($_GET['time']) && is_numeric($_GET['time'])){
			$folder = 'files/gb1/'.$i['id'].'/'.$_GET['time'];
		}else{
			$latest = 0;
			foreach(scandir('files/gb1/'.$i['id']) as $ts){
				if(is_numeric($ts) && $ts > $latest){
					$latest = $ts;
				}
			}
			$latest or panic();
			$folder = 'files/gb1/'.$i['id'].'/'.$latest;
		}
		$folder or panic();
		is_dir($folder) or panic();
		$folder = realpath($folder);
		
		$zippath = 'tmp/'.generateRandomString().time().'.zip';
		$zip = new ZipArchive();
		$zip->open($zippath, ZIPARCHIVE::CREATE) or panic();
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($folder),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach($files as $file){
			if(!$file->isDir()){
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath,strlen($folder)+1);
				$zip->addFile($filePath,$relativePath);
			}
		}
		$zip->close();
		
		$delzip = true;
	}
	
	$zippath or panic();
	file_exists($zippath) or panic();
	
	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename='.$i['filename']);
	header('Content-length: '.filesize($zippath));
	header('Proagma: no-cache');
	header('Expires: 0');
	readfile($zippath);
	if($delzip){
		unlink($zippath);
	}
	$f->download();
}
