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
 * This file contains the tracking reporting, based on tool_uploadcourse 2013 Frédéric Massart.
 *
 * @package   tool_uploadpage
 * @copyright 2019-2020 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/weblib.php');

/**
 * The tracking reporting class.
 *
 * @package   tool_uploadpage
 * @copyright 2019-2020 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploadpage_tracker {

    /**
     * Constant to output nothing.
     */
    const NO_OUTPUT = 0;

    /**
     * Constant to output HTML.
     */
    const OUTPUT_HTML = 1;

    /**
     * Constant to output plain text.
     */
    const OUTPUT_PLAIN = 2;

    /**
     * @var array columns to display.
     */
    protected $columns = array('line', 'result', 'id', 'shortname', 'fullname', 'idnumber', 'status');

    /**
     * @var int row number.
     */
    protected $rownb = 0;

    /**
     * @var int chosen output mode.
     */
    protected $outputmode;

    /**
     * @var object output buffer.
     */
    protected $buffer;

    /**
     * Constructor.
     *
     * @param int $outputmode desired output mode.
     * @param object $passthrough do we print output as well as buffering it.
     *
     */
    public function __construct($outputmode = self::NO_OUTPUT, $passthrough = null) {
        $this->outputmode = $outputmode;
        if ($this->outputmode == self::OUTPUT_PLAIN) {
            $this->buffer = new progress_trace_buffer(new text_progress_trace(), $passthrough);
        }

        if ($this->outputmode == self::OUTPUT_HTML) {
            $this->buffer = new progress_trace_buffer(new text_progress_trace(), $passthrough);
        }
    }

    /**
     * Output the results.
     *
     * @param int $total total courses.
     * @param int $created count of courses created.
     * @param int $updated count of courses updated.
     * @param int $deleted count of courses deleted.
     * @param int $nochange count of courses unchanged.
     * @param int $errors count of errors.
     * @return void
     */
    public function results($total, $created, $updated, $deleted, $nochange, $errors) {
        if ($this->outputmode == self::NO_OUTPUT) {
            return;
        }

        $message = array(
            get_string('coursestotal', 'tool_uploadpage', $total),
            get_string('coursescreated', 'tool_uploadpage', $created),
            get_string('coursesupdated', 'tool_uploadpage', $updated),
            get_string('coursesdeleted', 'tool_uploadpage', $deleted),
            get_string('coursesnotupdated', 'tool_uploadpage', $nochange),
            get_string('courseserrors', 'tool_uploadpage', $errors)
        );

        if ($this->outputmode == self::OUTPUT_PLAIN) {
            foreach ($message as $msg) {
                $this->buffer->output($msg);
            }
        }

        if ($this->outputmode == self::OUTPUT_HTML) {
            $buffer = new progress_trace_buffer(new html_list_progress_trace());
            foreach ($message as $msg) {
                $buffer->output($msg);
            }
            $buffer->finished();
        }
    }

    /**
     * Get the outcome indicator
     *
     * @param bool $outcome success or not?
     * @return object
     */
    private function getoutcomeindicator($outcome) {
        global $OUTPUT;

        switch ($this->outputmode) {
            case self::OUTPUT_PLAIN:
                return $outcome ? 'OK' : 'NOK';
            case self::OUTPUT_HTML:
                return $outcome ? $OUTPUT->pix_icon('i/valid', '') : $OUTPUT->pix_icon('i/invalid', '');
            default:
               return;
        }
    }

    /**
     * Write a HTML table cell
     *
     * @param object $message
     * @param int $column
     * @return void
     */
    private function writehtmltablecell($message, $column) {
        $this->buffer->output(html_writer::tag('td',
            $message,
            array('class' => 'c' . $column)
        ));
    }

    /**
     * Write a HTML table column header
     *
     * @param string $message
     * @param int $column
     * @return void
     */
    private function writehtmltableheader($message, $column) {
        $this->buffer->output(html_writer::tag('th',
            $message,
            array('class' => 'c' . $column,
            'scope' => 'col'
            )
        ));
    }

    /**
     * Write a HTML table row start
     *
     * @param int $row
     * @return void
     */
    private function writehtmltablerowstart($row) {
        $this->buffer->output(html_writer::start_tag('tr',
                                array('class' => 'r' . $row))
                            );
    }

    /**
     * Write a HTML table row close
     *
     * @return void
     */
    private function writehtmltablerowend() {
        $this->buffer->output(html_writer::end_tag('tr'));
    }

    /**
     * Write a HTML table start
     *
     * @param string $summary
     * @return void
     */
    private function writehtmltablestart($summary = null) {
        $this->buffer->output(html_writer::start_tag('table',
        array('class' => 'generaltable boxaligncenter flexible-wrap',
        'summary' => $summary)));
    }

    private function writehtmltableend() {
        $this->buffer->output(html_writer::end_tag('table'));
    }

    /**
     * Output one more line.
     *
     * @param int $line line number.
     * @param bool $outcome success or not?
     * @param array $status array of statuses.
     * @param object $data extra data to display.
     * @return void
     */
    public function output($line, $outcome, $status, $data) {
        if ($this->outputmode == self::NO_OUTPUT) {
            return;
        }

        $message = array(
            $line,
            self::getoutcomeindicator($outcome),
            isset($data->id) ? $data->id : '',
            isset($data->shortname) ? $data->shortname : '',
            isset($data->fullname) ? $data->fullname : '',
            isset($data->idnumber) ? $data->idnumber : ''
        );

        if ($this->outputmode == self::OUTPUT_PLAIN) {
            $this->buffer->output(implode("\t", $message));
            $this->buffer->output(implode("\t  ", $status));
        }

        if ($this->outputmode == self::OUTPUT_HTML) {
            $ci = 0;
            $this->rownb++;
            $this->writehtmltablerowstart($this->rownb % 2);
            $this->writehtmltablecell($message[0], $ci++);
            $this->writehtmltablecell($message[1], $ci++);
            $this->writehtmltablecell($message[2], $ci++);
            $this->writehtmltablecell($message[3], $ci++);
            $this->writehtmltablecell($message[4], $ci++);
            $this->writehtmltablecell($message[5], $ci++);
            $this->writehtmltablecell(implode(html_writer::empty_tag('br'), $status), $ci++);
            $this->writehtmltablerowend();
        }
    }

    /**
     * Start the output.
     *
     * @return void
     */
    public function start() {
        if ($this->outputmode == self::NO_OUTPUT) {
            return;
        }

        if ($this->outputmode == self::OUTPUT_PLAIN) {
            $columns = array_flip($this->columns);
            unset($columns['status']);
            $columns = array_flip($columns);
            $this->buffer->output(implode("\t", $columns));
        }

        if ($this->outputmode == self::OUTPUT_HTML) {
            $ci = 0;
            $this->writehtmltablestart(get_string('uploadpageresult', 'tool_uploadpage'));
            $this->writehtmltablerowstart(0);
            $this->writehtmltableheader(get_string('csvline', 'tool_uploadpage'), $c++);
            $this->writehtmltableheader(get_string('result', 'tool_uploadpage'), $c++);
            $this->writehtmltableheader(get_string('id', 'tool_uploadpage'), $c++);
            $this->writehtmltableheader(get_string('shortname'), $c++);
            $this->writehtmltableheader(get_string('fullname'), $c++);
            $this->writehtmltableheader(get_string('idnumber'), $c++);
            $this->writehtmltableheader(get_string('status'), $c++);
            $this->writehtmltablerowend();
        }
    }

    /**
     * Finish the output.
     *
     * @return void
     */
    public function finish() {
        if ($this->outputmode == self::NO_OUTPUT) {
            return;
        }

        if ($this->outputmode == self::OUTPUT_HTML) {
            $this->writehtmltableend();
        }
    }

    /**
     * Return text buffer.
     * @return string buffered plain text
     */
    public function get_buffer() {
        if ($this->outputmode == self::NO_OUTPUT) {
            return "";
        }
        return $this->buffer->get_buffer();
    }

}
