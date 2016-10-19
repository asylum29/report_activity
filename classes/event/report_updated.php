<?php

namespace report_activity\event;
defined('MOODLE_INTERNAL') || die();

class report_updated extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
		$this->data['objecttable'] = 'report_activity_visibility';
    }

    public static function get_name() {
        return get_string('key22', 'report_activity');
    }

    public function get_description() {
		return "The user with id {$this->userid} had changed visibility of the course module with id {$this->data['objectid']} in the course with id {$this->courseid}.";
    }

    public function get_url() {
        return new \moodle_url('/report/activity/index.php', array('id' => $this->courseid));
    }

}