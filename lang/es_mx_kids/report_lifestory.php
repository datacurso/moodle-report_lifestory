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

$string['activity'] = 'Actividad';
$string['altlogo'] = 'Logo Datacurso';
$string['clearselection'] = 'Limpiar';
$string['course'] = 'Curso';
$string['coursetotal'] = 'Total del curso';
$string['error_ai_service'] = 'Error del servicio de IA: {$a}';
$string['error_airequest'] = 'Error al comunicarse con el servicio de IA: {$a}';
$string['exportcsv'] = 'Exportar a CSV';
$string['exportpdf'] = 'Exportar a PDF';
$string['feedback'] = 'Retroalimentación';
$string['feedbackfromai'] = 'Retroalimentación de IA';
$string['generatefeedback'] = 'Genera retroalimentación con IA';
$string['generatingfeedback'] = 'Generando retroalimentación';
$string['gradepercent'] = 'Nota (%)';
$string['lifestory'] = 'Historia de vida del estudiante';
$string['lifestory:generateaifeedback'] = 'Generar retroalimentación con IA para los estudiantes';
$string['lifestory:view'] = 'Vea el informe de la historia de vida';
$string['nofeedbacktopdf'] = 'Genera retroalimentación de IA antes de exportar el PDF.';
$string['noreportdata'] = 'No hay datos de informe disponibles.';
$string['noresponse'] = 'No se recibió respuesta.';
$string['pluginname'] = 'Historia de vida del estudiante IA';
$string['privacy:metadata:ai_provider'] = 'Se envían datos al servicio de IA de Datacurso para generar retroalimentación basada en el historial académico del estudiante.';
$string['privacy:metadata:ai_provider:courses'] = 'Historial académico estructurado usado para el análisis: nombres de cursos, secciones y actividades, calificaciones, rangos y porcentajes, y textos de retroalimentación del profesor con el nombre del estudiante enmascarado mediante un marcador de posición.';
$string['privacy:metadata:ai_provider:lang'] = 'El idioma del usuario que solicita el análisis, añadido por la capa del proveedor de IA.';
$string['privacy:metadata:ai_provider:siteid'] = 'Un identificador persistente del sitio generado aleatoriamente (UUID), añadido por la capa del proveedor de IA para distinguir el sitio Moodle. No se deriva de la URL del sitio ni de ningún dato personal.';
$string['privacy:metadata:ai_provider:siteurl'] = 'La dirección web del sitio Moodle, añadida por la capa del proveedor de IA en cada solicitud.';
$string['privacy:metadata:ai_provider:studentid'] = 'Un identificador seudonimizado (hash HMAC) del estudiante que se analiza. El ID real del usuario nunca se envía.';
$string['privacy:metadata:ai_provider:studentname'] = 'Un marcador de posición genérico enviado en lugar del nombre real del estudiante. El nombre real nunca sale del sitio y se restaura localmente al mostrar la respuesta.';
$string['privacy:metadata:ai_provider:timezone'] = 'La zona horaria del usuario que solicita el análisis, añadida por la capa del proveedor de IA.';
$string['privacy:metadata:ai_provider:userid'] = 'Un identificador seudonimizado (hash HMAC) del usuario que solicita el análisis. El ID real del usuario nunca se envía.';
$string['range'] = 'Rango';
$string['searchusers'] = 'Buscar usuarios';
$string['section'] = 'Seccion';
$string['selectuser'] = 'Por favor seleccione un usuario para ver su historia de vida';
$string['studentlabel'] = 'Estudiante';
$string['total'] = 'Total';
$string['unexpected_ai_error'] = 'Error inesperado en el procesamiento de IA: {$a}';
