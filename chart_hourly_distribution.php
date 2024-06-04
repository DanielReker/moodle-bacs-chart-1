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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . "/formslib.php");
require_once(__DIR__ . '/filter_state.php');

use core\chart_bar;
use core\chart_series;

/**
 * Chart of data hourly distribution, actually a Moodle chart_bar wrapper
 *
 * @package    mod_bacs
 * @copyright  2024 BACS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_hourly_distribution {
    /** @var DatePeriod DateTime objects for each of 24 hours from 00:00 to 23:00 */
    private DatePeriod $dayhours;

    /** @var chart_bar Underlying bar chart object */
    private chart_bar $chart;

    /**
     * Construct empty hourly distribution chart
     */
    public function __construct() {
        $this->dayhours = new DatePeriod(
            new DateTime("00:00"),
            new DateInterval('PT1H'),
            new DateTime("24:00")
        );

        $labels = []; // Chart labels.
        foreach ($this->dayhours as $hour) {
            $labels[] = $hour->format("H:i"); // Hour xx:00.
        }

        $this->chart = new chart_bar();
        $this->chart->set_labels($labels); // Labels of chart are 00:00, 01:00, ..., 23:00.
    }

    /**
     * Add series of DateTime objects to chart
     *
     * @param array $datetimeseries Array of DateTime objects
     * @param string $seriesname Name of given series
     * @return void
     */
    public function add_date_times(array $datetimeseries, string $seriesname): void {
        $values = [];

        // Nullify each hour value.
        foreach ($this->dayhours as $hour) {
            $values[$hour->format("H")] = 0;
        }

        foreach ($datetimeseries as $datetime) {
            $values[$datetime->format("H")]++; // Get hour of given DateTime.
        }
        $series = new chart_series($seriesname, array_values($values));
        $this->chart->add_series($series);
    }

    /**
     * Render chart with given renderer
     *
     * @param renderer_base $renderer Renderer to render with
     * @return string Rendered chart
     * @throws coding_exception
     */
    public function render_by(renderer_base $renderer): string {
        return $renderer->render($this->chart);
    }
}
