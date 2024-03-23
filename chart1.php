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
    protected function definition()
    {
        $mform = $this->_form;

        // Date range selector filter (from/to)
        $mform->addElement("date_selector", "from", "From");
        $mform->addElement("date_selector", "to", "To");

        // Contest ID filter (assuming 0 means "all contests", i.e. no filter)
        $mform->addElement("text", "contest_id", "Contest ID (0 for any)");
        $mform->setType("contest_id", PARAM_INT );
        $mform->setDefault("contest_id", 228);

        // Apply filter button
        $mform->addElement('submit', 'apply_filter', "Apply filter");
    }
}

// Setting default filter values
$default_form_data = (object)[
    'from' => $DB->get_field_sql("SELECT MIN(submit_time) FROM mdl_bacs_submits_copy"),
    'to' => $DB->get_field_sql("SELECT MAX(submit_time) FROM mdl_bacs_submits_copy"),
    'contest_id' => 0
];

// Creating filter form instance
$mform = new filter_form();

// Gather form data, set to default if there's no yet
$mform->set_data($default_form_data);
$form_data = $mform->get_data();
if(!$form_data) $form_data = $default_form_data;


function generate_chart()
{
    global $DB, $form_data;
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

echo $OUTPUT->header();

// Render filter form
$mform->display();

// Render diagram
echo $OUTPUT->render(generate_chart());

echo $OUTPUT->footer();
