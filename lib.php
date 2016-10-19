<?php

defined('MOODLE_INTERNAL') || die;

function report_activity_extend_navigation_course($reportnav, $course, $context) {
	if (has_capability('report/activity:view', $context)) {
		$url = new moodle_url('/report/activity/index.php', array('id' => $course->id));
		$reportnav->add(get_string('pluginname', 'report_activity'), $url, null, navigation_node::TYPE_SETTING, null, new pix_icon('i/report', ''));
	}
}