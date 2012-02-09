<?php
/**
* @copyright  Copyright (c) 2009 Moodlerooms Inc. (http://www.moodlerooms.com)
* Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see http://opensource.org/licenses/gpl-3.0.html.
*
* @author     Chris Stones
* @author     Piers Harding
* @license    http://opensource.org/licenses/gpl-3.0.html     GNU Public License
*/

/**
 * GMail Block ProtoType 1
 * Oct. 16, 2008
 * MoodleRooms Inc.
 *
 * This block doesn't just have to be for the educational partners
 * It could just be a GMail Block by itself.
 * It would just require a little configuration.
 * PREREQ: Requires the G Saml auth plugin to obtain domain name
 * PREREQ: Requires curl be installed on server
 *
 * @author Chris Stones
 * @version $Id$
 * @package block_gmail
 * */
 /*
  *    1.  The Gmail block will display the most recent emails from Gmail within Moodle with the following informaiton:
         1. Gmail's email chain information CAN'T GET INFO FROM FEED
         2. The email's Subject
         3. The email's Arrival Date

   5. The Gmail block will display a link to the user's Gmail email

   7. The Gmail block will display a link to Compose a new email in Gmail

   8. The Gmail block will verify that a
      Gmail account exists for the user before displaying their email.

   9. The Gmail block will call the Gmail account creation process
      in the GMail Batch Account library if a Gmail account doesn't exist.


  */
 // TODO: style improveent the text should inhert from the container and always be small enough
 //       to fit in more of the message.

 // TODO:   Test and report for any prereqs
 // Prereq: This block Assumes that your google Apps block is set up and configured

 // http://us2.php.net/manual/en/ref.mcrypt.php
 // For Refreshing... we'll store password in encrypte format in the datadir unlockable with the md5
 // use AES

defined('MOODLE_INTERNAL') or die();

class block_gmail extends block_list {

    var $domain;
    var $oauthsecret;
    var $msgnumber;

    function init() {
        $this->title = get_string('pluginname', 'block_gmail');
    }

    /**
     * Default case: the block can be used in all course types
     * @return array
     * @todo finish documenting this function
     */
    function applicable_formats() {
        // Default case: the block can be used in courses and site index, but not in activities
        return array('all' => true, 'site' => true);
    }

    function has_config() {
        return true;
    }

    function get_content() {
    global $SESSION, $CFG, $USER, $OUTPUT;

        // quick and simple way to prevent block from showing up on front page
        if (!isloggedin()) {
            $this->content = NULL;
            return $this->content;
        }

        // which field is the username in
        $this->userfield = (get_config('blocks/gmail','username') ? get_config('blocks/gmail','username') : 'username');

        // quick and simple way to prevent block from showing up on users My Moodle if their email does not match the Google registered domain
        $this->domain = (get_config('blocks/gmail','domainname') ? get_config('blocks/gmail','domainname') : get_config('auth/gsaml','domainname'));

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        // This lib breaks install if left at top level only include
        // when we know we need it
        if ($USER->id !== 0) {
            require_once($CFG->dirroot.'/lib/simplepie/simplepie.class.php');
        }

        // Test for domain settings
        if(empty($this->domain)) {
            $this->content->items = array(get_string('mustusegoogleauthenticaion','block_gmail'));
            $this->content->icons = array();
            return $this->content;
        }

        if( !$this->oauthsecret = get_config('blocks/gmail','oauthsecret')) {
            $this->content->items = array(get_string('missingoauthkey','block_gmail'));
            $this->content->icons = array();
            return $this->content;
        }

        $feederror = false;
        // Obtain gmail feed data
        if(!$feeddata = $this->obtain_gmail_feed()) {
            $feederror = true;
        } else {
            // Parse google atom feed
            $feed = new SimplePie();
            $feed->set_raw_data($feeddata);
            $status = $feed->init();
            $msgs = $feed->get_items();
        }

        if ($feederror) {
            $this->content->items[] = get_string('sorrycannotgetmail','block_gmail');
        } else {

            //$unreadmsgsstr = get_string('unreadmsgs','block_gmail');
            $unreadmsgsstr = '';
            $composestr    = get_string('compose','block_gmail');
            $inboxstr      = get_string('inbox','block_gmail');

            // Obtain link option
            $newwinlnk = get_config('blocks/gmail','newwinlink');

            $composelink = '<a '.(($newwinlnk)?'target="_new"':'').' href="'.'http://mail.google.com/a/'.$this->domain.'/?AuthEventSource=SSO#compose">'.$composestr.'</a>';
            $inboxlink = '<a '.(($newwinlnk)?'target="_new"':'').' href="'.'http://mail.google.com/a/'.$this->domain.'">'.$inboxstr.'</a>';

            $this->content->items[] = '<img src="'.$OUTPUT->pix_url('gmail', 'block_gmail').'" alt="message" />&nbsp;' . $inboxlink.' '.$composelink.' '.$unreadmsgsstr.'<br/>';

            // Only show as many messages as specified in config
            $countmsg = true;
            if( !$msgnumber = get_config('blocks/gmail','msgnumber')) {
                // 0 msg means as many as you want.
                $countmsg = false;
            }
            $mc = 0;

            // only show the detail if they have access to it
            if (!has_capability('block/gmail:viewlist', $this->page->context)) {
                $mc = count($msgs);
                $this->content->items[] = get_string('unread','block_gmail', $mc).($mc == 1 ? '' : 's').'<br/>';
            }
            else {
                foreach( $msgs as $msg) {

                    if($countmsg and $mc == $msgnumber){
                        break;
                    }
                    $mc++;

                    // Displaying Message Data
                    $author = $msg->get_author();
                    $author->get_name();
                    $summary = $msg->get_description();

                    // Google partners need a special gmail url
                    $servicelink = $msg->get_link();
                    $servicelink = str_replace('http://mail.google.com/mail','http://mail.google.com/a/'.$this->domain,$servicelink);

                    // To Save Space given them option to show first and last or just last name
                    $authornames = split(" ",$author->get_name());
                    $author_first = array_shift($authornames);
                    $author_last = array_shift($authornames);
                    // Show first Name
                    if( !$showfirstname = get_config('blocks/gmail','showfirstname')) {
                        $author_first = '';
                    }

                    // Show last Name
                    if( !$showlastname = get_config('blocks/gmail','showlastname')) {
                        $author_last = '';
                    }

                    // I should do clean_param($summary, PARAM_TEXT) But then ' will have \'
                    if ($newwinlnk) {
                        $text  = '<a target="_new" title="'.format_string($summary);
                        $text .= '" href="'.$servicelink.'">'.format_string($msg->get_title()).'</a> '.$author_first.' '.$author_last;

                        $this->content->items[] = $text;
                    } else {
                        $text  = '<a title="'.format_string($summary);
                        $text .= '" href="'.$servicelink.'">'.format_string($msg->get_title()).'</a> '.$author_first.' '.$author_last;
                        $this->content->items[]  = $text;
                    }
                }
            }
        }

        return $this->content;
    }

    /**
     * This function uses 2 Legged OAuth to return the atom feed for
     * the users Gmail.
     */
    function obtain_gmail_feed() {
        global $USER;
        // http://code.google.com/p/oauth/
        // under Apache License, Version 2.0
        // http://www.apache.org/licenses/GPL-compatibility.html (some dispute if not GPL 3)
        // Moodle can be GPL 3 at your option

        // classes that clash with the real ssphp
        if (!class_exists('OAuthConsumer')) {
            require_once('OAuth.php');
        }
        $consumer  = new OAuthConsumer($this->domain, $this->oauthsecret, NULL);
        $parts = split('@', $USER->{$this->userfield});
        $user = array_shift($parts);
        $user      = "$user@$this->domain";
        $feed      = 'https://mail.google.com/mail/feed/atom';
        $params    = array('xoauth_requestor_id' => $user);
        $request   = OAuthRequest::from_consumer_and_token($consumer, NULL, 'GET', $feed, $params);
        $request->sign_request(new OAuthSignatureMethod_HMAC_SHA1(), $consumer, NULL);
        // URL Encode the the params
        $url = $feed.'?xoauth_requestor_id='.urlencode($user);

        // Perform a GET to obtain the feed
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // Stream of OAuth Header params
        curl_setopt($curl, CURLOPT_HTTPHEADER, array($request->to_header()));

        $feeddata = curl_exec($curl);
        $headers = curl_getinfo($curl);
        $errorNumber = curl_errno($curl);
        if (!$feeddata || $headers['http_code'] != 200) {
            // Prevent Users from seeing the really nasty errors unless thye are developers
            $feederror = curl_error($curl);
            debugging('Gmail feed (for '.$user.') failed with: '.$feederror.' '.$feeddata, DEBUG_DEVELOPER);
            $feeddata = '';
        }

        curl_close($curl);
        return $feeddata;
    }
}

