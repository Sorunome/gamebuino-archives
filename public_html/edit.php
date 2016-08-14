<?php
$startTime = microtime(true);
include_once('archive.php');

function panic(){
	header('Location:?error');
	die();
}

class File_edit extends File{
	private $edit = false;
	private $name_83 = '';
	private function populate_edit($obj){
		if($this->file_type['type'] == 1){ // github!
			$this->file_type['git_url'] = $obj['git_url'];
			$this->file_type['github_repo'] = $obj['github_repo'];
		}
		$this->name_83 = $obj['name_83'];
		
		$this->edit = true;
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
		$result = $db->sql_query(query_escape("SELECT t1.`git_url`,t1.`github_repo`,t1.`name_83` FROM `archive_files` AS t1 WHERE t1.`id`=%d",$this->id));
		if($obj = $db->sql_fetchrow($result)){
			$this->populate_edit($obj);
		}
		$db->sql_freeresult($result);
	}
	public function json_edit(){
		$this->goedit();
		
		return array_merge($this->json(),array(
			'file_type' => $this->file_type,
			'name_83' => $this->name_83
		));
	}
	public function template_edit($file = 'edit.inc'){
		$this->goedit();
		$t = new Template($file);
		$j = $this->json_edit();
		$t->loadJSON($j);
		
		$tb = new Template('edit_file_settings.inc');
		$tb->loadJSON($j);
		$t->addChild($tb);
		
		$tb = new Template('edit_builds.inc');
		$tb->loadJSON($j);
		$tb->addChildren($this->template_builds());
		$t->addChild($tb);
		return $t;
	}
	private function validate_save_vars(&$vars){
		global $db;
		if($vars['name'] != '' && $vars['complexity'] >= 0 && $vars['complexity'] <= 3 && $vars['version'] >= 0 && $vars['version'] <= 3 && preg_match("/^(\[\d+\])+$/",$vars['category'])){
			$cats = array();
			foreach(explode('][',substr($vars['category'],1,strlen($vars['category'])-2)) as $c){
				$cats[] = (int)$c;
			}
			if(!$cats){
				return false;
			}
			$cats = array_unique($cats);
			$vars['category'] = '['.implode('][',$cats).']';
			$num = sizeof($cats);
			$cats = implode(',',$cats);
			if(!preg_match('/^\d+(,\d+)*$/',$cats)){
				return false;
			}
			$result = $db->sql_query("SELECT COUNT(`id`) AS `num` FROM `archive_categories` WHERE `id` IN (".$cats.")");
			if(!($u = $db->sql_fetchrow($result)) || $u['num'] != $num){
				$db->sql_freeresult($result);
				return false;
			}
			$db->sql_freeresult($result);
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
				if($this->exists() && $this->file_type['github_repo'] == $vars['github_repo']){
					unset($_GET['repo_url']);
					return true; // ok, we don't actually need to change something anyways
				}
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
	public function save(){
		global $userid,$db,$forum_url;
		if(!$this->canEdit()){
			return '';
		}
		$this->goedit();
		$newFile = !$this->exists();
		$vars = array_merge(array(
			'name' => '',
			'description' => '',
			'topic_id' => 0,
			'repo_url' => '',
			'category' => '',
			'delimage0' => 'false',
			'delimage1' => 'false',
			'delimage2' => 'false',
			'delimage3' => 'false',
			'file_type' => 0,
			'github_repo' => '',
			'name_83' => '',
			'extra_authors' => ''
		),$_POST);
		
		if(preg_match('/^\d+(,\d+)*$/',$vars['extra_authors'])){
			$a = array_unique(explode(',',$vars['extra_authors']));
			if($k = array_search((string)$userid,$a) !== false){
				unset($a[$k]);
			}
			$vars['extra_authors'] = implode(',',$a);
			$result = $db->sql_query("SELECT COUNT(`user_id`) AS `num` FROM ".USERS_TABLE." WHERE `user_id` IN (".$vars['extra_authors'].")");
			if(!($u = $db->sql_fetchrow($result)) || $u['num'] != sizeof($a)){
				$vars['extra_authors'] = '';
			}
			$db->sql_freeresult($result);
		}else{
			$vars['extra_authors'] = '';
		}
		if($this->exists()){ // make sure we can't change it anymore
			$vars['name_83'] = $this->name_83;
		}
		$vars['file_type'] = (int)$vars['file_type'];
		$vars['repo_url'] = getUrl_safe($vars['repo_url']);
		
		$vars['topic_id'] = (int)preg_replace('/^\s*https?:\/\/'.preg_quote($forum_url,'/').'.*t=(\d+).*$/i','$1',$vars['forum_url']);
		$result = $db->sql_query("SELECT `topic_id` FROM ".TOPICS_TABLE." WHERE `topic_id` = ".(int)$vars['topic_id']);
		if(!($u = $db->sql_fetchrow($result)) || $u['topic_id'] != $vars['topic_id']){
			$vars['topic_id'] = 0;
		}
		$db->sql_freeresult($result);
		$_POST['topic_id'] = $vars['topic_id']; // we need to update $_POST!
		
		$vars['forum_url'] = (int)getUrl_safe($vars['forum_url']);
		
		if(!$this->validate_save_vars($vars)){
			return 'Missing required fields';
		}
		if(!$this->exists()){
			if($this->validate_save_filevars($vars)){
				// we add the file here and upload it later!
				$db->sql_query(query_escape("INSERT INTO `archive_files` (`author`,`votes`,`extra_authors`) VALUES (%d,'{}','')",$userid));
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
		$updateVars = array('name','description','topic_id','repo_url','name_83');
		foreach(array_merge($updateVars,array('category','extra_authors')) as $v){
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
		return $s;
	}
}

$templates = array();
$body_template = new Template('body.inc');
$body_template->title = '';
$body_template->startTime = $startTime;


function getBuildOutputMessage($id){
	global $db;
	if(!is_numeric($id)){
		return '';
	}
	$res = $db->sql_query(query_escape("SELECT `file`,`status`,`output` FROM `archive_queue` WHERE `id`=%d AND `type`=0",(int)$id));
	if(!($qdata = $db->sql_fetchrow($res))){
		$db->sql_freeresult($res);
		return '';
	}
	$db->sql_freeresult($res);
	$f = new File($qdata['file']);
	if(!$f->canEdit()){
		return '';
	}
	if(in_array($qdata['status'],array(1,2))){
		return ''; // TODO: contact backend
	}
	return $qdata['output'];
}

if(request_var('authorcheck',false)){
	$result = $db->sql_query(query_escape("SELECT `username`,`user_id` AS `id` FROM ".USERS_TABLE." WHERE `username_clean` LIKE '%s' LIMIT 1",strtolower(request_var('authorcheck','INVALID')).'%'));
	header('Content-Type: application/json');
	if($u = $db->sql_fetchrow($result)){
		echo json_encode($u);
	}else{
		echo json_encode(array(
			'id' => -1,
			'username' => ''
		));
	}
	$db->sql_freeresult($result);
}elseif(request_var('get_build',false)){
	global $db;
	$result = $db->sql_query(query_escape("SELECT `id`,`status`,UNIX_TIMESTAMP(`ts`) AS `ts`,`file` FROM `archive_queue` WHERE `type`=0 AND `id`=%d",(int)request_var('get_build','invalid')));
	header('Content-Type: application/json');
	if($b = $db->sql_fetchrow($result)){
		$f = new File($b['file']);
		if($f->canEdit()){
			$t = new Template('edit_build.inc');
			$t->id = (int)$b['id'];
			$t->status = (int)$b['status'];
			$t->ts = (int)$b['ts'];
			
			ob_start();
			$t->render();
			$t = ob_get_contents();
			ob_end_clean();
			echo json_encode(array(
				'success' => true,
				'pending' => ($b['status'] == 1 || $b['status'] == 2),
				'html' => $t
			));
		}else{
			echo json_encode(array(
				'success' => false
			));
		}
	}else{
		echo json_encode(array(
			'success' => false
		));
	}
	$db->sql_freeresult($result);
}elseif(request_var('delete_build',false)){
	global $db;
	$result = $db->sql_query(query_escape("SELECT `id`,`file`,`status` FROM `archive_queue` WHERE `type`=0 AND `id`=%d",(int)request_var('delete_build','invalid')));
	$success = false;
	if(($b = $db->sql_fetchrow($result)) && ($b['status'] == 0 || $b['status'] == 4)){
		$f = new File($b['file']);
		if($f->canEdit()){
			$db->sql_query(query_escape("DELETE FROM `archive_queue` WHERE `id`=%d",(int)$b['id']));
			$success = true;
		}
	}
	$db->sql_freeresult($result);
	header('Content-Type: text/javascript');
	echo json_encode(array(
		'success' => $success
	));
}elseif(request_var('save',false)){
	$body_template->title = 'Saving';
	$f = new File(request_var('save','invalid'));
	$templates[] = $f->save();
}elseif(request_var('build',false)){
	global $db;
	$f = new File(request_var('build','invalid'));
	header('Content-Type: application/json');
	$id = $f->build();
	echo json_encode(array(
		'success' => $id != -1,
		'id' => $id
	));
}elseif(request_var('build_message',false)){
	header('Content-Type: text/plain');
	echo getBuildOutputMessage(request_var('build_message','invalid'));
}elseif(isset($_GET['error'])){
	$body_template->title = 'Error';
	$templates[] = 'Something went wrong! Be sure to <a href="https://github.com/Sorunome/gamebuino-archives/issues" target="_blank">report the issue</a>!';
}else{
	$f = new File_edit(request_var('id',-1),true);
	$t = $f->template_edit();
	if($t->exists){
		$body_template->title = $t->name;
	}else{
		$body_template->title = 'Error';
	}
	$templates[] = $t;
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
