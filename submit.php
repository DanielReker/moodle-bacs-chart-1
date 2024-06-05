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
 * Submit made from some course at specific time
 *
 * @package    mod_bacs
 * @copyright  2024 BACS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit {
    /** @var DateTime Submit time */
    public DateTime $time;

    /** @var int ID of submit's course */
    public int $courseid;

    /**
     * Construct submit instance with given properties
     *
     * @param DateTime $time
     * @param int $courseid
     */
    public function __construct(DateTime $time, int $courseid) {
        $this->time = $time;
        $this->courseid = $courseid;
    }
}
