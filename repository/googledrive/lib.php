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
 * This plugin is used to access google drive links
 *
 * @since 2.0
 * @package    repository_googledrive
 * @copyright  2009 Dan Poltawski <talktodan@gmail.com>
 * @copyright  2012 Piers Harding
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->libdir.'/googleapi.php');


class google_drive extends google_docs {

    const REALM = 'https://www.googleapis.com/auth/drive.readonly.metadata https://www.googleapis.com/auth/drive.file https://docs.google.com/feeds/ https://spreadsheets.google.com/feeds/ https://spreadsheets.google.com/feeds/ https://docs.googleusercontent.com/.';
    const DRIVE_LIST_URL = 'https://www.googleapis.com/drive/v2/files';
    private $mygoogleoauth = null;

    /**
     * Constructor.
     *
     * @param google_oauth $googleoauth oauth curl class for making authenticated requests
     */
    public function __construct(google_oauth $googleoauth) {
        $this->mygoogleoauth = $googleoauth;
        parent::__construct($googleoauth);
    }

    /**
     * Returns a list of files the user has formated for files api
     *
     * @param string $search A search string to do full text search on the documents
     * @return mixed Array of files formated for fileapoi
     */
    public function get_file_list($search = '') {
        global $OUTPUT, $SESSION;

        $SESSION->googledrive = array();
        $search = clean_param($search, PARAM_ALPHANUMEXT);
        $content = $this->mygoogleoauth->get(self::DRIVE_LIST_URL);
        $doc = json_decode($content);
        $files = array();
        if (!empty($doc->items)) {
            foreach ($doc->items as $gdoc) {
                if (isset($gdoc->explicitlyTrashed) && $gdoc->explicitlyTrashed) {
                    continue;
                }
                $title = (!empty($gdoc->originalFilename) ? $gdoc->originalFilename : $gdoc->title);
                if (!empty($search) && !preg_match('/'.$search.'/i', $title)) {
                    continue;
                }
//                 error_log('TITLE: '.$title);
                $owner = (!empty($gdoc->ownerNames) ? implode(', ', $gdoc->ownerNames) : '');
                $source = (!empty($gdoc->webContentLink) ? $gdoc->webContentLink : (!empty($gdoc->selfUrl) ? $gdoc->selfUrl : (!empty($gdoc->downloadUrl) ?  $gdoc->downloadUrl : $gdoc->alternateLink)));
                $download = null;
                if ($gdoc->mimeType == 'application/vnd.google-apps.drawing') {
                    $download = $source;
                }
                else if ($gdoc->mimeType == 'application/vnd.google-apps.spreadsheet') {
                    if (isset($gdoc->exportLinks)) {
                        $links = (array) $gdoc->exportLinks;
//                         error_log('spreadsheet: '.var_export($links, true));
                        $download = $links['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                        $title .= '.xlsx';
                    }
                }
                else if ($gdoc->mimeType == 'application/vnd.google-apps.fusiontable') {
//                     error_log('fusion: '.var_export($gdoc, true));
                    $download = $gdoc->alternateLink;
//                     $title .= '.csv';
                }
                else {
                    $download = (!empty($gdoc->selfUrl) ? $gdoc->selfUrl : (!empty($gdoc->downloadUrl) ?  $gdoc->downloadUrl : $gdoc->alternateLink));
                }
                $url = (!empty($gdoc->downloadUrl) ?  $gdoc->downloadUrl : '');
                $size = (!empty($gdoc->fileSize) ? $gdoc->fileSize : (!empty($gdoc->quotaBytesUsed) ?  $gdoc->quotaBytesUsed : 'Unknown')).' Bytes';
                $thumb = (!empty($gdoc->thumbnailLink) ? $gdoc->thumbnailLink : (string) $OUTPUT->pix_url(file_extension_icon($title, 32)));
                $file = array( 'title' => $title,
                                'url' => $url,
                                'source' => $source,
                                'date'   => usertime(strtotime($gdoc->modifiedDate)),
                                'thumbnail' => $thumb,
                                'author' => $owner,
                                'size' => $size,
                                'mimetype' => $gdoc->mimeType,
                                'webContentLink' => (isset($gdoc->webContentLink) ? $gdoc->webContentLink : ''),
                                'selfUrl' => (isset($gdoc->selfUrl) ? $gdoc->selfUrl : ''),
                                'downloadUrl' => (isset($gdoc->downloadUrl) ? $gdoc->downloadUrl : ''),
                                'alternateLink' => $gdoc->alternateLink,
                                'download' => $download,
                                );
                $SESSION->googledrive[$source] = $file;
                $files[] = $file;
            }
        }
        return $files;
    }
}

/**
 * Google Docs Plugin
 *
 * @since 2.0
 * @package    repository_googledrive
 * @copyright  2009 Dan Poltawski <talktodan@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_googledrive extends repository {
    private $googleoauth = null;

    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);

        $returnurl = new moodle_url('/repository/repository_callback.php');
        $returnurl->param('callback', 'yes');
        $returnurl->param('repo_id', $this->id);
        $returnurl->param('sesskey', sesskey());

        $clientid = get_config('googledrive', 'clientid');
        $secret = get_config('googledrive', 'secret');
        $this->googleoauth = new google_oauth($clientid, $secret, $returnurl, google_drive::REALM);
        $this->check_login();
    }

    public function check_login() {
        return $this->googleoauth->is_logged_in();
    }

    public function print_login() {
        $url = $this->googleoauth->get_login_url();

        if ($this->options['ajax']) {
            $popup = new stdClass();
            $popup->type = 'popup';
            $popup->url = $url->out(false);
            return array('login' => array($popup));
        } else {
            echo '<a target="_blank" href="'.$url->out(false).'">'.get_string('login', 'repository').'</a>';
        }
    }

    public function get_listing($path='', $page = '') {
        $gdocs = new google_drive($this->googleoauth);

        $ret = array();
        $ret['dynload'] = true;
        $ret['list'] = $gdocs->get_file_list();
        return $ret;
    }

    public function search($search_text, $page = 0) {
        $gdocs = new google_drive($this->googleoauth);

        $ret = array();
        $ret['dynload'] = true;
        $ret['list'] = $gdocs->get_file_list($search_text);
        return $ret;
    }

    public function logout() {
        $this->googleoauth->log_out();
        return parent::logout();
    }

    public function get_file($url, $file = '') {
        global $SESSION;

        $gdocs = new google_drive($this->googleoauth);
//         error_log('download file: '.var_export($SESSION->googledrive[$url], true));
        $url = $SESSION->googledrive[$url]['download'];
        $path = $this->prepare_file($file);
        return $gdocs->download_file($url, $path);
    }

    public function supported_filetypes() {
        return '*';
    }
    public function supported_returntypes() {
        return FILE_EXTERNAL | FILE_INTERNAL;
    }

    public static function get_type_option_names() {
        return array('clientid', 'secret', 'pluginname');
    }

    public static function type_config_form($mform, $classname = 'repository') {

        $a = new stdClass;
        $a->docsurl = get_docs_url('Google_OAuth_2.0_setup');
        $a->callbackurl = google_oauth::callback_url()->out(false);

        $mform->addElement('static', null, '', get_string('oauthinfo', 'repository_googledrive', $a));

        parent::type_config_form($mform);
        $mform->addElement('text', 'clientid', get_string('clientid', 'repository_googledrive'));
        $mform->addElement('text', 'secret', get_string('secret', 'repository_googledrive'));

        $strrequired = get_string('required');
        $mform->addRule('clientid', $strrequired, 'required', null, 'client');
        $mform->addRule('secret', $strrequired, 'required', null, 'client');
    }
}
// Icon from: http://www.iconspedia.com/icon/google-2706.html.
