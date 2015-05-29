<?php
define('IN_PHPBB', true);
$phpbb_root_path = '../forum/';
$adminTypes = array(3);
$phpEx = substr(strrchr(__FILE__, '.'),1);
include($phpbb_root_path.'common.'.$phpEx);
if(isset($request)){
	$request->enable_super_globals();
}

$user->session_begin();
$auth->acl($user->data);
$user->setup();

$isLoggedIn = !($user->data['user_id'] == ANONYMOUS);
$isAdmin = $isLoggedIn && in_array((int)($user->data['user_type']),$adminTypes);
$username = $user->data['username'];
$userid = (int)($user->data['user_id']);
$versions = array(
	'<img src="/wiki/gamelist/alpha.png" alt="alpha"> Alpha',
	'<img src="/wiki/gamelist/beta.png" alt="beta"> Beta',
	'<img src="/wiki/gamelist/release.png" alt="release"> Finished'
);
$versionsDropdown = array(
	'Alpha',
	'Beta',
	'Finished'
);
$complexities = array(
	'<img src="/wiki/gamelist/basic.png" alt="basic"> Basic code complexity',
	'<img src="/wiki/gamelist/intermediate.png" alt="intermediate"> Intermediate code complexity',
	'<img src="/wiki/gamelist/advanced.png" alt="advanced"> Advanced code complexity'
);
$complexitiesDropdown = array(
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
function panic(){
	header('Location:?error');
	die();
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
function getEditForm($gamefile = false){
	global $versionsDropdown,$complexitiesDropdown;
	$edit = $gamefile!==false;
	if(!$edit){
		$gamefile = array(
			'name' => '',
			'description' => '',
			'version' => 0,
			'complexity' => 0,
			'forum_url' => '',
			'repo_url' => '',
			'category' => '[0]',
			'images' => '[]'
		);
	}
	$images = json_decode($gamefile['images'],true);
	for($i = 0;$i < 4;$i++){
		$images[$i] = $images[$i]?$images[$i]:'';
	}
	$html = '<form id="fileeditform" action="?'.($edit?'save='.$gamefile['id']:'upload').'" method="post" enctype="multipart/form-data">
			Name:<input type="text" name="name" value="'.htmlentities($gamefile['name']).'">'.
					getHelpHTML('The name of the file as it will be displayed').'<br>
			'.($edit?'New zip-file (leave blank if it didn\'t change):':'Zip-file:').'<input type="file" name="zip">'.
					getHelpHTML('The Zip-file containing the actual game, HEX (and INF) files').'<br>
			Forum-Topic (optional):<input type="url" name="forum_url" value="'.htmlentities($gamefile['forum_url']).'">'.
					getHelpHTML('A link to the forum topic where discussion about this program/game occured').'<br>
			Code-Repository (optional):<input type="url" name="repo_url" value="'.htmlentities($gamefile['repo_url']).'">'.
					getHelpHTML('A link to a repository (for example <a href="https://github.com" target="_blank">Github</a>) where the code is hosted on').'<br>
			Version:<select name="version" size="1">';
	
	foreach($versionsDropdown as $i => $v){
		$html .= '<option value="'.$i.'" '.($gamefile['version']==$i?'selected':'').'>'.$v.'</option>';
	}
	$html .= '</select>'.
					getHelpHTML('The version of the game, defined as the following:<br><table>
							<tr>
								<td>alpha</td><td>game under development with the basic gameplay implemented</td>
							</tr>
							<tr>
								<td>beta</td><td>a real game you can enjoy, but it still lacks a few features and a final polish</td>
							</tr>
							<tr>
								<td>release</td><td>the game is finished, working and will no longer evolve</td>
							</tr>
						</table>').'<br>
			Complexity:<select name="complexity" size="1">';
	
	foreach($complexitiesDropdown as $i => $c){
		$html .= '<option value="'.$i.'" '.($gamefile['complexity']==$i?'selected':'').'>'.$c.'</option>';
	}
	$html .= '</select>'.
					getHelpHTML('How complex the code is, defined as the following:<br><table>
						<tr>
							<td>basic</td><td>the code fits in one file <&nbsp;1500 lines and is easy to understand for a beginner</td>
						</tr>
						<tr>
							<td>intermediate</td><td>program across several files, object oriented, PROGMEM, tile maps, etc.</td>
						</tr>
						<tr>
							<td>advanced</td><td>involves assembly, pointers, 3D, streaming from the SD card, multi-player, etc.</td>
						</tr>
					</table>').'<br><input type="hidden" name="category" value="'.htmlentities($gamefile['category']).'">
			Categories:'.
					getHelpHTML('The categories your game should be in, you can add multiple').'<span id="categoriesContent">Please enable Javascript!</span>';
	$catlist = getCategoryListDropdown();
	$cats = explode('][',substr($gamefile['category'],1,strlen($gamefile['category'])-2));
	$html .= '<br>
			Description:'.
					getHelpHTML('A long description of your game').'<br>
			<textarea name="description">'.htmlentities($gamefile['description']).'</textarea>
			<br><br>
			Screenshots (all optional'.($edit?', only saved if changed':'').'):'.
					getHelpHTML('Nothing describes a game better than a screenshot! You can upload up to four, the first one will be the main screenshot.
								Animated GIFs are allowed.').'<br>';
	for($i = 0;$i < 4;$i++){
		$html .= 'Image '.($i+1).($i == 0?' (main image)':'').':<input type="file" name="image'.$i.'">'.($edit?' | Delete old: <input type="checkbox" name="delimage'.$i.'" value="true">':'').'<br>';
	}
	$html .= '<br>
			<input type="submit" value="'.($edit?'Save Edit':'Upload File').'">
		</form>
		<script type="text/javascript">
			(function(){
				var catlist = '.json_encode($catlist).',
					cats = '.json_encode($cats).',
					makeCatList = function(v){
						return $("<div>").addClass("categoryDropdown").append(
							$("<select>").attr("size","1").append(
								$.map(catlist,function(c,i){
									i = i.substr(1);
									return $("<option>").text(c).attr((i==v?"selected":"false"),"selected").val(i);
								})
							),"&nbsp;",
							$("<a>").text("x").attr("href","http://remove").click(function(e){
								e.preventDefault();
								$(this).parent().remove();
							})
						);
					};
				$("#categoriesContent").empty().append(
					$.map(cats,function(v){
						return makeCatList(v);
					})
				).after($("<a>").text("+ add Category").attr("href","http://add").click(function(e){
					e.preventDefault();
					$("#categoriesContent").append(makeCatList());
				}));
				$("#fileeditform").submit(function(e){
					var catIdsMix = $(".categoryDropdown select").map(function(){return this.value;}),
						catIds = [];
					$.each(catIdsMix,function(i,el){
						if($.inArray("["+el+"]",catIds) === -1){
							catIds.push("["+el+"]");
						}
					});
					this.category.value = catIds.join("");
					
					// no e.preventDefault() as we still want to send it
				});
			})();
		</script>';
	return $html;
}
function getFileSorter($url = '?',$limit = false){
	$cursort = (int)request_var('sort',0);
	$curdir = (int)request_var('direction',0);
	$curlimit = (int)request_var('limit',10);
	$sorts = array(
		'Date updated',
		'Date added',
		'Name',
		'Author',
		'Rating',
		'Downloads'
	);
	$html = '<div id="fileSorter">
		<div class="buttongroup">';
	foreach($sorts as $i => $s){
		if($i == $cursort){
			$html .= '<div class="button is-checked">'.$s.'</div>';
		}else{
			$html .= '<a class="button" href="'.$url.'&sort='.$i.'&direction='.$curdir.($limit?'&limit='.$curlimit:'').'">'.$s.'</a>';
		}
	}
	$html .= '	</div>
		<div class="buttongroup">';
	if($curdir == 0){
		$html .= '<div class="button is-checked">▼</div>
		<a class="button" href="'.$url.'&sort='.$cursort.'&direction=1'.($limit?'&limit='.$curlimit:'').'">▲</a>';
	}else{
		$html .= '<a class="button" href="'.$url.'&sort='.$cursort.'&direction=0'.($limit?'&limit='.$curlimit:'').'">▼</a>
		<div class="button is-checked">▲</div>';
	}
	if($limit){
		$html .= '</div><div class="buttongroup"><div class="button is-checked" style="cursor:default;">Limit:</div>';
		$limits = array(10,20,50,100,200);
		foreach($limits as $l){
			if($l == $curlimit){
				$html .= '<div class="button is-checked">'.$l.'</div>';
			}else{
				$html .= '<a class="button" href="'.$url.'&sort='.$cursort.'&direction='.$curdir.'&limit='.$l.'">'.$l.'</a>';
			}
		}
	}
	$html .= '</div>
		<div class="buttongroup" id="selectedfilesgroup">
			<div class="button" id="selectallfiles">Select all</div>
			<div class="button" id="deselectallfiles">Deselect all</div>
			<a class="button" id="downloadselectedfiles" download>Download selected</a>
		</div>
	</div>
	<script type="text/javascript">
		$(document).ready(function(){
			var DLIds = [],
				updateDlUrl = function(){
					$("#downloadselectedfiles").attr("href","?dlmult="+DLIds.join(","));
				};
			$(".fileDlCheckbox").prop("checked",false).change(function(){
				var fid = this.dataset.id;
				if(this.checked){
					DLIds.push(fid);
				}else{
					DLIds = $.grep(DLIds,function(v){
						return v != fid;
					});
				}
				updateDlUrl();
			});
			$("#selectallfiles").click(function(){
				DLIds = $(".fileDlCheckbox").prop("checked",true).map(function(){
					return this.dataset.id;
				}).get();
				updateDlUrl();
			});
			$("#deselectallfiles").click(function(){
				$(".fileDlCheckbox").prop("checked",false);
				DLIds = [];
				updateDlUrl();
			});
			$("#downloadselectedfiles").click(function(e){
				if(DLIds.length < 1){
					e.preventDefault();
					alert("No files selected!");
					return;
				}
				$(".stabilitywarning").remove();
				$.getJSON(this.href+"&info",function(data){
					if(data.stability > 0){
						var $elem = $("<div>").addClass("button stabilitywarning").text("Stability errors");
						switch(data.stability){
							case 1:
								$elem.text("Stability errors may occur due to file renaming").addClass("warning");
								break;
							case 2:
								$elem.text("Stability errors will occur as not all files could be re-named").addClass("error");
								break;
						}
						$("#selectedfilesgroup").append($elem);
					}
				})
			})
		});
	</script>';
	return $html;
}
function getFilesSQL($where = '',$limit = false){
	$s = "SELECT t1.`id`,t1.`author`,t1.`description`,t1.`images`,t1.`name`,t1.`downloads`,t1.`upvotes`,t1.`downvotes`,t2.`username` FROM `archive_files` AS t1 INNER JOIN ".USERS_TABLE." AS t2 ON t1.`author` = t2.`user_id`";
	$cursort = (int)request_var('sort',3);
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
	$s .= $where.' '.$sortcolumns[$cursort].' '.$dirs[$curdir];
	if($limit){
		$curlimit = (int)request_var('limit',10);
		if($curlimit < 1 || $curlimit > 200){
			$curlimit = 1;
		}
		$s .= ' LIMIT '.$curlimit;
	}
	return $s;
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
				<script type="text/javascript" src="jquery-2.0.3.min.js"></script>
			</head>
			<body>'.$globalnav.'
			<h1><img src="/navbar/gamebuino_logo_160.png" alt="gamebuino"> Games</h1><br>
			<div class="centercont buttongroup">
				<a class="button" href=".">Recent files</a>
				<a class="button" href="?cat=1">Browse files</a>'.
			($isLoggedIn?
				'<a class="button" href="/forum/ucp.php?mode=logout">Logout [ '.$username.' ]</a>
				<a class="button" href="?newfile">Upload file</a>'.
				($isAdmin?
					'<span class="button">Admin</span>'
				:'')
			:
				'<a class="button" href="/forum/ucp.php?mode=register">Register</a>
				<a class="button" href="/forum/ucp.php?mode=login">Login</a>'
			)
			.'</div>
			<article>';
	}
	private function getFooter(){
		return '</article>
			<footer>Archives software &copy;<a href="http://www.sorunome.de" target="_blank">Sorunome</a><br>Gamebuino &copy;Rodot<br>Something isn\'t working? <a href="https://github.com/Sorunome/gamebuino-archives/issues" target="_blank">Report the issue!</a></footer>
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
	global $db;
	$complexity = request_var('complexity',0);
	$version = request_var('version',0);
	$cid = request_var('category','');
	if(request_var('name','') != '' && $complexity >= 0 && $complexity <= 2 && $version >= 0 && $version <= 2 && preg_match("/^(\[\d+\])+$/",$cid)){
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
function getFileHTML($gamefile){
	$image = json_decode($gamefile['images'],true);
	$class = '';
	if(isset($image[0]) && $image[0] != ''){
		$image = 'uploads/screenshots/'.$image[0];
	}else{
		$image = '1x1.png';
		$class = 'noimage';
	}
	return '
		<div class="filecont">
			<div class="name">'.htmlentities($gamefile['name']).'</div>
			<div class="author"><a href="?author='.$gamefile['author'].'">'.htmlentities($gamefile['username']).'</a></div>
			<a href="?file='.$gamefile['id'].'">
				<div class="popup">
					<div class="description">'.htmlentities(cutAtChar($gamefile['description'])).'</div>
					<div class="downloads">'.$gamefile['downloads'].'</div>
					<div class="rating">+'.$gamefile['upvotes'].'/-'.$gamefile['downvotes'].'</div>
				</div>
				<img src="'.$image.'" alt="'.htmlentities($gamefile['name']).'" class="'.$class.'">
			</a>
			<input class="fileDlCheckbox" type="checkbox" data-id="'.$gamefile['id'].'">
		</div>
	';
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
if(request_var('file',false)){
	$fid = request_var('file','invalid');
	$html = '<b>Error: file not found</b>';
	$title = 'File not found';
	if((int)$fid == $fid){
		$result = $db->sql_query(query_escape("SELECT t1.`filename`,t1.`category`,t1.`forum_url`,t1.`repo_url`,t1.`version`,t1.`complexity`,t1.`id`,t1.`author`,
					t1.`description`,UNIX_TIMESTAMP(t1.`ts_updated`) AS `ts_updated`,UNIX_TIMESTAMP(t1.`ts_added`) AS `ts_added`,t1.`images`,t1.`name`,t1.`downloads`,
					t1.`upvotes`,t1.`downvotes`,t2.`username` FROM `archive_files` AS t1 INNER JOIN ".USERS_TABLE." AS t2 ON t1.`author` = t2.`user_id` WHERE t1.`id`=%d",$fid));
		if($gamefile = $db->sql_fetchrow($result)){
			$title = htmlentities($gamefile['name']);
			$html = '';
			if($userid == $gamefile['author'] || $isAdmin){
				$html = '<a href="?edit='.$fid.'" id="editfile">Edit</a>';
			}
			$cats = getCategoryList();
			$html .= '<table id="fileDescription" cellspacing="0" cellpadding="0">
				<tr><th colspan="2">'.htmlentities($gamefile['name']).' ( <a href="?dl='.$fid.'" download>Download</a> )</th></tr>
				<tr><td>Author</td><td><a href="?author='.$gamefile['author'].'">'.htmlentities($gamefile['username']).'</a></td></tr>
				<tr><td>Downloads</td><td>'.$gamefile['downloads'].'</td></tr>
				<tr><td>Rating</td><td>+'.$gamefile['upvotes'].'/-'.$gamefile['downvotes'].'&nbsp;&nbsp;&nbsp;'.
				($isLoggedIn?
					'<a href="?rate='.$gamefile['id'].'&dir=1">+</a> <a href="?rate='.$gamefile['id'].'&dir=-1">-</a>'
				:
					'<a href="/forum/ucp.php?mode=login">Login</a> to rate!'
				)
				.'</td></tr>
				<tr><td>Added</td><td>'.date($user->data['user_dateformat'],$gamefile['ts_added']).'</td></tr>
				<tr><td>Last&nbsp;Updated</td><td>'.date($user->data['user_dateformat'],$gamefile['ts_updated']).'</td></tr>
				<tr><td>Description</td><td>'.str_replace("\n",'<br>',htmlentities($gamefile['description'])).'</td></tr>
				<tr><td>Version</td><td>'.$versions[(int)$gamefile['version']].'</td></tr>
				<tr><td>Complexity</td><td>'.$complexities[(int)$gamefile['complexity']].'</td></tr>
				'.
				($gamefile['forum_url']!=''?
					'<tr><td>Forum-Topic</td><td><a href="'.htmlentities($gamefile['forum_url']).'">'.htmlentities($gamefile['forum_url']).'</a></td></tr>'
				:'').
				($gamefile['repo_url']!=''?
					'<tr><td>Code-Repository</td><td><a href="'.htmlentities($gamefile['repo_url']).'">'.htmlentities($gamefile['repo_url']).'</a></td></tr>'
				:'').'<tr><td>Categories</td><td>';
			foreach(explode('][',substr($gamefile['category'],1,strlen($gamefile['category'])-2)) as $c){
				$html .= '<a href="?cat='.$c.'">'.$cats[$c].'</a> ';
			}
			$html .= '
				</td></tr>
				</table><br>
				
					<h2>SCREENSHOTS</h2>';
					
			$images = json_decode($gamefile['images'],true);
			foreach($images as $i){
				if($i != ''){
					$html .= '<img src="uploads/screenshots/'.$i.'" alt="'.htmlentities($gamefile['name']).'" class="fileDescImage">';
				}
			}
			$html .= '<br>';
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
	if((int)$aid == $aid){
		$result = $db->sql_query(query_escape("SELECT `username` FROM ".USERS_TABLE." WHERE `user_id`=%d",$aid));
		if($author = $db->sql_fetchrow($result)){
			$db->sql_freeresult($result);
			$title = $author['username'];
			$files = 0;
			$result = $db->sql_query(query_escape("SELECT COUNT(`id`) AS `files` FROM `archive_files` WHERE `author`=%d",$aid));
			if($f = $db->sql_fetchrow($result)){
				$files = (int)$f;
			}
			$db->sql_freeresult($result);
			$html = '<table id="authorDescription" cellspacing="0" cellpadding="0">
				<tr><th colspan="2">'.htmlentities($author['username']).'</th></tr>
				<tr><td>Forum&nbsp;Profile</td><td><a href="/forum/memberlist.php?mode=viewprofile&u='.$aid.'">'.htmlentities($author['username']).'</a></td></tr>
				<tr><td>Number&nbsp;of&nbsp;files</td><td>'.$files.'</td></tr>
			</table><br>';
			$first = true;
			$result = $db->sql_query(query_escape(getFilesSQL("WHERE t1.`author`=%d"),$aid));
			while($gamefile = $db->sql_fetchrow($result)){
				if($first){
					$html .= getFileSorter('?author='.$aid,false);
					$first = false;
				}
				$html .= getFileHTML($gamefile);
			}
		}
		$db->sql_freeresult($result);
	}
	$page->getPage($title,$html);
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
			$first = true;
			$result2 = $db->sql_query(query_escape(getFilesSQL("WHERE t1.`category` LIKE '%s'"),'%['.(int)$cid.']%'));
			while($gamefile = $db->sql_fetchrow($result2)){
				if($first){
					$html .= getFileSorter('?cat='.$cid,false);
					$first = false;
				}
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
		$result = $db->sql_query(query_escape("SELECT `upvotes`,`downvotes`,`votes` FROM `archive_files` WHERE `id`=%d",$fid));
		if($gamefile = $db->sql_fetchrow($result)){
			$dir = (int)request_var('dir',0);
			if($dir == 1 || $dir == -1){
				$up = (int)$gamefile['upvotes'];
				$down = (int)$gamefile['downvotes'];
				$votes = json_decode($gamefile['votes'],true);
				if($votes == NULL){
					$votes = array();
				}
				if(isset($votes[$userid])){
					if($votes[$userid] > 0){
						$up--;
					}else{
						$down--;
					}
				}
				$votes[$userid] = $dir;
				if($dir > 0){
					$up++;
				}else{
					$down++;
				}
				$db->sql_freeresult($db->sql_query(query_escape("UPDATE `archive_files` SET `upvotes`=%d,`downvotes`=%d,`votes`='%s' WHERE `id`=%d",$up,$down,json_encode($votes),$fid)));
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
		$result = $db->sql_query(query_escape("SELECT `id`,`author`,`name`,`description`,`version`,`complexity`,`forum_url`,`repo_url`,`category`,`images` FROM `archive_files` WHERE `id`=%d",$fid));
		if($gamefile = $db->sql_fetchrow($result)){
			if($userid == $gamefile['author'] || $isAdmin){ // we may edit the file
				$html = '<h1>Editing file <i>'.htmlspecialchars($gamefile['name']).'</i></h1><a href="?file='.$fid.'">Back</a><br><br>'.getEditForm($gamefile);
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
}elseif(isset($_GET['newfile'])){
	$title = 'Upload file';
	$html = 'You need to <a href="/forum/ucp.php?mode=register">Register</a> or <a href="/forum/ucp.php?mode=login">Login</a> to be able to upload a file!';
	if($isLoggedIn){
		$html = '<h1>Upload new file</h1>'.getEditForm(false);
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
	$page->getPage('Error','Something went wrong! Be sure to <a href="https://github.com/Sorunome/gamebuino-archives/issues" target="_blank">report the issue</a>!');
}else{
	$html = '<h2>Gamebuino file archive Files</h2>'.getFileSorter('?',true);
	$result = $db->sql_query(getFilesSQL('',true));
	while($gamefile = $db->sql_fetchrow($result)){
		$html .= getFileHTML($gamefile);
	}
	$db->sql_freeresult($result);
	$html .= '';
	$page->getPage('Recent Files',$html);
}
?>