<?php
include_once('config.php');
include_once('template.php');
session_start();
define('IN_PHPBB', true);
$phpbb_root_path = '../forum/';
$adminTypes = array(3);
$phpEx = substr(strrchr(__FILE__, '.'),1);
include($phpbb_root_path.'common.'.$phpEx);
if(isset($request)){
	$request->enable_super_globals();
}

define('FILE_EXTRA_FRAGMENT',"t1.`filename`,t1.`category`,t1.`forum_url`,t1.`repo_url`,t1.`version`,t1.`complexity`,UNIX_TIMESTAMP(t1.`ts_updated`) AS `ts_updated`,UNIX_TIMESTAMP(t1.`ts_added`) AS `ts_added`,t1.`hits`");
define('FILE_SELECT',"t1.`id`,t1.`author`,t1.`description`,t1.`images`,t1.`name`,t1.`downloads`,t1.`upvotes`,t1.`downvotes`,t2.`username`,t1.`public` FROM `archive_files` AS t1 INNER JOIN ".USERS_TABLE." AS t2 ON t1.`author` = t2.`user_id`");
define('FILE_EXTRA_SELECT',FILE_EXTRA_FRAGMENT." FROM `archive_files` AS t1");
define('AUTHOR_SELECT',"t1.`username`,t1.`user_id`,COUNT(t2.`id`) AS `files` FROM ".USERS_TABLE." AS t1 INNER JOIN `archive_files` AS t2 ON t1.`user_id`=t2.`author`");

$user->session_begin();
$auth->acl($user->data);
$user->setup();

$isLoggedIn = !($user->data['user_id'] == ANONYMOUS);
$isAdmin = $isLoggedIn && in_array((int)($user->data['user_type']),$adminTypes);
$username = $user->data['username'];
$userid = (int)($user->data['user_id']);


function generateRandomString($length = 20) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$charactersLength = strlen($characters);
	$randomString = '';
	for($i = 0; $i < $length; $i++){
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}
	return $randomString;
}

function validate_url($url){
	$url = trim($url);
	return ((strpos($url, "http://") === 0 || strpos($url, "https://") === 0) &&
			filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED) !== false);
}

function getUrl_safe($url){
	if(validate_url($url)){
		return $url;
	}
	return '';
}

function query_escape(){
	global $db;
	$params = func_get_args();
	$query = $params[0];
	$args = array();
	for($i = 1;$i < count($params);$i++){
		$args[$i-1] = $db->sql_escape($params[$i]);
	}
	return vsprintf($query,$args);
}

function getCategoryList(){
	global $db;
	$cats = array();
	$result = $db->sql_query("SELECT `id`,`name` FROM `archive_categories`");
	while($cat = $db->sql_fetchrow($result)){
		$cats[(int)$cat['id']] = $cat['name'];
	}
	$db->sql_freeresult($result);
	return $cats;
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

function cutAtChar($string,$width = 150){
	if(strlen($string) > $width){
		$string = wordwrap($string, $width);
		return substr($string,0,strpos($string, "\n")).' [...]';
	}
	return $string;
}

function getHelpHTML($s){
	return '<div class="help"><img src="help_icon.png"><div class="text">'.$s.'</div></div>';
}

class Author{
	private $id = -1;
	private $name = '';
	private $numFiles = 0;
	public function __construct($obj){
		global $db;
		if(!is_array($obj)){
			if(!is_numeric($obj)){
				return;
			}
			$result = $db->sql_query(query_escape("SELECT ".AUTHOR_SELECT." WHERE `user_id`=%d",(int)$obj));
			if(!($obj = $db->sql_fetchrow($result))){
				$db->sql_freeresult($result);
				return;
			}
			$db->sql_freeresult($result);
		}
		$this->id = (int)$obj['user_id'];
		$this->name = $obj['username'];
		$this->numFiles = $obj['files'];
	}
	public function exists(){
		return $this->id != -1;
	}
	public function json(){
		return array(
			'id' => $this->id,
			'name' => $this->name,
			'numFiles' => $this->numFiles,
			'exists' => $this->exists()
		);
	}
	public function template(){
		$t = new Template('author.inc');
		$t->loadJSON($this->json());
		return $t;
	}
}
class Screenshots {
	private static $uploadDir = 'uploads/screenshots/';
	public static $maxfilesize = 20971520;
	public static function delete($s){
		if($s != ''){
			@unlink(self::$uploadDir.$s);
		}
	}
	private static function realUpload($tmpName,$fileName,$fid,$screenshotNum){
		if(preg_match('#([ !\#$%\'()+-.\d;=@-\[\]-{}~]+)\.(\w+)$#',$fileName,$name)){
			if (in_array(strtolower($name[2]), array('png', 'gif', 'jpg', 'jpeg', 'bmp', 'wbmp'))) {
				$uploadName = self::$uploadDir.$fid.'.'.$screenshotNum.'.'.strtolower($name[2]);
				if(move_uploaded_file($tmpName,$uploadName)){
					if (filesize($uploadName) < self::$maxfilesize) {
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
	public static function upload($fid,$i,$filename){
		global $db;
		$html = 'Error uploading file '.($i+1).'. Maybe it isn\'t an image or it is too large?<br>';
		if(sizeof($_FILES)>0 && isset($_FILES['image'.$i]) && !is_array($_FILES['image'.$i]['name']) && $_FILES['image'.$i]['name'] !== ''){
			$uploadFileName = $_FILES['image'.$i]['name'];
			$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `screenshotNum`=`screenshotNum`+1 WHERE `id`=%d",$fid)));
			$result = $db->sql_query(query_escape("SELECT `screenshotNum` FROM `archive_files` WHERE `id`=%d",$fid));
			if($screenshotNumId = $db->sql_fetchrow($result)){
				$screenshotNum = (int)$screenshotNumId['screenshotNum'];
				if($extension = self::realUpload($_FILES['image'.$i]['tmp_name'],$uploadFileName,$fid,$screenshotNum)){
					$html = '';
					$filename = $fid.'.'.$screenshotNum.'.'.$extension;
				}
			}
			$db->sql_freeresult($result);
		}
		return array($filename,$html);
	}
}
class Upload {
	private static $uploadZipDir = 'uploads/zip/';
	private static $maxfilesize = 20971520;
	public static function getZipName($fid){
		return self::$uploadZipDir.$fid.'.zip';
	}
	private static function realUpload($tmpName,$fileName,$fid){
		if(preg_match('#([ !\#$%\'()+-.\d;=@-\[\]-{}~]+)\.(\w+)$#',$fileName,$name)){
			$extension = strtolower($name[2]);
			if(in_array($extension,array('zip'))){
				$name = self::getZipName($fid);
				$oldName = self::$uploadZipDir.$fid.'.old.zip';
				if(file_exists($name)){
					rename($name,$oldName);
				}
				if(move_uploaded_file($tmpName,$name)){
					$fh = @fopen($name,'r');
					$blob = fgets($fh,5);
					fclose($fh);
					if(strpos($blob,'PK') === 0){ // this is a zip file!
						if(filesize($name) < self::$maxfilesize){
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
	public static function zipFile($fid){
		global $db;
		if(sizeof($_FILES)>0 && isset($_FILES['zip']) && !is_array($_FILES['zip']['name'])){
			$fileName = $_FILES['zip']['name'];
			if(self::realUpload($_FILES['zip']['tmp_name'],$fileName,$fid)){
				$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `filename`='%s',`ts_updated`=FROM_UNIXTIME('%s') WHERE `id`=%d",$fileName,time(),$fid)));
				return true;
			}
		}
		return false;
	}
}

class File{
	private $id = -1;
	private $authorId = -1;
	private $author = '';
	private $description = '';
	private $images = array();
	private $name = '';
	private $downloads = 0;
	private $upvotes = 0;
	private $downvotes = 0;
	private $extra = false;
	private $public = false;
	
	private $filename = '';
	private $categories = array();
	private $forum_url = '';
	private $repo_url = '';
	private $version = 0;
	private $complexity = 0;
	private $ts_updated = 0;
	private $ts_added = 0;
	private $hits = 0;
	
	private $edit = false;
	private $file_type = array('type' => 0);
	private $autobuild = true;
	private $build_path = '';
	private $build_command = '';
	private $build_makefile = false;
	private $build_filename = '';
	private function populate_edit($obj){
		$this->file_type = array(
			'type' => (int)$obj['file_type']
		);
		if($this->file_type['type'] == 1){ // github!
			$this->file_type['git_url'] = $obj['git_url'];
			$this->file_type['github_repo'] = $obj['github_repo'];
		}
		$this->autobuild = $obj['autobuild']?true:false;
		$this->build_path = $obj['build_path'];
		$this->build_command = $obj['build_command'];
		$this->build_makefile = $obj['build_makefile']?true:false;
		$this->build_filename = $obj['build_filename'];
		
		$this->edit = true;
	}
	private function populate_extra($obj){
		$this->filename = $obj['filename'];
		$this->categories = array();
		foreach(explode('][',substr($obj['category'],1,strlen($obj['category'])-2)) as $c){
			$this->categories[] = (int)$c;
		}
		$this->forum_url = $obj['forum_url'];
		$this->repo_url = $obj['repo_url'];
		$this->version = (int)$obj['version'];
		$this->complexity = (int)$obj['complexity'];
		$this->ts_updated = (int)$obj['ts_updated'];
		$this->ts_added = (int)$obj['ts_added'];
		$this->hits = (int)$obj['hits'];
		
		$this->extra = true;
	}
	private function populate($obj,$extra = false){
		$this->id = (int)$obj['id'];
		$this->authorId = (int)$obj['author'];
		$this->description = $obj['description'];
		$this->images = json_decode($obj['images'],true);
		if(!$this->images){
			$this->images = array();
		}
		for($i = sizeof($this->images);$i < 4;$i++){
			$this->images[] = '';
		}
		$this->name = $obj['name'];
		$this->downloads = (int)$obj['downloads'];
		$this->upvotes = (int)$obj['upvotes'];
		$this->downvotes = (int)$obj['downvotes'];
		$this->author = $obj['username'];
		$this->public = $obj['public']?true:false;
		
		if($extra){
			$this->populate_extra($obj);
		}
	}
	private function goedit(){
		global $userid,$isAdmin;
		if($this->edit){
			return;
		}
		if(!($userid == $this->authorId || $isAdmin || !$this->exists)){
			return;
		}
		global $db;
		$result = $db->sql_query(query_escape("SELECT t1.`file_type`,t1.`git_url`,t1.`github_repo`,t1.`autobuild`,t1.`build_path`,t1.`build_command`,t1.`build_makefile`,t1.`build_filename` FROM `archive_files` AS t1 WHERE t1.`id`=%d",$this->id));
		if($obj = $db->sql_fetchrow($result)){
			$this->populate_edit($obj);
		}
		$db->sql_freeresult($result);
	}
	private function goextra(){
		if($this->extra){ // we are already!
			return;
		}
		global $db;
		$result = $db->sql_query(query_escape("SELECT ".FILE_EXTRA_SELECT." WHERE t1.`id`=%d",$this->id));
		if($obj = $db->sql_fetchrow($result)){
			$this->populate_extra($obj);
		}
		$db->sql_freeresult($result);
	}
	public function __construct($obj,$extra = false){
		global $db;
		if(!is_array($obj)){
			if(!is_numeric($obj)){
				return;
			}
			$result = $db->sql_query(query_escape("SELECT ".($extra?FILE_EXTRA_FRAGMENT.',':'').FILE_SELECT." WHERE t1.`id`=%d",(int)$obj));
			if(!($obj = $db->sql_fetchrow($result))){
				$db->sql_freeresult($result);
				return;
			}
			$db->sql_freeresult($result);
		}
		$this->populate($obj,$extra);
	}
	public function exists(){
		return $this->id != -1;
	}
	public function json_short(){
		$image = '1x1.png';
		if(isset($this->images[0]) && $this->images[0] != ''){
			$image = 'uploads/screenshots/'.$this->images[0];
		}
		return array(
			'exists' => $this->exists(),
			'id' => $this->id,
			'authorId' => $this->authorId,
			'author' => $this->author,
			'description' => cutAtChar($this->description),
			'downloads' => $this->downloads,
			'upvotes' => $this->upvotes,
			'downvotes' => $this->downvotes,
			'image' => $image,
			'name' => $this->name,
			'public' => $this->public
		);
	}
	public function json(){
		if(!$this->extra){
			$this->goextra();
		}
		return array_merge($this->json_short(),array(
			'hits' => $this->hits,
			'downloads' => $this->downloads,
			'ts_added' => $this->ts_added,
			'ts_updated' => $this->ts_updated,
			'version' => $this->version,
			'complexity' => $this->complexity,
			'description' => $this->description,
			'forum_url' => $this->forum_url,
			'repo_url' => $this->repo_url,
			'images' => $this->images,
			'categories' => $this->categories
		));
	}
	public function json_edit(){
		if(!$this->edit){
			$this->goedit();
		}
		return array_merge($this->json(),array(
			'file_type' => $this->file_type,
			'autobuild' => $this->autobuild,
			'build_path' => $this->build_path,
			'build_command' => $this->build_command,
			'build_makefile' => $this->build_makefile,
			'build_filename' => $this->build_filename
		));
	}
	public function template_short(){
		$t = new Template('file_short.inc');
		$t->loadJSON($this->json_short());
		return $t;
	}
	public function template($file = 'file.inc'){
		$t = new Template($file);
		$t->loadJSON($this->json());
		return $t;
	}
	public function template_edit($file = 'edit.inc'){
		if($this->edit){
			$this->goedit();
		}
		$t = new Template($file);
		$t->loadJSON($this->json_edit());
		return $t;
	}
	public function visit(){
		global $db;
		if($this->exists()){
			if(isset($_SESSION['archives_last_file']) && $_SESSION['archives_last_file']!=$this->id){
				$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `hits`=`hits`+1 WHERE `id`=%d",$this->id)));
			}
			$_SESSION['archives_last_file'] = $this->id;
		}
	}
	public function rate($dir = 0){
		global $userid,$isLoggedIn,$db;
		$dir = (int)$dir;
		if(($dir != 1  && $dir != -1)|| !$isLoggedIn){
			return array(
				'id' => $this->id,
				'upvotes' => $this->upvotes,
				'downvotes' => $this->downvotes
			);
		}
		$votes = false;
		$result = $db->sql_query(query_escape("SELECT `votes` FROM `archive_files` WHERE `id`=%d",$this->id));
		if($votes = $db->sql_fetchrow($result)){
			$votes = json_decode($votes['votes'],true);
			if($votes == NULL){
				$votes = array();
			}
		}
		$db->sql_freeresult($result);
		
		if($votes === false){
			return $this->rate(0);
		}
		if(isset($votes[$userid])){
			if($votes[$userid] > 0){
				$this->upvotes--;
			}else{
				$this->downvotes--;
			}
		}
		$votes[$userid] = $dir;
		if($dir > 0){
			$this->upvotes++;
		}else{
			$this->downvotes++;
		}
		$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `upvotes`=%d,`downvotes`=%d,`votes`='%s' WHERE `id`=%d",$this->upvotes,$this->downvotes,json_encode($votes),$this->id)));
		return $this->rate(0);
	}
	private function validate_save_vars($vars){
		global $db;
		if($vars['name'] != '' && $vars['complexity'] >= 0 && $vars['complexity'] <= 3 && $vars['version'] >= 0 && $vars['version'] <= 3 && preg_match("/^(\[\d+\])+$/",$vars['category'])){
			foreach(explode('][',substr($vars['category'],1,strlen($vars['category'])-2)) as $c){
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
	private function validate_save_filevars($vars){
		switch($vars['file_type']){
			case 0: // zip upload
				return sizeof($_FILES)>0 && isset($_FILES['zip']) && !is_array($_FILES['zip']['name']) && $_FILES['zip']['name'] !== '';
			case 1: // github
				return $vars['github_repo'] != '';
		}
		return false;
	}
	private function upload_save(&$vars){
		global $db,$userid;
		if(!$this->validate_save_filevars($vars)){
			return false;
		}
		$success = false;
		switch($vars['file_type']){
			case 0:
				$success = Upload::zipFile($this->id);
				break;
			case 1:
				$included = true;
				include_once('github.php');
				$u = new GithubUser($userid);
				if($success = $u->setRepo($vars['github_repo'],$this->id)){
					$vars['repo_url'] = $u->getRepoUrl();
				}
				break;
		}
		if($success){
			$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `file_type`=%d WHERE `id`=%d",$vars['file_type'],$this->id)));
		}
		return $success;
	}
	private function imagearray_save_vars($vars){
		$fileArray = $this->images;
		for($i = count($fileArray);$i < 4;$i++){
			$fileArray[] = '';
		}
		$html = '';
		for($i = 0;$i < 4;$i++){
			if($vars['delimage'.$i] == 'true'){
				if($fileArray[$i] != ''){
					Screenshots::delete($fileArray[$i]);
					$fileArray[$i] = '';
				}
			}
			if(sizeof($_FILES)>0 && isset($_FILES['image'.$i]) && !is_array($_FILES['image'.$i]['name']) && $_FILES['image'.$i]['name'] !== ''){
				$a = Screenshots::upload($this->id,$i,$fileArray[$i]);
				if($a[0] != $fileArray[$i]){ // delete potentially old files
					Screenshots::delete($fileArray[$i]);
					$fileArray[$i] = $a[0];
				}
				$html .= $a[1];
			}
		}
		return array($fileArray,$html);
	}
	private function examin(){
		global $frontend_socket_file;
		if($this->build_command == 'DETECTING'){ // no need to run this
			return;
		}
		$socket = socket_create(AF_UNIX,SOCK_STREAM,0);
		if(!@socket_connect($socket,$frontend_socket_file)){
			return;
		}
		$s = json_encode(array(
			'type' => 'examin',
			'fid' => $this->id
		))."\n";
		socket_write($socket,$s,strlen($s));
		socket_close($socket);
	}
	public function save(){
		global $userid,$isAdmin,$db;
		if(!($userid == $this->authorId || $isAdmin || !$this->exists())){
			return '';
		}
		$this->goedit();
		$newFile = !$this->exists();
		if(isset($_POST['omit_build_settings']) && $_POST['omit_build_settings']){
			unset($_POST['build_path']);
			unset($_POST['build_command']);
			unset($_POST['build_makefile']);
			unset($_POST['build_filename']);
		}
		$vars = array_merge(array(
			'name' => '',
			'description' => '',
			'forum_url' => '',
			'repo_url' => '',
			'version' => 0,
			'complexity' => 0,
			'category' => '',
			'delimage0' => 'false',
			'delimage1' => 'false',
			'delimage2' => 'false',
			'delimage3' => 'false',
			'file_type' => 0,
			'github_repo' => '',
			'build_path' => '',
			'build_command' => '',
			'build_makefile' => 0,
			'build_filename' => 0,
			'autobuild' => 0
		),$_POST);
		$vars['complexity'] = (int)$vars['complexity'];
		$vars['version'] = (int)$vars['version'];
		$vars['file_type'] = (int)$vars['file_type'];
		$vars['build_makefile'] = (int)$vars['build_makefile'];
		$vars['autobuild'] = (int)$vars['autobuild'];
		$vars['forum_url'] = getUrl_safe($vars['forum_url']);
		$vars['repo_url'] = getUrl_safe($vars['repo_url']);
		if(!$this->validate_save_vars($vars)){
			return 'Missing required fields';
		}
		if(!$this->exists()){
			if($this->validate_save_filevars($vars)){
				// we add the file here and upload it later!
				$db->sql_query(query_escape("INSERT INTO `archive_files` (`author`,`votes`) VALUES (%d,'{}')",$userid));
				$this->id = $db->sql_nextid();
			}else{
				return 'Missing zip file!';
			}
		}
		$s = '';
		if($this->validate_save_filevars($vars)){
			if(!$this->upload_save($vars)){
				$s .= "Couldn't upload zip file / update repo";
				if($newFile){
					$db->sql_freeresult($db->sql_query(query_escape("DELETE FROM `archive_files` WHERE `id`=%d",$this->id)));
					return $s;
				}
				$s .= '<br>';
			}
		}
		$imagesarray = $this->imagearray_save_vars($vars);
		$query = "UPDATE `archive_files` SET";
		$params = array();
		$updateVars = array('name','description','forum_url','repo_url','version','complexity','build_path','build_command','build_makefile','build_filename','autobuild');
		foreach(array_merge($updateVars,array('category')) as $v){
			if(isset($_POST[$v])){
				$query .= "`$v`='%s',";
				$params[] = $vars[$v];
			}
		}
		$query .= "`images`='%s' WHERE `id`=%d";
		$params[] = json_encode($imagesarray[0]);
		$params[] = $this->id;
		array_unshift($params,$query);
		
		$db->sql_freeresult($db->sql_query(call_user_func_array('query_escape',$params)));
		
		$s .= $imagesarray[1];
		foreach($updateVars as $k){
			if(isset($_POST[$k])){
				$this->$k = $vars[$k];
			}
		}
		$s .= 'Saved file! <a href="?file='.$this->id.'">view it</a>';
		if(isset($_POST['autopick_build_settings']) && $_POST['autopick_build_settings']){
			$this->examin();
		}
		return $s;
	}
}
class Files{
	private $files = array();
	private $limit = false;
	private function getFilesSQL($where = ''){
		global $isLoggedIn,$userid,$isAdmin;
		if(empty($where)){
			$where = array();
		}else{
			$where = array($where);
		}
		$s = "SELECT ".FILE_SELECT.' ';
		$cursort = (int)request_var('sort',0);
		$curdir = (int)request_var('direction',0);
		if($cursort > 5 || $cursort < 0){
			$cursort = 0;
		}
		if($curdir > 1 || $curdir < 0){
			$curdir = 0;
		}
		if($cursort == 2 || $cursort == 3){ // name and author are sorted the other way
			$curdir = 1-$curdir;
		}
		$sortcolumns = array(
			'ORDER BY t1.`ts_updated`',
			'ORDER BY t1.`ts_added`',
			'ORDER BY LOWER(t1.`name`)',
			'ORDER BY LOWER(t2.`username`)',
			'ORDER BY (t1.`upvotes`-t1.`downvotes`)',
			'ORDER BY t1.`downloads`'
		);
		$dirs = array('DESC','ASC');
		if(isset($_GET['tags']) && preg_match("/^(\[\d+\])+$/",$cats = $_GET['tags'])){
			$addWhere = '';
			foreach(explode('][',substr($cats,1,strlen($cats)-2)) as $c){
				// we already validated with the regex that $c can only be digits so it is safe to use without escaping
				if($c != 0){
					if($addWhere !== ''){
						$addWhere .= ' OR ';
					}
					$addWhere .= "t1.`category` LIKE '%[".$c."]%'";
				}
			}
			if(!empty($addWhere)){
				$where[] = $addWhere;
			}
		}
		if(!$isAdmin){ // admins get to see everything
			if($isLoggedIn){
				$where[] = "t1.`public` = 1 OR t1.`author`=".(int)$userid;
			}else{
				$where[] = "t1.`public` = 1";
			}
		}
		if(sizeof($where) > 1){
			$where = 'WHERE ('.implode(') AND (',$where).')';
		}elseif(!empty($where[0])){
			$where = 'WHERE '.$where[0];
		}else{
			$where = '';
		}
		
		$s .= $where.' '.$sortcolumns[$cursort].' '.$dirs[$curdir];
		if($this->limit){
			$curlimit = (int)request_var('limit',10);
			if($curlimit != -1){
				if($curlimit < 1){
					$curlimit = 1;
				}
				$s .= ' LIMIT '.$curlimit;
			}
		}
		return $s;
	}
	public function __construct($where = '',$limit = false){
		global $db;
		$this->limit = $limit;
		$result = $db->sql_query($this->getFilesSQL($where));
		$this->files = array();
		while($gamefile = $db->sql_fetchrow($result)){
			$this->files[] = new File($gamefile);
		}
		$db->sql_freeresult($result);
		
	}
	public function json(){
		$json = array();
		foreach($this->files as $f){
			$json[] = $f->json_short();
		}
		return $json;
	}
	public function template(){
		$t = new Template('files.inc');
		$t->limit = $this->limit;
		$t2 = array();
		foreach($this->files as $f){
			$t2[] = $f->template_short();
		}
		if(isset($_GET['getFiles'])){
			header('Content-Type: text/html');
			foreach($t2 as $tt){
				$tt->render();
			}
			exit;
		}
		$t->addChildren($t2);
		return $t;
	}
}
