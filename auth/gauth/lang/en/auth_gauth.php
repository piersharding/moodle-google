<?php
/**
* @author  Piers Harding
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package auth/gauth
* @version 1.0
*
* Authentication Plugin: Google OpenId based SSO Authentication
*
* 2011-10  Created
**/

$string['pluginname']         = 'Google OpenId Authentication';
$string['auth_gauthtitle']         = 'Google OpenId Authentication';
$string['auth_gauthdescription']   = 'SSO Authentication using Google OpenId';

$string['auth_gauth_createusers'] = 'Automatically create users';
$string['auth_gauth_createusers_description'] = 'Check to have the module log automatically create users accounts if none exists';

$string['auth_gauth_casesensitive'] = 'Username is Case Sensitive';
$string['auth_gauth_casesensitive_description'] = 'When doing the lookup to match an OpenId user to a Moodle user, do a case sensitive search - usually this should be On';

$string['auth_gauth_duallogin'] = 'Enable Dual login for users';
$string['auth_gauth_duallogin_description'] = 'Enable user to login using their assigned login auth module and Google OpenId SSO';

$string['auth_gauth_username'] = 'OpenId username mapping';
$string['auth_gauth_username_description'] = 'Google OpenId attribute that is mapped to Moodle username - this defaults to openid, but you might want to use email';
$string['auth_gauth_userfield'] = 'Moodle username mapping';
$string['auth_gauth_userfield_description'] = 'Moodle user field that is mapped to the Google OpenId username attribute - this defaults to idnumber, but could be username, or email';

$string['auth_gauth_field_instructions'] = 'Here you can map further field values from Google to a Moodle user accounts.  The fields available are: firstname, lastname, email, lang, and openid';
$string['auth_gauth_user_cancel'] = 'The user has cancelled the login at Google';
$string['auth_gauth_user_not_loggedin'] = 'User not logged in';
$string['auth_gauth_openid_failure'] = 'OpenId negotiation with Google failed';
$string['auth_gauth_openid_empty'] = 'Google returned no data';
$string['auth_gauth_openid_key_empty'] = 'Key fields of firstname, lastname, or email returned empty from Google';
$string['auth_gauth_username_error'] = 'Google returned a set of data that does not contain the OpenId username mapping field. This field is required to login';
$string['pluginauthfailedusername'] = 'The Google OpenId authentication plugin failed - user {$a} disallowed due to invalid username format';
$string['pluginauthfailed'] = 'The Google OpenId authentication plugin failed - user {$a} disallowed (no user auto creation?) or dual login disabled';
