<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Google Docs Plugin
 *
 * @since 2.0
 * @package    repository
 * @subpackage googlelinks
 * @copyright  2009 Dan Poltawski <talktodan@gmail.com>
 * @copyright  2011 Piers Harding
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/local/oauth/lib.php');

class googlelinks_oauth_docs extends local_oauth {

    private $scope = 'https://docs.google.com/feeds/ https://spreadsheets.google.com/feeds/ https://docs.googleusercontent.com/.';
    private $api = 'https://docs.google.com/feeds/documents/private/full';
    private $site_name = 'googledocs.com';

    public function __construct() {
        parent::__construct($this->site_name);
    }

    /**
     * clean down the auth storage
     * @return nothing
     */
    public function wipe_auth() {
        parent::wipe_auth($this->site_name);
    }

    /**
     * Add a site into the site directory
     * @param array $oauth_params parameters to pass with token request
     * @return bool success/fail
     */
    public function authenticate($return_to) {
        global $SESSION, $FULLME;

        if (empty($this->access_token)) {
            if (empty($this->request_token)) {
                // obtain a request token
                $this->add_to_log('get request token');
                $this->request_token = $this->consumer->getRequestToken($this->site->request_token_url, array('scope' => $this->scope, 'xoauth_displayname' => get_site()));
                $this->store();

                // Authorize the request token
                $this->add_to_log('authorise request token');
                return $this->consumer->getAuthorizeRequest($this->site->authorize_token_url, $this->request_token, FALSE, $return_to);
            }
        }

        return true;
    }

    /**
     * Get a user content type file
     *
     * @param string $fileurl the Google user content file url
     * @return string file contents
     */
    public function get_user_content_file($fileurl) {
        global $USER;
        $parts = split('@', $USER->email);
        $user = array_shift($parts);
        $domain = $this->site->consumer_key;
        $oauthsecret = $this->site->consumer_secret;
        $user      = "$user@$domain";
        $parameters = array();
        list($download, $params) = explode('?', $fileurl, 2);
        $params = explode('&', $params);
        foreach ($params as $param) {
            list($k, $v) = explode('=', $param, 2);
            $parameters[$k] = $v;
        }
        $parameters['xoauth_requestor_id'] = $user;

        $consumer  = new OAuthConsumer($domain, $oauthsecret, NULL);
        $request   = OAuthRequest::from_consumer_and_token($consumer, NULL, 'GET', $download, $parameters);
        $request->sign_request(new OAuthSignatureMethod_HMAC_SHA1(), $consumer, NULL);

        // URL Encode the the params
        $params = array();
        foreach ($parameters as $k => $v) {
            $params[]= "$k=$v";
        }
        $url = $download . '?' . implode('&', $params);

        // Perform a GET to obtain the file
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array($request->to_header()));
        $content = curl_exec($curl);
        $headers = curl_getinfo($curl);
        $errorNumber = curl_errno($curl);
        if (!$content || $headers['http_code'] != 200) {
            $error = curl_error($curl);
            error_log('GoogleLinks: Get user content (for '.$user.' - '.$url.') failed with: '.$error.' '.$content);
            $content = '';
        }
        curl_close($curl);

        return $content;
    }

    /**
    * Add a site into the site directory
     * @param array $oauth_params parameters to pass with token request
    * @return bool success/fail
    */
    public function complete() {

        if (empty($this->access_token)) {
            if (empty($this->request_token)) {
                return false;
            }

            // Replace the request token with an access token
            $this->add_to_log('upgrade request token to access token');
            $this->access_token = $this->consumer->getAccessToken($this->site->access_token_url, $this->request_token);
            $this->store();
        }

        return true;
    }

    /**
    * Returns a list of files the user has formated for files api
     *
    * @param string $search A search string to do full text search on the documents
    * @return mixed Array of files formated for fileapoi
    */
    #FIXME
    public function get_file_list($search = ''){
        global $CFG, $OUTPUT, $SESSION;

        $content = $this->get_docs_feed($search);
        $xml = new SimpleXMLElement($content);
        $SESSION->googlelinks = array();

        $files = array();
        foreach($xml->entry as $gdoc){
            $docid  = (string) $gdoc->children('http://schemas.google.com/g/2005')->resourceId;
            list($type, $docid) = explode(':', $docid);

            $title  = '';
            $source = '';
            // FIXME: We're making hard-coded choices about format here.
            // If the repo api can support it, we could let the user
            // chose.
            switch($type){
                case 'document':
                    $title = $gdoc->title.'.rtf';
                    $download = 'https://docs.google.com/feeds/download/documents/Export?id='.$docid.'&exportFormat=rtf';
                    break;
                case 'presentation':
                    $title = $gdoc->title.'.ppt';
                    $download = 'https://docs.google.com/feeds/download/presentations/Export?id='.$docid.'&exportFormat=ppt';
                    break;
                case 'spreadsheet':
                    $title = $gdoc->title.'.xls';
                    $download = 'https://spreadsheets.google.com/feeds/download/spreadsheets/Export?key='.$docid.'&exportFormat=xls';
                    break;
                case 'pdf':
                    $title  = (string)$gdoc->title;
                    $download = (string)$gdoc->content[0]->attributes()->src;
                    break;
            }
            $source = (string)$gdoc->link->attributes()->href;

            if(!empty($source)){
                $SESSION->googlelinks[$source] = $download;
                $files[] =  array(  'title' => $title,
                                    'url' => "{$gdoc->link[0]->attributes()->href}",
                                    'source' => $source,
                                    'date'   => usertime(strtotime($gdoc->updated)),
                                    'thumbnail' => (string) $OUTPUT->pix_url(file_extension_icon($title, 32))
                            );
            }
        }
        return $files;
    }

    public function get_docs_feed($search) {

        $url = $this->api;
        if($search){
            $url.='?q='.urlencode($search);
        }
        $response = $this->getRequest($url, array());
        if ($response->status != 200) {
            if ($response->status == 401) {
                $this->wipe_auth();
            }
            throw new local_oauth_exception($response->message." : ".$response->body);
        }

        if (empty($response->body)) {
            return null;
        }
        return $response->body;
    }

    /**
     * Get the actual file contents
     * @param string $url
     * @throws local_oauth_exception
     * @return string
     */
    public function get_file($url) {
        global $SESSION;

        $parameters = array();
        $download = $SESSION->googlelinks[$url];

        // handle User Content files separately
        if (preg_match('/usercontent/', $download)) {
            $result = $this->get_user_content_file($download);
            if (empty($result)) {
                throw new local_oauth_exception('Could not get file');
            }
            return $result;
        }

        // normal files
        $response = $this->getRequest($download, $parameters);
        if ($response->status != 200) {
            throw new local_oauth_exception($response->message." : ".$response->body);
        }

        if (empty($response->body)) {
            return null;
        }
        return $response->body;
    }
}

class repository_googlelinks extends repository {
    private $subauthtoken = '';

    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        global $USER;
        parent::__construct($repositoryid, $context, $options);

        $this->oauth = new googlelinks_oauth_docs();

        // TODO: I wish there was somewhere we could explicitly put this outside of constructor..
        $googletoken = optional_param('oauth_token', false, PARAM_RAW);
        if($googletoken){
            $this->oauth->complete();
        }
    }

    /**
    * Add Plugin settings input to Moodle form
     * @param object $mform
    */
    public function type_config_form($mform) {
        global $CFG;
        parent::type_config_form($mform);
    }

    /**
    * Names of the plugin settings
     * @return array
    */
    public static function get_type_option_names() {
        return array('domainname', 'oauthsecret', 'pluginname');
    }

    public function check_login() {
        return $this->oauth->is_authorised();
    }

    public function print_login($ajax = true){
        global $CFG;
        if($ajax){
            $ret = array();
            $popup_btn = new stdClass();
            $popup_btn->type = 'popup';
            $returnurl = $CFG->wwwroot.'/repository/repository_callback.php?callback=yes&repo_id='.$this->id;
            $popup_btn->url = $this->oauth->authenticate($returnurl);
            $ret['login'] = array($popup_btn);
            return $ret;
        }
    }

    public function get_listing($path='', $page = '') {

        $ret = array();
        $ret['dynload'] = true;
        $ret['list'] = $this->oauth->get_file_list();
        return $ret;
    }

    public function search($query){

        $ret = array();
        $ret['dynload'] = true;
        $ret['list'] = $this->oauth->get_file_list($query);
        return $ret;
    }

    public function logout(){

        $this->oauth->wipe_auth();

        return parent::logout();
    }

    public function get_file($url, $file) {
        global $CFG;
        $path = $this->prepare_file($file);

        $fp = fopen($path, 'w');
        $content = $this->oauth->get_file($url);
        if ($content) {
            fwrite($fp, $content);
        }
        fclose($fp);

        return array('path'=>$path, 'url'=>$url);
    }

    public function supported_filetypes() {
       return array('document');
    }

    public function supported_returntypes() {
        return FILE_INTERNAL | FILE_EXTERNAL;
    }
}
//Icon from: http://www.iconspedia.com/icon/google-2706.html
