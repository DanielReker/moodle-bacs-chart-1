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
    private int $default_contest_id;

    function __construct()
    {
        // Setting default filter values
        $this->default_from = new DateTime("now", core_date::get_server_timezone_object());
        $this->default_from->setTime(0, 0, 0); // From the start of today

        $this->default_to = new DateTime("now", core_date::get_server_timezone_object()); // Till now
        $ceil_seconds = 60 - (int)$this->default_to->format("s");
        $this->default_to->add(new DateInterval("PT{$ceil_seconds}S")); // Ceil to nearest minute

        $this->default_contest_id = 0;

        parent::__construct();
    }

    // Get form data, defaults returned if there's no submitted data
    function get_data() : object {
        $form_data = parent::get_data();
        if(!$form_data) $form_data = (object)[
            'from' => $this->default_from->getTimestamp(),
            'to' => $this->default_to->getTimestamp(),
            'contest_id' => $this->default_contest_id
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

        // Contest ID filter (assuming 0 means "all contests", i.e. no filter)
        $mform->addElement('text', 'contest_id', 'Contest ID (0 for any)');
        $mform->setType('contest_id', PARAM_INT );
        $mform->setDefault('contest_id', $this->default_contest_id);

        // Apply filter button
        $mform->addElement('submit', 'apply_filter', 'Apply filter');
    }
}


function generate_chart($form_data)
{
    global $DB;
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

    // Form SQL query with filtering
    $sql_query_filtered_submits = "SELECT id, submit_time
                FROM mdl_bacs_submits_copy
                WHERE (submit_time BETWEEN {$form_data->from} AND {$form_data->to})";
    if($form_data->contest_id != 0) $sql_query_filtered_submits .= " AND (contest_id = {$form_data->contest_id})";

    $submits = $DB->get_records_sql($sql_query_filtered_submits);
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
