<?php
include_once('archive.php');

function panic(){
	header('Location:?error');
	die();
}

function getCategoryListDropdown($cid = 1,$pre = ''){
	global $db;
	$cats = array();
	$result = $db->sql_query(query_escape("SELECT `id`,`name` FROM `archive_categories` WHERE `category`=%d",$cid));
	while($cat = $db->sql_fetchrow($result)){
		if((int)$cat['id'] != 1){
			$cats['_'.$cat['id']] = $pre.'> '.$cat['name'];
			$cats = $cats + getCategoryListDropdown((int)$cat['id'],$pre.'-');
		}
	}
	$db->sql_freeresult($result);
	return $cats;
}

class Uploads {
	private $uploadZipDir = 'uploads/zip/';
	private $maxfilesize = 20971520;
	public function getZipName($fid){
		return $this->uploadZipDir.$fid.'.zip';
	}
	private function realUpload($tmpName,$fileName,$fid){
		if(preg_match('#([ !\#$%\'()+-.\d;=@-\[\]-{}~]+)\.(\w+)$#',$fileName,$name)){
			$extension = strtolower($name[2]);
			if(in_array($extension,array('zip'))){
				$name = $this->getZipName($fid);
				$oldName = $this->uploadZipDir.$fid.'.old.zip';
				if(file_exists($name)){
					rename($name,$oldName);
				}
				if(move_uploaded_file($tmpName,$name)){
					$fh = @fopen($name,'r');
					$blob = fgets($fh,5);
					fclose($fh);
					if(strpos($blob,'PK') === 0){ // this is a zip file!
						if(filesize($name) < $this->maxfilesize){
							$zip = new ZipArchive();
							if($zip->open($name)){
								if($zip->numFiles > 0){
									$zip->close();
									if(file_exists($oldName)){
										unlink($oldName);
									}
									return true;
								}
								$zip->close();
							}
						}
					}
				}
				if(file_exists($name)){
					unlink($name);
				}
				rename($oldName,$name);
			}
		}
		return false;
	}
	public function uploadZipFile($fid){
		global $db;
		if(sizeof($_FILES)>0 && isset($_FILES['zip']) && !is_array($_FILES['zip']['name'])){
			$fileName = $_FILES['zip']['name'];
			if($this->realUpload($_FILES['zip']['tmp_name'],$fileName,$fid)){
				$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `filename`='%s',`ts_updated`=FROM_UNIXTIME('%s') WHERE `id`=%d",$fileName,time(),$fid)));
				return true;
			}
		}
		return false;
	}
}
$upload = new Uploads();

class Screenshots {
	private $uploadDir = 'uploads/screenshots/';
	public $maxfilesize = 20971520;
	public function delete($s){
		if($s != ''){
			@unlink($this->uploadDir.$s);
		}
	}
	private function realUpload($tmpName,$fileName,$fid,$screenshotNum){
		if(preg_match('#([ !\#$%\'()+-.\d;=@-\[\]-{}~]+)\.(\w+)$#',$fileName,$name)){
			if (in_array(strtolower($name[2]), array('png', 'gif', 'jpg', 'jpeg', 'bmp', 'wbmp'))) {
				$uploadName = $this->uploadDir.$fid.'.'.$screenshotNum.'.'.strtolower($name[2]);
				if(move_uploaded_file($tmpName,$uploadName)){
					if (filesize($uploadName) < $this->maxfilesize) {
						if ($j = @imagecreatefromstring($h = file_get_contents($uploadName)) or substr($h, 0, 2) == 'BM') {
							return strtolower($name[2]);
						}else{
							unlink($uploadName);
						}
					}else{
						unlink($uploadName);
					}
				}
			}
		}
		return false;
	}
	public function upload($fid,$i,$filename){
		global $db;
		$html = 'Error uploading file '.($i+1).'. Maybe it isn\'t an image or it is too large?<br>';
		if(sizeof($_FILES)>0 && isset($_FILES['image'.$i]) && !is_array($_FILES['image'.$i]['name']) && $_FILES['image'.$i]['name'] !== ''){
			$uploadFileName = $_FILES['image'.$i]['name'];
			$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `screenshotNum`=`screenshotNum`+1 WHERE `id`=%d",$fid)));
			$result = $db->sql_query(query_escape("SELECT `screenshotNum` FROM `archive_files` WHERE `id`=%d",$fid));
			if($screenshotNumId = $db->sql_fetchrow($result)){
				$screenshotNum = (int)$screenshotNumId['screenshotNum'];
				if($extension = $this->realUpload($_FILES['image'.$i]['tmp_name'],$uploadFileName,$fid,$screenshotNum)){
					$html = '';
					$filename = $fid.'.'.$screenshotNum.'.'.$extension;
				}
			}
			$db->sql_freeresult($result);
		}
		return array($filename,$html);
	}
}
$screenshots = new Screenshots();

function validateUpload(){
	global $db,$versionsDropdown,$complexitiesDropdown;
	$complexity = request_var('complexity',0);
	$version = request_var('version',0);
	$cid = request_var('category','');
	if(request_var('name','') != '' && $complexity >= 0 && $complexity <= sizeof($complexitiesDropdown) && $version >= 0 && $version <= sizeof($versionsDropdown) && preg_match("/^(\[\d+\])+$/",$cid)){
		foreach(explode('][',substr($cid,1,strlen($cid)-2)) as $c){
			$result = $db->sql_query(query_escape("SELECT `id` FROM `archive_categories` WHERE `id`=%d",$c));
			if(!$db->sql_fetchrow($result)){
				$db->sql_freeresult($result);
				return false;
			}
			$db->sql_freeresult($result);
		}
		return true;
	}
	return false;
}
function getImagesArrayFromUpload($fid,$fileArray = false){
	global $screenshots;
	if(!$fileArray){
		$fileArray = array();
	}
	for($i = count($fileArray);$i < 4;$i++){
		$fileArray[] = '';
	}
	$html = '';
	
	for($i = 0;$i < 4;$i++){
		if(request_var('delimage'.$i,'') == 'true'){
			if($fileArray[$i] != ''){
				$screenshots->delete($fileArray[$i]);
				$fileArray[$i] = '';
			}
		}
		if(sizeof($_FILES)>0 && isset($_FILES['image'.$i]) && !is_array($_FILES['image'.$i]['name']) && $_FILES['image'.$i]['name'] !== ''){
			$a = $screenshots->upload($fid,$i,$fileArray[$i]);
			if($a[0] != $fileArray[$i]){
				$screenshots->delete($fileArray[$i]);
				$fileArray[$i] = $a[0];
			}
			$html .= $a[1];
		}
	}
	return array($fileArray,$html);
}

function getDlFiles($fid){
	global $upload;
	$zip = new ZipArchive();
	if($zip->open($upload->getZipName($fid)) !== false){
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
				$realzip = $upload->getZipName($fid);
			}else{
				$dlFiles = getDlFiles($fid) or panic();
				$newzip = new ZipArchive();
				$oldzip = new ZipArchive();
				$realzip = 'tmp/'.generateRandomString().time().'.zip';
				if($oldzip->open($upload->getZipName($fid))){
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
					if($oldzip->open($upload->getZipName($fid))){
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
	$fid = request_var('edit','invalid');
	$f = new File($fid,true);
	$t = $f->template('edit.inc');
	if($t->exists){
		$body_template->title = $t->name;
	}else{
		$body_template->title = 'Error';
	}
	$templates[] = $t;
	
}elseif(request_var('save',false)){
	$fid = request_var('save','invalid');
	$title = 'Error';
	$html = '<b>Error: Permission Denied</b>';
	if($isLoggedIn && (int)$fid == $fid){
		$result = $db->sql_query(query_escape("SELECT `author`,`images` FROM `archive_files` WHERE `id`=%d",$fid));
		if($gamefile = $db->sql_fetchrow($result)){
			if($userid == $gamefile['author'] || $isAdmin){ // we may edit the file
				if(validateUpload()){
					$imagesarray = getImagesArrayFromUpload($fid,json_decode($gamefile['images'],true));
					$db->sql_freeresult($db->sql_query(query_escape(
						"UPDATE `archive_files` SET `name`='%s',`description`='%s',`forum_url`='%s',`repo_url`='%s',`version`=%d,`complexity`=%d,`category`='%s',`images`='%s' WHERE `id`=%d",
							request_var('name','invalid'),request_var('description',''),getUrl_safe(request_var('forum_url','')),getUrl_safe(request_var('repo_url',''))
							,request_var('version',0),request_var('complexity',0),request_var('category','invalid'),json_encode($imagesarray[0])
							,$fid)));
					$html = '
						Saved file information for <i>'.htmlentities(request_var('name','invalid')).'</i>!<br>';
					if(sizeof($_FILES)>0 && isset($_FILES['zip']) && !is_array($_FILES['zip']['name']) && $_FILES['zip']['name'] !== ''){
						if($upload->uploadZipFile($fid)){
							$html .= 'Uploaded new zip file!<br>';
						}else{
							$html .= 'Error uploading new zip, maybe file isn\'t a zip? Maybe it is too large?<br>';
						}
					}
					$html .= $imagesarray[1];
				}else{
					$html = 'Error validating form, maybe you are missing required fields?<br>';
				}
				$html .= '<a href="?file='.$fid.'">Back</a>
				';
			}
		}
		$db->sql_freeresult($result);
	}
	$page->getPage($title,$html);
}elseif(isset($_GET['upload'])){
	$title = 'Upload file';
	$html = 'You need to <a href="/forum/ucp.php?mode=register">Register</a> or <a href="/forum/ucp.php?mode=login">Login</a> to be able to upload a file!';
	if($isLoggedIn){
		if(validateUpload() && sizeof($_FILES)>0 && isset($_FILES['zip']) && !is_array($_FILES['zip']['name']) && $_FILES['zip']['name'] !== ''){
			$db->sql_query(query_escape(
						"INSERT INTO `archive_files` (`name`,`description`,`forum_url`,`repo_url`,`version`,`complexity`,`category`,`author`,`images`,`votes`,`filename`,`ts_updated`) VALUES ('%s','%s','%s','%s',%d,%d,'%s',%d,'','{}','',FROM_UNIXTIME('%s'))",
							request_var('name','invalid'),request_var('description',''),getUrl_safe(request_var('forum_url','')),getUrl_safe(request_var('repo_url',''))
							,request_var('version',0),request_var('complexity',0),request_var('category','invalid'),$userid,time()
							));
			
			$fid = $db->sql_nextid();
			if($upload->uploadZipFile($fid)){
				$html = 'Uploaded new file <i>'.htmlentities(request_var('name','invalid')).'</i>!<br><a href="?file='.$fid.'">View file</a><br>';
				$imagesarray = getImagesArrayFromUpload($fid);
				$db->sql_query(query_escape("UPDATE `archive_files` SET `images`='%s' WHERE `id`=%d",json_encode($imagesarray[0]),$fid));
				$html .= $imagesarray[1];
			}else{
				$db->sql_freeresult($db->sql_query(query_escape("DELETE FROM `archive_files` WHERE `id`=%d",$fid)));
				$html = 'Error uploading zip, maybe file isn\'t a zip? Maybe it is too large?<br><a href="?newfile">Back</a>';
			}
		}else{
			$html = 'Error: Missing required field<br><a href="?newfile">Back</a>';
		}
	}
	$page->getPage($title,$html);
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
?>
