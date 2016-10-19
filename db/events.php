<?php

defined('MOODLE_INTERNAL') || die();

$observers = array(

	array(
		'eventname' => '\core\event\course_content_deleted',
		'callback' => '\report_activity\activity_observers::course_content_deleted',
	),
	array(
		'eventname' => '\core\event\course_module_deleted',
		'callback'  => '\report_activity\activity_observers::course_module_deleted'
	)

);
