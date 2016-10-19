<?php

namespace report_activity;
defined('MOODLE_INTERNAL') || die();

class activity_observers {
	
	public static function course_module_deleted($event) {
		global $DB;

		$DB->delete_records('report_activity_visibility', array('moduleid' => $event->objectid));
	}
	
	public static function course_content_deleted($event) {
		global $DB;

		$DB->delete_records('report_activity_visibility', array('courseid' => $event->objectid));
	}

}