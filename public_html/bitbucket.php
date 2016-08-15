<?php
require_once('archive.php');
require_once('git_webauth.php');
class WebgitUser extends Git_webauth {
	protected $token_column = 'bitbucket_token';
	private function set_refresh_token($s){
		global $db;
		if($this->id == -1){
			return; // something went wrong
		}
		$db->sql_query(query_escape("UPDATE `archive_users` SET `bitbucket_refresh_token`='%s' WHERE `id`=%d",$s,$this->id));
	}
	protected function getUserInfo($recursion = false){
		$json = $this->api_get('https://api.bitbucket.org/2.0/user');
		if(!isset($json['username'])){
			if($recursion || $this->id == -1){
				$this->set_refresh_token('');
				return false;
			}
			// let's try to refresh the oauth key before saying its invalid
			global $db,$bitbucket_oauth_client_id,$bitbucket_oauth_client_secret;
			$result = $db->sql_query(query_escape("SELECT `bitbucket_refresh_token` FROM `archive_users` WHERE `id`=%d",$this->id));
			if(!($res = $db->sql_fetchrow($result))){
				$db->sql_freeresult($result);
				return false;
			}
			$db->sql_freeresult($result);
			$json = $this->api_get('https://bitbucket.org/site/oauth2/access_token',false,array(
				'grant_type' => 'refresh_token',
				'refresh_token' => $res['bitbucket_refresh_token']
			),array(
				'Authorization' => 'Basic '.base64_encode("$bitbucket_oauth_client_id:$bitbucket_oauth_client_secret")
			));
			
			if(!$this->process_verify($json,'webhook')){
				$this->set_refresh_token('');
				return false;
			}
			$this->set_refresh_token($data['refresh_token']);
			return $this->getUserInfo(true);
		}
		return array(
			'username' => $json['username'],
			'avatar' => $json['links']['avatar']['href'],
			'repos_url' => $json['links']['repositories']['href'],
			'gid' => $json['uuid']
		);
	}
	protected function getRepos(){
		$json = $this->api_get($this->repos_url,false);
		$repos = array();
		foreach($json['values'] as $j){
			$repos[] = array(
				'full_name' => str_replace('https://api.bitbucket.org/2.0/repositories/','',$j['links']['self']['href']),
				'name' => $j['name'],
				'html_url' => $j['links']['html']['href'],
				'description' => $j['description']
			);
		}
		return $repos;
	}
	protected function getRepoInfo($repo){
		$json = $this->api_get('https://api.bitbucket.org/2.0/repositories/'.$repo,false);
		if(!$json || !isset($json['name'])){
			return false;
		}
		$git_url = '';
		foreach($json['links']['clone'] as $l){
			if($l['name'] == 'https'){
				$git_url = $l['href'];
				break;
			}
		}
		
		return array(
			'gid' => $json['owner']['uuid'],
			'git_url' => $git_url,
			'git_repo' => str_replace('https://api.bitbucket.org/2.0/repositories/','',$json['links']['self']['href']),
			'repo_url' => $json['links']['html']['href']
		);
	}
	public function authUser($code){
		global $bitbucket_oauth_client_id,$bitbucket_oauth_client_secret;
		return $this->api_verify('https://bitbucket.org/site/oauth2/access_token','webhook',$code,$bitbucket_oauth_client_id,$bitbucket_oauth_client_secret,function($data){
			$this->set_refresh_token($data['refresh_token']);
		});
	}
}
if(isset($included) && $included){
	return;
}elseif(isset($_GET['login'])){
	$t = new Template('popup.inc');
	$t->title = 'Login with Bitbucket';
	$t2 = new Template('webgit_login.inc');
	$t2->name = 'Bitbucket';
	$t2->url = 'https://bitbucket.org/site/oauth2/authorize?client_id='.$bitbucket_oauth_client_id.'&response_type=code';
	$t->addChild($t2);
	$t->render();
}elseif(isset($_GET['callback'])){
	$code = $_GET['code'];
	$t = new Template('popup.inc');
	$t->title = 'Login with Bitbucket';
	$t2 = new Template('webgit_login_done.inc');
	$u = new WebgitUser($userid);
	$t2->success = $u->authUser($code);
	$t2->name = 'Bitbucket';
	
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
}
