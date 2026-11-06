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
