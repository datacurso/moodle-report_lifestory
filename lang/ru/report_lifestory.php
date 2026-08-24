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

/**
 * Plugin strings are defined here.
 *
 * @package     report_lifestory
 * @category    string
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activity'] = 'Активность';
$string['altlogo'] = 'Логотип Datacurso';
$string['clearselection'] = 'Очистить выбор';
$string['course'] = 'Курс';
$string['coursetotal'] = 'Итого по курсу';
$string['error_ai_service'] = 'Ошибка службы ИИ: {$a}';
$string['error_airequest'] = 'Ошибка при соединении со службой ИИ: {$a}';
$string['event:csvexported'] = 'CSV истории жизни экспортирован';
$string['event:feedbackgenerated'] = 'Отзыв ИИ по истории жизни сгенерирован';
$string['event:pdfexported'] = 'PDF истории жизни экспортирован';
$string['event:reportviewed'] = 'Отчёт об истории жизни просмотрен';
$string['exportcsv'] = 'Экспорт в CSV';
$string['exportpdf'] = 'Экспорт в PDF';
$string['feedback'] = 'Обратная связь';
$string['feedbackfromai'] = 'Отзыв от ИИ';
$string['feedbackgeneratedon'] = 'Отзыв создан {$a}';
$string['generatefeedback'] = 'Создать отзыв с помощью ИИ';
$string['generatingfeedback'] = 'Создание отзыва';
$string['gradepercent'] = 'Оценка (%)';
$string['lifestory'] = 'История жизни студента';
$string['lifestory:generateaifeedback'] = 'Создать отзыв с помощью ИИ для студентов';
$string['lifestory:view'] = 'Просмотр отчета об истории жизни';
$string['nofeedbacktopdf'] = 'Сначала сгенерируйте отзыв ИИ перед экспортом PDF.';
$string['noreportdata'] = 'Нет доступных данных отчета.';
$string['noresponse'] = 'Ответ не получен.';
$string['pluginname'] = 'История жизни студента ИИ';
$string['privacy:metadata:ai_provider'] = 'Данные отправляются в службу ИИ Datacurso для генерации отзывов на основе академической истории студента.';
$string['privacy:metadata:ai_provider:courses'] = 'Структурированная академическая история, используемая для анализа: названия курсов, разделов и активностей, оценки, диапазоны и проценты, а также тексты отзывов преподавателей, в которых имя студента замаскировано заполнителем.';
$string['privacy:metadata:ai_provider:lang'] = 'Язык пользователя, запрашивающего анализ, добавляемый уровнем поставщика ИИ.';
$string['privacy:metadata:ai_provider:siteid'] = 'Постоянный случайно сгенерированный идентификатор сайта (UUID), добавляемый уровнем поставщика ИИ для различения сайта Moodle. Он не создается на основе URL сайта или каких-либо персональных данных.';
$string['privacy:metadata:ai_provider:siteurl'] = 'Веб-адрес сайта Moodle, добавляемый уровнем поставщика ИИ в каждый запрос.';
$string['privacy:metadata:ai_provider:studentid'] = 'Псевдонимизированный идентификатор (HMAC-хеш) анализируемого студента. Настоящий ID пользователя никогда не отправляется.';
$string['privacy:metadata:ai_provider:studentname'] = 'Общий заполнитель, отправляемый вместо настоящего имени студента. Настоящее имя никогда не покидает сайт и восстанавливается локально при отображении ответа.';
$string['privacy:metadata:ai_provider:timezone'] = 'Часовой пояс пользователя, запрашивающего анализ, добавляемый уровнем поставщика ИИ.';
$string['privacy:metadata:ai_provider:userid'] = 'Псевдонимизированный идентификатор (HMAC-хеш) пользователя, запрашивающего анализ. Настоящий ID пользователя никогда не отправляется.';
$string['privacy:metadata:report_lifestory_feedback'] = 'Хранит последний отзыв, созданный ИИ, для каждого студента, чтобы его можно было экспортировать и просматривать позже.';
$string['privacy:metadata:report_lifestory_feedback:courseid'] = 'Фильтр курса, с которым был создан отзыв (0 означает все курсы).';
$string['privacy:metadata:report_lifestory_feedback:feedback'] = 'Текст отзыва, созданного ИИ.';
$string['privacy:metadata:report_lifestory_feedback:studentid'] = 'Студент, к которому относится отзыв.';
$string['privacy:metadata:report_lifestory_feedback:timecreated'] = 'Время создания записи отзыва.';
$string['privacy:metadata:report_lifestory_feedback:timemodified'] = 'Время последнего изменения записи отзыва.';
$string['privacy:metadata:report_lifestory_feedback:usermodified'] = 'Пользователь, создавший отзыв.';
$string['range'] = 'Диапазон';
$string['regeneratefeedback'] = 'Повторно создать отзыв с помощью ИИ';
$string['searchusers'] = 'Поиск пользователей';
$string['section'] = 'Раздел';
$string['selectuser'] = 'Пожалуйста, выберите пользователя, чтобы увидеть его историю жизни';
$string['studentlabel'] = 'Студент';
$string['total'] = 'Всего';
$string['unexpected_ai_error'] = 'Неожиданная ошибка при обработке ИИ: {$a}';
