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
    /**
     * Default "from" time
     * @var DateTime
     */
    private DateTime $defaultfrom;

    /**
     * Default "to" time
     * @var DateTime
     */
    private DateTime $defaultto;

    /**
     * Default course ID
     * @var string
     */
    private string $defaultcourseid = 'all';

    /**
     * This overridden constructor calculates default filter values and then calls parent constructor
     * @throws Exception
     */
    public function __construct() {
        // Setting default filter values.
        $this->defaultfrom = new DateTime("now", core_date::get_server_timezone_object());
        $this->defaultfrom->setTime(0, 0); // From the start of today.
        $this->defaultto = new DateTime("now", core_date::get_server_timezone_object()); // Till now.
        $ceilseconds = 60 - (int)$this->defaultto->format("s");
        $this->defaultto->add(new DateInterval("PT{$ceilseconds}S")); // Ceil to nearest minute.

        parent::__construct();
    }

    /**
     * Get form data, defaults returned if there's no submitted data
     * @return stdClass
     */
    public function get_data(): stdClass {
        $formdata = parent::get_data();
        if (!$formdata) {
            $formdata = (object)[
                    'from' => $this->defaultfrom->getTimestamp(),
                    'to' => $this->defaultto->getTimestamp(),
                    'course_id' => $this->defaultcourseid,
            ];
        }
        return $formdata;
    }

    /**
     * Definition of form
     */
    protected function definition(): void {
        $mform = $this->_form;

        // Date range selector filter (from/to).
        $mform->addElement('date_time_selector', 'from', 'From');
        $mform->setDefault('from', $this->defaultfrom->getTimestamp());

        $mform->addElement('date_time_selector', 'to', 'To');
        $mform->setDefault('to', $this->defaultto->getTimestamp());

        // Course selector.
        $courses = ['all' => 'All'];
        foreach (get_available_courses() as $course) {
            $courses[$course->id] = $course->shortname;
        }
        $mform->addElement('select', 'course_id', 'Course', $courses);
        $mform->setDefault('course_id', $this->defaultcourseid);

        // Apply filter button.
        $mform->addElement('submit', 'apply_filter', 'Apply filter');
    }
}

/**
 * Check if course is available for current user
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
 * Get submits filtered by form data
 * @param stdClass $formdata
 * @return array
 * @throws dml_exception
 */
function get_filtered_submits(stdClass $formdata): array {
    global $DB;

    // Form SQL query with filtering.
    $courseidfilter = "";
    if ($formdata->course_id != 'all') {
        $courseidfilter = " AND (course.id = $formdata->courseid)";
    }
    $sql =
        "SELECT submit.id AS id, course.id AS course_id, submit_time
         FROM {bacs_submits} submit
         JOIN {bacs} contest ON submit.contest_id = contest.id
         JOIN {course} course ON contest.course = course.id
         WHERE (submit_time BETWEEN $formdata->from AND $formdata->to) $courseidfilter";

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
 * Generate renderable bar chart object by given form data
 * @param stdClass $formdata
 * @return chart_bar
 * @throws dml_exception
 */
function generate_chart(stdClass $formdata): chart_bar {
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
    $submits = get_filtered_submits($formdata);


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
echo $OUTPUT->render(generate_chart($filterform->get_data()));

echo $OUTPUT->footer();
