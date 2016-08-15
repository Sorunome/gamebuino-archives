<?php

abstract class Git_webauth {
	// column name of the token, e.g. github_token 
	protected $token_column = '';
	
	/*
	return array(
		'username' => (string),
		'avatar' => (string/href),
		'repos_url' => (string/api_href),
		'gid' => (string)/(int) (user ID)
	)
	*/
	abstract protected function getUserInfo();
	
	/*
	$this->repos_url is present
	return array(array(
		'full_name' => (string), (usually username/repo)
		'name' => (string),
		'html_url' => (string),
		'description' => (string)
	))
	*/
	abstract protected function getRepos();
	
	/*
	return array(
		'gid' => (string)/(int), (owner id / uuid)
		'git_url' => (string), (url to clone)
		'git_repo' => (string), (usually username/repo)
		'repo_url' => (string) (http url)
	)
	*/
	abstract protected function getRepoInfo($repo);
	
	/*
	call to
	return $this->api_verify($url,$scope,$code,$client_id,$client_secret[,$callback($json)])
	
	
	*/
	abstract public function authUser($code);
	
	protected $id = -1;
	private $uid = -1;
	private $token = '';
	private $username = '';
	private $avatar = '';
	protected $repos_url = '';
	private $repo_url = '';
	private $repos = array();
	private $http_headers = array(
		'Content-Type' => 'application/x-www-form-urlencoded',
		'Accept' => 'application/json',
		'User-Agent' => 'Ponies/42.1337.9001',
		'Pragma' => 'no-cache',
		'Cache-Control' => 'no-cache'
	);
	
	private function parseHeaders($headers){
		$s = '';
		foreach($headers as $k => $h){
			$s .= "$k: $h\r\n";
		}
		return $s;
	}
	protected function api_get($url,$token = true,$post = array(),$headers = array()){
		$headers = array_merge($this->http_headers,$headers);
		if($token){
			$headers['Authorization'] = 'Bearer '.$this->token;
		}
		$s = @file_get_contents($url,false,stream_context_create(array(
			'http' => array(
				'header' => $this->parseHeaders($headers),
				'method' => $post?'POST':'GET',
				'content' => http_build_query($post)
			)
		)));
		return json_decode($s,true);
	}
	protected function process_verify($json,$scope,$callback = NULL){
		if(!$json){
			return false;
		}
		$delimiter = ',';
		$scopes = isset($json['scopes'])?$json['scopes']:$json['scope'];
		if(strpos($scopes,' ') !== false){
			$delimiter = ' ';
		}
		foreach(explode($delimiter,$scopes) as $s){#
			if($s == $scope){
				$this->setToken($json['access_token']);
				if(is_callable($callback)){
					$callback($json);
				}
				return true;
			}
		}
		return false;
	}
	protected function api_verify($url,$scope,$code,$client_id,$client_secret,$callback = NULL){
		if($this->uid == -1){
			return false;
		}
		
		$headers = array_merge($this->http_headers,array(
			'Authorization' => 'Basic '.base64_encode("$client_id:$client_secret")
		));
		$s = file_get_contents($url,false,stream_context_create(array(
			'http' => array(
				'header' => $this->parseHeaders($headers),
				'method' => 'POST',
				'content' => http_build_query(array(
					'client_id' => $client_id,
					'client_secret' => $client_secret,
					'grant_type' => 'authorization_code',
					'code' => $code,
					'accept' => 'json'
				))
			)
		)));
		$json = json_decode($s,true);
		return $this->process_verify($json,$scope,$callback);
	}
	private function populate_basic(){
		if(!$this->exists()){
			return;
		}
		$data = $this->getUserInfo();
		if(!$data || !$data['username']){
			$this->setToken('');
			return;
		}
		foreach(array('username','avatar','repos_url','gid') as $k){
			$this->$k = $data[$k];
		}
	}
	private function populate_repos(){
		if($this->username == ''){
			$this->populate_basic();
		}
		if(!$this->exists()){
			return;
		}
		$this->repos = $this->getRepos();
	}
	public function __construct($uid){
		global $db;
		if(!is_numeric($uid) || !$this->token_column){
			return;
		}
		$result = $db->sql_query(query_escape("SELECT `id`,`$this->token_column`,`user_id` FROM `archive_users` WHERE `user_id`=%d",(int)$uid));
		if(!($obj = $db->sql_fetchrow($result))){
			$this->uid = (int)$uid;
			$db->sql_freeresult($result);
			return;
		}
		$db->sql_freeresult($result);
		
		$this->id = (int)$obj['id'];
		$this->uid = (int)$obj['user_id'];
		$this->token = $obj[$this->token_column];
	}
	public function exists(){
		return $this->id != -1 && $this->token != '';
	}
	private function setToken($token){
		global $db;
		if($this->uid == -1){
			return;
		}
		if($this->id != -1){
			$db->sql_query(query_escape("UPDATE `archive_users` SET `$this->token_column`='%s' WHERE `id`=%d",$token,$this->id));
		}else{
			$db->sql_query(query_escape("INSERT INTO `archive_users` (`user_id`,`$this->token_column`) VALUES (%d,'%s')",$this->uid,$token));
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
		$data = $this->getRepoInfo($repo);
		if(!$data || $data['gid'] != $this->gid || !$data['git_url']){
			return false;
		}
		$this->repo_url = $data['repo_url'];
		$db->sql_query(query_escape("UPDATE `archive_files` SET `git_url`='%s',`git_repo`='%s' WHERE `id`=%d",$data['git_url'],$data['git_repo'],(int)$fid));
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
