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
 * CLI Bulk Single Page Activity course registration script from a comma separated file.
 *
 * @package    tool_uploadpage
 * @copyright  2019-2020 LushOnline
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/phpunit/classes/util.php');
require_once($CFG->libdir . '/clilib.php');

$pluginversion = get_config('tool_uploadpage', 'version');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(array(
    'help' => false,
    'source' => '',
    'delimiter' => 'comma',
    'encoding' => 'UTF-8',
    'categoryid' => tool_uploadpage_helper::resolve_category_by_id_or_idnumber(null)
),
array(
    'h' => 'help',
    's' => 'source',
    'd' => 'delimiter',
    'e' => 'encoding',
    'c' => 'categoryid'
));

if ($unrecognized) {
    $unrecognized = implode(PHP_EOL."  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$help = get_string('pluginname', 'tool_uploadpage')." (".$pluginversion.")".PHP_EOL;
$help .= PHP_EOL;
$help .= "Options:".PHP_EOL;
$help .= "-h, --help                 Print out this help".PHP_EOL;
$help .= "-s, --source               CSV file".PHP_EOL;
$help .= "-d, --delimiter            CSV delimiter: colon, semicolon, tab, cfg, comma. Default: comma".PHP_EOL;
$help .= "-e, --encoding             CSV file encoding: UTF-8, ... Default: UTF-8".PHP_EOL;
$help .= "-c, --categoryid           ID of default category. Default: first category on site".PHP_EOL;
$help .= PHP_EOL;
$help .= "Example:".PHP_EOL;
$help .= "sudo -u www-data /usr/bin/php admin/tool/uploadpage/cli/uploadpage.php -s=./courses.csv -d=comma -e=UTF-8 -c=1".PHP_EOL;

if ($options['help']) {
    echo $help;
    die();
}

$start = get_string('pluginname', 'tool_uploadpage')." (".$pluginversion.")".PHP_EOL;
$start .= PHP_EOL;
$start .= "Options Used:".PHP_EOL;
$start .= "--source = ".$options['source'].PHP_EOL;
$start .= "--delimiter = ".$options['delimiter'].PHP_EOL;
$start .= "--encoding = ".$options['encoding'].PHP_EOL;
$start .= "--categoryid = ".$options['categoryid'].PHP_EOL;
$start .= PHP_EOL;

echo $start;

// File.
if (!empty($options['source'])) {
    $options['source'] = realpath($options['source']);
}

if (!file_exists($options['source'])) {
    echo get_string('invalidcsvfile', 'tool_uploadpage').PHP_EOL;
    echo $help;
    die();
}

// Encoding.
$encodings = core_text::get_encodings();
if (!isset($encodings[$options['encoding']])) {
    echo get_string('invalidencoding', 'tool_uploadpage').PHP_EOL;
    echo $help;
    die();
}

// Category id check.
if (!tool_uploadpage_helper::resolve_category_by_id_or_idnumber($options['categoryid'])) {
    echo get_string('invalidparentcategoryid', 'tool_uploadpage').PHP_EOL;
    echo $help;
    die();
}

// Emulate normal session.
cron_setup_user();

// Let's get started!
$content = file_get_contents($options['source']);
$importer = new tool_uploadpage_importer($content, $options['encoding'], $options['delimiter']);

$importid = $importer->get_importid();
unset($content);

if ($importer->haserrors()) {
    echo "Errors Reported during import:".PHP_EOL;
    echo implode(PHP_EOL, $importer->geterrors());
    die();
}

$importer = new tool_uploadpage_importer(null, null, null, $options['categoryid'], $importid, null);
$importer->execute(new tool_uploadpage_tracker(tool_uploadpage_tracker::OUTPUT_PLAIN, true));
