<?php
/*
	Plugin Name: qa Connect
	Plugin URI: http://www.smyx.net/qa-connect.html
	Plugin Description: Allows users to log in via sina, qq, qqweibo etc.
    Plugin Version: 1.0
    Plugin Date: 2012-12-12
    Plugin Author: 水脉烟香
    Plugin Author URI: http://www.smyx.net/
    Plugin License: GPLv2
    Plugin Minimum Question2Answer Version: 1.5
    Plugin Minimum PHP Version: 5.2
    Plugin Update Check URI:
*/
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;}
if (!QA_FINAL_EXTERNAL_USERS) { // login modules don't work with external user integration
	qa_register_plugin_module('login', 'qa-connect.php', 'qa_connect', 'qa Connect');}
// 将所有链接的相对地址设置为绝对地址，使用社交网络头像等
qa_register_plugin_overrides('override-function.php');

qa_register_plugin_module(
    'page',
    'qa-rest-api-page.php',
    'qa_rest_api_presentation_page',
    'REST API');
qa_register_plugin_module(
    'page',
    'qa-rest-api-response.php',
    'qa_rest_api_response_page',
    'REST API response');
qa_register_plugin_module(
    'page',
    'qa-rest-api-token.php',
    'qa_rest_api_token_page',
    'REST API wx token');
qa_register_plugin_module(
    'page',
    'wx-user-token.php',
    'wx_user_token',
    'REST API wx user token');
qa_register_plugin_module(
    'module',
    'qa-rest-api-options.php',
    'qa_rest_api_options_admin',
    'REST API option admin');


/*
$zend_loader_enabled = function_exists('zend_loader_enabled');


if ($zend_loader_enabled) {
	$zend_loader_version = function_exists('zend_loader_version') ? zend_loader_version() : '';
	if (version_compare($zend_loader_version, '3.3', '>=')) {
		$php_version = (version_compare(PHP_VERSION, '5.3', '>=')) ? 'zend/' : '';
		if (!QA_FINAL_EXTERNAL_USERS) { // login modules don't work with external user integration
			qa_register_plugin_module('login', $php_version . 'qa-connect.php', 'qa_connect', 'qa Connect');
		}
		// 将所有链接的相对地址设置为绝对地址，使用社交网络头像等
		qa_register_plugin_overrides('override-function.php');
	}
}
*/