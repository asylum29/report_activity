<?php

require_once('../../config.php');
require_once($CFG->dirroot.'/report/activity/locallib.php');

$show = optional_param('show', 0, PARAM_INT);
$hide = optional_param('hide', 0, PARAM_INT);
$id = $show ? $show : $hide;

list($course, $module) = get_course_and_cm_from_cmid($id);
if ($module->modname != 'assign' && $module->modname != 'quiz') print_error('error');
require_login($course);

$context = context_course::instance($course->id);
require_capability('report/activity:view', $context);
require_capability('report/activity:togglevisible', $context);

if ($show && confirm_sesskey()) {
    if (!report_activity_get_modvisible($module)) {
		report_activity_set_modvisible($module, 1);
		\report_activity\event\report_updated::create(array('context' => $context, 'objectid' => $module->id))->trigger();
	}
} else if ($hide && confirm_sesskey()) {
    if (report_activity_get_modvisible($module)) {
		report_activity_set_modvisible($module, 0);
		\report_activity\event\report_updated::create(array('context' => $context, 'objectid' => $module->id))->trigger();
	}
}

$redirecturl = new moodle_url('/report/activity/index.php');
$redirecturl->param('id', $course->id);
redirect($redirecturl);