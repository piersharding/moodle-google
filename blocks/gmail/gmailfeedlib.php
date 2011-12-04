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
 * Google GMail Atom Feed (Still no OAuth Access)
 *
 * Accessing the GMail Atom feed with OAuth enables real time updating
 * and no relience on remembering a users password at login.
 * 
 * I don't believe Zend Gdata api's have support for OAuth just yet. or it's buggy in it's current form.
 * 
 * Helpful docs:
 *    - http://code.google.com/apis/accounts/docs/OAuth.html
 *   - http://framework.zend.com/manual/en/zend.gdata.html#zend.gdata.introduction.getfeed
 *   - http://framework.zend.com/manual/en/zend.http.response.html (we want the raw response)
 * 
 *   - adding a gmail GApp
 *   - http://framework.zend.com/manual/en/zend.gdata.html#zend.gdata.introduction.creation
 * 
 *   Might Try
 * @link   http://code.google.com/apis/accounts/AuthForWebApps.html
 * 
 * @author Chris Stones
 * @version $Id$
 * @package block_gmail
 **/

// http://oauth.googlecode.com/svn/code/php/
// http://groups.google.com/group/google-apps-apis/browse_thread/thread/3faf624fd4412e95

function set_gmail_feed($username,$domain,$password) {
    global $CFG,$USER,$SESSION; // I'm expecting session to be set by now But it might get reset...

    // TODO: if moodle user is not the same as google user use the mapping

    // https request the link
    // https://mail.google.com/mail/feed/atom
    $username_dom = $username.'@'.$domain;//'cstones@mroomsdev.com';
    $username_dom = urlencode($username_dom);
    $password = $password;//"a344616";

    // Init cURL
    $url = 'https://'.$username_dom.':'.$password.'@mail.google.com/mail/feed/atom';
    $c = curl_init($url);

    $headers = array(
        "Host: mail.google.com",
        "Date: ".date(DATE_RFC822),
    );

    curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_ANY); // use authentication should select BASIC auth
    curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);      // need to process the results so disable direct output
    curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 1);
    curl_setopt($c, CURLOPT_UNRESTRICTED_AUTH, 1);   // always stay authorized
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 1);

    // Warn to only enable this debugging if absolutly necessary.
    if (debugging('',DEBUG_DEVELOPER) ) {  
        $SESSION->gmailfeedpw = $password;
        //curl_setopt($c, CURLOPT_HEADER, true); // include headers for debugging
    }

    $str = curl_exec($c); // Get it
    $SESSION->gmailfeed = base64_encode($str); // Store the feed data since we won't store passwords... yet

    curl_close($c); // Close the curl stream

    // TODO: add logging code when debugging is turned on
    // TODO: for DEBUG_DEVELOPER add feed watcher and refresh check
}

