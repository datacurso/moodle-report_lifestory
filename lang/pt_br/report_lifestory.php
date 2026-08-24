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

$string['activity'] = 'Atividade';
$string['altlogo'] = 'Logo Datacurso';
$string['clearselection'] = 'Limpar seleção';
$string['course'] = 'Curso';
$string['coursetotal'] = 'Total do curso';
$string['error_ai_service'] = 'Erro no serviço de IA: {$a}';
$string['error_airequest'] = 'Erro ao comunicar-se com o serviço de IA: {$a}';
$string['exportcsv'] = 'Exportar para CSV';
$string['exportpdf'] = 'Exportar para PDF';
$string['feedback'] = 'Feedback';
$string['feedbackfromai'] = 'Feedback da IA';
$string['generatefeedback'] = 'Gerar feedback com IA';
$string['generatingfeedback'] = 'Gerando feedback';
$string['gradepercent'] = 'Nota (%)';
$string['lifestory'] = 'História de vida do estudante';
$string['lifestory:generateaifeedback'] = 'Gerar feedback com IA para os estudantes';
$string['lifestory:view'] = 'Ver relatório da história de vida';
$string['nofeedbacktopdf'] = 'Gere o feedback da IA antes de exportar o PDF.';
$string['noreportdata'] = 'Nenhum dado de relatório disponível.';
$string['noresponse'] = 'Nenhuma resposta recebida.';
$string['pluginname'] = 'História de vida do estudante IA';
$string['privacy:metadata:ai_provider'] = 'Os dados são enviados ao serviço de IA da Datacurso para gerar feedback baseado no histórico acadêmico do estudante.';
$string['privacy:metadata:ai_provider:courses'] = 'Histórico acadêmico estruturado usado na análise: nomes de cursos, seções e atividades, notas, intervalos e porcentagens, e textos de feedback dos professores com o nome do estudante mascarado por um marcador de posição.';
$string['privacy:metadata:ai_provider:lang'] = 'O idioma do usuário que solicita a análise, adicionado pela camada do provedor de IA.';
$string['privacy:metadata:ai_provider:siteid'] = 'Um identificador persistente do site gerado aleatoriamente (UUID), adicionado pela camada do provedor de IA para distinguir o site Moodle. Ele não é derivado da URL do site nem de nenhum dado pessoal.';
$string['privacy:metadata:ai_provider:siteurl'] = 'O endereço web do site Moodle, adicionado pela camada do provedor de IA em cada solicitação.';
$string['privacy:metadata:ai_provider:studentid'] = 'Um identificador pseudonimizado (hash HMAC) do estudante analisado. O ID real do usuário nunca é enviado.';
$string['privacy:metadata:ai_provider:studentname'] = 'Um marcador de posição genérico enviado no lugar do nome real do estudante. O nome real nunca sai do site e é restaurado localmente quando a resposta é exibida.';
$string['privacy:metadata:ai_provider:timezone'] = 'O fuso horário do usuário que solicita a análise, adicionado pela camada do provedor de IA.';
$string['privacy:metadata:ai_provider:userid'] = 'Um identificador pseudonimizado (hash HMAC) do usuário que solicita a análise. O ID real do usuário nunca é enviado.';
$string['range'] = 'Intervalo';
$string['searchusers'] = 'Pesquisar usuários';
$string['section'] = 'Seção';
$string['selectuser'] = 'Por favor, selecione um usuário para ver sua história de vida';
$string['studentlabel'] = 'Estudante';
$string['total'] = 'Total';
$string['unexpected_ai_error'] = 'Erro inesperado no processamento da IA: {$a}';
