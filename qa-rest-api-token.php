<?php

require_once QA_INCLUDE_DIR.'app/posts.php';

/**
 * Class qa_rest_api_token_page
 * To hanlde wechat uesr login and registration
 */

class qa_rest_api_token_page
{

    public $directory;
    public $urltoroot;

    /**
     * @param $directory
     * @param $urltoroot
     */
    public function load_module($directory, $urltoroot)
    {
        error_reporting(0);
        $this->directory = $directory;
        $this->urltoroot = $urltoroot;
    }

    /**
     * @param $request
     * @return bool
     * match request from wechat app
     */
    function match_request($request)
    {
        $parts = explode('/', $request);

        return $parts [0] == 'api' && $parts [1] == 'v1' && $parts [2] = 'wx'; //&& sizeof ( $parts ) > 1;
    }

    /**
     * @param $request
     * process requests by keywords
     */
    function process_request($request)
    {
        header('Content-Type: application/json');

        $parts = explode('/', $request);
        $resource = $parts [3];
        $id = null;
        $range = false;
        $from = null;
        $to = null;
        if (sizeof($parts) == 5) {
            if (is_numeric($parts [4]) && intval($parts [4]) > 0)
                $id = $parts [4];
            else
                $resource = 'invalid';
        }

        /*
         * Internal security (non for third-party applications)
         *
         * if (qa_user_permit_error ( 'plugin_rest_api_permit' )) {
            http_response_code ( 401 );

            $ret_val = array ();

            $json_object = array ();

            $json_object ['statuscode'] = '401';
            $json_object ['message'] = 'Unauthorized';
            $json_object ['details'] = 'The user is not authorized to use the API.';

            array_push ( $ret_val, $json_object );
            echo json_encode ( $ret_val, JSON_PRETTY_PRINT );

            return;
        } */

        $method = $_SERVER['REQUEST_METHOD'];

        switch ($resource) {

            case 'tokens' :
                if ($id == null) {
                    if (strcmp($method, 'POST') == 0) {
                        $inputJSON = file_get_contents('php://input');

                        $content = json_decode($inputJSON, TRUE);

                        echo $this->get_token($content);

                    } else {
                        echo $this->testSomething();
                    }
                } else
                    echo $this->testSomething();

                break;

            case 'wxusers' :
                if ($id == null) {
                    if (strcmp($method, 'POST') == 0) {
                        $inputJSON = file_get_contents('php://input');
                        $content = json_decode($inputJSON, TRUE);

                        echo $this->get_wx_user_info($content);

                    } else {
                            echo $this->testSomething();
                    }
                } else
                    echo $this->testSomething();

                break;

            case 'test' :
                echo $this->testSomething();
                break;

            default :
                http_response_code(400);

                $ret_val = array();

                $json_object = array();

                $json_object ['statuscode'] = '400';
                $json_object ['message'] = 'Bad Request';
                $json_object ['details'] = 'The request URI does not match the API in the system, or the operation failed for unknown reasons.';

                array_push($ret_val, $json_object);
                echo json_encode($ret_val, JSON_PRETTY_PRINT);
        }
    }


    /**
     * @param $content
     * @return mixed|string
     * get content from wechat app
     * exchange imformation with wechat server
     * login wechat user in q2a
     * return token to wechat app in json format
     */

    function get_token($content)  //token, string; tokenJSON, JSON
    {

        $code = $content['code'];
        $token = $content['token'];

        $userToken = new wx_user_token($code);// construct a wx_user_token obj

        $tokenArr = $userToken->getUserTokenLogin($token); //array

        $ret_val = array();

        array_push($ret_val, $tokenArr);

        return json_encode($ret_val, JSON_PRETTY_PRINT);

    }

    /**
     * @param $content
     * @return mixed|string
     * get wechat user info when authurized in wechat app
     * save in database table 'qa_wxusers'
     * return in json format
     */
    function get_wx_user_info($content){

        $userInfo = new wx_user_token($content);// construct a wx_user_token obj

        $ret_val = array();
        $userInfoArr = array();
        $userInfoArr['nickName'] = $content['nickName'];
        $userInfoArr['avatarUrl'] = $content['avatarUrl'];
        $userInfoArr['gender'] = $content['gender'];
        $userInfoArr['province'] = $content['province'];
        $userInfoArr['city'] = $content['city'];
        $userInfoArr['country'] = $content['country'];
        $userInfoArr['token'] = $content['token'];

        array_push($ret_val,$userInfoArr);

        $saveUserInfo = $userInfo->wxUserUpdateProfiles($content);

        return json_encode($ret_val, JSON_PRETTY_PRINT);
    }

    /**
     * @param $content
     * @return mixed|string
     * alternative method to create wechat user in q2a database
     * NOT calling really
     */
    function wx_db_user_create($content)

    {
        $idForWXUser = null;
        $nickName = $content['nickName'];
        $avatarUrl = $content['avatarUrl'];
        $gender = $content['gender'];
        $province= $content['province'];
        $city = $content['city'];
        $country = $content['country'];

        qa_db_query_sub(
            'INSERT INTO qa_wxusers (idforwxuser,nickname,avatarurl,gender,province,city,country) ' .
            'VALUES ($,$,$,$,$,$,$)',
            $idForWXUser, $nickName,$avatarUrl,$gender,$province,$city,$country
        );

        $ret_val = array();
        $userInfoArr = array();
        array_push($ret_val,$userInfoArr);

        return json_encode($ret_val, JSON_PRETTY_PRINT);
    }

    /**
     * @return mixed|string
     * place holder for testing
     */
    function testSomething(){
        $testArr = array();
        $ret_val = array();
        array_push($ret_val, $testArr);
        return json_encode($ret_val, JSON_PRETTY_PRINT);

    }
}