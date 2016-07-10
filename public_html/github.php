<?php
include_once('archive.php');
class GithubUser {
	private $id = -1;
	private $uid = -1;
	private $gid = -1;
	private $token = '';
	private $username = '';
	private $avatar = '';
	private $repos_url = '';
	private $repo_url = '';
	private $repos = array();
	private function api_get($url,$token = true){
		$s = @file_get_contents($url,false,stream_context_create(array(
			'http' => array(
				'header' => "Content-type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nUser-Agent: Ponies/42.1337.9001\r\n".($token?"Authorization: token {$this->token}\r\n":''),
				'method' => 'GET'
			)
		)));
		return json_decode($s,true);
	}
	private function populate_basic(){
		if(!$this->exists()){
			return;
		}
		$json = $this->api_get('https://api.github.com/user');
		
		if(!isset($json['login'])){
			$this->setToken('');
			return;
		}
		$this->username = $json['login'];
		$this->avatar = $json['avatar_url'];
		$this->repos_url = $json['repos_url'];
		$this->gid = $json['id'];
	}
	private function populate_repos(){
		if($this->username == ''){
			$this->populate_basic();
		}
		if(!$this->exists()){
			return;
		}
		$json = $this->api_get($this->repos_url);
		$this->repos = array();
		foreach($json as $j){
			$this->repos[] = array(
				'full_name' => $j['full_name'],
				'name' => $j['name'],
				'html_url' => $j['html_url'],
				'description' => $j['description']
			);
		}
	}
	public function __construct($uid){
		global $db;
		if(!is_numeric($uid)){
			return;
		}
		$result = $db->sql_query(query_escape("SELECT `id`,`github_token`,`user_id` FROM `archive_users` WHERE `user_id`=%d",(int)$uid));
		if(!($obj = $db->sql_fetchrow($result))){
			$this->uid = (int)$uid;
			$db->sql_freeresult($result);
			return;
		}
		$db->sql_freeresult($result);
		
		$this->id = (int)$obj['id'];
		$this->uid = (int)$obj['user_id'];
		$this->token = $obj['github_token'];
	}
	public function exists(){
		return $this->id != -1 && $this->token != '';
	}
	public function setToken($token){
		global $db;
		if($this->uid == -1){
			return;
		}
		if($this->id != -1){
			$db->sql_query(query_escape("UPDATE `archive_users` SET `github_token`='%s' WHERE `id`=%d",$token,$this->id));
		}else{
			$db->sql_query(query_escape("INSERT INTO `archive_users` (`user_id`,`github_token`) VALUES (%d,'%s')",$this->uid,$token));
			$this->id = $db->sql_nextid();
		}
		$this->token = $token;
	}
	public function setRepo($repo,$fid){
		global $db;
		$this->populate_basic();
		if(!$this->exists()){
			return false;
		}
		$json = $this->api_get('https://api.github.com/repos/'.$repo,false);
		if(!$json || !isset($json['name'])){
			return false;
		}
		if($json['owner']['id'] != $this->gid){
			return false;
		}
		$this->repo_url = $json['html_url'];
		
		$db->sql_query(query_escape("UPDATE `archive_files` SET `git_url`='%s',`github_repo`='%s' WHERE `id`=%d",$json['git_url'],$json['full_name'],(int)$fid));
		return true;
	}
	public function getRepoUrl(){
		return $this->repo_url;
	}
	public function getInfo(){
		$this->populate_basic();
		if(!$this->exists()){
			return array(
				'exists' => false
			);
		}
		$this->populate_repos();
		return array(
			'exists' => $this->exists(),
			'username' => $this->username,
			'avatar' => $this->avatar,
			'repos' => $this->repos
		);
	}
}
if(isset($included) && $included){
	return;
}elseif(isset($_GET['login'])){
	$t = new Template('popup.inc');
	$t->title = 'Login with Github';
	$t2 = new Template('github_login.inc');
	$t2->github_url = 'https://github.com/login/oauth/authorize?scope=write:repo_hook&client_id='.$github_oauth_client_id;
	$t->addChild($t2);
	$t->render();
}elseif(isset($_GET['callback'])){
	$code = $_GET['code'];
	$t = new Template('popup.inc');
	$t->title = 'Login with Github';
	$t2 = new Template('github_login_done.inc');
	$t2->success = false;
	
	if($isLoggedIn){
		$json = json_decode(file_get_contents('https://github.com/login/oauth/access_token',false,stream_context_create(array(
			'http' => array(
				'header' => "Content-type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
				'method' => 'POST',
				'content' => http_build_query(array(
					'client_id' => $github_oauth_client_id,
					'client_secret' => $github_oauth_client_secret,
					'code' => $code,
					'accept' => 'json'
				))
			)
		))),true);
		if($json){
			foreach(explode(',',$json['scope']) as $s){
				if($s == 'write:repo_hook'){
					$u = new GithubUser($userid);
					$u->setToken($json['access_token']);
					$t2->success = true;
					break;
				}
			}
		}
	}
	$t->addChild($t2);
	$t->render();
}elseif(isset($_GET['userinfo'])){
	header('Content-Type: application/json');
	$u = new GithubUser($userid);
	if(!$u->exists()){
		echo '{"exists":false}';
		exit;
	}
	echo json_encode(array_merge(array(
		'exists' => true
	),$u->getInfo()));
	exit;
}
