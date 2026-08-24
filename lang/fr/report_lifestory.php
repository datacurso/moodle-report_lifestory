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

$string['activity'] = 'Activité';
$string['altlogo'] = 'Logo Datacurso';
$string['clearselection'] = 'Effacer la sélection';
$string['course'] = 'Cours';
$string['coursetotal'] = 'Total du cours';
$string['error_ai_service'] = 'Erreur du service IA : {$a}';
$string['error_airequest'] = 'Erreur de communication avec le service IA : {$a}';
$string['exportcsv'] = 'Exporter en CSV';
$string['exportpdf'] = 'Exporter en PDF';
$string['feedback'] = 'Retour d’information';
$string['feedbackfromai'] = 'Retour de l’IA';
$string['generatefeedback'] = 'Générer un retour avec l’IA';
$string['generatingfeedback'] = 'Génération du retour';
$string['gradepercent'] = 'Note (%)';
$string['lifestory'] = 'Histoire de vie de l’étudiant';
$string['lifestory:generateaifeedback'] = 'Générer un retour avec l’IA pour les étudiants';
$string['lifestory:view'] = 'Voir le rapport de l’histoire de vie';
$string['nofeedbacktopdf'] = 'Générez un retour IA avant d’exporter le PDF.';
$string['noreportdata'] = 'Aucune donnée de rapport disponible.';
$string['noresponse'] = 'Aucune réponse reçue.';
$string['pluginname'] = 'Histoire de vie de l’étudiant IA';
$string['privacy:metadata:ai_provider'] = 'Les données sont envoyées au service d’IA Datacurso pour générer un retour basé sur l’historique académique de l’étudiant.';
$string['privacy:metadata:ai_provider:courses'] = 'Historique académique structuré utilisé pour l’analyse : noms des cours, des sections et des activités, notes, plages et pourcentages, ainsi que les textes de retour des enseignants dans lesquels le nom de l’étudiant est masqué par un espace réservé.';
$string['privacy:metadata:ai_provider:lang'] = 'La langue de l’utilisateur qui demande l’analyse, ajoutée par la couche du fournisseur d’IA.';
$string['privacy:metadata:ai_provider:siteid'] = 'Un identifiant de site persistant généré aléatoirement (UUID), ajouté par la couche du fournisseur d’IA pour distinguer le site Moodle. Il n’est dérivé ni de l’URL du site ni d’aucune donnée personnelle.';
$string['privacy:metadata:ai_provider:siteurl'] = 'L’adresse web du site Moodle, ajoutée par la couche du fournisseur d’IA à chaque requête.';
$string['privacy:metadata:ai_provider:studentid'] = 'Un identifiant pseudonymisé (hachage HMAC) de l’étudiant analysé. L’ID réel de l’utilisateur n’est jamais envoyé.';
$string['privacy:metadata:ai_provider:studentname'] = 'Un espace réservé générique envoyé à la place du nom réel de l’étudiant. Le nom réel ne quitte jamais le site et est restauré localement lors de l’affichage de la réponse.';
$string['privacy:metadata:ai_provider:timezone'] = 'Le fuseau horaire de l’utilisateur qui demande l’analyse, ajouté par la couche du fournisseur d’IA.';
$string['privacy:metadata:ai_provider:userid'] = 'Un identifiant pseudonymisé (hachage HMAC) de l’utilisateur qui demande l’analyse. L’ID réel de l’utilisateur n’est jamais envoyé.';
$string['privacy:metadata:report_lifestory_feedback'] = 'Stocke le dernier retour généré par l’IA pour chaque étudiant afin de pouvoir l’exporter et le consulter ultérieurement.';
$string['privacy:metadata:report_lifestory_feedback:courseid'] = 'Le filtre de cours avec lequel le retour a été généré (0 signifie tous les cours).';
$string['privacy:metadata:report_lifestory_feedback:feedback'] = 'Le texte du retour généré par l’IA.';
$string['privacy:metadata:report_lifestory_feedback:studentid'] = 'L’étudiant concerné par le retour.';
$string['privacy:metadata:report_lifestory_feedback:timecreated'] = 'La date et l’heure de création de l’enregistrement du retour.';
$string['privacy:metadata:report_lifestory_feedback:timemodified'] = 'La date et l’heure de la dernière modification de l’enregistrement du retour.';
$string['privacy:metadata:report_lifestory_feedback:usermodified'] = 'L’utilisateur qui a généré le retour.';
$string['range'] = 'Plage';
$string['searchusers'] = 'Rechercher des utilisateurs';
$string['section'] = 'Section';
$string['selectuser'] = 'Veuillez sélectionner un utilisateur pour voir son histoire de vie';
$string['studentlabel'] = 'Étudiant';
$string['total'] = 'Total';
$string['unexpected_ai_error'] = 'Erreur inattendue lors du traitement par l’IA : {$a}';
