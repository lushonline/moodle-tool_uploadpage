# tool_uploadpage
![Moodle Plugin CI](https://github.com/lushonline/moodle-tool_uploadpage/workflows/Moodle%20Plugin%20CI/badge.svg?branch=master)

A tool to allow import of HTML as Page Activities using a comma separated value file (CSV).

This import creates a Moodle Course, consisting of a Single Page Activity.

The Page Activity Content is the HTML snippet from the file.

The Page Activity and Course are setup to support Moodle Completion based on viewing of the Page.

- [Installation](#installation)
- [Usage](#usage)

## Installation

---

1. Install the plugin the same as any standard moodle plugin either via the
   Moodle plugin directory, or you can use git to clone it into your source:

   ```sh
   git clone https://github.com/lushonline/moodle-tool_uploadpage.git admin/tool/uploadpage
   ```

   Or install via the Moodle plugin directory:
     
    https://moodle.org/plugins/tool_uploadpage

2. Then run the Moodle upgrade

This plugin requires no configuration.

## Usage
For more information see the [Wiki Pages](https://github.com/lushonline/moodle-tool_uploadpage/wiki)

## Acknowledgements
This was inspired in part by the great work of Frédéric Massart and Piers harding on the core [admin\tool\uploadcourse](https://github.com/moodle/moodle/tree/master/admin/tool/uploadcourse)
