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

/**
 * Filter form that allows user to select course and time period
 *
 * @package    mod_bacs
 * @copyright  2024 BACS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
