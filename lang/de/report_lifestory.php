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

$string['activity'] = 'Aktivität';
$string['altlogo'] = 'Datacurso-Logo';
$string['clearselection'] = 'Auswahl löschen';
$string['course'] = 'Kurs';
$string['coursetotal'] = 'Kursgesamt';
$string['error_ai_service'] = 'Fehler im KI-Dienst: {$a}';
$string['error_airequest'] = 'Fehler bei der Kommunikation mit dem KI-Dienst: {$a}';
$string['exportcsv'] = 'Als CSV exportieren';
$string['exportpdf'] = 'Als PDF exportieren';
$string['feedback'] = 'Rückmeldung';
$string['feedbackfromai'] = 'KI-Feedback';
$string['generatefeedback'] = 'Feedback mit KI generieren';
$string['generatingfeedback'] = 'Feedback wird generiert';
$string['gradepercent'] = 'Note (%)';
$string['lifestory'] = 'Lebensgeschichte des Studenten';
$string['lifestory:generateaifeedback'] = 'KI-Feedback für Studierende generieren';
$string['lifestory:view'] = 'Lebensgeschichtenbericht ansehen';
$string['nofeedbacktopdf'] = 'Generieren Sie KI-Feedback, bevor Sie das PDF exportieren.';
$string['noreportdata'] = 'Keine Berichts­daten verfügbar.';
$string['noresponse'] = 'Keine Antwort erhalten.';
$string['pluginname'] = 'KI-Lebensgeschichte des Studenten';
$string['privacy:metadata:ai_provider'] = 'Daten werden an den Datacurso-KI-Dienst gesendet, um Feedback auf Grundlage der akademischen Laufbahn des Studierenden zu generieren.';
$string['privacy:metadata:ai_provider:courses'] = 'Strukturierte akademische Laufbahn für die Analyse: Namen von Kursen, Abschnitten und Aktivitäten, Bewertungen, Bereiche und Prozentwerte sowie Feedback-Texte der Lehrenden, in denen der Name des Studierenden durch einen Platzhalter maskiert ist.';
$string['privacy:metadata:ai_provider:lang'] = 'Die Sprache des Benutzers, der die Analyse anfordert, hinzugefügt durch die KI-Anbieterschicht.';
$string['privacy:metadata:ai_provider:siteid'] = 'Eine dauerhafte, zufällig generierte Website-Kennung (UUID), die von der KI-Anbieterschicht hinzugefügt wird, um die Moodle-Website zu unterscheiden. Sie wird weder aus der Website-URL noch aus personenbezogenen Daten abgeleitet.';
$string['privacy:metadata:ai_provider:siteurl'] = 'Die Webadresse der Moodle-Website, die von der KI-Anbieterschicht bei jeder Anfrage hinzugefügt wird.';
$string['privacy:metadata:ai_provider:studentid'] = 'Eine pseudonymisierte Kennung (HMAC-Hash) des analysierten Studierenden. Die echte Benutzer-ID wird niemals gesendet.';
$string['privacy:metadata:ai_provider:studentname'] = 'Ein generischer Platzhalter, der anstelle des echten Namens des Studierenden gesendet wird. Der echte Name verlässt die Website nie und wird lokal wiederhergestellt, wenn die Antwort angezeigt wird.';
$string['privacy:metadata:ai_provider:timezone'] = 'Die Zeitzone des Benutzers, der die Analyse anfordert, hinzugefügt durch die KI-Anbieterschicht.';
$string['privacy:metadata:ai_provider:userid'] = 'Eine pseudonymisierte Kennung (HMAC-Hash) des Benutzers, der die Analyse anfordert. Die echte Benutzer-ID wird niemals gesendet.';
$string['privacy:metadata:report_lifestory_feedback'] = 'Speichert das zuletzt von der KI generierte Feedback für jeden Studenten, damit es exportiert und später eingesehen werden kann.';
$string['privacy:metadata:report_lifestory_feedback:courseid'] = 'Der Kursfilter, mit dem das Feedback generiert wurde (0 bedeutet alle Kurse).';
$string['privacy:metadata:report_lifestory_feedback:feedback'] = 'Der von der KI generierte Feedbacktext.';
$string['privacy:metadata:report_lifestory_feedback:studentid'] = 'Der Student, auf den sich das Feedback bezieht.';
$string['privacy:metadata:report_lifestory_feedback:timecreated'] = 'Der Zeitpunkt, zu dem der Feedbackdatensatz erstellt wurde.';
$string['privacy:metadata:report_lifestory_feedback:timemodified'] = 'Der Zeitpunkt der letzten Änderung des Feedbackdatensatzes.';
$string['privacy:metadata:report_lifestory_feedback:usermodified'] = 'Der Benutzer, der das Feedback generiert hat.';
$string['range'] = 'Bereich';
$string['searchusers'] = 'Benutzer suchen';
$string['section'] = 'Abschnitt';
$string['selectuser'] = 'Bitte wählen Sie einen Benutzer, um seine Lebensgeschichte zu sehen';
$string['studentlabel'] = 'Student';
$string['total'] = 'Gesamt';
$string['unexpected_ai_error'] = 'Unerwarteter Fehler bei der KI-Verarbeitung: {$a}';
