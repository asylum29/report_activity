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

require_once('../../config.php');
require_once($CFG->dirroot.'/report/activity/locallib.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

$stractivity = get_string('pluginname', 'report_activity');

$PAGE->set_url('/report/activity/index.php', array('id' => $id));

require_login($course);
$context = context_course::instance($course->id);
require_capability('report/activity:view', $context);

$PAGE->set_title("$course->shortname: $stractivity");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

$event = \report_activity\event\report_viewed::create(array('context' => $context));
$event->trigger();

$modinfo = get_fast_modinfo($course);

echo $OUTPUT->header();
echo $OUTPUT->heading($stractivity);

groups_print_course_menu($course, $PAGE->url);
$activitygroup = groups_get_course_group($course);

$hasstats = $hasgraders = false;
$toggle = has_capability('report/activity:togglevisible', $context);

$graders = report_activity_get_course_graders($id);
if (count($graders) > 0) {
    echo $OUTPUT->heading(get_string('key2', 'report_activity'), 3);

    $reporttable = report_activity_table::create_table('generaltable report_activity_gradertable', 0);
    $reporttable->head = array(
        get_string('key3', 'report_activity'), 
        get_string('key4', 'report_activity')
    );

    foreach ($graders as $grader) {
        $reportcells = array();
        
        $userurl = "$CFG->wwwroot/user/view.php?id=$grader->id&course=$id";
        $content = $OUTPUT->user_picture($grader, array('size' => 15)) . '&nbsp;' .html_writer::link($userurl, fullname($grader));
        $reportcells[] = report_activity_table::create_cell($content);
        
        $lastaccess = report_activity_get_last_access_to_course($course->id, $grader->id);
        $content = $lastaccess ? userdate($lastaccess) . '&nbsp; (' . format_time(time() - $lastaccess) . ')' : get_string('never');
        $reportcells[] = report_activity_table::create_cell($content);
        
        $reporttable->data[] = report_activity_table::create_row($reportcells);
    }

    echo html_writer::table($reporttable);
    $hasgraders = true;
}

$assignstotal = array(
    'countusers'  => 0,
    'countgrades' => 0
);
$assigns = report_activity_get_assign_grades_data($modinfo, $activitygroup, !$toggle);
if (count($assigns) > 0) {
    echo $OUTPUT->heading(get_string('key5', 'report_activity'), 3);

    $reporttable = report_activity_table::create_table('generaltable report_activity_reporttable', 5);
    $reporttable->head = array(
        get_string('key6', 'report_activity'), 
        get_string('key7', 'report_activity'), 
        get_string('key8', 'report_activity'), 
        get_string('key9', 'report_activity'),
        get_string('key10', 'report_activity'), 
        get_string('key11', 'report_activity')
    );

    $assignscalc = array('participants' => 0, 'needgrade' => 0, 'submitted' => 0, 'graded' => 0);

    foreach ($assigns as $modid => $assign) {
        $reportcells = array();
        
        $content = $OUTPUT->pix_icon('icon', '', 'assign', array('class' => 'icon')) . $assign->name;
        if ($assign->visible) {
            $assignurl = "$CFG->wwwroot/mod/assign/view.php?id=$modid";
            $content = html_writer::link($assignurl, $content);
        }
        if ($toggle) {
            $showhide = $assign->modvisible ? 'hide' : 'show';
            $toggleurl = "$CFG->wwwroot/report/activity/toggle.php?$showhide=$modid&sesskey=".sesskey();
            $icon = $OUTPUT->pix_icon("t/$showhide", get_string($showhide), '', array('class' => 'iconsmall'));
            $content .= html_writer::link($toggleurl, $icon);
        }
        if ($assign->nograde)
            $content .= $OUTPUT->pix_icon('nograde', get_string('key21', 'report_activity'), 'report_activity', array('class' => 'iconsmall'));
        if ($assign->teamsubmission) 
            $content .= $OUTPUT->pix_icon('i/users', get_string('key20', 'report_activity'), '', array('class' => 'iconsmall'));
        $reportcells[] = report_activity_table::create_cell($content);
        
        $value = '—';
        if (!$assign->teamsubmission) {
            $value = $assign->participants;
            if ($assign->modvisible) {
                $assignscalc['participants'] += $value;
                if (!$assign->nograde) {
                    $assignscalc['needgrade'] += $value;
                }
            }
        }
        $reportcells[] = report_activity_table::create_cell($value);
        
        $value = '—';
        if (!$assign->teamsubmission) {
            $value = $assign->submitted;
            if ($assign->modvisible) {
                $assignscalc['submitted'] += $value;
            }
        }
        $reportcells[] = report_activity_table::create_cell($value);
        
        $value = '—';
        if (!$assign->teamsubmission && $assign->participants > 0) {
            $percent = $assign->submitted / $assign->participants * 100;
            $value = report_activity_percentformat_value($percent, false);
        }
        $reportcells[] = report_activity_table::create_cell($value);
        
        $graded = 0;
        $value = '—';
        if (!$assign->teamsubmission && !$assign->nograde) {
            $graded = $assign->submitted - $assign->need_grading;
            $value = $graded . '&nbsp;' . ($assign->need_grading != 0 ? 
                $OUTPUT->pix_icon('alert', get_string('key12', 'report_activity'), 'report_activity', array('class' => 'icon')) : '');
            if ($assign->modvisible) {
                $assignscalc['graded'] += $graded;
            }
        }
        $reportcells[] = report_activity_table::create_cell($value);
        
        $value = '—';
        if (!$assign->teamsubmission && !$assign->nograde && $assign->participants > 0) {
            $percent = $graded / $assign->participants * 100;
            $value = report_activity_percentformat_value($percent, false);
        }
        $reportcells[] = report_activity_table::create_cell($value);
        
        $class = $assign->modvisible ? '' : 'dimmed_text';
        $reporttable->data[] = report_activity_table::create_row($reportcells, $class);
        
        if ($assign->modvisible) {
            $subval = !$assign->teamsubmission ? $assign->submitted : 0;
            $gradeval = !$assign->nograde ? $graded : $subval;
            $assignstotal['countgrades'] += ($subval + $gradeval) / 2;
        }
    }

    $reportcells = array();
    $reportcells[] = report_activity_table::create_cell(get_string('key13', 'report_activity'));
    $reportcells[] = report_activity_table::create_cell($assignscalc['participants']);
    $reportcells[] = report_activity_table::create_cell($assignscalc['submitted']);
    $value = $assignscalc['participants'] > 0 ? $assignscalc['submitted'] / $assignscalc['participants'] * 100 : 0;
    $reportcells[] = report_activity_table::create_cell(report_activity_percentformat_value($value));
    $reportcells[] = report_activity_table::create_cell($assignscalc['graded']);
    $value = $assignscalc['needgrade'] > 0 ? $assignscalc['graded'] / $assignscalc['needgrade'] * 100 : 0;
    $reportcells[] = report_activity_table::create_cell(report_activity_percentformat_value($value));
    $reporttable->data[] = report_activity_table::create_row($reportcells);

    echo html_writer::table($reporttable);
    $assignstotal['countusers'] = $assignscalc['participants'];
    $hasstats = true;
}

$quizestotal = array(
    'countusers'  => 0,
    'countgrades' => 0
);
$quizes = report_activity_get_quiz_grades_data($modinfo, $activitygroup, !$toggle);
if (count($quizes) > 0) {
    echo $OUTPUT->heading(get_string('key14', 'report_activity'), 3);

    $reporttable = report_activity_table::create_table('generaltable report_activity_reporttable', 5);
    $reporttable->head = array(
        get_string('key6', 'report_activity'), 
        get_string('key15', 'report_activity'), 
        get_string('key16', 'report_activity'), 
        get_string('key17', 'report_activity')
    );

    foreach ($quizes as $modid => $quiz) {
        $reportcells = array();

        $content = $OUTPUT->pix_icon('icon', '', 'quiz', array('class' => 'icon')) . $quiz->name;
        if ($quiz->visible) {
            $quizurl = "$CFG->wwwroot/mod/quiz/view.php?id=$modid";
            $content = html_writer::link($quizurl, $content);
        }
        if ($toggle) {
            $showhide = $quiz->modvisible ? 'hide' : 'show';
            $toggleurl = "$CFG->wwwroot/report/activity/toggle.php?$showhide=$modid&sesskey=".sesskey();
            $icon = $OUTPUT->pix_icon("t/$showhide", get_string($showhide), '', array('class' => 'iconsmall'));
            $content .= html_writer::link($toggleurl, $icon);
        }
        if ($quiz->noquestions)
            $content .= $OUTPUT->pix_icon('noquestions', get_string('key23', 'report_activity'), 'report_activity', array('class' => 'iconsmall'));
        $reportcells[] = report_activity_table::create_cell($content);
        
        $reportcells[] = report_activity_table::create_cell($quiz->countusers);
        if ($quiz->modvisible) {
            $quizestotal['countusers'] += $quiz->countusers;
        }
        
        $reportcells[] = report_activity_table::create_cell($quiz->countgrades);
        if ($quiz->modvisible) {
            $quizestotal['countgrades'] += $quiz->countgrades;
        }
        
        $value = '—';
        if ($quiz->countusers > 0) {
            $percent = $quiz->countgrades / $quiz->countusers * 100;
            $value = report_activity_percentformat_value($percent, false);
        }
        $reportcells[] = report_activity_table::create_cell($value);
        
        $class = $quiz->modvisible ? '' : 'dimmed_text';
        $reporttable->data[] = report_activity_table::create_row($reportcells, $class);
    }

    $reportcells = array();
    $reportcells[] = report_activity_table::create_cell(get_string('key13', 'report_activity'));
    $reportcells[] = report_activity_table::create_cell($quizestotal['countusers']);
    $reportcells[] = report_activity_table::create_cell($quizestotal['countgrades']);
    $value = $quizestotal['countusers'] > 0 ? $quizestotal['countgrades'] / $quizestotal['countusers'] * 100 : 0;
    $reportcells[] = report_activity_table::create_cell(report_activity_percentformat_value($value));
    $reporttable->data[] = report_activity_table::create_row($reportcells);

    echo html_writer::table($reporttable);
    $hasstats = true;
}

if ($hasstats) {
    $alltasks = $assignstotal['countusers'] + $quizestotal['countusers'];
    $allgrades = $assignstotal['countgrades'] + $quizestotal['countgrades'];
    $value = $alltasks > 0 ? $allgrades / $alltasks * 100 : 0;
    $value = report_activity_percentformat_value($value);
    echo $OUTPUT->heading(get_string('key19', 'report_activity', $value), 3);
} else if (!$hasgraders) {
    echo $OUTPUT->heading(get_string('key18', 'report_activity'), 3);
}

echo $OUTPUT->footer();
