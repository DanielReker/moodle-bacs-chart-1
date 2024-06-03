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
 * State of filter: time period and selected course
 *
 * @package    mod_bacs
 * @copyright  2024 BACS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_state {
    /** @var DateTime Beginning of time period */
    public DateTime $from;

    /** @var DateTime End of time period */
    public DateTime $to;

    /** @var string Selected course ID, or 'all' if all courses are selected */
    public string $courseid;


    /**
     * Construct class instance from given parameters
     *
     * @param DateTime $from
     * @param DateTime $to
     * @param string $courseid
     */
    public function __construct(DateTime $from, DateTime $to, string $courseid = 'all') {
        $this->from = $from;
        $this->to = $to;
        $this->courseid = $courseid;
    }

    /**
     * Make class instance from stdCLass object containing same properties (from and to properties are int timestamps)
     *
     * @param stdClass $data
     * @return filter_state
     */
    public static function from_std_class(stdClass $data): filter_state {
        $from = new DateTime();
        $from->setTimestamp($data->from);
        $to = new DateTime();
        $to->setTimestamp($data->to);
        return new filter_state($from, $to, $data->courseid);
    }

    /**
     * Calculate default filter state
     * Time period is from beginning of current day till current moment
     * All courses are selected
     *
     * @param DateTimeZone $datetimezone
     * @return filter_state
     * @throws Exception
     */
    public static function get_default(DateTimeZone $datetimezone): filter_state {
        $from = new DateTime("now", $datetimezone);
        $from->setTime(0, 0); // From the start of today.
        $to = new DateTime("now", $datetimezone); // Till now.
        $ceilseconds = 60 - (int)$to->format("s");
        $to->add(new DateInterval("PT{$ceilseconds}S")); // Ceil to nearest minute.
        return new filter_state($from, $to);
    }
}
