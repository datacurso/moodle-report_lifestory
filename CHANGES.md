## [1.0.7] - 2026-11-05

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.3**.

### 🚀 Added

- Add Moodle 5.3 compatibility: declare `supported = [500, 503]` and add `MOODLE_503_STABLE` to the Jenkins and GitHub Actions CI matrices
- Record the scope of each stored AI feedback (analysed courses and courses where hidden grades were included) in the new `scope` field of `report_lifestory_feedback`

### 🐞 Fixed

- Leave hidden grades out of the data sent to the AI service when the requester cannot see them, and hide, recalculate or keep course and category totals that contain them following the course setting "Hide totals if they contain hidden items"
- Show a stored AI feedback, on the page and in the PDF, only to users who can see the grades of every course it analysed and, where it included hidden grades, those hidden grades
- Lock the "Generate AI feedback" button in the same click so a double click no longer sends two generation requests
- Require the student to be enrolled in the filtered course before showing its grades and the generate and CSV buttons
- Show "No report data available." for a course without graded activities instead of a course total with "Error"

## [1.0.6] - 2026-10-06

### 🚀 Added

- Add Moodle 5.0, 5.1 and 5.2 compatibility (`MOODLE_500_STABLE` branch)
- Add Jenkins CI (`Jenkinsfile.moodleplugin`) running the plugin checks on Moodle 5.0 and 5.2 with MariaDB

### 🔄 Changed

- Require Moodle 5.0 (`requires = 2025041400`) and declare `supported = [500, 502]`
- Make the GitHub Actions plugin CI manual-only (Jenkins runs the automatic CI); its matrix targets `MOODLE_500_STABLE` and `MOODLE_502_STABLE` with PostgreSQL 16 and the `MOODLE_500_STABLE` branch of `aiprovider_datacurso`
- Transliterate accents and non-Latin scripts in the PDF filename instead of dropping them ("Mejía" becomes "Mejia")

### ⚠️ Deprecated

### ❌ Removed

### 🐞 Fixed

- Keep the leading zero of the day in the PDF filename date so it always has eight digits (`20261006` instead of `2026106`)
- Stop including `lib/navigationlib.php` manually in the PHPUnit tests: it is already loaded by core and Moodle 5.1+ throws a coding exception when a component includes it

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
