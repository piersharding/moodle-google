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
 * GMail Block Global Settings
 *
 * @author Chris Stones
 *         based off Mark's code
 * @version $Id$
 * @package block_gmail
 **/

defined('MOODLE_INTERNAL') or die();
require_once "{$CFG->libdir}/adminlib.php";

$configs = array();

$configs[] = new admin_setting_configtext('domainname', get_string('domainnamestr', 'block_gmail'), get_string('domainnameinfo', 'block_gmail'), '', PARAM_RAW, 30);

$configs[] = new admin_setting_configtext('username', get_string('usernamestr', 'block_gmail'), get_string('usernameinfo', 'block_gmail'), 'username', PARAM_RAW, 30);

$configs[] = new admin_setting_configpasswordunmask('oauthsecret', get_string('oauthsecretstr', 'block_gmail'), get_string('oauthsecretinfo', 'block_gmail'), '');

$configs[] = new admin_setting_configtext('msgnumber', get_string('msgnumberunread', 'block_gmail'), get_string('msgnumberunreadinfo', 'block_gmail'), '0', PARAM_RAW, 5);

// Open links in new window
$configs[] = new admin_setting_configcheckbox('newwinlink', get_string('newwinlink', 'block_gmail'), get_string('newwinlinkinfo', 'block_gmail'), '1');

// Choose to Show First and Last Names to Save Space
$configs[] = new admin_setting_configcheckbox('showfirstname', get_string('showfirstname', 'block_gmail'), get_string('showfirstnameinfo', 'block_gmail'), '1');

$configs[] = new admin_setting_configcheckbox('showlastname', get_string('showlastname', 'block_gmail'), get_string('showlastnameinfo', 'block_gmail'), '1');

// Define the config plugin so it is saved to
// the config_plugin table then add to the settings page
foreach ($configs as $config) {
    $config->plugin = 'blocks/gmail';
    $settings->add($config);
}
