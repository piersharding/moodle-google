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
 * OAuth library
 * @package   grade/export/fusion
 * @copyright 2010 Moodle Pty Ltd (http://moodle.com)
 * @author    Piers Harding
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/googleapi.php');



/**
 * OAuth 2.0 client for Google Services
 *
 * @package   core
 * @copyright 2012 Dan Poltawski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class google_fusion_oauth extends google_oauth {
}


class fusion_grade_export_oauth_fusion_exception extends moodle_exception { };

class fusion_grade_export_oauth_fusion {

    private $scope = 'https://www.googleapis.com/auth/fusiontables';
    private $api = 'https://www.google.com/fusiontables/api/query';
    private $googleapi = false;

    public function __construct($googleapi) {
        $this->googleapi = $googleapi;
    }


    public function getCSV($url, $parameters = array()) {

        $response = $this->googleapi->get($url, $parameters);
        if (empty($response)) {
            return null;
        }
        $lines = array();
        foreach (explode("\n", $response) as $row) {
            if ($row) {
                $lines[]=  str_getcsv($row, ',', '');
            }
        }
        return $lines;
    }

    public function getCSVTable($url, $parameters = array()) {

        $data = $this->getCSV($url, $parameters);
        if (empty($data)) {
            return null;
        }
        $header = array_shift($data);
        $table = array();
        foreach ($data as $row) {
            $table[]= array_combine($header, $row);
        }
        return $table;
    }

    /**
     * Add a site into the site directory
     * @return array of tables - table id/name
     */
    public function show_tables() {
        return $this->getCSVTable($this->api, array('sql' => "SHOW TABLES"));
    }

    /**
     * Add a site into the site directory
     * @return array of tables - table id/name
     */
    public function table_exists($name) {
        $tables = $this->getCSVTable($this->api, array('sql' => "SHOW TABLES"));
        foreach ($tables as $table) {
            if ($table['name'] == $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a site into the site directory
     * @return array of tables - table id/name
     */
    public function desc_table($id) {
        return $this->getCSVTable($this->api, array('sql' => "DESCRIBE ".$id));
    }

    /**
     * Add a site into the site directory
     * @return array of tables - table id/name
     */
    public function table_by_name($name, $hdr=false) {
        $tables = $this->getCSVTable($this->api, array('sql' => "SHOW TABLES"));
        foreach ($tables as $table) {
            if ($table['name'] == $name) {
                if ($hdr) {
                    return $table;
                }
                else {
                    return $this->desc_table($table['table id']);
                }
            }
        }
        return false;
    }

    /**
     * Add a site into the site directory
     * @return array of tables - table id/name
     */
    public function create_table($tablename, $fields) {
        $columns = array();
        foreach ($fields as $name => $type) {
            $columns[]= "$name: $type";
        }
        $table_def = "CREATE TABLE '".$tablename."' (".implode(", ", $columns).")";
        $response = $this->googleapi->post($this->api, array('sql' => $table_def));
        return  $response;
    }


    /**
     * Add a site into the site directory
     * @return array of tables - table id/name
     */
    public function insert_rows($tablename, $rows) {

        $table = $this->table_by_name($tablename, true);
        $table_id = $table['table id'];
        $desc = $this->desc_table($table_id);
        $fields = array();
        foreach ($desc as $column) {
            $fields[]= "'".$column['name']."'";
        }

        $lines = array();
        foreach ($rows as $row) {
            $values = array();
            foreach ($row as $value) {
                $values[]= "'$value'";
            }
            $lines[]= "INSERT INTO ".$table_id." (".implode(', ', $fields).") VALUES (".implode(", ", $values).") ";
        }
        // bail if there are no lines to add
        if (empty($lines)) {
            return null;
        }
        $sql = " ".implode("; ", $lines)."; ";
        $response = $this->googleapi->post($this->api, array('sql' => $sql));
        return  $response;
    }

}
