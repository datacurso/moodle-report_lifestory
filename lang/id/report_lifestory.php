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

$string['activity'] = 'Aktivitas';
$string['altlogo'] = 'Logo Datacurso';
$string['calculatedweight'] = 'Bobot terhitung';
$string['clearselection'] = 'Hapus pilihan';
$string['contributiontototal'] = 'Kontribusi terhadap total kursus';
$string['course'] = 'Kursus';
$string['coursetotal'] = 'Total kursus';
$string['error_ai_service'] = 'Kesalahan layanan AI: {$a}';
$string['error_airequest'] = 'Kesalahan komunikasi dengan layanan AI: {$a}';
$string['event:csvexported'] = 'CSV kisah hidup diekspor';
$string['event:feedbackgenerated'] = 'Umpan balik AI kisah hidup dihasilkan';
$string['event:pdfexported'] = 'PDF kisah hidup diekspor';
$string['event:reportviewed'] = 'Laporan kisah hidup dilihat';
$string['exportcsv'] = 'Ekspor ke CSV';
$string['exportpdf'] = 'Ekspor ke PDF';
$string['feedback'] = 'Umpan balik';
$string['feedbackfromai'] = 'Umpan balik dari AI';
$string['feedbackgeneratedon'] = 'Umpan balik dihasilkan pada {$a}';
$string['generatefeedback'] = 'Hasilkan umpan balik dengan AI';
$string['generatingfeedback'] = 'Menghasilkan umpan balik';
$string['grade'] = 'Nilai';
$string['gradepercent'] = 'Nilai (%)';
$string['lifestory'] = 'Kisah hidup siswa';
$string['lifestory:generateaifeedback'] = 'Hasilkan umpan balik AI untuk siswa';
$string['lifestory:view'] = 'Lihat laporan kisah hidup';
$string['nocoursesavailable'] = 'Siswa ini tidak memiliki pendaftaran kursus yang tersedia untuk ditampilkan dalam laporan ini.';
$string['nofeedbacktopdf'] = 'Buat umpan balik AI sebelum mengekspor PDF.';
$string['noreportdata'] = 'Tidak ada data laporan yang tersedia.';
$string['noresponse'] = 'Tidak ada respons yang diterima.';
$string['pdfnocoursedata'] = 'Tidak ada data nilai yang tersedia untuk kursus ini.';
$string['percentage'] = 'Persentase';
$string['pluginname'] = 'Kisah hidup siswa AI';
$string['privacy:metadata:ai_provider'] = 'Data dikirim ke layanan AI Datacurso untuk menghasilkan umpan balik berdasarkan riwayat akademik siswa.';
$string['privacy:metadata:ai_provider:courses'] = 'Riwayat akademik terstruktur yang digunakan untuk analisis: nama kursus, bagian, dan aktivitas, nilai, rentang, dan persentase, serta teks umpan balik guru dengan nama siswa yang disamarkan menggunakan penampung.';
$string['privacy:metadata:ai_provider:lang'] = 'Bahasa pengguna yang meminta analisis, ditambahkan oleh lapisan penyedia AI.';
$string['privacy:metadata:ai_provider:siteid'] = 'Pengenal situs persisten yang dibuat secara acak (UUID), ditambahkan oleh lapisan penyedia AI untuk membedakan situs Moodle. Pengenal ini tidak diturunkan dari URL situs maupun dari data pribadi apa pun.';
$string['privacy:metadata:ai_provider:siteurl'] = 'Alamat web situs Moodle, ditambahkan oleh lapisan penyedia AI pada setiap permintaan.';
$string['privacy:metadata:ai_provider:studentid'] = 'Pengenal tersamar (hash HMAC) dari siswa yang dianalisis. ID pengguna yang sebenarnya tidak pernah dikirim.';
$string['privacy:metadata:ai_provider:studentname'] = 'Penampung generik yang dikirim sebagai pengganti nama asli siswa. Nama asli tidak pernah meninggalkan situs dan dipulihkan secara lokal saat respons ditampilkan.';
$string['privacy:metadata:ai_provider:timezone'] = 'Zona waktu pengguna yang meminta analisis, ditambahkan oleh lapisan penyedia AI.';
$string['privacy:metadata:ai_provider:userid'] = 'Pengenal tersamar (hash HMAC) dari pengguna yang meminta analisis. ID pengguna yang sebenarnya tidak pernah dikirim.';
$string['privacy:metadata:report_lifestory_feedback'] = 'Menyimpan umpan balik terbaru yang dihasilkan AI untuk setiap siswa agar dapat diekspor dan dilihat kembali di kemudian hari.';
$string['privacy:metadata:report_lifestory_feedback:courseid'] = 'Filter kursus yang digunakan saat umpan balik dihasilkan (0 berarti semua kursus).';
$string['privacy:metadata:report_lifestory_feedback:feedback'] = 'Teks umpan balik yang dihasilkan AI.';
$string['privacy:metadata:report_lifestory_feedback:studentid'] = 'Siswa yang menjadi subjek umpan balik.';
$string['privacy:metadata:report_lifestory_feedback:timecreated'] = 'Waktu pembuatan catatan umpan balik.';
$string['privacy:metadata:report_lifestory_feedback:timemodified'] = 'Waktu perubahan terakhir catatan umpan balik.';
$string['privacy:metadata:report_lifestory_feedback:usermodified'] = 'Pengguna yang menghasilkan umpan balik.';
$string['range'] = 'Rentang';
$string['regeneratefeedback'] = 'Hasilkan ulang umpan balik dengan AI';
$string['searchbutton'] = 'Cari';
$string['searchmorematches'] = 'Lebih banyak siswa cocok dengan pencarian Anda. Persempit teks pencarian untuk membatasi hasil.';
$string['searchnoresults'] = 'Tidak ada siswa yang cocok dengan pencarian Anda.';
$string['searchusers'] = 'Cari pengguna';
$string['section'] = 'Bagian';
$string['selectuser'] = 'Silakan pilih pengguna untuk melihat kisah hidupnya';
$string['studentlabel'] = 'Siswa';
$string['total'] = 'Total';
$string['unexpected_ai_error'] = 'Kesalahan tak terduga dalam pemrosesan AI: {$a}';
