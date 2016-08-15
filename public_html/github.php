<?php
require_once('archive.php');
require_once('git_webauth.php');

class WebgitUser extends Git_webauth {
	protected $token_column = 'github_token';
	protected function getUserInfo(){
		$json = $this->api_get('https://api.github.com/user');
		return array(
			'username' => $json['login'],
			'avatar' => $json['avatar_url'],
			'repos_url' => $json['repos_url'],
			'gid' => $json['id']
		);
	}
	protected function getRepos(){
		$json = $this->api_get($this->repos_url,false);
		$repos = array();
		foreach($json as $j){
			$repos[] = array(
				'full_name' => $j['full_name'],
				'name' => $j['name'],
				'html_url' => $j['html_url'],
				'description' => $j['description']
			);
		}
		return $repos;
	}
	protected function getRepoInfo($repo){
		$json = $this->api_get('https://api.github.com/repos/'.$repo,false);
		return array(
			'gid' => $json['owner']['id'],
			'git_url' => $json['git_url'],
			'git_repo' => $json['full_name'],
			'repo_url' => $json['html_url']
		);
	}
	public function authUser($code){
		global $github_oauth_client_id,$github_oauth_client_secret;
		return $this->api_verify('https://github.com/login/oauth/access_token','write:repo_hook',$code,$github_oauth_client_id,$github_oauth_client_secret);
	}
	public function addHook($repo,$code,$fid){
		global $site_url;
		$json = $this->api_get('https://api.github.com/repos/'.$repo.'/hooks',true,json_encode(array(
			'name' => 'web',
			'config' => array(
				'url' => $site_url.'/github.php?build='.$fid,
				'content_type' => 'json',
				'secret' => $code
			),
			'active' => true,
			'events' => array('release','push')
		)),array(
			'Content-Type' => 'application/json'
		));
	}
}
if(isset($included) && $included){
	return;
}elseif(isset($_GET['login'])){
	$t = new Template('popup.inc');
	$t->title = 'Login with Github';
	$t2 = new Template('webgit_login.inc');
	$t2->name = 'Github';
	$t2->url = 'https://github.com/login/oauth/authorize?scope=write:repo_hook&client_id='.$github_oauth_client_id;
	$t->addChild($t2);
	$t->render();
}elseif(isset($_GET['callback'])){
	$code = $_GET['code'];
	$t = new Template('popup.inc');
	$t->title = 'Login with Github';
	$t2 = new Template('webgit_login_done.inc');
	$u = new WebgitUser($userid);
	$t2->success = $u->authUser($code);
	$t2->name = 'Github';
	
	$t->addChild($t2);
	$t->render();
}elseif(isset($_GET['userinfo'])){
	header('Content-Type: application/json');
	$u = new WebgitUser($userid);
	if(!$u->exists()){
		echo '{"exists":false}';
		exit;
	}
	echo json_encode(array_merge(array(
		'exists' => true
	),$u->getInfo()));
	exit;
}elseif(isset($_GET['build'])){
	header('Content-Type: text/plain');
	$f = new File($_GET['build']);
	if(!$f->exists()){
		echo 'File doesn\'t exist!';
		exit;
	}
	if(!isset($_SERVER['HTTP_X_HUB_SIGNATURE']) || !isset($_SERVER['HTTP_X_GITHUB_EVENT'])){
		echo 'Permission denied';
		exit;
	}
	$hash = explode('=',$_SERVER['HTTP_X_HUB_SIGNATURE']);
	$data = file_get_contents('php://input');
	if(hash_hmac($hash[0],$data,$f->getHookKey()) != $hash[1] || !($data = json_decode($data,true))){
		echo 'Permission denied';
		exit;
	}
	
	$msg = '';
	switch($_SERVER['HTTP_X_GITHUB_EVENT']){
		case 'ping':
			$msg = 'Just pinging, nothing to do';
			break;
		case 'release':
			if($data['action'] != 'published'){
				$msg = 'This release isn\'t public, nothing to do';
			}
			break;
		case 'push':
			if(strpos($data['ref'],'refs/tags/') !== 0 || !$data['created']){
				$msg = 'No new tag was pushed, nothing to do';
			}
			break;
		default:
			$msg = 'Unkown event, nothing to do';
	}
	if($msg){
		echo $msg;
		exit;
	}
	if($f->build(true) == -1){
		echo 'Triggering build failed!';
		exit;
	}
	echo 'Triggered building of file!';
	exit;
}
