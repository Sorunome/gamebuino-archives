<?php
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
define('FILE_SELECT',"t1.`id`,t1.`author`,t1.`description`,t1.`images`,t1.`name`,t1.`downloads`,t1.`upvotes`,t1.`downvotes`,t2.`username` FROM `archive_files` AS t1 INNER JOIN ".USERS_TABLE." AS t2 ON t1.`author` = t2.`user_id`");
define('FILE_EXTRA_SELECT',FILE_EXTRA_FRAGMENT." FROM `archive_files` AS t1 INNER JOIN ".USERS_TABLE." AS t2 on t1.`author` = t2.`user_id`");
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

function cutAtChar($string,$width = 150){
	if(strlen($string) > $width){
		$string = wordwrap($string, $width);
		return substr($string,0,strpos($string, "\n")).' [...]';
	}
	return $string;
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
	
	private $filename = '';
	private $categories = array();
	private $forum_url = '';
	private $repo_url = '';
	private $version = 0;
	private $complexity = 0;
	private $ts_updated = 0;
	private $ts_added = 0;
	private $hits = 0;
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
		if($extra){
			$this->populate_extra($obj);
		}
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
			'name' => $this->name
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
			'ts_udated' => $this->ts_updated,
			'version' => $this->version,
			'complexity' => $this->complexity,
			'description' => $this->description,
			'forum_url' => $this->forum_url,
			'repo_url' => $this->repo_url,
			'images' => $this->images,
			'categories' => $this->categories
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
}
class Files{
	private $files = array();
	private function getFilesSQL($where = '',$limit = false){
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
			if($where === ''){
				$where = $addWhere;
			}elseif($addWhere !== ''){
				$where = '('.$where.') AND ('.$addWhere.')';
			}
		}
		if($where !== ''){
			$where = 'WHERE '.$where;
		}
		$s .= $where.' '.$sortcolumns[$cursort].' '.$dirs[$curdir];
		if($limit){
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
		$result = $db->sql_query($this->getFilesSQL($where,$limit));
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
