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

require_once(__DIR__ . '/submit.php');

/**
 * Manager of BACS submits
 *
 * @package    mod_bacs
 * @copyright  2024 BACS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submits_manager {
    /** @var DateTimeZone Preferred timezone */
    private DateTimeZone $timezone;

    /**
     * Construct
     *
     * @param DateTimeZone $timezone Preferred timezone
     */
    public function __construct(DateTimeZone $timezone) {
        $this->timezone = $timezone;
    }

    /**
     * Get courses filtered by availability
     *
     * @return array
     */
    public static function get_available_courses(): array {
        return array_values(array_filter(get_courses(), fn($course) => self::is_course_available($course->id)));
    }

    /**
     * Check if course is available for current user
     *
     * @param int $courseid
     * @return bool
     * @throws coding_exception
     */
    private static function is_course_available(int $courseid): bool {
        return has_capability('mod/bacs:viewany', context_course::instance($courseid));
    }

    /**
     * Get all submits in given time period from given course
     *
     * @param DateTime $from Minimum submits time
     * @param DateTime $to Maxmimum submits time
     * @param int|null $courseid Specific course ID, null if any
     * @return array Filtered submit objects
     * @throws dml_exception
     */
    public function get_filtered(DateTime $from, DateTime $to, int|null $courseid = null): array {
        global $DB;

        // Form SQL query with filtering.
        $courseidfilter = "";
        if (!is_null($courseid)) {
            $courseidfilter = " AND (contest.course = $courseid)";
        }

        $sql =
            "SELECT submit.id AS id, submit_time, contest.course AS course_id
             FROM {bacs_submits} submit
             JOIN {bacs} contest ON submit.contest_id = contest.id
             WHERE (submit_time BETWEEN {$from->getTimestamp()} AND {$to->getTimestamp()}) $courseidfilter";

        return array_map(
            fn($submit) => new submit((new DateTime("@$submit->submit_time"))->setTimezone($this->timezone), $submit->course_id),
            $DB->get_records_sql($sql)
        );
    }

    /**
     * Get all submits in given time period from given course that are also available for current user
     *
     * @param DateTime $from Minimum submits time
     * @param DateTime $to Maxmimum submits time
     * @param int|null $courseid Specific course ID, null if any
     * @return array Filtered submit objects
     * @throws dml_exception
     */
    public function get_available_filtered(DateTime $from, DateTime $to, int|null $courseid = null): array {
        return array_values(array_filter(
            $this->get_filtered($from, $to, $courseid),
            fn($submit) => self::is_course_available($submit->courseid)
        ));
    }
}
