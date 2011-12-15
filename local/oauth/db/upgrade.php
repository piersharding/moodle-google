<?php
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// This file is part of Moodle - http://moodle.org/                      //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//                                                                       //
// Moodle is free software: you can redistribute it and/or modify        //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation, either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// Moodle is distributed in the hope that it will be useful,             //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details.                          //
//                                                                       //
// You should have received a copy of the GNU General Public License     //
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.       //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/**
 * Upgrade code for the oauth plugin
 * @package   localoauth
 * @copyright 2010 Moodle Pty Ltd (http://moodle.com)
 * @author    Piers Harding
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_local_oauth_upgrade($oldversion) {
    global $CFG, $USER, $DB, $OUTPUT;

    require_once($CFG->libdir.'/db/upgradelib.php'); // Core Upgrade-related functions

    $result = true;

    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes


    if ($result && $oldversion < 2010060903) {
    /// Define field sitecourseid to be added to oauth_site_directory
        $table = new xmldb_table('oauth_access_token');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('siteid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('access_token', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            $table->add_index('siteid', XMLDB_INDEX_NOTUNIQUE, array('siteid'));
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        }

        if (! $DB->get_record('oauth_site_directory', array('name' => 'google.com')) {
            // create a default entry for all Google Fusion OAuth integration
            $record = new object();
            $record->name                = 'google.com';
            $record->request_token_url   = 'https://www.google.com/accounts/OAuthGetRequestToken';
            $record->authorize_token_url = 'https://www.google.com/accounts/OAuthAuthorizeToken';
            $record->access_token_url    = 'https://www.google.com/accounts/OAuthGetAccessToken';
            $record->consumer_key        = '<your consumer key>';
            $record->consumer_secret     = '<your consumer secret>';
            $record->enabled             = '0';
            $DB->insert_record('oauth_site_directory', $record);
        }

        if (! $DB->get_record('oauth_site_directory', array('name' => 'googledocs.com')) {
            // create a default entry for all Google Docs OAuth integration
            $record = new object();
            $record->name                = 'googledocs.com';
            $record->request_token_url   = 'https://www.google.com/accounts/OAuthGetRequestToken';
            $record->authorize_token_url = 'https://www.google.com/accounts/OAuthAuthorizeToken';
            $record->access_token_url    = 'https://www.google.com/accounts/OAuthGetAccessToken';
            $record->consumer_key        = '<your consumer key>';
            $record->consumer_secret     = '<your consumer secret>';
            $record->enabled             = '0';
            $DB->insert_record('oauth_site_directory', $record);
        }

    /// oauth savepoint reached
        upgrade_plugin_savepoint($result, 2010060903, 'local', 'oauth');
    }

    return $result;
}
