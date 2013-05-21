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

    // the Drive realm appears to be broken at the moment - must set a broader one
    // const REALM = 'https://www.googleapis.com/auth/drive.readonly.metadata https://www.googleapis.com/auth/drive.file https://docs.google.com/feeds/ https://spreadsheets.google.com/feeds/ https://spreadsheets.google.com/feeds/ https://docs.googleusercontent.com/.';
    const REALM = 'https://www.googleapis.com/auth/drive.readonly.metadata https://www.googleapis.com/auth/drive https://docs.google.com/feeds/ https://spreadsheets.google.com/feeds/ https://spreadsheets.google.com/feeds/ https://docs.googleusercontent.com/.';
    const DRIVE_FILE_URL = 'https://www.googleapis.com/drive/v2/files';
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
        $search = clean_param($search, PARAM_ALPHANUMEXT);
        $content = $this->mygoogleoauth->get(self::DRIVE_FILE_URL);
        $doc = json_decode($content);
        $files = array();
        if (!empty($doc->items)) {
            foreach ($doc->items as $gdoc) {
                $file = self::process_gdoc($gdoc);
                if (!$file) {
                    continue;
                }
                if (!empty($search) && !preg_match('/'.$search.'/i', $file['title'])) {
                    continue;
                }
                $files[] = $file;
            }
        }
        return $files;
    }

    public function get_file_info($gid) {
        $content = $this->mygoogleoauth->get(self::DRIVE_FILE_URL.'/'.$gid);
        $gdoc = json_decode($content);
        $gdoc->source = $gdoc->id;
        return $gdoc;
    }

    public static function process_gdoc($gdoc) {
        global $OUTPUT;

        if (isset($gdoc->explicitlyTrashed) && $gdoc->explicitlyTrashed) {
            return false;
        }
        $title = (!empty($gdoc->originalFilename) ? $gdoc->originalFilename : $gdoc->title);
        $owner = (!empty($gdoc->ownerNames) ? implode(', ', $gdoc->ownerNames) : '');
        $download = null;
        if ($gdoc->mimeType == 'application/vnd.google-apps.drawing') {
            $download = self::get_link($gdoc);
        }
        else if ($gdoc->mimeType == 'application/vnd.google-apps.spreadsheet') {
            if (isset($gdoc->exportLinks)) {
                $links = (array) $gdoc->exportLinks;
                $download = $links['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                $title .= '.xlsx';
            }
        }
        else if ($gdoc->mimeType == 'application/vnd.google-apps.fusiontable') {
            $download = $gdoc->alternateLink;
        }
        else {
            $download = (!empty($gdoc->selfUrl) ? $gdoc->selfUrl : (!empty($gdoc->downloadUrl) ?  $gdoc->downloadUrl : $gdoc->alternateLink));
        }
        $url = (!empty($gdoc->downloadUrl) ?  $gdoc->downloadUrl : '');
        $size = (!empty($gdoc->fileSize) ? $gdoc->fileSize : (!empty($gdoc->quotaBytesUsed) ?  $gdoc->quotaBytesUsed : 'Unknown')).' Bytes';
        $thumb = (!empty($gdoc->thumbnailLink) ? $gdoc->thumbnailLink : (string) $OUTPUT->pix_url(file_extension_icon($title, 32)));
        // todo: make sure there's nothing else out there reliant on the $source being the URL
        $file = array( 'title' => $title,
                        'url' => $url,
                        'source' => $gdoc->id,
                        'date'   => usertime(strtotime($gdoc->modifiedDate)),
                        'thumbnail' => $thumb,
                        'author' => $owner,
                        'size' => $size,
                        'mimetype' => $gdoc->mimeType,
                        'webContentLink' => (isset($gdoc->webContentLink) ? $gdoc->webContentLink : ''),
                        'selfUrl' => (isset($gdoc->selfUrl) ? $gdoc->selfUrl : ''),
                        'downloadUrl' => (isset($gdoc->downloadUrl) ? $gdoc->downloadUrl : ''),
                        'alternateLink' => $gdoc->alternateLink,
                        'download' => $url,
                        );
        return $file;
    }

    public static function get_link($gdoc) {
        return (!empty($gdoc->webContentLink) ? $gdoc->webContentLink : (!empty($gdoc->selfUrl) ? $gdoc->selfUrl : (!empty($gdoc->downloadUrl) ?  $gdoc->downloadUrl : $gdoc->alternateLink)));
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

    /**
     * Download file contents from Google Drive and store it in a local Moodle file
     * @param string $reference Serialized Drive "file" object
     * @param string $file Not actually used
     * @return array('path'=>$path, 'url'=>$url);
     */
    public function get_file($reference, $file='') {
        $ref = unserialize($reference);
        $f = google_drive::process_gdoc($ref);
        $url = $f['download'];

        $path = $this->prepare_file($ref->source);
        $gdocs = new google_drive($this->googleoauth);
        return $gdocs->download_file($url, $path);
    }

    public function supported_filetypes() {
        return '*';
    }
    public function supported_returntypes() {
        return FILE_EXTERNAL | FILE_INTERNAL | FILE_REFERENCE;
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

    /**
     * A "reference" is supposed to be "the information required by the repository to locate the original file"
     * Strictly speaking, the minimum I need is the gid. But you are expected to derive several other pieces
     * of information from the reference, such as various URL's. So, I'll store the entire "file" info object
     * returned from Google Drive (https://developers.google.com/drive/v2/reference/files#resource)
     *
     * @param string $source a Google Drive file id
     * @return string reference suitable to be inserted into mdl_files_reference.reference
     */
    public function get_file_reference($source) {
        // todo: code replication?
        $gdocs = new google_drive($this->googleoauth);
        return serialize($gdocs->get_file_info($source));
    }

    /**
     * Return the URL for a file, based on its reference
     * @param string $reference
     * @return string URL
     */
    public function get_link($reference) {
        return google_drive::get_link(unserialize($reference));
    }

    /**
     * Return information about a file, based on its reference
     * @param string $reference
     * @return stdClass with contenthash & filesize
     */
    public function get_file_by_reference($reference) {
        $ref = unserialize($reference->reference);
        $gdocs = new google_drive($this->googleoauth);
        $newref = $gdocs->get_file_info($ref->id);
        $fileinfo = new stdClass();
        if (isset($fileinfo->filesize)) {
            $fileinfo->filesize = $newref->filesize;
        } else {
            $fileinfo->filesize = 0;
        }
        if (isset($newref->md5Checksum)) {
            $fileinfo->contenthash = $newref->md5Checksum;
        }
        return $fileinfo;
    }

    /**
     * Send a file to the browser.
     *
     * @param stored_file $storedfile The storedfile object containing a reference to the file
     * @param int $lifetime How long to cache the file
     * @param int $filter
     * @param boolean $forcedownload
     * @param array $options
     */
    public function send_file($storedfile, $lifetime=86400, $filter=0, $forcedownload=false, array $options = null) {
        $ref = unserialize($storedfile->get_reference());
        if (isset($ref->webContentLink)) {
            header('Location: ' . $ref->webContentLink );
            return;
        }
        if ($forcedownload) {
            // arbitrarily pick the first export format
            if (isset($ref->exportLinks) && is_object($ref->exportLinks)) {
                $arr = (array)$ref->exportLinks;
                reset($arr);
                header('Location: '. current($arr));
                return;
            }
        }
        if (isset($ref->alternateLink)) {
            header('Location: ' . $ref->alternateLink);
            return;
        }
    }
}
// Icon from: http://www.iconspedia.com/icon/google-2706.html.
