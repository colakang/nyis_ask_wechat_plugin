

<?php

//zend53   

//Decode by www.dephp.cn  QQ 2859470

?>

<?php

if (!class_exists("classOAuthV2")) {

	class classOAuthV2
	{
		public $client_id;
		public $client_secret;
		public $access_token;
		public $refresh_token;
		public $http_code;
		public $url;
		public $host;
		public $root_domain;
		public $timeout = 30;
		public $connecttimeout = 30;
		public $ssl_verifypeer = false;
		public $format;
		public $decode_json = true;
		public $http_info;
		public $useragent = "OAuth2 v0.1";
		public $debug = false;
		static 		public $boundary = "";

		public function __construct($client_id, $client_secret, $access_token = NULL, $refresh_token = NULL, $openid = NULL)
		{
			$this->client_id = $client_id;
			$this->client_secret = $client_secret;
			$this->access_token = $access_token;
			$this->refresh_token = $refresh_token;
			$this->openid = $openid;
		}

		public function getAuthorizeURL($authorize_url, $url, $response_type = "code", $state = NULL, $display = NULL)
		{
			$params = array();
			$params["client_id"] = $this->client_id;
			$params["redirect_uri"] = $url;
			$params["response_type"] = $response_type;
			$params["state"] = $state;
			$params["display"] = $display;
			return $authorize_url . "?" . self::build_http_query($params);
		}

		public function getAccessToken($keys)
		{
			$params = array();
			$params["client_id"] = $this->client_id;
			$params["client_secret"] = $this->client_secret;


			if ($keys["code"]) {

				$params["grant_type"] = "authorization_code";
				$params["code"] = $keys["code"];
				$params["redirect_uri"] = $keys["redirect_uri"];
			}

			else if ($keys["token"]) {

				$params["grant_type"] = "refresh_token";
				$params["refresh_token"] = $keys["refresh_token"];
			}

			else if ($keys["password"]) {

				$params["grant_type"] = "password";
				$params["username"] = $keys["username"];
				$params["password"] = $keys["password"];
			}

			else {

				return "wrong auth type";
			}



			$response = $this->oAuthRequest($keys["access_token_url"], "POST", $params);


			if ($keys["pause"]) {

				if ($keys["pause"] == "qq") {

					if (strpos($response, "access_token") !== false) {

						parse_str($response, $token);
						$me = $this->oAuthRequest("https://graph.qq.com/oauth2.0/me", "GET", array("access_token" => $token["access_token"]));


						if ($me) {

							$lpos = strpos($me, "(");
							$rpos = strrpos($me, ")");
							$str = substr($me, $lpos + 1, $rpos - $lpos - 1);
							$output = json_decode($str, true);
						}



						if ($output["openid"]) {

							$token = array_merge($output, $token);
							$this->openid = $token["openid"];
						}

						else {

							return $output;
						}

					}

					else {

						$lpos = strpos($token, "(");
						$rpos = strrpos($token, ")");
						$str = substr($token, $lpos + 1, $rpos - $lpos - 1);
						return json_decode($str, true);
					}

				}

				else if ($keys["pause"] == "qqweibo") {

					parse_str($response, $token);


					if ($token["errorCode"]) {

						return array("error" => $token["errorCode"], "error_description" => $token["errorMsg"]);
					}

				}

			}

			else {

				$token = json_decode($response, true);
			}



			if (is_array($token) && !isset($token["error"])) {

				$this->access_token = $token["access_token"];
				$this->refresh_token = $token["refresh_token"];
			}



			return $token;
		}

		public function parseSignedRequest($signed_request)
		{
			list($encoded_sig, $payload) = explode(".", $signed_request, 2);
			$sig = self::base64decode($encoded_sig);
			$data = json_decode(self::base64decode($payload), true);


			if (strtoupper($data["algorithm"]) !== "HMAC-SHA256") {

				return "-1";
			}



			$expected_sig = hash_hmac("sha256", $payload, $this->client_secret, true);
			return $sig !== $expected_sig ? "-2" : $data;
		}

		public function base64decode($str)
		{
			return base64_decode(strtr($str . str_repeat("=", 4 - (strlen($str) % 4)), "-_", "+/"));
		}

		public function getTokenFromJSSDK()
		{
			$key = "weibojs_" . $this->client_id;
			if (isset($_COOKIE[$key]) && ($cookie = $_COOKIE[$key])) {

				parse_str($cookie, $token);
				if (isset($token["access_token"]) && isset($token["refresh_token"])) {

					$this->access_token = $token["access_token"];
					$this->refresh_token = $token["refresh_token"];
					return $token;
				}

				else {

					return false;
				}

			}

			else {

				return false;
			}

		}

		public function getTokenFromArray($arr)
		{
			if (isset($arr["access_token"]) && $arr["access_token"]) {

				$token = array();
				$this->access_token = $token["access_token"] = $arr["access_token"];
				if (isset($arr["refresh_token"]) && $arr["refresh_token"]) {

					$this->refresh_token = $token["refresh_token"] = $arr["refresh_token"];
				}



				return $token;
			}

			else {

				return false;
			}

		}

		public function get($url, $parameters = array())
		{
			$response = $this->oAuthRequest($url, "GET", $parameters);


			if ($this->decode_json) {

				return json_decode($response, true);
			}



			return $response;
		}

		public function post($url, $parameters = array(), $multi = false)
		{
			$response = $this->oAuthRequest($url, "POST", $parameters, $multi);


			if ($this->decode_json) {

				return json_decode($response, true);
			}



			return $response;
		}

		public function delete($url, $parameters = array())
		{
			$response = $this->oAuthRequest($url, "DELETE", $parameters);


			if ($this->decode_json) {

				return json_decode($response, true);
			}



			return $response;
		}

		public function oAuthRequest($url, $method, $parameters, $multi = false)
		{
			if ((strrpos($url, "http://") !== 0) && (strrpos($url, "https://") !== 0)) {

				$url = "$this->host$url";
			}



			if ($this->format === "json") {

				$url = $url . ".json";
			}



			if ($this->root_domain == "qq.com") {

				$parameters["format"] = "json";
				$parameters["oauth_consumer_key"] = $this->client_id;
				$parameters["openid"] = $this->openid;
			}



			if (!empty($this->access_token)) {

				$parameters["access_token"] = $this->access_token;
			}



			switch ($method) {

			case "GET":

				$url = $url . "?" . self::build_http_query($parameters);
				return $this->http($url, "GET");
			default:

				$headers = array();
				if (!$multi && (is_array($parameters) || is_object($parameters))) {

					$body = self::build_http_query($parameters);
				}

				else {

					$body = self::build_http_query_multi($parameters);
					$headers[] = "Content-Type: multipart/form-data; boundary=" . self::$boundary;
				}



				return $this->http($url, $method, $body, $headers);
			}

		}

		public function http($url, $method, $postfields = NULL, $headers = array())
		{
			$this->http_info = array();
			$ci = curl_init();
			curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
			curl_setopt($ci, CURLOPT_USERAGENT, $this->useragent);
			curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
			curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
			curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ci, CURLOPT_ENCODING, "");
			curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
			curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, "getHeader"));
			curl_setopt($ci, CURLOPT_HEADER, false);


			switch ($method) {

			case "POST":

				curl_setopt($ci, CURLOPT_POST, true);


				if (!empty($postfields)) {

					curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
					$this->postdata = $postfields;
				}



				break;


			case "DELETE":

				curl_setopt($ci, CURLOPT_CUSTOMREQUEST, "DELETE");


				if (!empty($postfields)) {

					$url = "$url?$postfields";
				}

			}



			curl_setopt($ci, CURLOPT_URL, $url);


			if ($headers) {

				curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
			}



			curl_setopt($ci, CURLINFO_HEADER_OUT, true);
			$response = curl_exec($ci);
			$this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
			$this->http_info = array_merge($this->http_info, curl_getinfo($ci));
			$this->url = $url;


			if ($this->debug) {

				echo "=====post data======\r\n";
				var_dump($postfields);
				echo "=====info=====\r\n";
				print_r(curl_getinfo($ci));
				echo "=====\$response=====\r\n";
				print_r($response);
			}



			curl_close($ci);
			return $response;
		}

		public function getHeader($ch, $header)
		{
			$i = strpos($header, ":");


			if (!empty($i)) {

				$key = str_replace("-", "_", strtolower(substr($header, 0, $i)));
				$value = trim(substr($header, $i + 2));
				$this->http_header[$key] = $value;
			}



			return strlen($header);
		}

		static public function build_http_query_multi($params)
		{
			if (!$params) {

				return "";
			}



			uksort($params, "strcmp");
			$pairs = array();
			self::$boundary = $boundary = uniqid("------------------");
			$MPboundary = "--" . $boundary;
			$endMPboundary = $MPboundary . "--";
			$multipartbody = "";


			foreach ($params as $parameter => $value ) {

				if (in_array($parameter, array("pic", "image", "picture"))) {

					$url = ltrim($value, "@");
					$content = get_url_contents($url);
					$array = explode("?", basename($url));
					$filename = $array[0];
					$multipartbody .= $MPboundary . "\r\n";
					$multipartbody .= "Content-Disposition: form-data; name=\"" . $parameter . "\"; filename=\"" . $filename . "\"\r\n";
					$multipartbody .= "Content-Type: image/unknown\r\n\r\n";
					$multipartbody .= $content . "\r\n";
				}

				else {

					$multipartbody .= $MPboundary . "\r\n";
					$multipartbody .= "content-disposition: form-data; name=\"" . $parameter . "\"\r\n\r\n";
					$multipartbody .= $value . "\r\n";
				}

			}



			$multipartbody .= $endMPboundary;
			return $multipartbody;
		}

		static public function build_http_query($params)
		{
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
	}

}



if (!class_exists("sinaweiboAPP")) {

	class sinaweiboAPP extends classOAuthV2
	{
		public $host = "https://api.weibo.com/2/";
		public $format = "json";

		public function show_user($uid)
		{
			$params = array();
			$params["uid"] = $uid;
			return $this->get("users/show", $params);
		}
	}

}



if (!class_exists("qqconnectAPP")) {

	class qqconnectAPP extends classOAuthV2
	{
		public $host = "https://graph.qq.com/";
		public $root_domain = "qq.com";

		public function show_user()
		{
			return $this->get("user/get_user_info");
		}
	}

}



if (!class_exists("qqweiboAPP")) {

	class qqweiboAPP extends classOAuthV2
	{
		public $host = "https://open.t.qq.com/api/";
		public $root_domain = "qq.com";

		public function get_ip()
		{
			if ($HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"]) {

				$ip = $HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"];
			}

			else if ($HTTP_SERVER_VARS["HTTP_CLIENT_IP"]) {

				$ip = $HTTP_SERVER_VARS["HTTP_CLIENT_IP"];
			}

			else if ($HTTP_SERVER_VARS["REMOTE_ADDR"]) {

				$ip = $HTTP_SERVER_VARS["REMOTE_ADDR"];
			}

			else if (getenv("HTTP_X_FORWARDED_FOR")) {

				$ip = getenv("HTTP_X_FORWARDED_FOR");
			}

			else if (getenv("HTTP_CLIENT_IP")) {

				$ip = getenv("HTTP_CLIENT_IP");
			}

			else if (getenv("REMOTE_ADDR")) {

				$ip = getenv("REMOTE_ADDR");
			}

			else {

				$ip = "Unknown";
			}



			return $ip;
		}

		public function show_user($name = NULL)
		{
			$params = array();
			$params["clientip"] = $this->get_ip();


			if (!$name) {

				return $this->get("user/info", $params);
			}



			$params["name"] = $name;
			return $this->get("user/other_info", $params);
		}
	}

}


?>

