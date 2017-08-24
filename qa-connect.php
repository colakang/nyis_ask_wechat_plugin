<?php

//zend53   

//Decode by www.dephp.cn  QQ 2859470

?>

<?php

class qa_connect
{
	public $directory;
	public $urltoroot;

	public function load_module($directory, $urltoroot)
	{
		error_reporting(0);
		$this->directory = $directory;
		$this->urltoroot = $urltoroot;
	}

	public function get_keys($default = "")
	{
		$sina_1 = qa_opt("qa_connect_sina_1");
		$qzone_1 = qa_opt("qa_connect_qzone_1");


		if ($sina_1) {

			$sina_2 = qa_opt("qa_connect_sina_2");
		}



		if ($qzone_1) {

			$qzone_2 = qa_opt("qa_connect_qzone_2");
		}



		$keys = array("qzone" => $qzone_2 ? array($qzone_1, $qzone_2) : array(), "sina" => $sina_2 ? array($sina_1, $sina_2) : array());
		return $keys;
	}

	public function getAuthorizeURL($authorize_url, $redirect_uri, $client_id, $response_type = "code", $scope = NULL, $state = NULL, $display = NULL)
	{
		$params = array();
		$params["client_id"] = $client_id;
		$params["redirect_uri"] = $redirect_uri;
		$params["response_type"] = $response_type;
		$params["state"] = $state;


		if ($scope) {

			$params["scope"] = $scope;
		}



		if ($display) {

			$params["display"] = $display;
		}



		return $authorize_url . "?" . $this->build_http_query($params);
	}

	public function build_http_query($params)
	{
		if (function_exists("http_build_query")) {

			return http_build_query($params);
		}



		if (!$params) {

			return "";
		}



		uksort($params, "strcmp");
		$pairs = array();


		foreach ($params as $parameter => $value ) {

			if (is_array($value)) {

				natsort($value);


				foreach ($value as $duplicate_value ) {

					$pairs[] = $parameter . "=" . $duplicate_value;
				}

			}

			else {

				$pairs[] = $parameter . "=" . $value;
			}

		}



		return implode("&", $pairs);
	}

	public function get_authorize_url($media, $apikey, $redirect_uri, $state)
	{
		switch ($media) {

		case "sina":

			$authorize_url = "https://api.weibo.com/oauth2/authorize";
			break;


		case "qzone":

			$authorize_url = "https://graph.qq.com/oauth2.0/authorize";
			$scope = "get_user_info";
			break;


		case "qq":

			$authorize_url = "https://open.t.qq.com/cgi-bin/oauth2/authorize";
			break;


		default:

			return NULL;
		}



		$state = (!empty($state) ? $media . md5($media . $state) : $media);
		return $this->getAuthorizeURL($authorize_url, $redirect_uri, $apikey[0], "code", $scope, $state, $display);
	}

	public function get_token($code, $media, $keys, $redirect_uri, $myapp = "", $out_user = "")
	{
		if (empty($keys)) {

			$keys = $this->get_keys($myapp);
		}



		$apikey = $keys[$media];
		class_exists("classOAuthV2") || require (dirname(__FILE__) . "/class.php");
		$params = array();


		switch ($media) {

		case "sina":

			$params["access_token_url"] = "https://api.weibo.com/oauth2/access_token";
			break;


		case "qzone":

			$params["pause"] = "qq";
			$params["access_token_url"] = "https://graph.qq.com/oauth2.0/token";
			break;


		case "qq":

			$params["pause"] = "qqweibo";
			$params["access_token_url"] = "https://open.t.qq.com/cgi-bin/oauth2/access_token";
			break;


		default:

			return NULL;
		}



		if (!$code) {

			$params["token"] = 1;
			$params["refresh_token"] = $redirect_uri;
		}

		else {

			$params["code"] = $code;
			$params["redirect_uri"] = $redirect_uri;
		}



		$to = new classOAuthV2($apikey[0], $apikey[1]);
		$token = $to->getAccessToken($params);

		if ($token["expires_in"]) {

			$token["expires_in"] = time() + $token["expires_in"];
		}



		if ($out_user && $token["access_token"]) {

			switch ($media) {

			case "sina":

				$o = new sinaweiboAPP($apikey[0], $apikey[1], $token["access_token"]);
				$show_user = $o->show_user($token["uid"]);
				$user = array("uid" => $show_user["idstr"], "username" => $show_user["screen_name"], "name" => $show_user["name"], "avatar" => $show_user["avatar_large"], "url" => $show_user["url"] ? $show_user["url"] : "http://weibo.com/" . $show_user["idstr"], "location" => $show_user["location"], "description" => $show_user["description"]);
				break;


			case "qzone":

				$o = new qqconnectAPP($apikey[0], $apikey[1], $token["access_token"], "", $token["openid"]);
				$show_user = $o->show_user();
				$user = array("uid" => $token["openid"], "username" => $show_user["nickname"], "name" => $show_user["nickname"], "avatar" => $show_user["figureurl_2"]);
				break;


			case "qq":

				$o = new qqweiboAPP($apikey[0], $apikey[1], $token["access_token"], "", $token["openid"]);
				$show_user = $o->show_user();
				$show_user = $show_user["data"];
				$user = array("uid" => $show_user["name"], "username" => $show_user["name"], "name" => $show_user["nick"], "avatar" => $show_user["head"] . "/100", "url" => $show_user["homepage"] ? $show_user["homepage"] : "http://t.qq.com/" . $show_user["name"], "location" => $show_user["location"], "description" => $show_user["introduction"]);
				break;


			default:

				return NULL;
			}



			if (!$user["uid"]) {

				return var_dump($show_user);
			}



			return $user;
		}



		return $token;
	}

	public function admin_form()
	{
		$saved = false;


		if (qa_clicked("qa_connect_save_button")) {

			qa_opt("qa_connect_qzone_1", qa_post_text("qa_connect_qzone_1"));
			qa_opt("qa_connect_qzone_2", qa_post_text("qa_connect_qzone_2"));
			qa_opt("qa_connect_sina_1", qa_post_text("qa_connect_sina_1"));
			qa_opt("qa_connect_sina_2", qa_post_text("qa_connect_sina_2"));
			$saved = true;
		}



		return array(
	"ok"      => $saved ? "保存成功" : NULL,
	"fields"  => array(
		array("label" => "<p>QQ互联: <a href=\"http://connect.qq.com/manage/\" target=\"_blank\">申请</a></p> APP ID", "value" => qa_opt("qa_connect_qzone_1"), "tags" => "NAME=\"qa_connect_qzone_1\""),
		array("label" => "KEY:", "value" => qa_opt("qa_connect_qzone_2"), "tags" => "NAME=\"qa_connect_qzone_2\""),
		array("label" => "<p>新浪微博: <a href=\"http://open.weibo.com/connect\" target=\"_blank\">申请</a></p> App Key", "value" => qa_opt("qa_connect_sina_1"), "tags" => "NAME=\"qa_connect_sina_1\""),
		array("label" => "App Secret:", "value" => qa_opt("qa_connect_sina_2"), "tags" => "NAME=\"qa_connect_sina_2\"")
		),
	"buttons" => array(
		array("label" => "保存设置", "tags" => "NAME=\"qa_connect_save_button\"")
		)
	);
	}

	public function check_login()
	{
		require_once $this->directory . 'qa-connect-utils.php';
		if (isset($_GET["code"])) {
			parse_str($_SERVER["QUERY_STRING"]);
			$media = substr($state, 0, -32);
			$state = substr($state, -32);
			if ($state == md5($media . $_SESSION["oauth_state"])) {
				$user = $this->get_token($code, $media, "", $_SESSION["tourl"], $from, true);
				$duplicates = 0;
				if (is_array($user) && $user["uid"]) {
					unset($_GET["code"]);
					unset($_GET["openid"]);
					unset($_GET["openkey"]);
					unset($_GET["from"]);
					$duplicates = qa_log_in_external_user($media, $user['uid'], array(
						'email' => @$user['email'],
						'handle' => @$user['username'],
						'confirmed' => true,
						'name' => @$user['name'],
						'location' => @$user['location'],
						'website' => @$user['url'],
						'about' => @$user['description'],
						'avatar' => strlen(@$user['avatar']) ? qa_retrieve_url($user['avatar']) : null,
					));
					//qa_log_in_external_user($media, $user["uid"], array("email" => @$user["email"], "handle" => @$user["username"], "confirmed" => true, "name" => @$user["name"], "location" => @$user["location"], "website" => @$user["url"], "about" => @$user["description"]));
/*
					if (strlen(@$user["avatar"])) {
						$userid = qa_get_logged_in_userid();
						qa_db_user_profile_set($userid, "social_avatar", $user["avatar"]);

						if ((qa_opt("db_time") - qa_get_logged_in_user_field("created")) < 10) {

							qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_AVATAR, true);
						}
					}
*/
					if($duplicates > 0) {
						qa_redirect('logins', array('confirm' => '1', 'to' => $topath));
					} else {
						qa_redirect_raw(qa_opt('site_url') . $topath);
					}
	
				}
			}
		}
	}

	public function login_html($tourl, $context)
	{
		/*  Modify By C.K At  10-8-2015
			
		if ($context == "menu") {

			return false;
		}

		*/
		$_SESSION["tourl"] = $tourl;
		$_SESSION["oauth_state"] = uniqid(rand(), true);
		$plugin_url = $this->urltoroot;
		$keys = $this->get_keys();


		if ($keys["qzone"]) {

			$authorize_url_qzone = $this->get_authorize_url("qzone", $keys["qzone"], $tourl, $_SESSION["oauth_state"]);
			echo "<a class='open-login-button context-menu action-login  qzone  icon-qzone' href='$authorize_url_qzone' title='QQ' rel='nofollow' ><img src='{$plugin_url}login_qzone.gif' border=0 /></a> ";
		}


		if ($keys["sina"]) {

			$authorize_url_sina = $this->get_authorize_url("sina", $keys["sina"], $tourl, $_SESSION["oauth_state"]);
			echo "<a class='open-login-button context-menu action-login sina icon-sina' href='$authorize_url_sina' title='新浪微博' rel='nofollow' >新浪微博登录</a> ";
			//echo "<a class='open-login-button context-menu action-login sina  icon-sina' href='$authorize_url_sina' title='新浪微博' rel='nofollow' ><img src='{$plugin_url}login_sina.gif' border=0 /></a> ";
		}

	}
}

if (!defined("QA_VERSION")) {

	header("Location: ../");
	exit();
}


?>




