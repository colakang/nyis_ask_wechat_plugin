<?php

require_once QA_INCLUDE_DIR.'app/posts.php';

/**
 * Class qa_rest_api_response_page
 * To handle JSON output of requests
 */

class qa_rest_api_response_page {
	
	var $userslimit;
	var $questionslimit;
	var $answerslimit;
	var $categorieslimit;// by zijian
	var $tagslimit;//zz by zijian

    /**
     * @param $request
     * @return bool
     * match request links from wechat app
     */
	function match_request($request) {
		$parts = explode ( '/', $request );
		
		return $parts [0] == 'api' && $parts [1] == 'v1' && $parts [2] == 'q2a'; //&& sizeof ( $parts ) > 1;
	}

    /**
     * @param $request
     * process requests with different key words
     */
	function process_request($request) {
		header ( 'Content-Type: application/json' );
		
		$this->userslimit = qa_opt ( 'plugin_rest_api_max_users' );
		$this->questionslimit = qa_opt ( 'plugin_rest_api_max_questions' );
		$this->answerslimit = qa_opt ( 'plugin_rest_api_max_answers' );
		$this->commentslimit = qa_opt ('plugin_rest_api_max_comments');
		$this->categorieslimit = qa_opt('plugin_rest_api_max_categories');
		$this->tagslimit = qa_opt('plugin_rest_api_max_tags');
		
		$parts = explode ( '/', $request );
		$resource = $parts [3];
		$id = null;
		$range = false;
		$from = null;
		$to = null;
		if (sizeof ( $parts ) == 5) {
			if (is_numeric($parts [4]) && intval($parts [4]) > 0)
				$id = $parts [4];
			else 
				$resource = 'invalid';
		} else if (sizeof ( $parts ) == 7) {
			if (strcmp($parts [4], 'range') == 0 &&
				is_numeric($parts [5]) &&
				intval($parts [5]) > 0 &&
				is_numeric($parts [5]) &&
				intval($parts [6]) > 0 &&
				intval($parts [6]) > intval($parts [5])) {
					$range = true;
					$from = $parts [5];
					$to = $parts [6];
					if (strcmp($resource, 'users') != 0 &&
						strcmp($resource, 'questions') != 0 &&
						strcmp($resource, 'answers') != 0 &&
                        strcmp($resource, 'comments')!=0 )
						$resource = 'invalid';
						
			} else {
				$resource = 'invalid';
			}
		} else if (sizeof($parts) == 6 || sizeof($parts) > 7) {
			$resource = 'invalid';	
		}

		
		$method = $_SERVER['REQUEST_METHOD'];
		
		switch ($resource) {

            case 'mine' :
                if ($id != null) {
                    echo $this->get_my_list_json ( $id );
                }
                else
                    echo $this->placeholder();
                break;

            // an alternative method of news
            case 'news' :
                if ($id == null) {
                    echo $this->get_news ();
                }
                else
                    echo $this->placeholder();
                break;

            // calling method to get json about news,questions,answers and comments
			case 'questions' :
				if ($id == null) {
					if (strcmp($method, 'POST') == 0) {
						$inputJSON = file_get_contents('php://input');

						$content = json_decode( $inputJSON, TRUE );
						
						echo $this->post_question ($content);
					} else {
                            echo $this->get_questions_news_list_json();
					}
				} else
                    echo $this->get_question_json($id);
				break;
			
			case 'answers' :
				if ($id == null) {
					if (strcmp($method, 'POST') == 0) {
						$inputJSON = file_get_contents('php://input');
						$content = json_decode( $inputJSON, TRUE );
					
						echo $this->post_answer ($content);
					    }

				}
				else
					echo $this->get_answer ( $id );

				break;

            case 'comments' :
                if ($id == null) {
                    if (strcmp($method, 'POST') == 0) {
                        $inputJSON = file_get_contents('php://input');
                        $content = json_decode( $inputJSON, TRUE );

                        echo $this->post_comment ($content);
                    }

                }

                break;

            case 'search' :
                if ($id == null) {
                    if (strcmp($method, 'POST') == 0) {
                        $inputJSON = file_get_contents('php://input');

                        $content = json_decode( $inputJSON, TRUE );

                        echo $this->post_search ($content);
                    }

                }

                break;

            case 'users' :
                if ($id == null) {

                    echo $this->get_users ();
                } else {
                    echo $this->get_user ( $id );
                }
                break;

			case 'categories' :
				if ($id == null)
					echo $this->get_categories ();
				else
					echo $this->get_category ( $id );
				break;
			
			case 'tags' :
				if ($id == null)
					echo $this->get_tags ();
				else
					echo $this->get_tag ( $id );
				break;
			
			default :
				http_response_code ( 400 );
				
				$ret_val = array ();
				
				$json_object = array ();
				
				$json_object ['statuscode'] = '400';
				$json_object ['message'] = 'Bad Request';
				$json_object ['details'] = 'The request URI does not match the API in the system, or the operation failed for unknown reasons.';
				
				array_push ( $ret_val, $json_object );
				echo json_encode ( $ret_val, JSON_PRETTY_PRINT );
		}
	}

    /**
     * @return mixed|string
     * API that return a list of all users in json format
     */

	function get_users() {
		$rows = qa_db_query_sub ( "SELECT ^users.userid, ^users.handle, ^userpoints.points, ^userpoints.qposts, ^userpoints.aposts, ^userpoints.cposts FROM ^users INNER JOIN ^userpoints ON ^users.userid=^userpoints.userid;" );
		
		$ret_val = array ();
		
		while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
			$json_object = array ();
			
			$json_object ['userid'] = intval ( $row ['userid'] );
			$json_object ['handle'] = $row ['handle'];
			$json_object ['points'] = intval ( $row ['points'] );
			$json_object ['qcount'] = intval ( $row ['qposts'] );
			$json_object ['acount'] = intval ( $row ['aposts'] );
			$json_object ['ccount'] = intval ( $row ['cposts'] );
			
			array_push ( $ret_val, $json_object );
		}
		
		if ($this->userslimit == 0) { // Maximum number of users in response is 10
			if (count ( $ret_val ) > 10) {
				$random_keys = array_rand ( $ret_val, 10 );
				
				$random_rows = array ();
				
				for($i = 0; $i < count ( $random_keys ); ++ $i)
					array_push ( $random_rows, $ret_val [$random_keys [$i]] );
				
				$ret_val = $random_rows;
			}
		} else if ($this->userslimit == 1) { // Maximum number of users in response is 100
			if (count ( $ret_val ) > 100) {
				$random_keys = array_rand ( $ret_val, 100 );
				
				$random_rows = array ();
				
				for($i = 0; $i < count ( $random_keys ); ++ $i)
					array_push ( $random_rows, $ret_val [$random_keys [$i]] );
				
				$ret_val = $random_rows;
			}
		}
		
		if ($ret_val == null) {
			http_response_code ( 404 );
			
			$json_object = array ();
			
			$json_object ['statuscode'] = '404';
			$json_object ['message'] = 'Not found';
			$json_object ['details'] = 'The requested resource was not found.';
			
			array_push ( $ret_val, $json_object );
		} else
			http_response_code ( 200 );
		
		return json_encode ( $ret_val, JSON_PRETTY_PRINT );
	}

    /**
     * @param $userid
     * @return mixed|string
     * API that return a user's info in json format
     */
	function get_user($userid) {
		$rows = qa_db_query_sub ( "SELECT ^users.userid, ^users.handle, ^userpoints.points, ^userpoints.qposts, ^userpoints.aposts, ^userpoints.cposts FROM ^users INNER JOIN ^userpoints ON ^users.userid=^userpoints.userid WHERE ^users.userid=$userid;" );
		
		$ret_val = array ();
		
		while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
			$json_object = array ();
			
			$json_object ['userid'] = intval ( $row ['userid'] );
			$json_object ['handle'] = $row ['handle'];
			$json_object ['points'] = intval ( $row ['points'] );
			$json_object ['qcount'] = intval ( $row ['qposts'] );
			$json_object ['acount'] = intval ( $row ['aposts'] );
            $json_object ['ccount'] = intval ( $row ['cposts'] );
			array_push ( $ret_val, $json_object );
		}
		
		if ($ret_val == null) {
			http_response_code ( 404 );
			
			$json_object = array ();
			
			$json_object ['statuscode'] = '404';
			$json_object ['message'] = 'Not found';
			$json_object ['details'] = 'The requested resource was not found.';
			
			array_push ( $ret_val, $json_object );
		} else
			http_response_code ( 200 );
		
		return json_encode ( $ret_val, JSON_PRETTY_PRINT );
	}

    /**
     * @return mixed|string
     * API that return all news in a list of 5 in json format
     */
    function get_news() {
        $rowsQuestion= qa_db_query_sub ( "SELECT postid, title, content, categoryid, tags, userid, created, acount FROM ^posts WHERE type='NEWS' ORDER BY created DESC LIMIT 5;" );

        $ret_val = array ();

        while ( ($rowQ = qa_db_read_one_assoc ( $rowsQuestion, true )) !== null ) {


            $json_object = array ();

            $json_object ['question_id'] = intval ( $rowQ ['postid'] );
            $json_object ['question_title'] = $rowQ ['title'];
            $json_object ['question_content'] = $rowQ ['content'];
            $json_object ['quetion_categoryid'] = intval ( $rowQ ['categoryid'] );
            $tags = explode ( ',', $rowQ ['tags'] );
            $json_object ['question_tags'] = $tags;
            $json_object ['question_userid'] = intval ( $rowQ ['userid'] );
            $json_object ['question_creation_date'] = $rowQ ['created'];
            $json_object ['question_answer_count'] = intval ( $rowQ ['acount'] );

            array_push ( $ret_val, $json_object );

        }

        if ($this->questionslimit == 0) { // Maximum number of questions in response is 10
            if (count ( $ret_val ) > 10) {
                $random_keys = array_rand ( $ret_val, 10 );

                $random_rows = array ();

                for($i = 0; $i < count ( $random_keys ); ++ $i)
                    array_push ( $random_rows, $ret_val [$random_keys [$i]] );

                $ret_val = $random_rows;
            }
        } else if ($this->questionslimit == 1) { // Maximum number of questions in response is 100
            if (count ( $ret_val ) > 100) {
                $random_keys = array_rand ( $ret_val, 100 );

                $random_rows = array ();

                for($i = 0; $i < count ( $random_keys ); ++ $i)
                    array_push ( $random_rows, $ret_val [$random_keys [$i]] );

                $ret_val = $random_rows;
            }
        }

        if ($ret_val == null) {
            http_response_code ( 404 );

            $json_object = array ();

            $json_object ['statuscode'] = '404';
            $json_object ['message'] = 'Not found';
            $json_object ['details'] = 'The requested resource was not found.';

            array_push ( $ret_val, $json_object );
        } else
            http_response_code ( 200 );

        return json_encode ( $ret_val, JSON_PRETTY_PRINT );
    }



    /**
     * @return mixed|string
     * API to get a list of questions and also news, in json format
     */

    function get_questions_news_list_json() {

        $ret_val = array ();

        $question_object = $this->get_questions_list_obj_arr();
        $news_object = $this->get_news_list_obj_arr();


        array_push ( $ret_val, $question_object, $news_object);


        if ($ret_val == null) {
            http_response_code ( 404 );

            $json_object = array ();

            $json_object ['statuscode'] = '404';
            $json_object ['message'] = 'Not found';
            $json_object ['details'] = 'The requested resource was not found.';

            array_push ( $ret_val, $json_object );
        } else
            http_response_code ( 200 );

        return json_encode ( $ret_val, JSON_PRETTY_PRINT );
    }


    /**
     * @param $questionid
     * @return mixed|string
     * API to get details, such as question, answers, comments of a specific question in json format
     */
    function get_question_json ($questionid) {

        $ret_val = array ();

        $question_object = $this->get_question_obj_arr($questionid);
        $answer_object = $this->get_answer_obj_arr($questionid);
        $comment_object = $this->get_comment_of_question_obj_arr($questionid);
        $comment_of_answer_object = $this->get_comment_of_answer_obj_arr($questionid);


        $news_object = $this->get_news_obj_arr($questionid);

        array_push ( $ret_val, $question_object, $answer_object, $comment_object, $comment_of_answer_object, $news_object);


        if ($ret_val == null) {
            http_response_code ( 404 );

            $json_object = array ();

            $json_object ['statuscode'] = '404';
            $json_object ['message'] = 'Not found';
            $json_object ['details'] = 'The requested resource was not found.';

            array_push ( $ret_val, $json_object );
        } else
            http_response_code ( 200 );

        return json_encode ( $ret_val, JSON_PRETTY_PRINT );
    }

    /**
     * @param $userid
     * @return mixed|string
     * API to get details, such as questions, answers, comments and wxuserinfo of a specific user, in json format
     */

    function get_my_list_json ($userid) {

        $ret_val = array ();
        //for arrays
        $question_object = $this->get_my_question_obj_arr($userid);
        $answer_object = $this->get_my_answer_obj_arr($userid);
        $comment_object = $this->get_my_comment_obj_arr($userid);
        $comment_of_answer_object = $this->get_my_comment_of_question_obj_arr($userid);
        $wxuser_object = $this->get_my_wxuser_info_obj_arr($userid);

        array_push ( $ret_val, $question_object, $answer_object, $comment_object, $comment_of_answer_object, $wxuser_object);
        // }

        if ($ret_val == null) {
            http_response_code ( 404 );

            $json_object = array ();

            $json_object ['statuscode'] = '404';
            $json_object ['message'] = 'Not found';
            $json_object ['details'] = 'The requested resource was not found.';

            array_push ( $ret_val, $json_object );
        } else
            http_response_code ( 200 );

        return json_encode ( $ret_val, JSON_PRETTY_PRINT );
    }


    /**
     * @param $answerid
     * @return mixed|string
     * get details of a specific answer directly, in json format
     */
	function get_answer($answerid) {
		$rows = qa_db_query_sub ( "SELECT postid, parentid, content FROM ^posts WHERE type='A' && postid=$answerid;" );

		$ret_val = array ();
		

		while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {
			$questionid0fAnswer = intval ( $row ['parentid'] ); 

			$rowsQuestion = qa_db_query_sub ( "SELECT postid, title, content, categoryid, tags, userid, created, acount FROM ^posts WHERE type='Q' && postid=$questionid0fAnswer;" );

			while ( ($rowQ = qa_db_read_one_assoc ( $rowsQuestion, true )) !== null) {

				$json_object = array ();
				$questionObject = array();
				$answerObjetct = array();
				$commentObject = array();
			
				$json_object ['answer_id'] = intval ( $row ['postid'] );
				$json_object ['question_id'] = intval ( $row ['parentid'] );
				$json_object ['answer_content'] = $row ['content'];
				$json_object ['question_title'] = $rowQ ['title'];
				$json_object ['question_content'] = $rowQ ['content'];

                $testString = "testString";


                $json_object ['TESING'] = "testing";

                array_push ( $ret_val, $json_object,$testString,$questionObject,$answerObjetct,$commentObject );//array push 3 objests
			}
		}
		
		if ($ret_val == null) {
			http_response_code ( 404 );
			
			$json_object = array ();
			
			$json_object ['statuscode'] = '404';
			$json_object ['message'] = 'Not found';
			$json_object ['details'] = 'The requested resource was not found.';
			
			array_push ( $ret_val, $json_object );
		} else
			http_response_code ( 200 );
		
		return json_encode ( $ret_val, JSON_PRETTY_PRINT );
	}

    /**
     * @param $questionid
     * @return array
     * a helper function to return a questions detials in a array
     */
    function get_question_obj_arr($questionid) {

        $rows = qa_db_query_sub ( "SELECT postid, title, content, ^posts.created, handle,notify, ^posts.userid, ^users.avatarblobid FROM ^posts left outer JOIN ^users ON ^posts.userid=^users.userid WHERE type='Q' && postid=$questionid;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {

            if($row ['handle'] == null){
                $row ['handle'] = 'guest';
            }

            $avatarul = null;

            if($row ['avatarblobid'] != null){
                $avatarul = "http://ask.nyis.com/?qa=image&qa_blobid=".$row ['avatarblobid']."&qa_size=40";
            }

            else{
                $avatarul = "http://ask.nyis.com/?qa=image&qa_blobid=11951552689474032586&qa_size=40";

            }

            $questionObject = array();

            $questionObject ['question_id'] = intval ( $row ['postid'] );
            $questionObject ['question_title'] =  $row ['title'];
            $questionObject ['question_content'] = $row ['content'];
            $questionObject ['question_creation_date'] = $row ['created'];
            $questionObject ['question_handle'] =  $row ['handle'];
            $questionObject ['question_notify'] =  $row ['notify'];
            $questionObject ['question_userid'] =  $row ['userid'];
            $questionObject ['question_blobid'] =  $row ['avatarblobid'];
            $questionObject ['question_avatarurl'] =  $avatarul;


            array_push ($ret_val,$questionObject);
        }

        return $ret_val;
    }

    /**
     * @param $questionid
     * @return array
     * a helper function to return answers of a question in an array
     */
    function get_answer_obj_arr($questionid) {

        $rows = qa_db_query_sub ( "SELECT postid, parentid, content, handle,^posts.created, notify, ^posts.userid, ^users.avatarblobid FROM ^posts left outer JOIN ^users ON ^posts.userid=^users.userid WHERE type='A' && parentid=$questionid;" );

        $ret_val = array ();

            while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {

                if($row ['handle'] == null){
                    $row ['handle'] = 'guest';
                }

                $avatarul = null;

                if($row ['avatarblobid'] != null){
                    $avatarul = "http://ask.nyis.com/?qa=image&qa_blobid=".$row ['avatarblobid']."&qa_size=40";
                }
                else{
                    $avatarul = "http://ask.nyis.com/?qa=image&qa_blobid=11951552689474032586&qa_size=40";

                }

                $answerObject = array();

                $answerObject ['answer_id'] = intval ( $row ['postid'] );
                $answerObject ['question_id'] = intval ( $row ['parentid'] );
                $answerObject ['answer_content'] = $row ['content'];
                $answerObject ['answer_handle'] = $row ['handle'];
                $answerObject ['answer_creation_date'] = $row ['created'];
                $answerObject ['answer_notify'] =  $row ['notify'];
                $answerObject ['answer_userid'] =  $row ['userid'];
                $answerObject ['answer_blobid'] =  $row ['avatarblobid'];
                $answerObject ['answer_avatarurl'] =  $avatarul;


                array_push ($ret_val,$answerObject);
            }

        return $ret_val;
    }

    /**
     * @param $questionid
     * @return array
     * a helper function to return comments of a question in an array
     */
    function get_comment_of_question_obj_arr($questionid) { //changed from answerid form questionid
        $rows = qa_db_query_sub ( "SELECT postid, parentid, content, handle,^posts.created, notify, ^posts.userid, ^users.avatarblobid FROM ^posts left outer JOIN ^users ON ^posts.userid=^users.userid WHERE type='C' && parentid=$questionid;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {

            if($row ['handle'] == null){
                $row ['handle'] = 'guest';
            }

            $avatarul = null;

            if($row ['avatarblobid'] != null){
                $avatarul = "http://ask.nyis.com/?qa=image&qa_blobid=".$row ['avatarblobid']."&qa_size=40";
            }
            else{
                $avatarul = "http://ask.nyis.com/?qa=image&qa_blobid=11951552689474032586&qa_size=40";

            }

            $commentObject = array();

            $commentObject ['comment_id'] = intval ( $row ['postid'] );
            $commentObject ['question_id'] = intval ( $row ['parentid'] );
            $commentObject ['comment_content'] = $row ['content'];
            $commentObject ['comment_handle'] = $row ['handle'];
            $commentObject ['comment_creation_date'] = $row ['created'];
            $commentObject ['comment_notify'] =  $row ['notify'];
            $commentObject ['comment_userid'] =  $row ['userid'];
            $commentObject ['comment_blobid'] =  $row ['avatarblobid'];
            $commentObject ['comment_avatarurl'] =  $avatarul;

            array_push ($ret_val,$commentObject);
        }

        return $ret_val;
    }

    /**
     * @param $questionid
     * @return array
     * a helper function to return comment of a answers in an array
     */
    function get_comment_of_answer_obj_arr($questionid) { //changed from answerid form questionid
        $rows = qa_db_query_sub ( "SELECT postid, parentid FROM ^posts WHERE type='A' && parentid=$questionid;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {
            $questionid0fAnswer = intval ( $row ['postid'] );

            $rowsOfComment = qa_db_query_sub ( "SELECT postid, parentid, content, handle,^posts.created, notify, ^posts.userid, ^users.avatarblobid FROM ^posts left outer JOIN ^users ON ^posts.userid=^users.userid  WHERE type='C' && parentid=$questionid0fAnswer;" );

            while ( ($rowCMT = qa_db_read_one_assoc ( $rowsOfComment, true )) !== null) {


                if($rowCMT['handle'] == null){
                    $rowCMT['handle'] = 'guest';
                }

                $avatarul = null;

                if($rowCMT ['avatarblobid'] != null){
                    $avatarul = "http://ask.nyis.com/?qa=image&qa_blobid=".$rowCMT ['avatarblobid']."&qa_size=40";
                }
                else{
                    $avatarul = "http://ask.nyis.com/?qa=image&qa_blobid=11951552689474032586&qa_size=40";

                }

                $commentObject = array();

                $commentObject ['comment_id'] = intval($rowCMT['postid']);
                $commentObject ['answer_id'] = intval($rowCMT ['parentid']);
                $commentObject ['comment_content'] = $rowCMT ['content'];
                $commentObject ['comment_handle'] = $rowCMT ['handle'];
                $commentObject ['comment_creation_date'] = $rowCMT ['created'];
                $commentObject ['comment_notify'] =  $rowCMT ['notify'];
                $commentObject ['comment_userid'] =  $rowCMT ['userid'];
                $commentObject ['comment_userid'] =  $rowCMT ['userid'];
                $commentObject ['comment_blobid'] =  $rowCMT ['avatarblobid'];
                $commentObject ['comment_avatarurl'] =  $avatarul;

                array_push($ret_val, $commentObject);

            }
        }

        return $ret_val;
    }

    /**
     * @param $questionid
     * @return array
     * a helper function to return news in an array
     */

    function get_news_obj_arr($questionid) {
        $rows = qa_db_query_sub ( "SELECT postid, title, content, categoryid, tags, userid, created, acount FROM ^posts WHERE type='NEWS' && postid=$questionid;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {

            $questionObject = array();

            $questionObject ['question_id'] = intval ( $row ['postid'] );
            $questionObject ['question_title'] =  $row ['title'];
            $questionObject ['question_content'] = $row ['content'];
            $questionObject ['quetion_categoryid'] = intval ( $row ['categoryid'] );
            $tags = explode ( ',', $row ['tags'] );
            $questionObject ['question_tags'] = $tags;
            $questionObject ['question_userid'] = intval ( $row ['userid'] );
            $questionObject ['question_creation_date'] = $row ['created'];
            $questionObject ['question_answer_count'] = intval ( $row ['acount'] );

            array_push ($ret_val,$questionObject);
        }

        return $ret_val;
    }

    /**
     * @param $userid
     * @return array
     * a helper function to return a questions list for a specific user
     */
    function get_my_question_obj_arr($userid) {
        $rows = qa_db_query_sub ( "SELECT postid, title, content, categoryid, tags, userid, created, acount FROM ^posts WHERE type='Q' && userid=$userid ORDER BY created DESC;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {

            $questionObjetct = array();

            $questionObjetct ['question_id'] = intval ( $row ['postid'] );
            $questionObjetct ['question_title'] =  $row ['title'];
            $questionObjetct ['question_content'] = $row ['content'];
            $questionObjetct ['quetion_categoryid'] = intval ( $row ['categoryid'] );
            $tags = explode ( ',', $row ['tags'] );
            $questionObjetct ['question_tags'] = $tags;
            $questionObjetct ['question_userid'] = intval ( $row ['userid'] );
            $questionObjetct ['question_creation_date'] = $row ['created'];
            $questionObjetct ['question_answer_count'] = intval ( $row ['acount'] );

            array_push ($ret_val,$questionObjetct);
        }

        return $ret_val;
    }

    /**
     * @param $userid
     * @return array
     * a helper function to return a answers list for a specific user
     */
    function get_my_answer_obj_arr($userid) {
        $rows = qa_db_query_sub ( "SELECT postid, parentid, content, userid, created FROM ^posts WHERE type='A' && userid=$userid;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {

            $answerObjetct = array();

            $answerObjetct ['answer_id'] = intval ( $row ['postid'] );
            $answerObjetct ['question_id'] = intval ( $row ['parentid'] );
            $answerObjetct ['answer_content'] = $row ['content'];
            $answerObjetct ['answer_creation_date'] = $row['created'];

            array_push ($ret_val,$answerObjetct);
        }

        return $ret_val;
    }

    /**
     * @param $userid
     * @return array
     * a helper function to return a comments list for a specific user
     */
    function get_my_comment_obj_arr($userid) { //changed from answerid form questionid
        $rows = qa_db_query_sub ( "SELECT postid, parentid, content, userid, created FROM ^posts WHERE type='C' && userid=$userid;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {

            $commentObjetct = array();

            $commentObjetct ['comment_id'] = intval ( $row ['postid'] );
            $commentObjetct ['question_id'] = intval ( $row ['parentid'] );
            $commentObjetct ['comment_content'] = $row ['content'];
            $commentObjetct ['comment_creation_date'] = $row['created'];

            array_push ($ret_val,$commentObjetct);
        }

        return $ret_val;
    }

    /**
     * @param $userid
     * @return array
     * a helper function to return a comments of questions list for a specific user
     */
    function get_my_comment_of_question_obj_arr($userid) {
        $rows = qa_db_query_sub ( "SELECT postid, parentid, content, userid, created FROM ^posts WHERE type='A' && userid=$userid;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {
            $questionid0fAnswer = intval ( $row ['postid'] );

            $rowsOfComment = qa_db_query_sub ( "SELECT postid, parentid, content, userid, created FROM ^posts WHERE type='C' && parentid=$questionid0fAnswer;" );

            while ( ($rowCMT = qa_db_read_one_assoc ( $rowsOfComment, true )) !== null) {

                $commentObjetct = array();

                $commentObjetct ['comment_id'] = intval($rowCMT ['postid']);
                $commentObjetct ['answer_id'] = intval($rowCMT ['parentid']);
                $commentObjetct ['comment_content'] = $rowCMT ['content'];
                $commentObjetct ['comment_creation_date'] = $rowCMT['created'];

                array_push($ret_val, $commentObjetct);

            }
        }

        return $ret_val;
    }

    /**
     * @param $userid
     * @return array
     * a helper function to return a wechat user info list for a specific user
     */
    function get_my_wxuser_info_obj_arr($userid){
        $rows = qa_db_query_sub('SELECT nickname, avatarurl FROM qa_wxusers where qauserid = $', $userid );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
            $userObj = array();
            if($row ['nickname'] != null){
                $userObj['nickname'] = $row ['nickname'];
            }
            else{
                $userObj['nickname'] = "wechat user";
            }

            if($row ['avatarurl'] != null){
                $userObj['avatarurl'] = $row['avatarurl'];
            }
            //default image in ask
            else{
                $userObj['avatarurl'] = "http://ask.nyis.com/?qa=image&qa_blobid=11951552689474032586&qa_size=120";
            }

            array_push($ret_val, $userObj);

        }

        return $ret_val;
    }

    /**
     * @return array
     * an helper function to get a list questions
     */
    function get_questions_list_obj_arr() {
        $rows = qa_db_query_sub ( "SELECT postid, title, content, categoryid, tags, userid, created, acount FROM ^posts WHERE type='Q' ORDER BY created DESC LIMIT 10;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {

            $questionObjetct = array();

            $questionObjetct ['question_id'] = intval ( $row ['postid'] );
            $questionObjetct ['question_title'] =  $row ['title'];
            $questionObjetct ['question_content'] = $row ['content'];
            $questionObjetct ['quetion_categoryid'] = intval ( $row ['categoryid'] );
            $tags = explode ( ',', $row ['tags'] );
            $questionObjetct ['question_tags'] = $tags;
            $questionObjetct ['question_userid'] = intval ( $row ['userid'] );
            $questionObjetct ['question_creation_date'] = $row ['created'];
            $questionObjetct ['question_answer_count'] = intval ( $row ['acount'] );

            array_push ($ret_val,$questionObjetct);
        }

        return $ret_val;
    }

    /**
     * @return array
     * an helper function to get a list of news as questions, a limit output of 5
     */
    function get_news_list_obj_arr() {
        $rows = qa_db_query_sub ( "SELECT postid, title, content, categoryid, tags, userid, created, acount FROM ^posts WHERE type='NEWS' ORDER BY created DESC LIMIT 5;" );

        $ret_val = array ();
        $static_image_url= "http://new_image_url";

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null) {

            $questionObjetct = array();

            $questionObjetct ['question_id'] = intval ( $row ['postid'] );
            $questionObjetct ['question_title'] =  $row ['title'];
            $questionObjetct ['question_content'] = $row ['content'];
//            $questionObjetct ['question_url'] = preg_replace('/https?:\/\/[^ ]+?(?:\.jpg|\.png|\.gif)/',$static_image_url,$row ['content']);
            preg_match_all('/src="([^\s"]+)/', $row ['content'], $matches);
            $questionObjetct ['question_url'] = $matches[1];
            $questionObjetct ['quetion_categoryid'] = intval ( $row ['categoryid'] );
            $tags = explode ( ',', $row ['tags'] );
            $questionObjetct ['question_tags'] = $tags;
            $questionObjetct ['question_userid'] = intval ( $row ['userid'] );
            $questionObjetct ['question_creation_date'] = $row ['created'];
            $questionObjetct ['question_answer_count'] = intval ( $row ['acount'] );

            array_push ($ret_val,$questionObjetct);
        }

        return $ret_val;
    }

    /**
     * @param $content
     * @return mixed|string
     * API to POST a new question then return in json format
     */
    function post_question( $content ) {
        require_once QA_INCLUDE_DIR.'qa-app-posts.php';
        require_once QA_INCLUDE_DIR.'qa-app-post-create.php';

        $id = qa_post_create('Q', null, $content['title'], $content['content'], 'html', $content['categoryid'], $content['tags'], $content['userid']);

        $rows = qa_db_query_sub ( "SELECT postid, title, content, categoryid, tags, userid, created, acount FROM ^posts WHERE type='Q' && postid=$id;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
            $json_object = array ();

            $json_object ['questionid'] = intval ( $row ['postid'] );
            $json_object ['title'] = $row ['title'];
            $json_object ['content'] = $row ['content'];
            $json_object ['categoryid'] = intval ( $row ['categoryid'] );
            $tags = explode ( ',', $row ['tags'] );
            $json_object ['tags'] = $tags;
            $json_object ['userid'] = intval ( $row ['userid'] );
            $json_object ['creationdate'] = $row ['created'];
            $json_object ['acount'] = intval ( $row ['acount'] );

            array_push ( $ret_val, $json_object );
        }

        if ($ret_val == null) {
            http_response_code ( 404 );

            $json_object = array ();

            $json_object ['statuscode'] = '404';
            $json_object ['message'] = 'Not found';
            $json_object ['details'] = 'The requested resource was not found.';

            array_push ( $ret_val, $json_object );
        } else
            http_response_code ( 200 );

        return json_encode ( $ret_val, JSON_PRETTY_PRINT );
    }

    /**
     * @param $content
     * @return mixed|string
     * API to POST a new answer then return in json format
     */
	function post_answer( $content ) {
		require_once QA_INCLUDE_DIR.'qa-app-posts.php';
		require_once QA_INCLUDE_DIR.'qa-app-post-create.php';
	
		$id = qa_post_create('A', $content['questionid'], null, $content['content'], 'html', null, null, $content['userid']);
	
		$rows = qa_db_query_sub ( "SELECT postid, parentid, content FROM ^posts WHERE type='A' && postid=$id;" );
		
		$ret_val = array ();
		
		while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
			$json_object = array ();
				
			$json_object ['answerid'] = intval ( $row ['postid'] );
			$json_object ['questionid'] = intval ( $row ['parentid'] );
			$json_object ['content'] = $row ['content'];
				
			array_push ( $ret_val, $json_object );
		}
		
		if ($ret_val == null) {
			http_response_code ( 404 );
				
			$json_object = array ();
				
			$json_object ['statuscode'] = '404';
			$json_object ['message'] = 'Not found';
			$json_object ['details'] = 'The requested resource was not found.';
				
			array_push ( $ret_val, $json_object );
		} else
			http_response_code ( 200 );
		
		return json_encode ( $ret_val, JSON_PRETTY_PRINT );
	}

    /**
     * @param $content
     * @return mixed|string
     * API to POST a new comment then return in json format
     */
    function post_comment( $content ) {
        require_once QA_INCLUDE_DIR.'qa-app-posts.php';
        require_once QA_INCLUDE_DIR.'qa-app-post-create.php';

        $id = qa_post_create('C', $content['parentid'], null, $content['content'], 'html', null, null);

        $rows = qa_db_query_sub ( "SELECT postid, parentid, content FROM ^posts WHERE type='C' && postid=$id;" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
            $json_object = array ();

            $json_object ['commentid'] = intval ( $row ['postid'] );
            $json_object ['parentid'] = intval ( $row ['parentid'] );
            $json_object ['content'] = $row ['content'];

            array_push ( $ret_val, $json_object );
        }

        if ($ret_val == null) {
            http_response_code ( 404 );

            $json_object = array ();

            $json_object ['statuscode'] = '404';
            $json_object ['message'] = 'Not found';
            $json_object ['details'] = 'The requested resource was not found.';

            array_push ( $ret_val, $json_object );
        } else
            http_response_code ( 200 );

        return json_encode ( $ret_val, JSON_PRETTY_PRINT );
    }

    /**
     * @param $content
     * @return mixed|string
     * API for searching function, return in json format
     */
    function post_search( $content ) {

        require_once QA_INCLUDE_DIR.'app/search.php';

        $ret_val = array ();

        $query = $content['query'];
        $page = $content['page'];
        $start = $page * 10;
        $count = 10;

        $results=qa_get_search_results($query, $start, $count, null, true, 20);


        foreach ($results as $result){
            $obj = array();

            $obj['question_creation_date'] =date("Y-m-d H:i:s",time($result['question']['created']));

            $obj['question_postid'] = $result['question_postid'];
            $obj['title'] = $result['title'];
            $obj['url'] = $result['url'];
            $obj['views'] = $result['question']['views'];
            $obj['created_stamp'] = $result['question']['created'];

            array_push($ret_val, $obj);
        }


        if ($ret_val == null) {
            http_response_code ( 404 );

            $json_object = array ();

            $json_object ['statuscode'] = '404';
            $json_object ['message'] = 'Not found';
            $json_object ['details'] = 'The requested resource was not found.';

            array_push ( $ret_val, $json_object );
        } else
            http_response_code ( 200 );

        return json_encode ( $ret_val, JSON_PRETTY_PRINT );
    }


    /**
     * @return mixed|string
     * API to get all categories, return in json format
     */
	function get_categories() {
		$rows = qa_db_query_sub ( "SELECT categoryid, title, qcount FROM ^categories;" );
		
		$ret_val = array ();
		
		while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
			$json_object = array ();
			
			$json_object ['categoryid'] = intval ( $row ['categoryid'] );
			$json_object ['title'] = $row ['title'];
			$json_object ['qcount'] = intval ( $row ['qcount'] );
			
			array_push ( $ret_val, $json_object );
		}
		
		if ($ret_val == null) {
			http_response_code ( 404 );
			
			$json_object = array ();
			
			$json_object ['statuscode'] = '404';
			$json_object ['message'] = 'Not found';
			$json_object ['details'] = 'The requested resource was not found.';
			
			array_push ( $ret_val, $json_object );
		} else
			http_response_code ( 200 );
		
		return json_encode ( $ret_val, JSON_PRETTY_PRINT );
	}

    /**
     * @param $categoryid
     * @return mixed|string
     * API to get a specific category by id
     */
	function get_category($categoryid) {
		$rows = qa_db_query_sub ( "SELECT categoryid, title, qcount FROM ^categories WHERE categoryid=$categoryid;" );
		
		$ret_val = array ();
		
		while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
			$json_object = array ();
			
			$json_object ['categoryid'] = intval ( $row ['categoryid'] );
			$json_object ['title'] = $row ['title'];
			$json_object ['qcount'] = intval ( $row ['qcount'] );
			
			array_push ( $ret_val, $json_object );
		}
		
		if ($ret_val == null) {
			http_response_code ( 404 );
			
			$json_object = array ();
			
			$json_object ['statuscode'] = '404';
			$json_object ['message'] = 'Not found';
			$json_object ['details'] = 'The requested resource was not found.';
			
			array_push ( $ret_val, $json_object );
		} else
			http_response_code ( 200 );
		
		return json_encode ( $ret_val, JSON_PRETTY_PRINT );
	}

    /**
     * @return mixed|string
     * API to get all tags
     */
	function get_tags() {
		$rows = qa_db_query_sub ( "SELECT distinct ^words.wordid, ^words.word, ^words.tagcount FROM ^words INNER JOIN ^posttags ON ^words.wordid=^posttags.wordid Order by ^words.tagcount DESC Limit 4;" );
		
		$ret_val = array ();
		
		while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
			$json_object = array ();
			
			$json_object ['tagid'] = intval ( $row ['wordid'] );
			$json_object ['title'] = $row ['word'];
			$json_object ['tagcount'] = intval ( $row ['tagcount'] );
			
			array_push ( $ret_val, $json_object );
		}
		
		if ($ret_val == null) {
			http_response_code ( 404 );
			
			$json_object = array ();
			
			$json_object ['statuscode'] = '404';
			$json_object ['message'] = 'Not found';
			$json_object ['details'] = 'The requested resource was not found.';
			
			array_push ( $ret_val, $json_object );
		} else
			http_response_code ( 200 );
		
		return json_encode ( $ret_val, JSON_PRETTY_PRINT );
	}

    /**
     * @param $tagid
     * @return mixed|string
     * API to get a specific tag
     */
	function get_tag($tagid) {
		$rows = qa_db_query_sub ( "SELECT ^words.wordid, ^words.word, ^words.tagcount FROM ^words INNER JOIN ^posttags ON ^words.wordid=^posttags.wordid WHERE ^words.wordid=$tagid;" );
		
		$ret_val = array ();
		
		while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
			$json_object = array ();
			
			$json_object ['tagid'] = intval ( $row ['wordid'] );
			$json_object ['title'] = $row ['word'];
			$json_object ['tagcount'] = intval ( $row ['tagcount'] );
			
			array_push ( $ret_val, $json_object );
		}
		
		if ($ret_val == null) {
			http_response_code ( 404 );
			
			$json_object = array ();
			
			$json_object ['statuscode'] = '404';
			$json_object ['message'] = 'Not found';
			$json_object ['details'] = 'The requested resource was not found.';
			
			array_push ( $ret_val, $json_object );
		} else
			http_response_code ( 200 );
		
		return json_encode ( $ret_val, JSON_PRETTY_PRINT );
	}

    /**
     * @return mixed|string
     * placeholder for testing
     */
    function placeholder()
    {
        $ret_val = array ();
        return json_encode ( $ret_val, JSON_PRETTY_PRINT );
    }


    /**
     * @return mixed|string
     * an alternative methods to return a list of all questions in json format
     */
    function get_questions_list() {
        $rowsQuestion= qa_db_query_sub ( "SELECT postid, title, content, categoryid, tags, userid, created, acount FROM ^posts WHERE type='Q' ORDER BY created DESC;" );

        $ret_val = array ();

        while ( ($rowQ = qa_db_read_one_assoc ( $rowsQuestion, true )) !== null ) {


            $json_object = array ();

            $json_object ['question_id'] = intval ( $rowQ ['postid'] );
            $json_object ['question_title'] = $rowQ ['title'];
            $json_object ['question_content'] = $rowQ ['content'];
            $json_object ['quetion_categoryid'] = intval ( $rowQ ['categoryid'] );
            $tags = explode ( ',', $rowQ ['tags'] );
            $json_object ['question_tags'] = $tags;
            $json_object ['question_userid'] = intval ( $rowQ ['userid'] );
            $json_object ['question_creation_date'] = $rowQ ['created'];
            $json_object ['question_answer_count'] = intval ( $rowQ ['acount'] );

            array_push ( $ret_val, $json_object );

        }

        if ($this->questionslimit == 0) { // Maximum number of questions in response is 10
            if (count ( $ret_val ) > 10) {
                $random_keys = array_rand ( $ret_val, 10 );

                $random_rows = array ();

                for($i = 0; $i < count ( $random_keys ); ++ $i)
                    array_push ( $random_rows, $ret_val [$random_keys [$i]] );

                $ret_val = $random_rows;
            }
        } else if ($this->questionslimit == 1) { // Maximum number of questions in response is 100
            if (count ( $ret_val ) > 100) {
                $random_keys = array_rand ( $ret_val, 100 );

                $random_rows = array ();

                for($i = 0; $i < count ( $random_keys ); ++ $i)
                    array_push ( $random_rows, $ret_val [$random_keys [$i]] );

                $ret_val = $random_rows;
            }
        }

        if ($ret_val == null) {
            http_response_code ( 404 );

            $json_object = array ();

            $json_object ['statuscode'] = '404';
            $json_object ['message'] = 'Not found';
            $json_object ['details'] = 'The requested resource was not found.';

            array_push ( $ret_val, $json_object );
        } else
            http_response_code ( 200 );

        return json_encode ( $ret_val, JSON_PRETTY_PRINT );
    }


    /**
     * @return mixed|string
     * alternative method to get answers in json format
     */
    function get_answers() {
        $rows = qa_db_query_sub ( "SELECT postid, parentid, content FROM ^posts WHERE type='A';" );

        $ret_val = array ();

        while ( ($row = qa_db_read_one_assoc ( $rows, true )) !== null ) {
            $questionid0fAnswer = intval ( $row ['parentid'] );
            $rowsQuestion = qa_db_query_sub ( "SELECT postid, title, content, categoryid, tags, userid, created, acount FROM ^posts WHERE type='Q' && postid=$questionid0fAnswer;" );
            while ( ($rowQ = qa_db_read_one_assoc ( $rowsQuestion, true )) !== null) {

                $json_object = array ();

                $json_object ['answerid'] = intval ( $row ['postid'] );
                $json_object ['questionid'] = intval ( $row ['parentid'] );
                //$json_object ['content'] = $row ['content'];
                $json_object ['answercontent'] = $row ['content'];
                $json_object ['questiontitle'] = $rowQ ['title'];
                $json_object ['questioncontent'] = $rowQ ['content'];

                array_push ( $ret_val, $json_object );
            }
        }

        if ($this->answerslimit == 0) { // Maximum number of answers in response is 10
            if (count ( $ret_val ) > 10) {
                $random_keys = array_rand ( $ret_val, 10 );

                $random_rows = array ();

                for($i = 0; $i < count ( $random_keys ); ++ $i)
                    array_push ( $random_rows, $ret_val [$random_keys [$i]] );

                $ret_val = $random_rows;
            }
        } else if ($this->answerslimit == 1) { // Maximum number of answers in response is 100
            if (count ( $ret_val ) > 100) {
                $random_keys = array_rand ( $ret_val, 100 );

                $random_rows = array ();

                for($i = 0; $i < count ( $random_keys ); ++ $i)
                    array_push ( $random_rows, $ret_val [$random_keys [$i]] );

                $ret_val = $random_rows;
            }
        }

        if ($ret_val == null) {
            http_response_code ( 404 );

            $json_object = array ();

            $json_object ['statuscode'] = '404';
            $json_object ['message'] = 'Not found';
            $json_object ['details'] = 'The requested resource was not found.';

            array_push ( $ret_val, $json_object );
        } else
            http_response_code ( 200 );

        return json_encode ( $ret_val, JSON_PRETTY_PRINT );
    }
	
}