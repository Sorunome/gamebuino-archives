<?php
$startTime = microtime(true);
include_once('archive.php');

function panic(){
	header('Location:./?error');
	die();
}

class File_edit extends File{
	protected $edit = false;
	protected $name_83 = '';
	protected function populate_edit($obj){
		if($this->file_type['type'] == 1 || $this->file_type['type'] == 2){ // github / bitbucket!
			$this->file_type['git_url'] = $obj['git_url'];
			$this->file_type['git_repo'] = $obj['git_repo'];
		}
		$this->name_83 = $obj['name_83'];
		
		$this->edit = true;
	}
	protected function goedit(){
		global $userid,$isAdmin;
		if($this->edit){
			return;
		}
		if(!($userid == $this->authorId || $isAdmin || !$this->exists)){
			return;
		}
		global $db;
		$result = $db->sql_query(query_escape("SELECT t1.`git_url`,t1.`git_repo`,t1.`name_83` FROM `archive_files` AS t1 WHERE t1.`id`=%d",$this->id));
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
	private function validate_save_vars(&$vars,$newFile){
		global $db;
		if($vars['name'] != '' && preg_match("/^(\[\d+\])+$/",$vars['category'])){
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
			
			if($newFile){
				if(!preg_match('/^[A-Z0-9!#%&\'()\-@^_`{}~ ]{1,8}$/',$vars['name_83'])){
					return false;
				}
				$result = $db->sql_query(query_escape("SELECT COUNT(`id`) AS `num` FROM `archive_files` WHERE `name_83`='%s'",$vars['name_83']));
				if($u = $db->sql_fetchrow($result)){
					if($u['num'] > 0){
						$db->sql_freeresult($result);
						return false;
					}
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
			case 2: // bitbucket
				return $vars['git_repo'] != '';
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
			case 2:
				if($this->exists() && $this->file_type['git_repo'] == $vars['git_repo']){
					unset($_POST['repo_url']);
					return true; // ok, we don't actually need to change something anyways
				}
				$included = true;
				$files = array('','github.php','bitbucket.php');
				include_once($files[$vars['file_type']]);
				$u = new WebgitUser($userid);
				if($success = $u->setRepo($vars['git_repo'],$this->id)){
					$vars['repo_url'] = $u->getRepoUrl();
					$_POST['repo_url'] = $vars['repo_url'];
					
					$vars['hook_key'] = generateRandomString(32);
					$_POST['hook_key'] = $vars['hook_key'];
					$u->addHook($vars['git_repo'],$vars['hook_key'],$this->id);
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
	private function soft_validate_save_vars(&$vars,$newFile){
		global $userid,$db,$forum_url;
		// extra authors must be an int csv list
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
		
		// name_83 cannot be changed
		if(!$newFile){
			$vars['name_83'] = $this->name_83;
		}
		
		$vars['file_type'] = (int)$vars['file_type'];
		$vars['repo_url'] = getUrl_safe($vars['repo_url']);
		
		if(isset($vars['git_repo_'.$vars['file_type']])){ // repos are set in git_repo_<file_type>, however we want it in git_repo
			$vars['git_repo'] = $vars['git_repo_'.$vars['file_type']];
			$_POST['git_repo'] = $vars['git_repo'];
		}
		
		// we actually store the topic ID, however the user gives us a string
		$vars['topic_id'] = (int)preg_replace('/^\s*https?:\/\/'.preg_quote($forum_url,'/').'.*t=(\d+).*$/i','$1',$vars['forum_url']);
		if($vars['topic_id'] != 0){
			$result = $db->sql_query("SELECT `topic_id` FROM ".TOPICS_TABLE." WHERE `topic_id` = ".(int)$vars['topic_id']);
			if(!($u = $db->sql_fetchrow($result)) || $u['topic_id'] != $vars['topic_id']){
				$vars['topic_id'] = 0;
			}
			$db->sql_freeresult($result);
		}
		$_POST['topic_id'] = $vars['topic_id']; // we need to update $_POST!
		
		$vars['forum_url'] = (int)getUrl_safe($vars['forum_url']);
	}
	public function save(){
		global $userid,$db;
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
			'git_repo' => '',
			'name_83' => '',
			'extra_authors' => ''
		),$_POST);
		
		$this->soft_validate_save_vars($vars,$newFile);
		
		if(!$this->validate_save_vars($vars,$newFile)){
			return 'Missing required fields';
		}
		if($newFile){
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
		
		// time to actually update the the file!
		$query = "UPDATE `archive_files` SET";
		$params = array();
		$updateVars = array('name','description','topic_id','repo_url','name_83','hook_key');
		foreach(array_merge($updateVars,array('category','extra_authors')) as $v){
			if(isset($_POST[$v])){
				$query .= "`$v`='%s',";
				$params[] = $vars[$v];
			}
		}
		$query .= "`images`='%s' WHERE `id`=%d";
		
		// and of course add images
		$params[] = json_encode($imagesarray[0]);
		$params[] = $this->id;
		array_unshift($params,$query);
		
		$db->sql_freeresult($db->sql_query(call_user_func_array('query_escape',$params)));
		
		$s .= $imagesarray[1]; // is there any output from the image saving?
		
		// let's update our object a bit
		foreach($updateVars as $k){
			if(isset($_POST[$k])){
				$this->$k = $vars[$k];
			}
		}
		$s .= 'Saved file! <a href="./?file='.$this->id.'">view it</a>';
		if($newFile){
			$this->build();
		}
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
	$f = new File_edit(request_var('save','invalid'),true);
	$templates[] = $f->save();
}elseif(request_var('build',false)){
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
}elseif(request_var('check_name_83',false)){
	global $db;
	header('Content-Type: application/json');
	$name = request_var('check_name_83','');
	if(!preg_match('/^[A-Z0-9!#%&\'()\-@^_`{}~ ]{1,8}$/',$name)){
		echo json_encode(array(
			'success' => false,
			'msg' => 'The name must follow the 8.3 filename standard!'
		));
		exit;
	}
	$result = $db->sql_query(query_escape("SELECT COUNT(`id`) AS `num` FROM `archive_files` WHERE `name_83`='%s'",$name));
	if($u = $db->sql_fetchrow($result)){
		if($u['num'] == 0){
			$db->sql_freeresult($result);
			echo json_encode(array(
				'success' => true,
				'suggest' => false,
				'name' => $name
			));
			exit;
		}
	}
	$db->sql_freeresult($result);
	
	
	// Dark magic ahead. DON'T attempt to undersatnd. Or maybe do, idk
	// now we have to come up with suggestions...
	$numlen = 1;
	while(true){
		$namelen = strlen($name);
		$namefrag = $name;
		
		while($namelen + $numlen > 8){
			$namefrag = substr($namefrag,0,-1);
			$namelen--;
			if($namelen == 0){
				echo json_encode(array(
					'success' => false,
					'msg' => 'The name is already taken and I couldn\'t find a suggestion for you, sorry!'
				));
				exit;
			}
		}
		$result = $db->sql_query(query_escape("SELECT `name_83` FROM `archive_files` WHERE `name_83` REGEXP '%s'",'^'.str_replace(')','[)]',str_replace('(','[(]',$namefrag)).'[0-9]{'.$numlen.'}$'));
		$namesTaken = array();
		while($r = $db->sql_fetchrow($result)){
			$namesTaken[] = $r['name_83'];
		}
		$db->sql_freeresult($result);
		$namesTaken[] = $namefrag; // we can't actually take this...
		
		$nameres = $namefrag;
		for($i = pow(10,$numlen-1);in_array($nameres,$namesTaken) && $i < pow(10,$numlen);$nameres = $namefrag.($i++));
		
		if(!in_array($nameres,$namesTaken)){ // that's it, we are done!
			break;
		}
		$numlen++;
	}
	
	echo json_encode(array(
		'success' => true,
		'suggest' => true,
		'name' => $nameres
	));
}else{
	$f = new File_edit(request_var('id',-1),true);
	$t = $f->template_edit();
	if($t->exists){
		$body_template->title = $t->name;
	}else{
		$body_template->title = 'New File';
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
