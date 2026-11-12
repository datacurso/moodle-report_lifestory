## [1.0.9] - 2026-11-12

**Compatibility note:** This version is compatible only with **Moodle 4.5**.

### 🔄 Changed

- Narrow `$plugin->supported` to `[405, 405]`: Moodle 5.0 to 5.3 are served by the `MOODLE_503_STABLE` branch and Moodle Workplace by the `WORKPLACE_405_STABLE` branch
- The README lists Moodle 4.5 only
- CI runs on Jenkins only, against Moodle 4.5 (mariadb and pgsql)

### ❌ Removed

- Remove the GitHub Actions workflows (`plugin-ci.yml` and `moodle-release.yml`)

### 🐞 Fixed

- Leave hidden grades out of the data sent to the AI service when the requester cannot see them, and hide, recalculate or keep course and category totals that contain them following the course setting "Hide totals if they contain hidden items"
- Record the scope of each stored AI feedback (analysed courses and courses where hidden grades were included) in the new `scope` field of `report_lifestory_feedback`, added by the upgrade step 2026111200
- Show a stored AI feedback, on the page and in the PDF, only to users who can see the grades of every course it analysed and, where it included hidden grades, those hidden grades
- Lock the "Generate AI feedback" button in the same click so a double click no longer sends two generation requests
- Require the student to be enrolled in the filtered course before showing its grades and the generate and CSV buttons
- Show "No report data available." for a course without graded activities instead of a course total with "Error"

## [1.0.7] - 2026-11-06

### 🚀 Added

- The plugin can be switched off from the Datacurso AI provider (Site administration > AI > Datacurso plugins list > Settings); the plugin has no "Enabled" setting of its own. While it is off, the report category and page under Site administration > Reports and the course navigation entry are hidden, and the report page (including the AI feedback, CSV and PDF exports) and the `report_lifestory_search_students` web service refuse with the Datacurso AI provider's message: users are told to contact the administrator, and site administrators get a link to the provider's plugins list. With the plugin on, nothing changes. Requires `aiprovider_datacurso` 2026110400.

### 🔄 Changed

### ⚠️ Deprecated

### ❌ Removed

### 🐞 Fixed

### 🔐 Security

## [1.0.4] - 2026-04-21

### 🚀 Added

- Add PHPUnit regression test to verify report link visibility for non-admin roles with `report/lifestory:view`

### 🔄 Changed

- Keep the plugin category under Site administration > Reports while using explicit capability checks for access

### ⚠️ Deprecated

### ❌ Removed

### 🐞 Fixed

- Fix report link visibility for non-admin roles by removing `$hassiteconfig` gate and requiring `report/lifestory:view`

### 🔐 Security
