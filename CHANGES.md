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
