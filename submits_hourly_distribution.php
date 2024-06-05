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

require(__DIR__ . '/../config.php');
global $OUTPUT, $PAGE, $CFG;
require_once(__DIR__ . '/filter_form.php');
require_once(__DIR__ . '/chart_hourly_distribution.php');
require_once(__DIR__ . '/submits_manager.php');

// Set up page.
$PAGE->set_url(new moodle_url('/bacs_charts/submits_hourly_distribution.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_heading("Submits amount distribution during the day");
$PAGE->set_title("Sumbits during the day");
$PAGE->set_pagelayout("standard");

require_login();

// Controller logic.

$timezone = core_date::get_server_timezone_object(); // Preferred timezone.
$submitsmanager = new submits_manager($timezone); // Submits manager.
$filterform = new filter_form(submits_manager::get_available_courses(), $timezone); // Filter form.

// Make chart.
$chart = new chart_hourly_distribution();
$filterstate = $filterform->get_filter_state();
$submits = $submitsmanager->get_available_filtered(
    $filterstate->from,
    $filterstate->to,
    $filterstate->courseid
);
$chart->add_date_times(
    array_map(fn($submit) => $submit->time, $submits),
    "Submits"
);

// Render page.
echo $OUTPUT->header();
$filterform->display();
echo $chart->render_by($OUTPUT);
echo $OUTPUT->footer();
