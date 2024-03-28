<?php

global $OUTPUT, $PAGE, $DB, $CFG;

require_once(dirname(__FILE__, 2) . '/config.php');
require_once("{$CFG->libdir}/formslib.php");


// Set up page
$PAGE->set_url(new moodle_url('/bacs_charts/chart1.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_heading("Submits amount distribution during the day");
$PAGE->set_title("Sumbits during the day");
$PAGE->set_pagelayout("standard");

require_login();


// Filter form
class filter_form extends moodleform {
    private DateTime $default_from, $default_to;
    private $default_course_id = 'all';

    function __construct()
    {
        // Setting default filter values
        $this->default_from = new DateTime("now", core_date::get_server_timezone_object());
        $this->default_from->setTime(0, 0, 0); // From the start of today

        $this->default_to = new DateTime("now", core_date::get_server_timezone_object()); // Till now
        $ceil_seconds = 60 - (int)$this->default_to->format("s");
        $this->default_to->add(new DateInterval("PT{$ceil_seconds}S")); // Ceil to nearest minute

        parent::__construct();
    }

    // Get form data, defaults returned if there's no submitted data
    function get_data(): object {
        $form_data = parent::get_data();
        if(!$form_data) $form_data = (object)[
            'from' => $this->default_from->getTimestamp(),
            'to' => $this->default_to->getTimestamp(),
            'course_id' => $this->default_course_id
        ];
        return $form_data;
    }

    protected function definition()
    {
        $mform = $this->_form;

        // Date range selector filter (from/to)
        $mform->addElement('date_time_selector', 'from', 'From');
        $mform->setDefault('from', $this->default_from->getTimestamp());

        $mform->addElement('date_time_selector', 'to', 'To');
        $mform->setDefault('to', $this->default_to->getTimestamp());

        // Course selector
        $courses = ['all' => 'All'];
        foreach (get_available_courses() as $course){
            $courses[$course->id] = $course->shortname;
        }
        $mform->addElement('select', 'course_id', 'Course', $courses);
        $mform->setDefault('course_id', $this->default_course_id);

        // Apply filter button
        $mform->addElement('submit', 'apply_filter', 'Apply filter');
    }
}

// Check is course available for current user
function is_course_available($course_id)
{
    $context = context_course::instance($course_id);
    return has_capability('mod/bacs:viewany', $context);
}

// Get courses filtered by availability
function get_available_courses(): array
{
    $available_courses = [];

    $courses = get_courses();
    foreach($courses as $course) {
        if (is_course_available($course->id))
            $available_courses[] = $course;
    }

    return $available_courses;
}


function get_filtered_submits($form_data): array
{
    global $DB;

    // Form SQL query with filtering
    $course_id_filter = "";
    if($form_data->course_id != 'all') $course_id_filter = " AND (course.id = $form_data->course_id)";
    $sql =
        "SELECT submit.id AS id, course.id AS course_id, submit_time
         FROM {bacs_submits} submit
         JOIN {bacs} contest ON submit.contest_id = contest.id  
         JOIN {course} course ON contest.course = course.id
         WHERE (submit_time BETWEEN $form_data->from AND $form_data->to) $course_id_filter";

    // Filter submits by availability
    $submits = [];
    foreach ($DB->get_records_sql($sql) as $submit) {
        if (is_course_available($submit->course_id))
            $submits[] = $submit;
    }

    return $submits;
}


function generate_chart($form_data): \core\chart_bar
{
    $labels = []; // Chart labels
    $submits_per_hour = []; // Diagram values

    // Iterate over all 24 hours
    $day_period = new DatePeriod(
        new DateTime("00:00"),
        new DateInterval('PT1H'),
        new DateTime("24:00")
    );
    foreach ($day_period as $date) {
        $labels[] = $date->format("H:i"); // Hour xx:00
        $submits_per_hour[$date->format("H")] = 0; //
    }

    // Gather filtered submits from DB
    $submits = get_filtered_submits($form_data);


    foreach ($submits as $submit){
        $submits_per_hour[date("H", $submit->submit_time)]++;
    }

    $chart = new \core\chart_bar();
    $series = new \core\chart_series("Submits", array_values($submits_per_hour));
    $chart->set_labels($labels);
    $chart->add_series($series);

    return $chart;
}



// Render page

// Creating filter form instance
$filter_form = new filter_form();

echo $OUTPUT->header();

// Render filter form
$filter_form->display();

// Render diagram
echo $OUTPUT->render(generate_chart($filter_form->get_data()));

echo $OUTPUT->footer();
