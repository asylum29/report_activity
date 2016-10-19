<?php

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
