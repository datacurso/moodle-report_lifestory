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
 * Provides live user search functionality for the lifestory report, including AJAX requests and dynamic result rendering.
 *
 * @module      report_lifestory/user_search
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/templates', 'core/notification'],
    function ($, Ajax, Templates, Notification) {

        let searchTimeout = null;
        const SEARCH_DELAY = 300;

        /**
         * Calls the external function to search for students.
         * @param {string} query Search term entered by the user.
         * @return {Promise}
         */
        const searchStudents = function (query) {
            return Ajax.call([{
                methodname: 'report_lifestory_search_students',
                args: { query: query }
            }])[0];
        };

        /**
         * Renders the search results using a Mustache template.
         * @param {Array} students List of student objects returned by the webservice.
         * @param {string} baseUrl Base URL of the report.
         */
        const renderResults = function (students, baseUrl) {
            const container = $('#search-results');
            container.empty();

            if (!students || students.length === 0) {
                container.addClass('d-none');
                return;
            }

            const context = {
                students: students.map(student => ({
                    id: student.id,
                    fullname: student.fullname,
                    email: student.email,
                    profileimageurl: student.profileimageurl,
                    baseurl: baseUrl,
                    initial: student.fullname.charAt(0).toUpperCase()
                }))
            };

            Templates.render('report_lifestory/search_results', context)
                .then(function (html, js) {
                    container.html(html).removeClass('d-none');
                    Templates.runTemplateJS(js);
                })
                .catch(Notification.exception);
        };

        /**
         * Initializes the user search functionality.
         * @param {string} baseUrl Base URL for the current page.
         */
        const init = function (baseUrl) {
            const searchInput = $('#usersearch');
            const resultsContainer = $('#search-results');

            if (searchInput.length === 0) {
                return;
            }

            // Input event
            searchInput.on('input', function () {
                const query = $(this).val().trim();

                if (query.length === 0) {
                    resultsContainer.addClass('d-none').empty();
                    return;
                }

                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }

                searchTimeout = setTimeout(function () {
                    searchStudents(query)
                        .then(function (response) {
                            const students = response && response.students ? response.students : [];
                            renderResults(students, baseUrl);
                        })
                        .catch(Notification.exception);
                }, SEARCH_DELAY);
            });

            // Show results when input is focused
            searchInput.on('focus', function () {
                const query = $(this).val().trim();
                if (query.length > 0 && resultsContainer.children().length > 0) {
                    resultsContainer.removeClass('d-none');
                }
            });

            // Hide results when clicking outside
            $(document).on('click', function (e) {
                if (!$(e.target).closest('#usersearch-wrapper').length) {
                    resultsContainer.addClass('d-none');
                }
            });

            // Prevent submitting form with Enter
            searchInput.on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        };

        return { init: init };
    });
