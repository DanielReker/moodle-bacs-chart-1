<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * This page displays a histogram showing BACS submits amount distribution during the day.
 * Submits can be filtered by a simple form by course and time period.
 *
 * @package    mod_bacs
 * @copyright  2024 BACS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\chart_bar;
use core\chart_series;

require_once(dirname(__FILE__, 2) . '/config.php');
require_once(dirname(__FILE__) . '/filter_state.php');

global $OUTPUT, $PAGE, $DB, $CFG;

require_once("$CFG->libdir/formslib.php");


// Set up page.
$PAGE->set_url(new moodle_url('/bacs_charts/chart1.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_heading("Submits amount distribution during the day");
$PAGE->set_title("Sumbits during the day");
$PAGE->set_pagelayout("standard");

require_login();




/**
 * Filter form that allows user to select course and time period
 */
class filter_form extends moodleform {
    /** @var filter_state Default filter state */
    private filter_state $defaultfilterstate;

    /**
     * This overridden constructor calculates default filter values and then calls parent constructor
     *
     * @throws Exception
     */
    public function __construct() {
        $this->defaultfilterstate = filter_state::get_default(core_date::get_server_timezone_object());
        parent::__construct();
    }

    /**
     * Get form data, defaults returned if there's no submitted data
     *
     * @return filter_state
     */
    public function get_filter_state(): filter_state {
        $formdata = parent::get_data();
        if ($formdata) {
            return filter_state::from_std_class($formdata);
        } else {
            return $this->defaultfilterstate;
        }
    }

    /**
     * Definition of form
     */
    protected function definition(): void {
        $mform = $this->_form;

        // Date range selector filter (from/to).
        $mform->addElement('date_time_selector', 'from', 'From');
        $mform->setDefault('from', $this->defaultfilterstate->from->getTimestamp());

        $mform->addElement('date_time_selector', 'to', 'To');
        $mform->setDefault('to', $this->defaultfilterstate->to->getTimestamp());

        // Course selector.
        $courses = ['all' => 'All'];
        foreach (get_available_courses() as $course) {
            $courses[$course->id] = $course->shortname;
        }
        $mform->addElement('select', 'courseid', 'Course', $courses);
        $mform->setDefault('courseid', $this->defaultfilterstate->courseid);

        // Apply filter button.
        $mform->addElement('submit', 'apply_filter', 'Apply filter');
    }
}

/**
 * Check if course is available for current user
 *
 * @param int $courseid
 * @return bool
 * @throws coding_exception
 */
function is_course_available(int $courseid): bool {
    $context = context_course::instance($courseid);
    return has_capability('mod/bacs:viewany', $context);
}

/**
 * Get courses filtered by availability
 *
 * @return array
 */
function get_available_courses(): array {
    $availablecourses = [];

    $courses = get_courses();
    foreach ($courses as $course) {
        if (is_course_available($course->id)) {
            $availablecourses[] = $course;
        }
    }

    return $availablecourses;
}

/**
 * Get submits filtered by given filter state
 *
 * @param filter_state $filterstate
 * @return array
 * @throws dml_exception
 */
function get_filtered_submits(filter_state $filterstate): array {
    global $DB;

    // Form SQL query with filtering.
    $courseidfilter = "";
    if ($filterstate->courseid != 'all') {
        $courseidfilter = " AND (course.id = $filterstate->courseid)";
    }
    $sql =
        "SELECT submit.id AS id, course.id AS course_id, submit_time
         FROM {bacs_submits} submit
         JOIN {bacs} contest ON submit.contest_id = contest.id
         JOIN {course} course ON contest.course = course.id
         WHERE (submit_time BETWEEN {$filterstate->from->getTimestamp()} AND {$filterstate->to->getTimestamp()}) $courseidfilter";

    // Filter submits by availability.
    $submits = [];
    foreach ($DB->get_records_sql($sql) as $submit) {
        if (is_course_available($submit->course_id)) {
            $submits[] = $submit;
        }
    }

    return $submits;
}

/**
 * Generate renderable bar chart object by given filter state
 * @param filter_state $filterstate
 * @return chart_bar
 * @throws dml_exception
 */
function generate_chart(filter_state $filterstate): chart_bar {
    $labels = []; // Chart labels.
    $submitsperhour = []; // Diagram values.

    // Iterate over all 24 hours.
    $dayperiod = new DatePeriod(
        new DateTime("00:00"),
        new DateInterval('PT1H'),
        new DateTime("24:00")
    );
    foreach ($dayperiod as $date) {
        $labels[] = $date->format("H:i"); // Hour xx:00.
        $submitsperhour[$date->format("H")] = 0;
    }

    // Gather filtered submits from DB.
    $submits = get_filtered_submits($filterstate);


    foreach ($submits as $submit) {
        $submitsperhour[date("H", $submit->submit_time)]++;
    }

    $chart = new chart_bar();
    $series = new chart_series("Submits", array_values($submitsperhour));
    $chart->set_labels($labels);
    $chart->add_series($series);

    return $chart;
}



// Render page.

// Creating filter form instance.
$filterform = new filter_form();

echo $OUTPUT->header();

// Render filter form.
$filterform->display();

// Render diagram.
echo $OUTPUT->render(generate_chart($filterform->get_filter_state()));

echo $OUTPUT->footer();
