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

/**
 * Reusable controller to display a loader and disable a button while an action is being processed.
 *
 * @module      report_lifestory/button_loader
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Str from 'core/str';

export const init = () => {
    const buttons = document.querySelectorAll("#btn-feedback-ai");

    buttons.forEach(button => {
        button.addEventListener("click", async e => {
            if (button.classList.contains('report_lifestory-btnloading')) {
                e.preventDefault();
                return;
            }

            e.preventDefault();

            const redirect = button.href || button.dataset.redirect;
            const originalText = button.textContent.trim();
            button.dataset.originalText = originalText;

            const loaderText = await Str.get_string('generatingfeedback', 'report_lifestory');

            // Show loader
            button.classList.add('report_lifestory-btnloading');
            button.setAttribute("aria-disabled", "true");
            button.style.pointerEvents = "none";
            button.textContent = loaderText;

            if (!redirect) {
                return;
            }

            window.location.href = redirect;
        });
    });
};
