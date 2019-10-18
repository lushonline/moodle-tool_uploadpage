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
 * This file contains the procesing for the add/update of a Percipio Course.
 *
 * @package   tool_uploadpage
 * @copyright 2019 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

/**
 * Import Course form.
 *
 * @package   tool_uploadpage
 * @copyright 2019 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploadpage_importer {

    /** @var string $error The errors message from reading the csv */
    var $error = '';

    /** @var array $records The csv imported info */
    var $records = array();
    var $importid = 0;
    var $importer = null;
    var $foundheaders = array();
	
		var $generator = null;
	
	    /** @var array of errors where the key is the line number. */
    protected $errors = array();

    /** @var int line number. */
    protected $linenb = 0;

    /** @var bool whether the process has been started or not. */
    protected $processstarted = false;
	
    public function fail($msg) {
        $this->error = $msg;
        return false;
    }

    public function get_importid() {
        return $this->importid;
    }

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

    public function list_found_headers() {
        return $this->foundheaders;
    }

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

    private function get_row_data($row, $index) {
        if ($index < 0) {
            return '';
        }
        return isset($row[$index]) ? $row[$index] : '';
    }

    /**
     * Constructor - parses the raw text for sanity.
     */
    public function __construct($text = null, $encoding = null, $delimiter = 'comma', $category=1, $importid = 0, $mappingdata = null) {
			global $CFG;

			// The format of our records is:
			// COURSE_IDNUMBER	COURSE_SHORTNAME	COURSE_FULLNAME	COURSE_SUMMARY	COURSE_TAGS	COURSE_VISIBLE	COURSE_CATEGORYIDNUMBER	COURSE_CATEGORYNAME	PAGE_NAME	PAGE_INTRO	PAGE_CONTENT

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
     * @return array of errors from parsing the xml.
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

	
			// Now actually do the work
			foreach ($records as $record) {
				$this->linenb++;
        $total++;
		
				if (tool_uploadpage_helper::validate_import_record($record)) {
					$course = tool_uploadpage_helper::create_course_from_import_record($record);
					$page = tool_uploadpage_helper::create_page_from_import_record($record);

					if ($existing = tool_uploadpage_helper::get_course_by_idnumber($course->idnumber)) {
						$update_course = false;
						$mergedcourse = tool_uploadpage_helper::update_course_with_import_course($existing,$course);
						if ($mergedcourse === false) {
							$update_course = false;
							$mergedcourse = $existing;
						} else {
							$update_course = true;
						}

						//Now check the page
						$add_page = false;
						$update_page = false;
						$existingpage = tool_uploadpage_helper::get_page_by_name($page->name, $mergedcourse->id);
					
						if ($existingpage) {
							$mergedpage = tool_uploadpage_helper::update_page_with_import_page($existingpage, $page);
							if ($mergedpage === false) {
								$update_page = false;
								$add_page = false;
								$mergedpage = $page;
							} else {
								$add_page = false;
								$update_page = true;
							}
						} else {
							$page->course = $existing->id;
							$add_page = true;
							$update_page = false;
							$mergedpage = $page;
						}
					
						if ($update_course === false && $add_page === false && $update_page === false) {
							//Course data not changed.
							$nochange++;
							$status = array("Course Not Updated");
							$tracker->output($this->linenb, true, $status, $mergedcourse);
						} else {
							//Course or page differs so we need to update
							$updated++;
							if ($update_course) {
								$course_update_result = update_course($mergedcourse);
								$status = array("Course Updated");
							}

							if ($add_page) {
								$pageresponse = $generator->create_module('page', $mergedpage);
								$mergedpage->id = $pageresponse->id;

								$cm = get_coursemodule_from_instance('page', $mergedpage->id);
								$cm->idnumber = $mergedcourse->idnumber;
								$DB->update_record('course_modules',$cm);
								$status = array("Course Updated - Page Activity Added");
							}

							if ($update_page) {
								$DB->update_record('page', $mergedpage);
								$cm = get_coursemodule_from_instance('page', $mergedpage->id);
								$cm->idnumber = $course->idnumber;
								$DB->update_record('course_modules',$cm);
								$status = array("Course Updated - Page Activity Updated");
							}
							$tracker->output($this->linenb, true, $status, $mergedcourse);
						}
					} else {
						$created++;
						$status = array("Course Created");
					
						$newcourse = create_course($course);
						$page->course = $newcourse->id;
					
						//Now we need to add a Page
						$pagerecord = $generator->create_module('page', $page);
					
						$cm = get_coursemodule_from_instance('page', $pagerecord->id);
						$cm->idnumber = $course->idnumber;
						$DB->update_record('course_modules',$cm);
					
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
