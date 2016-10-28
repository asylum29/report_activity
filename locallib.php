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

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

/****************************************/
/***************Public API***************/
/****************************************/

function report_activity_get_last_access_to_course($courseid, $userid) {
	global $DB;
	
	return $DB->get_field('user_lastaccess', 'timeaccess', array('courseid' => $courseid, 'userid' => $userid));
}

function report_activity_get_course_graders($courseid) {
	$context = context_course::instance($courseid);	
	$graders = get_enrolled_users($context, 'mod/assign:grade', null, 'u.*', null, null, null, true);
	foreach ($graders as $grader) {
		if (!is_enrolled($context, $grader, 'mod/quiz:grade', true))
			unset($graders[$grader->id]);
	}
	return count($graders) > 0 ? $graders : false;
}

function report_activity_get_assign_grades_data($modinfo, $activitygroup, $onlyvisible = false) {
	global $DB;

	$modules = $modinfo->get_instances_of('assign');
	$course = $modinfo->get_course();
	
	$result = array();
	
	foreach ($modules as $module) {
		
		$visible = report_activity_get_modvisible($module);
		if ($onlyvisible && !$visible) continue;
		$cm = context_module::instance($module->id);
		$assign = new assign($cm, $module, $course);
		$instance = $assign->get_instance();
		$moddata = new stdClass();
		
		$moddata->name = $module->name;
		$moddata->teamsubmission = $instance->teamsubmission;
		$moddata->nograde = $instance->grade == 0;
		$moddata->modvisible = $visible;
		$moddata->visible = has_capability('mod/assign:view', $cm);
		
		if ($instance->teamsubmission) { // расчет по правилам Moodle
			$moddata->participants = $assign->count_teams($activitygroup);
			$moddata->submitted = $assign->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_DRAFT) +
								  $assign->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED);
			$moddata->need_grading = $assign->count_submissions_need_grading();
		} else { // расчет по собственным правилам
			list($esql, $uparams) = get_enrolled_sql($cm, 'mod/assign:submit', $activitygroup, 'u.*', null, null, null, true);
			$info = new \core_availability\info_module($module);
			list($fsql, $fparams) = $info->get_user_list_sql(true);
			if ($fsql) $uparams = array_merge($uparams, $fparams);
			$psql = "SELECT COUNT(*) FROM {user} u JOIN ($esql) e ON u.id = e.id " . ($fsql ? "JOIN ($fsql) f ON u.id = f.id" : "");
			$moddata->participants = $DB->count_records_sql($psql, $uparams);
			
			$select = "SELECT COUNT(DISTINCT(s.userid)) ";
			$table = "FROM {assign_submission} s ";
			$ujoin = "JOIN ($esql) e ON s.userid = e.id " . ($fsql ? "JOIN ($fsql) f ON s.userid = f.id " : "");
			$where = "WHERE s.assignment = :assign AND s.timemodified IS NOT NULL AND (s.status = :stat1 OR s.status = :stat2) ";
			$sparams = array(
				'assign' => $module->instance,
				'stat1'  => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
				'stat2'  => ASSIGN_SUBMISSION_STATUS_DRAFT
			);
			$sparams = array_merge($sparams, $uparams);
			$moddata->submitted = $DB->count_records_sql($select . $table . $ujoin . $where, $sparams);
			
			$select = "SELECT COUNT(s.userid) ";
			$gjoin = "LEFT JOIN {assign_grades} g ON s.assignment = g.assignment AND s.userid = g.userid AND g.attemptnumber = s.attemptnumber ";
			$where .= "AND s.latest = 1 AND (s.timemodified >= g.timemodified OR g.timemodified IS NULL OR g.grade IS NULL)";
			$moddata->need_grading = $DB->count_records_sql($select . $table . $ujoin . $gjoin . $where, $sparams);
		}
		
		$result[$module->id] = $moddata;
		
	}
	
	return count($result) > 0 ? $result : false;
}

function report_activity_get_quiz_grades_data($modinfo, $activitygroup, $onlyvisible = false) {
	global $DB;

	$modules = $modinfo->get_instances_of('quiz');
	
	$result = array();
	
	foreach ($modules as $module) {
	
		$visible = report_activity_get_modvisible($module);
		if ($onlyvisible && !$visible) continue;
		$cm = context_module::instance($module->id);		
		$moddata = new stdClass();
		
		$moddata->name = $module->name;
		$moddata->modvisible = $visible;
		$moddata->visible = has_capability('mod/quiz:view', $cm);

		list($esql, $uparams) = get_enrolled_sql($cm, 'mod/quiz:attempt', $activitygroup, 'u.*', null, null, null, true);
		$info = new \core_availability\info_module($module);
		list($fsql, $fparams) = $info->get_user_list_sql(true);
		if ($fsql) $uparams = array_merge($uparams, $fparams);
		$psql = "SELECT COUNT(*) FROM {user} u JOIN ($esql) e ON u.id = e.id " . ($fsql ? "JOIN ($fsql) f ON u.id = f.id" : "");
		$moddata->countusers = $DB->count_records_sql($psql, $uparams);
		
		$select = "SELECT COUNT(qg.id) ";
		$table = "FROM {quiz_grades} qg ";
		$ujoin = "JOIN ($esql) e ON qg.userid = e.id " . ($fsql ? "JOIN ($fsql) f ON qg.userid = f.id " : "");
		$where = "WHERE qg.quiz = :quiz";
		$qparams = array_merge(array('quiz' => $module->instance), $uparams);
		$moddata->countgrades = $DB->count_records_sql($select . $table . $ujoin . $where, $qparams);

		$result[$module->id] = $moddata;
		
	}
	
	return count($result) > 0 ? $result : false;
}

function report_activity_set_modvisible($module, $visible) {
	global $DB;
	
	$count = $DB->count_records('report_activity_visibility', array('moduleid' => $module->id));
	if ($count > 0) {
		$DB->set_field('report_activity_visibility', 'visible', $visible, array('moduleid' => $module->id));
	} else {
		$DB->execute('INSERT INTO {report_activity_visibility} (courseid, moduleid, visible) VALUES (?, ?, ?)', array($module->course, $module->id, $visible));
	}
}

function report_activity_get_modvisible($module) {
	global $DB;
	
	$record = $DB->get_record('report_activity_visibility', array('moduleid' => $module->id));
	
	return !$record ? $module->visible : $record->visible;
}

/******************************************/
/***************Internal API***************/
/******************************************/

function report_activity_percentformat_value($value, $color = true) {
	$class = '';
	if ($color) {
		if ($value < 50) {
			$class = 'report_activity_red';
		} else if ($value < 85) {
			$class = 'report_activity_yellow';
		} else {
			$class = 'report_activity_green';
		}
	}
	return html_writer::start_span($class) . format_float($value, 2, true, true) . '%' . html_writer::end_span();
}

class report_activity_table {

	public static function create_table($class = 'table', $cellpadding = 5) {
		$table = new html_table();
		$table->attributes['class'] = $class;
		$table->cellpadding = $cellpadding;
		return $table;
	}
	
	public static function create_cell($content, $class = '') {
		$cell = new html_table_cell();
		$cell->attributes['class'] = $class;
		$cell->text = $content;
		return $cell;
	}
	
	public static function create_row($cells, $class = '') {
		$row = new html_table_row();
		$row->attributes['class'] = $class;
		$row->cells = $cells;
		return $row;
	}
	
}