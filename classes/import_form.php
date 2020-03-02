<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the form add/update a page framework.
 *
 * @package   tool_uploadpage
 * @copyright 2019-2020 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
require_once($CFG->libdir.'/formslib.php');

/**
 * The form for add/update a page framework.
 *
 * @package   tool_uploadpage
 * @copyright 2019-2020 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploadpage_import_form extends moodleform {

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $mform = $this->_form;
        $element = $mform->createElement('filepicker', 'importfile', get_string('importfile', 'tool_uploadpage'));
        $mform->addElement($element);
        $mform->addRule('importfile', null, 'required');

        $mform->addElement('hidden', 'confirm', 0);
        $mform->setType('confirm', PARAM_BOOL);

        $mform->addElement('hidden', 'needsconfirm', 1);
        $mform->setType('needsconfirm', PARAM_BOOL);

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploadpage'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }
        $mform->addHelpButton('delimiter_name', 'csvdelimiter', 'tool_uploadpage');

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploadpage'), $choices);
        $mform->setDefault('encoding', 'UTF-8');
        $mform->addHelpButton('encoding', 'encoding', 'tool_uploadpage');

        $this->add_action_buttons(false, get_string('import', 'tool_uploadpage'));
    }

    /**
     * Set the error message
     *
     * @param string $msg
     * @return void
     */
    public function set_import_error($msg) {
        $mform = $this->_form;

        $mform->setElementError('importfile', $msg);
    }

}
