This admin tool allows import of HTML as Page Activities
using a comma separated value file (CSV).

This has been tested on Moodle 3.1 & 3.6

This import creates a Moodle Course, consisting of a Single
Page Activity. The Page Activity Content is the HTML snippet
from the file.

Install from git:

Navigate to Moodle root folder
git clone https://github.com/lushonline/moodle-tool_uploadpage admin/tool/uploadpage
cd admin/tool/uploadpage
git branch -a
git checkout master
Click the 'Notifications' link on the frontpage administration block or from your Moodle root folder
run: php admin/cli/upgrade.php if you have access to a command line interpreter.



