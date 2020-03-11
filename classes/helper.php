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
 * Links and settings
 *
 * Class containing a set of helpers, based on admin\tool\uploadcourse by 2013 Frédéric Massart.
 *
 * @package    tool_uploadpage
 * @copyright  2019-2020 LushOnline
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

/**
 * Class containing a set of helpers.
 *
 * @package   tool_uploadpage
 * @copyright 2019-2020 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploadpage_helper {

    /**
     * Validate we have the minimum info to create/update course
     *
     * @param object $record The record we imported
     * @return bool true if validated
     */
    public static function validate_import_record($record) {
        // As a minimum we need.
        // course idnumber.
        // course shortname.
        // course longname.

        if (empty($record->course_idnumber)) {
            return false;
        }

        if (empty($record->course_shortname)) {
            return false;
        }

        if (empty($record->course_fullname)) {
            return false;
        }
        return true;
    }

    /**
     * Resolve a category by IDnumber.
     *
     * @param string $idnumber category IDnumber.
     * @return int category ID.
     */
    public static function resolve_category_by_idnumber($idnumber) {
          global $DB;

        $params = array('idnumber' => $idnumber);
        $id = $DB->get_field_select('course_categories', 'id', 'idnumber = :idnumber', $params, IGNORE_MISSING);
        return $id;
    }

    /**
     * Resolve a category by ID
     *
     * @param string $id category ID.
     * @return int category ID.
     */
    public static function resolve_category_by_id_or_idnumber($id) {
        global $DB;
        $params = array('id' => $id);
        if (is_numeric($id)) {
            if ($DB->record_exists('course_categories', $params)) {
                return $id;
            } else {
                return null;
            }
        } else {
            $params = array('idnumber' => $id);
            try {
                $id = $DB->get_field_select('course_categories', 'id', 'idnumber = :idnumber', $params, MUST_EXIST);
                return $id;
            } catch (Exception $e) {
                return null;
            }
        }
    }

    /**
     * Retrieve a page by its name.
     *
     * @param string $name page name
     * @param string $courseid course identifier
     * @return object page.
     */
    public static function get_page_by_name($name, $courseid) {
        global $DB;

        $params = array('name' => $name, 'course' => $courseid);
        $pages = $DB->get_records('page', $params);

        if (count($pages) != 0) {
             return array_pop($pages);
        } else {
             return null;
        }
    }

    /**
     * Retrieve a course by its idnumber.
     *
     * @param string $courseidnumber course idnumber
     * @return object course or null
     */
    public static function get_course_by_idnumber($courseidnumber) {
        global $DB;

        $params = array('idnumber' => $courseidnumber);
        $courses = $DB->get_records('course', $params);

        if (count($courses) == 1) {
            $course = array_pop($courses);
            $tags = core_tag_tag::get_item_tags_array('core', 'course', $course->id);

            $course->tags = array();

            foreach ($tags as $key => $value) {
                array_push($course->tags, $value);
            }

            return $course;
        } else {
            return null;
        }
    }

    /**
     * Create a course from the import record.
     *
     * @param object $record Validated Imported Record
     * @param string $tagdelimiter The value to use to split the delimited $record->course_tags string
     * @return object course or null
     */
    public static function create_course_from_import_record($record, $tagdelimiter="|") {
        $course = new \stdClass();
        $course->idnumber = $record->course_idnumber;
        $course->shortname = $record->course_shortname;
        $course->fullname = $record->course_fullname;
        $course->summary = $record->course_summary;
        $course->summaryformat = 1; // FORMAT_HTML.
        $course->visible = $record->course_visible;

        // Split the tag string into an array.
        if (!empty($record->course_tags)) {
            $course->tags = explode($tagdelimiter, $record->course_tags);
        } else {
            $course->tags = array();
        }

        // Fixed default values.
        $course->format = "singleactivity";
        $course->numsections = 0;
        $course->newsitems = 0;
        $course->showgrades = 0;
        $course->showreports = 0;
        $course->startdate = time();
        $course->activitytype = "page";

        $course->category = self::get_or_create_category_from_import_record($record);

        return $course;
    }

    /**
     * Return the category id, creating the category if necessary from the import record.
     *
     * @param object $record Validated Imported Record
     * @return int The category id
     */
    public static function get_or_create_category_from_import_record($record) {
        global $CFG;
        $categoryid = $record->category;

        if (!empty($record->course_categoryidnumber)) {
            $categoryid = self::resolve_category_by_idnumber($record->course_categoryidnumber);
            if ($categoryid === false) {

                if (!empty($record->course_categoryname)) {
                    // Category not found and we have a name so we need to create.
                    $category = new \stdClass();
                    $category->parent = $record->category;
                    $category->name = $record->course_categoryname;
                    $category->idnumber = $record->course_categoryidnumber;

                    if (method_exists('\core_course_category', 'create')) {
                        $createdcategory = core_course_category::create($category);
                    } else {
                        require_once($CFG->libdir . '/coursecatlib.php');
                        $createdcategory = coursecat::create($category);
                    }
                    $categoryid = $createdcategory->id;
                }
            }
        }
        return $categoryid;
    }

    /**
     * Create a page from the import record.
     *
     * @param object $record Validated Imported Record
     * @return object course or null
     */
    public static function create_page_from_import_record($record) {
        // All data provided by the data generator.
        $page = new \stdClass();
        $page->name = $record->page_name;
        $page->printintro = 0;
        $page->printheading = 1;
        $page->intro = $record->page_intro;
        $page->content = $record->page_content;
        $page->contentformat = 1; // FORMAT_HTML.

        return $page;
    }

    /**
     * Merge changes from $importedcourse into $existingcourse
     *
     * @param object $existingcourse Course Record for existing course
     * @param object $importedcourse  Course Record for imported course
     * @return object course or FALSE if no changes
     */
    public static function update_course_with_import_course($existingcourse, $importedcourse) {
        $updateneeded = false;
        $result = $existingcourse;
        $updates = array();

        if ($existingcourse->fullname !== $importedcourse->fullname) {
            array_push($updates, "fullname is different");
            $result->fullname = $importedcourse->fullname;
            $updateneeded = true;
        }

        if ($existingcourse->shortname !== $importedcourse->shortname) {
            array_push($updates, "shortname is different");
            $result->shortname = $importedcourse->shortname;
            $updateneeded = true;
        }

        if ($existingcourse->idnumber !== $importedcourse->idnumber) {
            array_push($updates, "idnumber is different");
            $result->idnumber = $importedcourse->idnumber;
            $updateneeded = true;
        }

        // We need to apply Moodle FORMAT_HTML conversion as this is how summary would have been stored.
        $options = array();
        $options['filter'] = false;
        $formatted = format_text($importedcourse->summary, FORMAT_HTML, $options);

        if ($existingcourse->summary !== $formatted) {
            array_push($updates, "summary is different");
            $result->summary = $importedcourse->summary;
            $updateneeded = true;
        }

        if ($existingcourse->visible !== $importedcourse->visible) {
            array_push($updates, "visible is different");
            $result->visible = $importedcourse->visible;
            $updateneeded = true;
        }

        // Sort the arrays and then compare.
        sort($existingcourse->tags);
        sort($importedcourse->tags);

        if ($existingcourse->tags !== $importedcourse->tags) {
            array_push($updates, "tags is different");
            $result->tags = $importedcourse->tags;
            $updateneeded = true;
        }

        if ($existingcourse->category !== $importedcourse->category) {
            array_push($updates, "category is different");
            $result->category = $importedcourse->category;
            $updateneeded = true;
        }

        if ($updateneeded) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Merge changes from $importedpage into $existingpage
     *
     * @param object $existingpage Page Record for existing page
     * @param object $importedpage  page Record for imported page
     * @return object page or FALSE if no changes
     */
    public static function update_page_with_import_page($existingpage, $importedpage) {
        $updateneeded = false;
        $result = $existingpage;

        if ($existingpage->name !== $importedpage->name) {
            $result->name = $importedpage->name;
            $updateneeded = true;
        }

        if ($existingpage->intro !== $importedpage->intro) {
            $result->intro = $importedpage->intro;
            $updateneeded = true;
        }

        if ($existingpage->content !== $importedpage->content) {
            $result->content = $importedpage->content;
            $updateneeded = true;
        }

        if ($updateneeded) {
            return $result;
        } else {
            return false;
        }
    }
}