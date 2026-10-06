## [1.0.6] - 2026-10-06

### 🚀 Added

- Add Moodle 5.0, 5.1 and 5.2 compatibility (`MOODLE_500_STABLE` branch)

### 🔄 Changed

- Require Moodle 5.0 (`requires = 2025041400`) and declare `supported = [500, 502]`
- Run plugin CI against `MOODLE_500_STABLE` and `MOODLE_502_STABLE` with the `MOODLE_500_STABLE` branch of `aiprovider_datacurso`
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
