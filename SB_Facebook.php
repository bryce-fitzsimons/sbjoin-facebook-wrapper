<?php
require_once "/var/www/sbjoin/env.php";

Class SB_Facebook {

	private $fb = null;
	private $fb_settings = [
		'app_id' => '58xxxxxxxxxxxxxxxx',
		'app_secret' => '3dxxxxxxxxxxxxxxxxxxxxxxxxxxx',
		'default_graph_version' => 'v2.5',
		'default_access_token' => null
	];
	private $permissions = ['email', 'user_likes', 'user_birthday', 'publish_actions'];
	private $app_settings = [
		'login_callback_url' => 'http://dev.sbjoin.com/1.6/fb_login-callback.php',
		'post_data_url' => 'http://sbjoin.com'
	];
	
	public $user = null;	// Facebook user object
	public $user_id = null;
	
	public $logged_in = false;
	public $login_url = "";
	
	public $status = 0;
	public $status_message = "";
	
	
	public function __construct(){
		if(	isset($_SESSION['facebook_access_token']) &&
			$_SESSION['facebook_access_token']!=-1
		){
			$this->fb_settings['default_access_token'] = $_SESSION['facebook_access_token'];
		}else{
			unset( $this->fb_settings['default_access_token'] );
		}
		
		$this->fb = new Facebook\Facebook($this->fb_settings);
	}
	
	
	public function login(){
		try {
			$response = $this->fb->get('/me?fields=id,name,first_name,last_name,gender,birthday,location,timezone,email,permissions');
			$this->user = $response->getGraphUser();
			die(print_r($this->user));
			
			$this->user_id = $this->user['id'];
			$this->permissions = $this->getPermissions($this->user);
			
			$this->logged_in = 1;
			$this->status = 1;
			$this->status_message = "Logged in";			
			return 1;
			
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			$this->logged_in = 0;
			$this->status = -1;
			$this->status_message = "Graph error: " . $e->getMessage();
			
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			$this->logged_in = 0;
			$this->status = -1;
			$this->status_message = "SDK error: " . $e->getMessage();
			
		}
		
		if($this->status == -1){
			$helper = $this->fb->getRedirectLoginHelper();
			$this->login_url = $helper->getLoginUrl($this->app_settings['login_callback_url'], $this->permissions);
			return 0;
		}
	}
	
	
	public function getLongAccessToken(){
		$helper = $this->fb->getRedirectLoginHelper();
		$_SESSION['FBRLH_state']=$_GET['state'];

		try {
			$accessToken = $helper->getAccessToken();
			
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			$this->status = -1;
			$this->status_message = "1. Graph error: " . $e->getMessage();
			
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			$this->status = -1;
			$this->status_message = "1. SDK error: " . $e->getMessage();
			
		}

		if(isset($accessToken)){
			// Logged in
			$oAuth2Client = $this->fb->getOAuth2Client();
			$longAccessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
			$this->status = 1;
			return (string)$longAccessToken;

		} elseif ($helper->getError()) {
			$this->status = -2;
			$this->status_message = "Token request error: " . $helper->getError();
			return -1;
			
		}
		
		$this->status = -3;
		return -3;
	}
	
	
	public function reRequestPermissionsURL($permissions = null){
		if(!$permissions) $permissions = $this->permissions;
		$helper = $this->fb->getRedirectLoginHelper();
		return $helper->getReRequestUrl($this->app_settings['login_callback_url'], $permissions);
	}
	
	
	public function getPermissions($user = null){
		// If no user object is passed, we are re-requesting it. Otherwise parse from user object.
		if(!$user){
			try {
				$response = $this->fb->get('/me/permissions');
				$this->status = 1;
				$this->status_message = "Fetched permissions";
			
			} catch(Facebook\Exceptions\FacebookResponseException $e) {
				$this->status = -1;
				$this->status_message = "Graph error: " . $e->getMessage();
				return 0;
		
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
				$this->status = -1;
				$this->status_message = "SDK error: " . $e->getMessage();
				return 0;
		
			}
			$tmp = $response->getGraphEdge()->asArray();
			
		}else{
			$tmp = $user['permissions'];
			
		}
		
		$permissions = [];
		foreach($tmp as $k=>$v){
			$permissions[$v['permission']] = $v['status'];
		}
		return $permissions;
		
	}
	
	
	public function post($options = null){
		if( !is_array($options) )
		$options =	[
			'link' => $this->app_settings['post_data_url'],
			'message' => "Just testing the Facebook API..."
		];

		try {
			$response = $this->fb->post('/me/feed', $options);
			
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			$this->status = -1;
			$this->status_message = "Graph error: " . $e->getMessage();
			return 0;
			
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			$this->status = -1;
			$this->status_message = "SDK error: " . $e->getMessage();
			return 0;
			
		}

		$this->status = 1;
		$this->status_message = "";
		$graphNode = $response->getGraphNode();
		return $graphNode['id'];
	}
	
}