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
 * This file contains the procesing for the add/update of a single page course.
 *
 * @package   tool_uploadpage
 * @copyright 2019-2020 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

/**
 * Main processing class for adding and updating single page course.
 *
 * @package   tool_uploadpage
 * @copyright 2019-2020 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploadpage_importer {

    /**
     * @var string $error   Last error message.
     */
    protected $error = '';

    /**
     * @var array $records   The records to process.
     */
    protected $records = array();

    /**
     * @var int $importid   The import id.
     */
    protected $importid = 0;

    /**
     * @var object $importer   The importer object.
     */
    protected $importer = null;

    /**
     * @var array $foundheaders   The headers found in the import file.
     */
    protected $foundheaders = array();

    /**
     * @var object $generator   The generator used for creating the courses and activities.
     */
    protected $generator = null;

    /**
     * @var array $errors   The array of all errors identified.
     */
    protected $errors = array();

    /**
     * @var int $error   The current line number we are processing.
     */
    protected $linenb = 0;

    /**
     * @var bool $processstarted   Indicates if we have started processing.
     */
    protected $processstarted = false;

    /**
     * Return a Failure
     *
     * @param string $msg
     * @return bool Always returns false
     */
    public function fail($msg) {
        $this->error = $msg;
        return false;
    }

    /**
     * Get the importid
     *
     * @return string the import id
     */
    public function get_importid() {
        return $this->importid;
    }

    /**
     * Return the list of required headers for the import
     *
     * @return array contains the column headers
     */
    public static function list_required_headers() {
        return array(
        'COURSE_IDNUMBER',
        'COURSE_SHORTNAME',
        'COURSE_FULLNAME',
        'COURSE_SUMMARY',
        'COURSE_TAGS',
        'COURSE_VISIBLE',
        'COURSE_CATEGORYIDNUMBER',
        'COURSE_CATEGORYNAME',
        'PAGE_NAME',
        'PAGE_INTRO',
        'PAGE_CONTENT',
        );
    }

    /**
     * Retunr the list of headers found in the CSV
     *
     * @return array contains the column headers
     */
    public function list_found_headers() {
        return $this->foundheaders;
    }

    /**
     * Get the mapping array of file column position to our object values
     *
     * @param object $data
     * @return array the object key to column
     */
    private function read_mapping_data($data) {
        if ($data) {
            return array(
            'course_idnumber' => $data->header0,
            'course_shortname' => $data->header1,
            'course_fullname' => $data->header2,
            'course_summary' => $data->header3,
            'course_tags' => $data->header4,
            'course_visible' => $data->header5,
            'course_categoryidnumber' => $data->header6,
            'course_categoryname' => $data->header7,
            'page_name' => $data->header8,
            'page_intro' => $data->header9,
            'page_content' => $data->header10
            );
        } else {
            return array(
            'course_idnumber' => 0,
            'course_shortname' => 1,
            'course_fullname' => 2,
            'course_summary' => 3,
            'course_tags' => 4,
            'course_visible' => 5,
            'course_categoryidnumber' => 6,
            'course_categoryname' => 7,
            'page_name' => 8,
            'page_intro' => 9,
            'page_content' => 10
            );
        }
    }

    /**
     * Get the row of data from the CSV
     *
     * @param int $row
     * @param int $index
     * @return object
     */
    private function get_row_data($row, $index) {
        if ($index < 0) {
            return '';
        }
        return isset($row[$index]) ? $row[$index] : '';
    }

    /**
     * Constructor
     *
     * @param string $text
     * @param string $encoding
     * @param string $delimiter
     * @param integer $category
     * @param integer $importid
     * @param object $mappingdata
     */
    public function __construct($text = null, $encoding = null, $delimiter = 'comma',
                                $category=1, $importid = 0, $mappingdata = null) {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $type = 'singlepagecourse';

        if (!$importid) {
            if ($text === null) {
                return;
            }
              $this->importid = csv_import_reader::get_new_iid($type);

              $this->importer = new csv_import_reader($this->importid, $type);

            if (!$this->importer->load_csv_content($text, $encoding, $delimiter)) {
                $this->fail(get_string('invalidimportfile', 'tool_uploadpage'));
                $this->importer->cleanup();
                return;
            }
        } else {
               $this->importid = $importid;
               $this->importer = new csv_import_reader($this->importid, $type);
        }

        if (!$this->importer->init()) {
               $this->fail(get_string('invalidimportfile', 'tool_uploadpage'));
               $this->importer->cleanup();
               return;
        }

        if ($category != 1) {
              $categorycheck = tool_uploadpage_helper::resolve_category_by_id_or_idnumber($category);
            if ($categorycheck == null) {
                $this->fail(get_string('invalidimportfile', 'tool_uploadpage'));
                $this->importer->cleanup();
                return;
            } else {
                $category = $categorycheck;
            }
        }

        $this->foundheaders = $this->importer->get_columns();

        $record = null;
        $records = array();

        while ($row = $this->importer->next()) {
              $mapping = $this->read_mapping_data($mappingdata);

              $record = new \stdClass();
              $record->course_idnumber = $this->get_row_data($row, $mapping['course_idnumber']);
              $record->course_shortname = $this->get_row_data($row, $mapping['course_shortname']);
              $record->course_fullname = $this->get_row_data($row, $mapping['course_fullname']);
              $record->course_summary = $this->get_row_data($row, $mapping['course_summary']);
              $record->course_tags = $this->get_row_data($row, $mapping['course_tags']);
              $record->course_visible = $this->get_row_data($row, $mapping['course_visible']);
              $record->course_categoryidnumber = $this->get_row_data($row, $mapping['course_categoryidnumber']);
              $record->course_categoryname = $this->get_row_data($row, $mapping['course_categoryname']);
              $record->page_name = $this->get_row_data($row, $mapping['page_name']);
              $record->page_intro = $this->get_row_data($row, $mapping['page_intro']);
              $record->page_content = $this->get_row_data($row, $mapping['page_content']);

              $record->category = $category;

              array_push($records, $record);
        }

        $this->records = $records;

        $this->importer->close();
        if ($this->records == null) {
               $this->fail(get_string('invalidimportfile', 'tool_uploadpage'));
               return;
        }
    }

    /**
     * Get the error information
     *
     * @return string the last error
     */
    public function get_error() {
        return $this->error;
    }

    /**
     * Execute the process.
     *
     * @param object $tracker the output tracker to use.
     * @return void
     */
    public function execute($tracker = null) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/phpunit/classes/util.php');
        require_once($CFG->libdir . '/coursecatlib.php');
        require_once($CFG->dirroot . '/mod/page/lib.php');

        if ($this->processstarted) {
              throw new coding_exception('Process has already been started');
        }
        $this->processstarted = true;

        if (empty($tracker)) {
              $tracker = new tool_uploadpage_tracker(tool_uploadpage_tracker::NO_OUTPUT);
        }
        $tracker->start();

        $generator = phpunit_util::get_data_generator();
        $records = $this->records;

        $total = 0;
        $created = 0;
        $updated = 0;
        $deleted = 0;
        $nochange = 0;
        $errors = 0;

        // We will most certainly need extra time and memory to process big files.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        // Now actually do the work.
        foreach ($records as $record) {
              $this->linenb++;
             $total++;

            if (tool_uploadpage_helper::validate_import_record($record)) {
                $course = tool_uploadpage_helper::create_course_from_import_record($record);
                $page = tool_uploadpage_helper::create_page_from_import_record($record);

                if ($existing = tool_uploadpage_helper::get_course_by_idnumber($course->idnumber)) {
                    $updatecourse = false;
                    $mergedcourse = tool_uploadpage_helper::update_course_with_import_course($existing, $course);
                    if ($mergedcourse === false) {
                        $updatecourse = false;
                        $mergedcourse = $existing;
                    } else {
                        $updatecourse = true;
                    }

                    // Now check the page.
                    $addpage = false;
                    $updatepage = false;
                    $existingpage = tool_uploadpage_helper::get_page_by_name($page->name, $mergedcourse->id);

                    if ($existingpage) {
                        $mergedpage = tool_uploadpage_helper::update_page_with_import_page($existingpage, $page);
                        if ($mergedpage === false) {
                            $updatepage = false;
                            $addpage = false;
                            $mergedpage = $page;
                        } else {
                            $addpage = false;
                            $updatepage = true;
                        }
                    } else {
                        $page->course = $existing->id;
                        $addpage = true;
                        $updatepage = false;
                        $mergedpage = $page;
                    }

                    if ($updatecourse === false && $addpage === false && $updatepage === false) {
                        // Course data not changed.
                        $nochange++;
                        $status = array("Course Not Updated");
                        $tracker->output($this->linenb, true, $status, $mergedcourse);
                    } else {
                        // Course or page differs so we need to update.
                        $updated++;
                        if ($updatecourse) {
                            update_course($mergedcourse);
                            $status = array("Course Updated");
                        }

                        if ($addpage) {
                            $pageresponse = $generator->create_module('page', $mergedpage);
                            $mergedpage->id = $pageresponse->id;

                            $cm = get_coursemodule_from_instance('page', $mergedpage->id);
                            $cm->idnumber = $mergedcourse->idnumber;
                            $DB->update_record('course_modules', $cm);
                            $status = array("Course Updated - Page Activity Added");
                        }

                        if ($updatepage) {
                            $DB->update_record('page', $mergedpage);
                            $cm = get_coursemodule_from_instance('page', $mergedpage->id);
                            $cm->idnumber = $course->idnumber;
                            $DB->update_record('course_modules', $cm);
                            $status = array("Course Updated - Page Activity Updated");
                        }
                        $tracker->output($this->linenb, true, $status, $mergedcourse);
                    }
                } else {
                    $created++;
                    $status = array("Course Created");

                    $newcourse = create_course($course);
                    $page->course = $newcourse->id;

                    // Now we need to add a Page.
                    $pagerecord = $generator->create_module('page', $page);

                    $cm = get_coursemodule_from_instance('page', $pagerecord->id);
                    $cm->idnumber = $course->idnumber;
                    $DB->update_record('course_modules', $cm);

                    $tracker->output($this->linenb, true, $status, $newcourse);
                }
            } else {
                $errors++;
                $status = array("Invalid Import Record");

                $tracker->output($this->linenb, false, $status, null);
            }
        }

        $tracker->finish();
        $tracker->results($total, $created, $updated, $deleted, $nochange, $errors);
    }
}
