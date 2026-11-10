<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace report_lifestory\local;

use aiprovider_datacurso\local\plugin_settings;

/**
 * State of the life story report in the Datacurso AI provider, per Moodle Workplace tenant.
 *
 * The report has no "Enabled" switch of its own: the provider keeps it, per tenant. The tenant that applies
 * is always the tenant of the current user, never the tenant that owns the course: a user of a tenant that
 * disabled the report cannot use it in any course, including courses of another tenant and shared courses.
 * This matches billing, as the provider charges each AI call to the tenant of the current user. On a site
 * without tool_tenant the provider uses its site default.
 *
 * @package     report_lifestory
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_state {
    /** @var string Component name the provider knows the report by. */
    public const COMPONENT = 'report_lifestory';

    /**
     * Whether the Datacurso AI provider is installed.
     *
     * @return bool
     */
    public static function is_provider_available(): bool {
        return class_exists(plugin_settings::class);
    }

    /**
     * Whether the report is enabled in the provider for the tenant of the current user.
     *
     * Without the provider the report is treated as disabled, so no entry point links to it.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        if (!self::is_provider_available()) {
            return false;
        }
        return plugin_settings::is_enabled(self::COMPONENT);
    }

    /**
     * Stop the request when the report is disabled in the provider for the tenant of the current user.
     *
     * @return void
     * @throws \moodle_exception plugindisabled or plugindisabled_admin (aiprovider_datacurso) when the report is disabled.
     */
    public static function require_enabled(): void {
        plugin_settings::require_enabled(self::COMPONENT);
    }
}
