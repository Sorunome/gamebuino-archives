<?php
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

$user->session_begin();
$auth->acl($user->data);
$user->setup();

$isLoggedIn = !($user->data['user_id'] == ANONYMOUS);
$isAdmin = $isLoggedIn && in_array((int)($user->data['user_type']),$adminTypes);
$username = $user->data['username'];
$userid = (int)($user->data['user_id']);


$versions = array(
	'',
	'<img src="/wiki/gamelist/alpha.png" alt="alpha"> Alpha',
	'<img src="/wiki/gamelist/beta.png" alt="beta"> Beta',
	'<img src="/wiki/gamelist/release.png" alt="release"> Finished'
);
$versionsDropdown = array(
	'--none--',
	'Alpha',
	'Beta',
	'Finished'
);
$complexities = array(
	'',
	'<img src="/wiki/gamelist/basic.png" alt="basic"> Basic code complexity',
	'<img src="/wiki/gamelist/intermediate.png" alt="intermediate"> Intermediate code complexity',
	'<img src="/wiki/gamelist/advanced.png" alt="advanced"> Advanced code complexity'
);
$complexitiesDropdown = array(
	'--none--',
	'Basic',
	'Intermediate',
	'Advanced'
);

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
class Template {
	private $_templateDir = 'templates/';
	private $_file = '';
	private $_children = array();
	public $properties = array();
	public function __construct($file){
		$this->_file = $file;
	}
	private function setDefault($a){
		foreach($a as $k => $v){
			if(!isset($this->properties[$k])){
				$this->properties[$k] = $v;
			}
		}
	}
	private function renderChildren($key = '_children'){
		foreach($this->$key as $c){
			if(is_string($c)){
				echo $c;
			}else{
				$c->render();
			}
		}
	}
	public function render(){
		if(file_exists($this->_templateDir.$this->_file)){
			include($this->_templateDir.$this->_file);
		}else{
			throw new Exception("Couldn't find template file {$this->_templateDir}{$this->_file} !");
		}
	}
	public function addChildren($c,$key = '_children'){
		if(is_array($c)){
			$this->$key = array_merge($this->$key,$c);
		}else{
			$this->$key[] = $c;
		}
	}
	public function addChild($c,$key = '_children'){
		$this->addChildren($c,$key);
	}
	public function loadJSON($j){
		foreach($j as $k => $v){
			$this->properties[$k] = $v;
		}
	}
	public function __set($k,$v){
		$this->properties[$k] = $v;
	}
	public function __get($k){
		return $this->properties[$k];
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
		// t1.`filename`,t1,`category`,t1.`forum_url`,t1.`repo_url`,t1.`version`,t1.`complexity`,UNIX_TIMESTAMP(t1.`ts_updated`) AS `ts_updated`,UNIX_TIMESTAMP(t1.`ts_added`) AS `ts_added`,t1.`hits`
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
	public function html_short(){
		if(!$this->exists()){
			return '';
		}
		$class = '';
		if(isset($this->images[0]) && $this->images[0] != ''){
			$image = 'uploads/screenshots/'.$this->images[0];
		}else{
			$image = '1x1.png';
			$class = 'noimage';
		}
		return '
			<div class="filecont">
				<div class="name">'.$this->name.'</div>
				<div class="author"><a href="?author='.$this->authorId.'">'.$this->author.'</a></div>
				<a href="?file='.$this->id.'">
					<div class="popup">
						<div class="description">'.cutAtChar($this->description).'</div>
						<div class="downloads">'.$this->downloads.'</div>
						<div class="rating">+'.$this->upvotes.'/-'.$this->downvotes.'</div>
					</div>
					<img src="'.$image.'" alt="'.$this->name.'" class="'.$class.'">
				</a>
				<input class="fileDlCheckbox" type="checkbox" data-id="'.$this->id.'">
			</div>
		';
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
	public function html(){
		global $userid,$isAdmin,$user,$versions,$complexities;
		if(!$this->exists()){
			return '';
		}
		if(!$this->extra){
			$this->goextra();
		}
		
		$html = '';
		if($userid == $this->authorId || $isAdmin){
			$html = '<a href="?edit='.$this->id.'" id="editfile">Edit</a>';
		}
		$cats = getCategoryList();
		$html .= '<table id="fileDescription" cellspacing="0" cellpadding="0">
			<tr><th colspan="2">'.$this->name.' ( <a href="?dl='.$this->id.'" download>Download</a> )</th></tr>
			<tr><td>Author</td><td><a href="?author='.$this->authorId.'">'.$this->author.'</a></td></tr>
			<tr><td>Hits</td><td>'.$this->hits.'</td></tr>
			<tr><td>Downloads</td><td>'.$this->downloads.'</td></tr>
			<tr><td>Rating</td><td>+'.$this->upvotes.'/-'.$this->downvotes.'&nbsp;&nbsp;&nbsp;'.
			($isLoggedIn?
				'<a href="?rate='.$this->id.'&dir=1">+</a> <a href="?rate='.$this->id.'&dir=-1">-</a>'
			:
				'<a href="/forum/ucp.php?mode=login">Login</a> to rate!'
			)
			.'</td></tr>
			<tr><td>Added</td><td>'.date($user->data['user_dateformat'],$this->ts_added).'</td></tr>
			<tr><td>Last&nbsp;Updated</td><td>'.date($user->data['user_dateformat'],$this->ts_updated).'</td></tr>
			<tr><td>Description</td><td>'.str_replace("\n",'<br>',$this->description).'</td></tr>
			'.($this->version > 0?'<tr><td>Version</td><td>'.$versions[$this->version].'</td></tr>':'').'
			'.($this->complexity > 0?'<tr><td>Complexity</td><td>'.$complexities[$this->complexity].'</td></tr>':'').'
			'.
			($this->forum_url!=''?
				'<tr><td>Forum-Topic</td><td><a href="'.$this->forum_url.'" target="_blank">'.$this->forum_url.'</a></td></tr>'
			:'').
			($this->repo_url!=''?
				'<tr><td>Code-Repository</td><td><a href="'.$this->repo_url.'" target="_blank">'.$this->repo_url.'</a></td></tr>'
			:'').'<tr><td>'.(FOLDERS?'Categories':'Tags').'</td><td>';
		foreach($this->categories as $c){
			$html .= '<a href="'.(FOLDERS?'?cat='.$c:'.?tags=['.$c.']').'">'.$cats[$c].'</a> ';
		}
		$html .= '
			</td></tr>
			</table><br>
			
				<h2>SCREENSHOTS</h2>';
				
		
		foreach($this->images as $i){
			if($i != ''){
				$html .= '<img src="uploads/screenshots/'.$i.'" alt="'.$this->name.'" class="fileDescImage">';
			}
		}
		return $html;
	}
}
class Files{
	private function getFilesSQL($where = '',$limit = false){
		$s = "SELECT ".FILE_SELECT;
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
		if(!FOLDERS && isset($_GET['tags']) && preg_match("/^(\[\d+\])+$/",$cats = $_GET['tags'])){
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
	public function get($where,$limit){
		global $db;
		$result = $db->sql_query($this->getFilesSQL('',true));
		$f = array();
		while($gamefile = $db->sql_fetchrow($result)){
			$f[] = new File($gamefile);
		}
		$db->sql_freeresult($result);
		
		return $f;
	}
}
$files = new Files;
