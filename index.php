<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Obtener el parámetro del informe seleccionado (si existe)
$reporttype = optional_param('type', '', PARAM_ALPHA);

// Configuración básica de página
admin_externalpage_setup('report_rolcomparativa');
$context = context_system::instance();

// Verificar permiso BASE (si no tiene esto, no entra a nada)
require_capability('report/rolcomparativa:view', $context);

// ==========================================================
// DEFINICIÓN DE INFORMES DISPONIBLES
// ==========================================================
// Aquí mapeamos: Clave URL => [Titulo, Permiso Requerido]
$available_reports = [
    'grades' => [
        'title' => get_string('report_grades_title', 'report_rolcomparativa'),
        'capability' => 'report/rolcomparativa:view_report_grades'
    ],
    'logs' => [
        'title' => get_string('report_logs_title', 'report_rolcomparativa'),
        'capability' => 'report/rolcomparativa:view_report_logs'
    ]
];

// ==========================================================
// LÓGICA DE VISTA
// ==========================================================

$PAGE->set_url(new moodle_url('/report/rolcomparativa/index.php', ['type' => $reporttype]));
$PAGE->set_title(get_string('pluginname', 'report_rolcomparativa'));
$PAGE->set_heading(get_string('pluginname', 'report_rolcomparativa'));

echo $OUTPUT->header();

// 1. Mostrar Pestañas o Menú de Selección
$tabs = [];
foreach ($available_reports as $key => $info) {
    // Solo mostramos la opción si el usuario tiene el permiso específico
    if (has_capability($info['capability'], $context)) {
        $tabs[] = new tabobject(
            $key, 
            new moodle_url('/report/rolcomparativa/index.php', ['type' => $key]), 
            $info['title']
        );
    }
}

// Si no hay pestañas (no tiene permisos para ningún sub-reporte)
if (empty($tabs)) {
    echo $OUTPUT->notification('No tienes permisos para ver ningún informe específico.', 'warning');
    echo $OUTPUT->footer();
    die();
}

// Renderizar las pestañas
echo $OUTPUT->tabtree($tabs, $reporttype);

// 2. Renderizar el Informe Seleccionado
if ($reporttype && isset($available_reports[$reporttype])) {
    
    // Doble verificación de seguridad antes de ejecutar SQL
    require_capability($available_reports[$reporttype]['capability'], $context);

    echo $OUTPUT->heading($available_reports[$reporttype]['title']);

    // SWITCH PARA ELEGIR LA CONSULTA SQL
    switch ($reporttype) {
        case 'grades':
            // AQUÍ IRÁ TU CONSULTA SQL #1 (Profesores/Notas)
            echo "<p>Cargando consulta de notas...</p>"; 
            // $sql = "SELECT ...";
            // $DB->get_records_sql...
            break;

        case 'logs':
            // AQUÍ IRÁ TU CONSULTA SQL #2 (Admin/Logs)
            echo "<p>Cargando consulta de logs administrativos...</p>";
            // $sql = "SELECT ...";
            break;
    }

} else {
    // Estado inicial: Ningún informe seleccionado
    echo $OUTPUT->box(get_string('select_report', 'report_rolcomparativa'), 'generalbox');
}

echo $OUTPUT->footer();