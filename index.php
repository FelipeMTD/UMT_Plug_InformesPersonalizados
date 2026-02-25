<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
// INCLUIR LA LIBRERÍA LOCAL
require_once(__DIR__ . '/locallib.php'); 

$reporttype = optional_param('type', '', PARAM_ALPHA);

admin_externalpage_setup('report_rolcomparativa');
$context = context_system::instance();

require_capability('report/rolcomparativa:view', $context);

// Definición de informes (Igual que tenías)
$available_reports = [
    'grades' => [
        'title' => get_string('report_grades_title', 'report_rolcomparativa'),
        'capability' => 'report/rolcomparativa:view_report_grades'
    ],
    'logs' => [
        'title' => get_string('report_logs_title', 'report_rolcomparativa'),
        'capability' => 'report/rolcomparativa:view_report_logs'
    ],
    'coursestatus' => [
        'title' => get_string('report_coursestatus_title', 'report_rolcomparativa'),
        'capability' => 'report/rolcomparativa:view_report_coursestatus'
    ],
];

$PAGE->set_url(new moodle_url('/report/rolcomparativa/index.php', ['type' => $reporttype]));
$PAGE->set_title(get_string('pluginname', 'report_rolcomparativa'));
$PAGE->set_heading(get_string('pluginname', 'report_rolcomparativa'));

echo $OUTPUT->header();

// Pestañas (Tu código estaba bien aquí)
$tabs = [];
foreach ($available_reports as $key => $info) {
    if (has_capability($info['capability'], $context)) {
        $tabs[] = new tabobject($key, new moodle_url('/report/rolcomparativa/index.php', ['type' => $key]), $info['title']);
    }
}

if (empty($tabs)) {
    echo $OUTPUT->notification('No tienes permisos.', 'warning');
    echo $OUTPUT->footer();
    die();
}
echo $OUTPUT->tabtree($tabs, $reporttype);

// SWITCH SIMPLIFICADO
if ($reporttype && isset($available_reports[$reporttype])) {
    require_capability($available_reports[$reporttype]['capability'], $context);
    echo $OUTPUT->heading($available_reports[$reporttype]['title']);

    switch ($reporttype) {
        case 'grades':
            report_rolcomparativa_print_grades(); // Función en locallib.php
            break;
        case 'logs':
            report_rolcomparativa_print_logs();   // Función en locallib.php
            break;
        case 'coursestatus':
            report_rolcomparativa_print_coursestatus();
            break;
    }
} else {
    echo $OUTPUT->box(get_string('select_report', 'report_rolcomparativa'), 'generalbox');
}

echo $OUTPUT->footer();