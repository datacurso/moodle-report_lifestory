## [1.0.7-wp] - 2026-11-10

### 🚀 Added

- Add `\report_lifestory\local\plugin_state`, which asks the Datacurso AI provider whether the report is enabled for the Moodle Workplace tenant of the current user
- Add PHPUnit coverage for the report disabled in the provider, per tenant (`tests/plugin_disabled_test.php`)

### 🔄 Changed

- The report is switched on and off only from the Datacurso AI provider, per tenant, through `\aiprovider_datacurso\local\plugin_settings`; turning it off for one tenant never affects another tenant
- The tenant that decides is always the tenant of the current user, never the tenant that owns the course: when a tenant disables the report, none of its users can use it in any course, including courses of another tenant and shared courses, while users of an enabled tenant keep it in every course. This matches billing, as the provider charges each AI call to the tenant of the current user
- When the report is disabled for the tenant, the report page and the `report_lifestory_search_students` web service refuse with the provider message (`plugindisabled`, or `plugindisabled_admin` for site administrators), and the course navigation entry and the entries under Site administration > Reports are hidden (also hidden while the provider is missing)
- The minimum required version of `aiprovider_datacurso` is raised to 2026110901 (1.7.0-wp), the first release that keeps the state of the suite plugins per tenant
- The README states that this branch supports Moodle Workplace 4.5 only and requires the Datacurso AI provider 1.7.0-wp or later
- No upgrade step is needed: the report never had an "Enabled" setting of its own, so it stays enabled until it is switched off in the provider

### ⚠️ Deprecated

### ❌ Removed

### 🐞 Fixed

- Skip the PHPUnit tests that reach the provider when `aiprovider_datacurso` is not installed, as the CI pipeline does not install declared dependencies

### 🔐 Security

## [1.0.6-wp] - 2026-10-08

### 🚀 Added

- Add Jenkins CI (`Jenkinsfile.moodleplugin`) running the plugin checks on Moodle 4.5 with MariaDB and PostgreSQL

### 🔄 Changed

- Transliterate accents and non-Latin scripts in the PDF filename instead of dropping them ("Mejía" becomes "Mejia"), synchronized from `MOODLE_500_STABLE` (1.0.6)

### ⚠️ Deprecated

### ❌ Removed

### 🐞 Fixed

- Keep the leading zero of the day in the PDF filename date so it always has eight digits (`20261006` instead of `2026106`)
- Stop including `lib/navigationlib.php` manually in the PHPUnit tests: it is already loaded by core
- Skip the AI client PHPUnit tests when `aiprovider_datacurso` is not installed

### 🔐 Security

## [1.0.5-wp] - 2026-10-05

### 🚀 Added

- First Moodle Workplace 4.5 release, synchronized from `main` (1.0.5)
- Student search with pagination, scoped to courses whose grades the viewer can see
- Course filter in the life story report
- Server-side storage of AI-generated feedback with generation timestamps
- Event logging for report actions
- Pseudonymization of student names in the AI payload
- Grade range and percentage in the AI payload
- Handling for nonexistent users and students without course enrolments

### 🔄 Changed

- `$plugin->supported` restricted to `[405, 405]` (Moodle Workplace 4.5)
- PDF export layout and language support
- CSV export handles UTF-8 directly instead of normalizing text
- Privacy metadata now declares the data sent to the AI provider

### ⚠️ Deprecated

### ❌ Removed

- `.github/workflows/moodle-release.yml` (Workplace releases are created manually)

### 🐞 Fixed

- Session key required for CSV export and feedback actions
- Capability and student-role checks for AI feedback generation
- Load the user grade report library to handle hidden grades
- Remove unneeded JavaScript initialization for the user grade report

### 🔐 Security

- Course access control before showing student grades

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
