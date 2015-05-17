<?php
define('IN_PHPBB', true);
$phpbb_root_path = '../forum/';
$adminTypes = array(3);
$phpEx = substr(strrchr(__FILE__, '.'),1);
include($phpbb_root_path.'common.'.$phpEx);
$request->enable_super_globals();

$user->session_begin();
$auth->acl($user->data);
$user->setup();

$isLoggedIn = !($user->data['user_id'] == ANONYMOUS);
$isAdmin = $isLoggedIn && in_array((int)($user->data['user_type']),$adminTypes);
$username = $user->data['username'];
$userid = (int)($user->data['user_id']);
$versions = array(
	'<img src="http://gamebuino.com/wiki/gamelist/alpha.png" alt="alpha"> Alpha',
	'<img src="http://gamebuino.com/wiki/gamelist/beta.png" alt="beta"> Beta',
	'<img src="http://gamebuino.com/wiki/gamelist/release.png" alt="release"> Finished'
);
$versionsDropdown = array(
	'Alpha',
	'Beta',
	'Finished'
);
$complexities = array(
	'<img src="http://gamebuino.com/wiki/gamelist/basic.png" alt="basic"> Basic code complexity',
	'<img src="http://gamebuino.com/wiki/gamelist/intermediate.png" alt="intermediate"> Intermediate code complexity',
	'<img src="http://gamebuino.com/wiki/gamelist/advanced.png" alt="advanced"> Advanced code complexity'
);
$complexitiesDropdown = array(
	'Basic',
	'Intermediate',
	'Advanced'
);

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
$userIdList = array();
function getUserById($id){
	global $userIdList,$db;
	if($id == (int)$id){
		$id = (int)$id;
		if(isset($userIdList[$id])){
			return $userIdList[$id];
		}else{
			$result = $db->sql_query(query_escape("SELECT `username` FROM ".USERS_TABLE." WHERE `user_id`=%d",$id));
			if($u = $db->sql_fetchrow($result)){
				$db->sql_freeresult($result);
				$userIdList[$id] = $u['username'];
				return $u['username'];
			}else{
				$db->sql_freeresult($result);
				return 'Anonymous';
			}
		}
	}
	return 'Anonymous';
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
			$cats[(int)$cat['id']] = $pre.'> '.$cat['name'];
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
class Page {
	private function getHeader($title){
		global $isLoggedIn,$username,$isAdmin;
		ob_start();
		include('../navbar/navbar.html');
		$globalnav = ob_get_clean();
		return '<!DOCTYPE html>
			<html>
			<head>
				<title>Gamebuino Archive - '.$title.'</title>
				<meta http-equiv="content-type" content="text/html; charset=UTF-8">
				<link rel="stylesheet" type="text/css" href="style.css">
				<meta http-equiv="content-language" content="en-gb">
				<link rel="shortcut icon" href="/favicon.ico">
			</head>
			<body>'.$globalnav.'
			<h1><img src="http://gamebuino.com/navbar/gamebuino_logo_160.png" alt="gamebuino"> Games</h1><br>
			<ul id="headerNav" class="centercont">
				<li><a href=".">Recent files</a></li>
				<li><a href="?cat=1">Browse files</a></li>'.
			($isLoggedIn?
				'<li><a href="/forum/ucp.php?mode=logout">Logout [ '.$username.' ]</a></li>
				<li><a href="?newfile">Upload file</a></li>'.
				($isAdmin?
					'<li>Admin</li>'
				:'')
			:
				'<li><a href="/forum/ucp.php?mode=register">Register</a></li>
				<li><a href="/forum/ucp.php?mode=login">Login</a></li>'
			)
			.'</ul>
			<article>';
	}
	private function getFooter(){
		return '</article>
			<footer>Archives software &copy;<a href="http://www.sorunome.de" target="_blank">Sorunome</a><br>Gamebuino &copy;Rodot</footer>
			</body>
			</html>';
	}
	public function getPage($title,$html){
		echo $this->getHeader($title).$html.$this->getFooter();
	}
}
$page = new Page();

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
							if(file_exists($oldName)){
								unlink($oldName);
							}
							return true;
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


function validateUpload(){
	global $db;
	$complexity = request_var('complexity',0);
	$version = request_var('version',0);
	$cid = request_var('category',1);
	if(request_var('name','') != '' && $complexity >= 0 && $complexity <= 2 && $version >= 0 && $version <= 2 && $cid != 1 && (int)$cid == $cid){
		$result = $db->sql_query(query_escape("SELECT `id` FROM `archive_categories` WHERE `id`=%d",$cid));
		if($db->sql_fetchrow($result)){
			$db->sql_freeresult($result);
			return true;
		}
		$db->sql_freeresult($result);
	}
	return false;
}
function getImagesArrayFromUpload(){
	return array(
		getUrl_safe(request_var('image0','')),
		getUrl_safe(request_var('image1','')),
		getUrl_safe(request_var('image2','')),
		getUrl_safe(request_var('image3',''))
	);
}
function getFileHTML($gamefile){
	$image = json_decode($gamefile['images'],true);
	if(isset($image[0]) && $image[0] != ''){
		$image = $image[0];
	}else{
		$image = 'http://gamebuino.com/forum/styles/metrolike/imageset/forum_read.gif';
	}
	return '
		<a class="filecont" href="?file='.$gamefile['id'].'">
			<div class="name">'.htmlentities($gamefile['name']).'</div>
			<div class="author">'.htmlentities(getUserById($gamefile['author'])).'</div>
			<div class="popup">
				<div class="description">'.htmlentities(cutAtChar($gamefile['description'])).'</div>
				<div class="downloads">'.$gamefile['downloads'].'</div>
				<div class="rating">'.$gamefile['rating'].'</div>
			</div>
			<img src="'.$image.'" alt="'.htmlentities($gamefile['name']).'">
		</a>
	';
}
if(request_var('file',false)){
	$fid = request_var('file','invalid');
	$html = '<b>Error: file not found</b>';
	$title = 'File not found';
	if((int)$fid == $fid){
		$result = $db->sql_query(query_escape("SELECT `filename`,`category`,`forum_url`,`repo_url`,`version`,`complexity`,`id`,`author`,`description`,UNIX_TIMESTAMP(`ts_updated`) AS `ts_updated`,UNIX_TIMESTAMP(`ts_added`) AS `ts_added`,`images`,`name`,`downloads`,`rating` FROM `archive_files` WHERE `id`=%d",$fid));
		if($gamefile = $db->sql_fetchrow($result)){
			$title = htmlentities($gamefile['name']);
			$html = '';
			if($userid == $gamefile['author'] || $isAdmin){
				$html = '<a href="?edit='.$fid.'" id="editfile">Edit</a>';
			}
			$cats = getCategoryList();
			$html .= '<table id="fileDescription" cellspacing="0" cellpadding="0">
				<tr><th colspan="2">'.htmlentities($gamefile['name']).' ( <a href="?dl='.$fid.'">Download</a> )</th></tr>
				<tr><td>Author</td><td><a href="/forum/memberlist.php?mode=viewprofile&u='.$gamefile['author'].'">'.htmlentities(getUserById($gamefile['author'])).'</a></td></tr>
				<tr><td>Downloads</td><td>'.$gamefile['downloads'].'</td></tr>
				<tr><td>Rating</td><td>'.$gamefile['rating'].' '.
				($isLoggedIn?
					'<a href="?rate='.$gamefile['id'].'&dir=1">+</a> <a href="?rate='.$gamefile['id'].'&dir=-1">-</a>'
				:
					'<a href="/forum/ucp.php?mode=login">Login</a> to rate!'
				)
				.'</td></tr>
				<tr><td>Added</td><td>'.date($user->data['user_dateformat'],$gamefile['ts_added']).'</td></tr>
				<tr><td>Last&nbsp;Updated</td><td>'.date($user->data['user_dateformat'],$gamefile['ts_updated']).'</td></tr>
				<tr><td>Description</td><td>'.htmlentities($gamefile['description']).'</td></tr>
				<tr><td>Version</td><td>'.$versions[(int)$gamefile['version']].'</td></tr>
				<tr><td>Complexity</td><td>'.$complexities[(int)$gamefile['complexity']].'</td></tr>
				'.
				($gamefile['forum_url']!=''?
					'<tr><td>Forum-Topic</td><td><a href="'.htmlentities($gamefile['forum_url']).'">'.htmlentities($gamefile['forum_url']).'</a></td></tr>'
				:'').
				($gamefile['repo_url']!=''?
					'<tr><td>Code-Repository</td><td><a href="'.htmlentities($gamefile['repo_url']).'">'.htmlentities($gamefile['repo_url']).'</a></td></tr>'
				:'')
				.'
				<tr><td>Category</td><td><a href="?cat='.$gamefile['category'].'">'.$cats[(int)$gamefile['category']].'</a></td></tr>
				</table><br>
				
					<h2>SCREENSHOTS</h2>';
					
			$images = json_decode($gamefile['images'],true);
			foreach($images as $i){
				if($i != ''){
					$html .= '<img src="'.$i.'" alt="'.htmlentities($gamefile['name']).'" class="fileDescImage">';
				}
			}
			if(class_exists('ZipArchive')){
				$html .= '<br>';
				$zip = new ZipArchive();
				if($zip->open($upload->getZipName($fid))){
					$html .= '<div id="zipcontentswrap"><div id="zipcontents">
						<div id="zipcontentsheader">Archive contents</div>';
					for($i = 0;$i < $zip->numFiles;$i++){
						$html .= '<div class="zipcontentsitem">'.htmlentities($zip->getNameIndex($i)).'</div>';
					}
					$html .= '</div></div>';
				}else{
					$html .= '<b>Couldn\'t open zip archive!</b>';
				}
				$zip->close();
			}
		}
		$db->sql_freeresult($result);
	}
	$page->getPage($title,$html);
}elseif(request_var('dl',false)){
	$fid = request_var('dl','invalid');
	if((int)$fid == $fid){
		$result = $db->sql_query(query_escape("SELECT `filename` FROM `archive_files` WHERE `id`=%d",$fid));
		if($gamefile = $db->sql_fetchrow($result)){
			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename='.$gamefile['filename']);
			$realzip = $upload->getZipName($fid);
			header('Content-length: '.filesize($realzip));
			header('Proagma: no-cache');
			header('Expires: 0');
			readfile($realzip);
			$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `downloads`=`downloads`+1 WHERE `id`=%d",$fid)));
		}
		$db->sql_freeresult($result);
	}
}elseif(request_var('cat',false)){
	$cid = request_var('cat','invalid');
	$html = '<b>Error: cateogry not found</b>';
	$title = 'Category not found';
	if((int)$cid == $cid){
		$result = $db->sql_query(query_escape("SELECT `id`,`category`,`name` FROM `archive_categories` WHERE `id`=%d",$cid));
		if($cat = $db->sql_fetchrow($result)){
			$title = $cat['name'];
			$html = '
				<h1>'.$cat['name'].'</h1>';
			if($cat['category'] != $cid){
				$html .= '<a class="subcatlink" href="?cat='.$cat['category'].'">Parent Category</a>';
			}
			$result2 = $db->sql_query(query_escape("SELECT `id`,`name` FROM `archive_categories` WHERE `category`=%d",$cid));
			while($incat = $db->sql_fetchrow($result2)){
				if($incat['id'] != $cid){
					$html .= '<a class="subcatlink" href="?cat='.$incat['id'].'">'.$incat['name'].'</a>';
				}
			}
			$db->sql_freeresult($result2);
			
			$result2 = $db->sql_query(query_escape("SELECT `id`,`author`,`description`,`images`,`name`,`downloads`,`rating` FROM `archive_files` WHERE `category`=%d ORDER BY `ts_updated` DESC",$cid));
			while($gamefile = $db->sql_fetchrow($result2)){
				$html .= getFileHTML($gamefile);
			}
			$db->sql_freeresult($result2);
			
		}
		$db->sql_freeresult($result);
	}
	$page->getPage($title,$html);
}elseif(request_var('rate',false)){
	$fid = request_var('rate','invalid');
	if($isLoggedIn && (int)$fid == $fid){
		$result = $db->sql_query(query_escape("SELECT `rating`,`votes` FROM `archive_files` WHERE `id`=%d",$fid));
		if($gamefile = $db->sql_fetchrow($result)){
			$dir = (int)request_var('dir',0);
			if($dir == 1 || $dir == -1){
				$rating = (int)$gamefile['rating'];
				$votes = json_decode($gamefile['votes'],true);
				if($votes == NULL){
					$votes = array();
				}
				if(isset($votes[$userid])){
					$rating -= $votes[$userid]; // revert the old vote
				}
				$votes[$userid] = $dir;
				$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `rating`=%d,`votes`='%s' WHERE `id`=%d",$rating + $dir,json_encode($votes),$fid)));
			}
		}
		$db->sql_freeresult($result);
	}
	
	header('Location: ?file='.$fid);
}elseif(request_var('edit',false)){
	$fid = request_var('edit','invalid');
	$title = 'Error';
	$html = '<b>Error: Permission Denied</b>';
	if($isLoggedIn && (int)$fid == $fid){
		$result = $db->sql_query(query_escape("SELECT `author`,`name`,`description`,`version`,`complexity`,`forum_url`,`repo_url`,`category`,`images` FROM `archive_files` WHERE `id`=%d",$fid));
		if($gamefile = $db->sql_fetchrow($result)){
			if($userid == $gamefile['author'] || $isAdmin){ // we may edit the file
				$images = json_decode($gamefile['images'],true);
				for($i = 0;$i < 4;$i++){
					$images[$i] = $images[$i]?$images[$i]:'';
				}
				$html = '
					<a href="?file='.$fid.'">Back</a><br><br>
					<form action="?save='.$fid.'" method="post" enctype="multipart/form-data">
						Name:<input type="text" name="name" value="'.htmlentities($gamefile['name']).'"><br>
						New zip-file (leave blank if it didn\'t change):<input type="file" name="zip"><br>
						Forum-Topic (optional):<input type="url" name="forum_url" value="'.htmlentities($gamefile['forum_url']).'"><br>
						Code-Repository (optional):<input type="url" name="repo_url" value="'.htmlentities($gamefile['repo_url']).'"><br>
						Version:<select name="version" size="1">';
				
				foreach($versionsDropdown as $i => $v){
					$html .= '<option value="'.$i.'" '.($gamefile['version']==$i?'selected':'').'>'.$v.'</option>';
				}
				$html .= '</select><br>
						Complexity:<select name="complexity" size="1">';
				
				foreach($complexitiesDropdown as $i => $c){
					$html .= '<option value="'.$i.'" '.($gamefile['complexity']==$i?'selected':'').'>'.$c.'</option>';
				}
				$html .= '</select><br>
						Category:<select name="category" size="1">';
				$cats = getCategoryListDropdown();
				foreach($cats as $i => $c){
					if($i != 1){
						$html .= '<option value="'.$i.'" '.($gamefile['category']==$i?'selected':'').'>'.$c.'</option>';
					}
				}
				$html .= '</select><br>
						Description:<br>
						<textarea name="description">'.htmlentities($gamefile['description']).'</textarea>
						<br>
						Screenshots (all optional):<br>
						Image 1 (main image):<input type="url" name="image0" value="'.htmlentities($images[0]).'"><br>
						Image 2:<input type="url" name="image1" value="'.htmlentities($images[1]).'"><br>
						Image 3:<input type="url" name="image2" value="'.htmlentities($images[2]).'"><br>
						Image 4:<input type="url" name="image3" value="'.htmlentities($images[3]).'"><br>
						<input type="submit" value="Save Edit">
					</form>
				';
			}
		}
		$db->sql_freeresult($result);
	}
	$page->getPage($title,$html);
}elseif(request_var('save',false)){
	$fid = request_var('save','invalid');
	$title = 'Error';
	$html = '<b>Error: Permission Denied</b>';
	if($isLoggedIn && (int)$fid == $fid){
		$result = $db->sql_query(query_escape("SELECT `author` FROM `archive_files` WHERE `id`=%d",$fid));
		if($gamefile = $db->sql_fetchrow($result)){
			if($userid == $gamefile['author'] || $isAdmin){ // we may edit the file
				if(validateUpload()){
					$db->sql_freeresult($db->sql_query(query_escape(
						"UPDATE `archive_files` SET `name`='%s',`description`='%s',`forum_url`='%s',`repo_url`='%s',`version`=%d,`complexity`=%d,`category`=%d,`images`='%s' WHERE `id`=%d",
							request_var('name','invalid'),request_var('description',''),getUrl_safe(request_var('forum_url','')),getUrl_safe(request_var('repo_url',''))
							,request_var('version',0),request_var('complexity',0),request_var('category','invalid'),json_encode(getImagesArrayFromUpload())
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
}elseif(isset($_GET['newfile'])){
	$title = 'Upload file';
	$html = 'You need to <a href="/forum/ucp.php?mode=register">Register</a> or <a href="/forum/ucp.php?mode=login">Login</a> to be able to upload a file!';
	if($isLoggedIn){
		$html = '<h1>Upload new file</h1>
			<form action="?upload" method="post" enctype="multipart/form-data">
					Name:<input type="text" name="name"><br>
					Zip-file:<input type="file" name="zip"><br>
					Forum-Topic (optional):<input type="url" name="forum_url"><br>
					Code-Repository (optional):<input type="url" name="repo_url"><br>
					Version:<select name="version" size="1">';
				
				foreach($versionsDropdown as $i => $v){
					$html .= '<option value="'.$i.'">'.$v.'</option>';
				}
				$html .= '</select><br>
						Complexity:<select name="complexity" size="1">';
				
				foreach($complexitiesDropdown as $i => $c){
					$html .= '<option value="'.$i.'">'.$c.'</option>';
				}
				$html .= '</select><br>
						Category:<select name="category" size="1">';
				$cats = getCategoryListDropdown();
				foreach($cats as $i => $c){
					if($i != 1){
						$html .= '<option value="'.$i.'">'.$c.'</option>';
					}
				}
				$html .= '</select><br>
					Description:<br>
					<textarea name="description"></textarea>
					<br>
					Screenshots (all optional):<br>
					Image 1 (main image):<input type="url" name="image0"><br>
					Image 2:<input type="url" name="image1"><br>
					Image 3:<input type="url" name="image2"><br>
					Image 4:<input type="url" name="image3"><br>
					<input type="submit" value="Upload">
				</form>';
	}
	$page->getPage($title,$html);
}elseif(isset($_GET['upload'])){
	$title = 'Upload file';
	$html = 'You need to <a href="/forum/ucp.php?mode=register">Register</a> or <a href="/forum/ucp.php?mode=login">Login</a> to be able to upload a file!';
	if($isLoggedIn){
		if(validateUpload() && sizeof($_FILES)>0 && isset($_FILES['zip']) && !is_array($_FILES['zip']['name']) && $_FILES['zip']['name'] !== ''){
			$db->sql_query(query_escape(
						"INSERT INTO `archive_files` (`name`,`description`,`forum_url`,`repo_url`,`version`,`complexity`,`category`,`author`,`images`,`votes`,`filename`) VALUES ('%s','%s','%s','%s',%d,%d,%d,%d,'%s','{}','')",
							request_var('name','invalid'),request_var('description',''),getUrl_safe(request_var('forum_url','')),getUrl_safe(request_var('repo_url',''))
							,request_var('version',0),request_var('complexity',0),request_var('category','invalid'),$userid,json_encode(getImagesArrayFromUpload())
							));
			
			$fid = $db->sql_nextid();
			if($upload->uploadZipFile($fid)){
				$html = 'Uploaded new file <i>'.htmlentities(request_var('name','invalid')).'</i>!<br><a href="?file='.$fid.'">View file</a>';
			}else{
				$db->sql_freeresult($db->sql_query(query_escape("DELETE FROM `archive_files` WHERE `id`=%d",$fid)));
				$html = 'Error uploading zip, maybe file isn\'t a zip? Maybe it is too large?<br><a href="?newfile">Back</a>';
			}
		}else{
			$html = 'Error: Missing required field<br><a href="?newfile">Back</a>';
		}
	}
	$page->getPage($title,$html);
}else{
	$html = '<h2>Recent Files</h2><div id="files">';
	$result = $db->sql_query("SELECT `id`,`author`,`description`,`images`,`name`,`downloads`,`rating` FROM `archive_files` ORDER BY `ts_updated` DESC LIMIT 10");
	while($gamefile = $db->sql_fetchrow($result)){
		$html .= getFileHTML($gamefile);
	}
	$db->sql_freeresult($result);
	$html .= '</div>';
	$page->getPage('Recent Files',$html);
}
?>