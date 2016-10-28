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
 * activity report
 *
 * @package    report_activity
 * @copyright  2016 Aleksandr Raetskiy <ksenon3@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_activity\event;
defined('MOODLE_INTERNAL') || die();

class report_viewed extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    public static function get_name() {
        return get_string('key1', 'report_activity');
    }

    public function get_description() {
        return "The user with id '$this->userid' viewed the activity report for the course with id '$this->courseid'.";
    }

    public function get_url() {
        return new \moodle_url('/report/activity/index.php', array('id' => $this->courseid));
    }

}
