<?php
/**
 * Author	水脉烟香
 * Blog  	http://www.smyx.net/
 * Created  Dec 12, 2012
 */

// 将所有链接的相对地址设置为绝对地址
///*
function qa_path_to_root() {
	$site_url = qa_opt('site_url');
	if (!empty($site_url)) {
		return $site_url;
	} else {
		global $qa_root_url_relative;
		return $qa_root_url_relative;
	} 
} 
//*/
// 使用社交网络头像
function qa_get_user_avatar_html($flags, $email, $handle, $blobid, $width, $height, $size, $padding = false) {
	if (qa_opt('avatar_allow_gravatar') && ($flags &QA_USER_FLAGS_SHOW_GRAVATAR)) {
		$html = qa_get_gravatar_html($email, $size);
	} elseif (qa_opt('avatar_allow_upload') && (($flags &QA_USER_FLAGS_SHOW_AVATAR))) {
		if (isset($blobid)) {
			$html = qa_get_avatar_blob_html($blobid, $width, $height, $size, $padding);
		} elseif (strlen($handle)) {
			$userprofile = qa_db_select_with_pending(qa_db_user_profile_selectspec($handle, false));
			if (!empty($userprofile['social_avatar'])) {
				$html = '<img src="' . $userprofile['social_avatar'] . '" width="' . $size . '" height="' . $size . '" class="qa-avatar-image" />';
			} else {
				$html = null;
			} 
		} 
	} 
	if (!isset($html)) {
		if ((qa_opt('avatar_allow_gravatar') || qa_opt('avatar_allow_upload')) && qa_opt('avatar_default_show') && strlen(qa_opt('avatar_default_blobid'))) {
			$html = qa_get_avatar_blob_html(qa_opt('avatar_default_blobid'), qa_opt('avatar_default_width'), qa_opt('avatar_default_height'), $size, $padding);
		} else {
			$html = null;
		} 
	} 
	return (isset($html) && strlen($handle)) ? ('<A HREF="' . qa_path_html('user/' . $handle) . '" CLASS="qa-avatar-link">' . $html . '</A>') : $html;
}

function qa_set_logged_in_user($userid, $handle='', $remember=false, $source=null)
{
	// if a logout was requested, do extra stuff
	if (!isset($userid)) {
		// get all modules which have a custom logout logic
		$loginmodules=qa_load_modules_with('login', 'do_logout');

		// do the work
		foreach ($loginmodules as $module) {
			$module->do_logout();
		}
	}
	
	// then just perform the default tasks
	qa_set_logged_in_user_base($userid, $handle, $remember, $source);
}

function qa_log_in_external_user($source, $identifier, $fields)
{

	require_once QA_INCLUDE_DIR.'qa-db-users.php';
	$remember = qa_opt('open_login_remember') ? true : false;
	
	$users=qa_db_user_login_find($source, $identifier);
	$countusers=count($users);
	if ($countusers>1)
		qa_fatal_error('External login mapped to more than one user'); // should never happen
	
	/*
	 * To allow for more than one account from the same openid/openauth provider to be 
	 * linked to an Q2A user, we need to override the way session source is stored
	 * Supposing userid 01 is linked to 2 yahoo accounts, the session source will be
	 * something like 'yahoo-xyz' when logging in with the first yahoo account and
	 * 'yahoo-xyt' when logging in with the other.
	 */
	
	$aggsource = qa_open_login_get_new_source($source, $identifier);
	// prepare some data
	if(empty($fields['handle'])) {
		$ohandle = ucfirst($source);
	} else {
		$ohandle = preg_replace('/[\\@\\+\\/]/', ' ', $fields['handle']);
	}
	$oemail = null;
	if (strlen(@$fields['email']) && $fields['confirmed']) { // only if email is confirmed
		$oemail = $fields['email'];
	}
	
	if ($countusers) { // user exists so log them in
		//always update email and handle
		if($oemail) qa_db_user_login_set__open($source, $identifier, 'oemail', $oemail);
		qa_db_user_login_set__open($source, $identifier, 'ohandle', $ohandle);
		
		qa_set_logged_in_user($users[0]['userid'], $users[0]['handle'], $remember, $aggsource);
	
	} else { // create and log in user
		require_once QA_INCLUDE_DIR.'qa-app-users-edit.php';
		
		qa_db_user_login_sync(true);
		
		$users=qa_db_user_login_find($source, $identifier); // check again after table is locked
		if (count($users)==1) {
			//always update email and handle
			if($oemail) qa_db_user_login_set__open($source, $identifier, 'oemail', $oemail);
			qa_db_user_login_set__open($source, $identifier, 'ohandle', $ohandle);
			
			qa_db_user_login_sync(false);
			qa_set_logged_in_user($users[0]['userid'], $users[0]['handle'], $remember, $aggsource);
			
		} else {
			$handle=qa_handle_make_valid(@$fields['handle']);

			// check if email address already exists
			$emailusers = array();
			if (strlen(@$fields['email']) && $fields['confirmed']) { // only if email is confirmed
				$emailusers=qa_db_user_find_by_email_or_oemail__open($fields['email']);
				
				if (count($emailusers)) {
					// unset regular email to prevent duplicates
					unset($fields['email']); 
				}
			}
			
			$userid=qa_create_new_user((string)@$fields['email'], null /* no password */, $handle,
				isset($fields['level']) ? $fields['level'] : QA_USER_LEVEL_BASIC, @$fields['confirmed']);
			
			qa_db_user_set($userid, 'oemail', $oemail);
			qa_db_user_login_add($userid, $source, $identifier);
			qa_db_user_login_set__open($source, $identifier, 'oemail', $oemail);
			qa_db_user_login_set__open($source, $identifier, 'ohandle', $ohandle);
			qa_db_user_login_sync(false);
			
			$profilefields=array('name', 'location', 'website', 'about');
			
			foreach ($profilefields as $fieldname)
				if (strlen(@$fields[$fieldname]))
					qa_db_user_profile_set($userid, $fieldname, $fields[$fieldname]);
					
			if (strlen(@$fields['avatar']))
				qa_set_user_avatar($userid, $fields['avatar']);
					
			qa_set_logged_in_user($userid, $handle, $remember, $aggsource);
			
			//return count($emailusers); //original
            return $userid;
		}
	}
	
	//return 0;
    return 0;
}

/**
 *  override qa_set_user_avatar by zijian
 *  @para int $uesrid, string avatarUrl, int $oldblobid
 *  @return false
 */

function qa_set_user_avatar($userid, $avatarUrl, $oldblobid=null)
{
    require_once QA_INCLUDE_DIR.'util/image.php';

        $rows = qa_db_query_sub('SELECT avatarblobid FROM ^users where userid = $', $userid);

        while (($row = qa_db_read_one_assoc($rows, true)) !== null) {
            $oldblobid = intval($row ['avatarblobid']);
        }

      $imagesize=@getimagesize($avatarUrl); // filename/ url not file content

      $width=$imagesize[0];
      $height=$imagesize[1];

      $imagedata = file_get_contents($avatarUrl);


        // always call qa_gd_image_resize(), even if the size is the same, to take care of possible PNG transparency
        qa_image_constrain($width, $height, '400', '400');


    if (isset($imagedata)) {
        require_once QA_INCLUDE_DIR.'app/blobs.php';

        $newblobid = qa_create_blob($imagedata, 'jpeg', null, $userid, null, qa_remote_ip_address());

        if (isset($newblobid)) {

            qa_db_query_sub('UPDATE ^users SET avatarblobid = $, avatarwidth = $, avatarheight = $ WHERE userid = $', $newblobid, $width, $height, $userid);

            if (isset($oldblobid))
                qa_delete_blob($oldblobid);
//            qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_AVATAR, true);
//            qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_GRAVATAR, false);

        }
    }

    return false;

}

