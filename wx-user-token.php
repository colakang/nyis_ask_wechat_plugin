<?php

require_once QA_INCLUDE_DIR.'app/posts.php';

/**
 * Class wx_user_token
 * Class to generate token for wechat users
 */

class wx_user_token {

    protected $code;
    protected $wxAppID;
    protected $wxAppSecret;
    protected $wxLoginUrl;

    /**
     * wx_user_token constructor.
     * @param $codeOrContentPara
     * construct a new object of wxuser
     */
    function __construct($codeOrContentPara)
    {
        $codeOrContent = $codeOrContentPara;
        if(is_string($codeOrContent)) {
            $this->code = $codeOrContent;
            $this->wxAppID = '';
            $this->wxAppSecret = '';
            $this->wxLoginUrl = sprintf("https://api.weixin.qq.com/sns/jscode2session?" .
                "appid=%s&secret=%s&js_code=%s&grant_type=authorization_code", $this->wxAppID, $this->wxAppSecret, $this->code);
        }
        else if (is_array($codeOrContent)){
            $this->content = $codeOrContent;
        }
    }

    /**
     * @return array
     * generate token, check login status and registration
     */
    public function getUserTokenLogin($token){

        $result = $this->curl_get($this->wxLoginUrl);// $result is json format: openid, session_key, session_in
        $wxResult = json_decode($result,true); //$wxResult is array format, try to save in cache
        //xdebug_start_trace();
        //add an old token?
        $oldToken = $token;

        $tokenArr = array();
        $tokenStr = $this->grantToken($wxResult,$oldToken); // String

        $qauserid = $this->checkWXUserRecord($wxResult);

        if ($qauserid == null){
            $qauserid= $this->wxUserRegistration($wxResult);
        }

        $tokenArr ['token'] = $tokenStr;
        $tokenArr ['qauserid'] = $qauserid;

        return $tokenArr ;
        //xdebug_stop_trace();
    }


    /* error check
    public function get(){
        $result = $this->curl_get($this->wxLoginUrl);
        $wxResult = json_decode($result,true);
        if(empty($wxResult)){
            throw new Exception('session_key and openid error');
        }
        else{
            $loginFail = array_key_exists('errcode',$wxResult);
            if($loginFail){
                $this->processLoginError($wxResult);
            }
            else{
                $this->grantToken($wxResult);
            }
        }
    }
*/
    /**
     * @param $wxResult
     * @return null
     * @return $qauserid
     * check exsting wechat user record
     */
    private function checkWXUserRecord($wxResult) {
        $openid = $wxResult['openid'];
        $qauserid = null ;

        if ($openid != null) {

            $rows = qa_db_query_sub("SELECT openid, qauserid FROM qa_wxusers WHERE qa_wxusers.openid = '$openid';");
            while (($row = qa_db_read_one_assoc($rows, true)) !== null) {

                $openidFromDB = $row ['openid'];

                if ($openidFromDB == $openid) {
                    $qauserid = $row ['qauserid'];;
                }
                else {
                    $qauserid =null;
                }
            }
        }
        else{
            $qauserid = null;
        }

        return $qauserid;
    }


    /**
     * @param $wxResult
     * @return int|mixed
     * registration for wechat user and return userid
     */
    function wxUserRegistration($wxResult)
    {

        $qahandle = null;
        $newidforwxuser = null;

        $qauserid = $this->check_login($wxResult,$qahandle,$newidforwxuser);

                $idforwxuser = null;

        $openid = $wxResult['openid'];

        require_once QA_INCLUDE_DIR . 'util/string.php';

        qa_db_query_sub(
            'INSERT INTO qa_wxusers (idforwxuser,qauserid,openid) ' .
            'VALUES ($,$,$)',
            $idforwxuser, $qauserid, $openid
        );

        return $qauserid;
    }

    /**
     * @param $content
     * @return mixed|string
     * method to update qa_users and qa_wxusers table when wechat user authurized detail user into
     */
    function wxUserUpdateProfiles($content)

    {
        $nickName = $content['nickName'];
        $avatarUrl = $content['avatarUrl'];
        $gender = $content['gender'];
        $province = $content['province'];
        $city = $content['city'];
        $country = $content['country'];
        $token = $content['token'];

        $cachedJSON = $this->getWXCache($token);
        $cachedArray = json_decode($cachedJSON, true);


        $openid = $cachedArray['openid'];

        if ($openid != null) {

            qa_db_query_sub('UPDATE qa_wxusers SET nickname= $, avatarUrl=$, gender = $, province = $, city = $, country = $ WHERE openid = $', $nickName, $avatarUrl, $gender, $province, $city, $country, $openid);
        }

        $ret_val = array();
        $userInfoArr = array();
        array_push($ret_val, $userInfoArr);

        //to update handle in qauser table
        $qauserid = null;

        $rows = qa_db_query_sub('SELECT qauserid FROM qa_wxusers where openid = $', $openid);

        while (($row = qa_db_read_one_assoc($rows, true)) !== null) {
            $qauserid = intval($row ['qauserid']);
        }

        if ($qauserid != null && $nickName != null) {

            qa_db_query_sub('UPDATE ^users SET handle= $ WHERE userid = $', $nickName, $qauserid);
        }

        //to update avater in qauser table
        if ($qauserid != null && $avatarUrl != null) {
           require_once QA_INCLUDE_DIR.'app/users-edit.php';

            qa_set_user_avatar($qauserid, $avatarUrl);

        }

        return json_encode($ret_val, JSON_PRETTY_PRINT);
   }


    /**
     * @param $wxResult
     * @param $qahandle
     * @param $newidforwxuser
     * @return int|mixed
     * Method to login wxuser as a qauser
     */
    public function check_login($wxResult,$qahandle,$newidforwxuser)
    {


        require_once $this->directory . 'qa-connect-utils.php';


        $media = "wechat";
        $identifier = $wxResult['openid'];

        $user = array();
        $uid = $newidforwxuser;
        $email = null;
        $name = $qahandle;
        $location = null;
        $website = null;
        $about = 'user from wechat';
        $avatar = null;

        $user['uid'] = $uid;
        $user['email'] = $email;
        $user['name'] = $name;
        $user['location']= $location;
        $user['url'] = $website;
        $user['description']=$about;
        $user['avatar'] = $avatar;

        array_push($user);

        $fileds = array(
            'email' => @$user['email'],
            'handle' => @$user['name'],
            'confirmed' => true,
            'name' => @$user['name'],
            'location' => @$user['location'],
            'website' => @$user['url'],
            'about' => @$user['description'],
            'avatar' => null,
        );

        if (is_array($user)) {

            $duplicates = qa_log_in_external_user($media, $identifier,$fileds);

            return $duplicates;
        }

    }

    /**
     * @param $url
     * @param int $httpCode
     * @return mixed
     * method to parse wxLoginUrl and return resolved contents
     */
    public function curl_get($url, $httpCode = 0){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);

        //do not check SSL, true in Linux
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);
        $file_contents = curl_exec($ch);
        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $file_contents;
    }

    /**
     * @param $wxResult
     * @return string
     * grant token for user by it openid, save in cache as key-value pairs
     * return token in a sting
     */
    private function grantToken($wxResult,$oldToken){
        //openid for token
        xdebug_start_trace();
        $openid = $wxResult['openid'];
        //session_key for token
        $session_key = $wxResult['session_key'];

        $cachedValue = $this->prepareCachedValue($wxResult,$oldToken);
        $token = $this->saveToCache($cachedValue,$openid,$session_key);
        xdebug_stop_trace();
        return $token;

    }

    /**
     * @param $cachedValue
     * @param $openid
     * @param $session_key
     * @return string
     * save key,value pairs of token and session_key in mencache and return key
     */
    private function saveToCache($cachedValue,$openid,$session_key){

        $token = $cachedValue['old_token'];

        $cachedJSON = $this->getWXCache($token);

        $cachedArray = json_decode($cachedJSON,true);

        $sessionKeyFromCache = $cachedArray['session_key'];

        $sessionKeyFromPara= $session_key;

        if ( $sessionKeyFromCache == $sessionKeyFromPara){

            $key = $token;
        }
        else {

            $key = $this->generateToken($openid, $session_key);// no need to generate each time
            $value = json_encode($cachedValue);
            $result = $this->setWXCache($key, $value);
        }

        return $key;// this is the token to return

    }

    /**
     * @param $token
     * @return array|string
     * get wechat cache from mencache
     */
    private function getWXCache($token){
        $mem = new Memcache();
        $mem->addServer("127.0.0.1", 11211);
        $memKey = $token;
        $result = $mem->get($memKey);

        if ($result) {
            return $result;

        }

        else{
            return ''; //
        }
        /*
        else {
            echo "No matching key found yet. Let's start adding that now!";
            //$mem->set("blah", "I am data!  I am held in memcached!") or die("Couldn't save anything to memcached...");
            $mem->set($memKey, $memValue) or die("Couldn't save anything to memcached...");
        }
        */

    }

    /**
     * @param $paraKey
     * @param $paraValue
     * @return string
     * set wechat openid and session_key in memcache
     */
    private function setWXCache($paraKey,$paraValue)
    {
        $mem = new Memcache();
        $mem->addServer("127.0.0.1", 11211);

        $memKey = $paraKey;
        $memValue = $paraValue;

        $mem->set($memKey, $memValue);

        return '';

    }

    /**
     * @param $paraOpenid
     * @param $paraSession_key
     * @return string
     */
    public function generateToken($paraOpenid, $paraSession_key){
        $openid = $paraOpenid;
        $session_key = $paraSession_key;
        //salt
        $salt = $this->tokenSalt();
        return md5($openid.$session_key.$salt);
    }

    /**
     * @return string
     * salt for generating token
     */
    private function tokenSalt(){

        $token_salt = '';
        return $token_salt;
    }

    /**
     * @param $length
     * @return null|string
     * alternative method to generate token randomly from pool
     */
    private function getRandChar($length)
    {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;

        for ($i = 0;
             $i < $length;
             $i++) {
            $str .= $strPol[rand(0, $max)];
        }

        return $str;
    }

    /**
     * @param $wxResult
     * @return mixed
     * prepare cache value to save
     */
    private function prepareCachedValue($wxResult,$oldToken){
        $cachedValue = $wxResult;
        $cachedValue['old_token'] = $oldToken;

        return $cachedValue;

    }


    /**
     * @param $openid
     * @return mixed
     * method to get uesrid in qa_wxusers table
     */
    function getWXUserid ($openid){
        if($openid !=null){
            $rows= qa_db_query_sub('SELECT idforwxuser FROM qa_wxusers  WHERE openid = $',$openid);
        }
        while (($row = qa_db_read_one_assoc($rows, true)) !== null) {
            $idforwxuser = $row['idforwxuser'];
        }

        return $idforwxuser;
    }

    /**
     * @param $openid
     * @param $qauserid
     * @param $qahandle
     * @return null
     * method to update qa_wxusers table
     */
    function wxUserUpdateCore($openid,$qauserid,$qahandle){
        if($openid != null) {

            qa_db_query_sub('UPDATE qa_wxusers SET qauserid= $, qahandle=$ WHERE openid = $', $qauserid, $qahandle,$openid);
        }

        return null;
    }

    /**
     * @param $qahandle
     * @return int|null
     * method to get qauserid by handle
     */
    function getQAUserid($qahandle){
        $qauserid = null;

        $rows = qa_db_query_sub ( "SELECT ^users.userid, ^users.handle FROM ^users WHERE ^users.handle = $qahandle;" );

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
            $qauserid = intval($row ['userid']);
        }

        return $qauserid;
    }

    /**
     * @param $wxResult
     * @throws Exception
     */
    private function processLoginError($wxResult){
        throw new Exception(
            [
                'msg'=>'wechat server api requset fails ',
                'errorCode'=> '999'
            ]
        );
    }

}