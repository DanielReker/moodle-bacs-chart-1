<?php


defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . "/formslib.php");
require_once(__DIR__ . '/filter_state.php');

use core\chart_bar;
use core\chart_series;



class chart_hourly_distribution {
    private DatePeriod $dayhours;
    private chart_bar $chart;

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

    public function add_date_times(array $datetimeseries, string $seriesname): void {
        $values = [];

        // Nullify each hour value.
        foreach ($this->dayhours as $hour) {
            $values[$hour->format("H")] = 0;
        }

        foreach ($datetimeseries as $datetime) {
            $values[$datetime->format("H")]++; // Get hour of given DateTime.
        }
        $series = new chart_series("Submits", array_values($values));
        $this->chart->add_series($series);
    }

    public function render_by(renderer_base $renderer): string {
        return $renderer->render($this->chart);
    }
}
