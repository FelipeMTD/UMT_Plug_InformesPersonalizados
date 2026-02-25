<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
// Incluimos la librería con las funciones de los informes
require_once(__DIR__ . '/locallib.php'); 

// 1. Capturar parámetros
// 'type': define qué pestaña se muestra. Por defecto carga 'coursestatus' (Free).
$reporttype = optional_param('type', 'coursestatus', PARAM_ALPHA);
// 'searchUser': captura lo que el usuario escribe en el buscador.
$searchterm = optional_param('searchUser', '', PARAM_TEXT);

// 2. Configuración básica de la página de administración
admin_externalpage_setup('report_rolcomparativa');
$context = context_system::instance();

// 3. Verificación de seguridad base (debe tener permiso para ver el plugin)
require_capability('report/rolcomparativa:view', $context);

// 4. Definición de Informes Disponibles
// Aquí registramos las pestañas y sus permisos específicos
$available_reports = [
    'coursestatus' => [
        'title' => get_string('report_coursestatus_title', 'report_rolcomparativa'),
        'capability' => 'report/rolcomparativa:view_report_coursestatus'
    ],
    'paidcoursestatus' => [ // NUEVO INFORME
        'title' => get_string('report_paidcoursestatus_title', 'report_rolcomparativa'),
        'capability' => 'report/rolcomparativa:view_report_paidcoursestatus'
    ]
];

// 5. Configuración de URL y Encabezados
$PAGE->set_url(new moodle_url('/report/rolcomparativa/index.php', ['type' => $reporttype]));
$PAGE->set_title(get_string('pluginname', 'report_rolcomparativa'));
$PAGE->set_heading(get_string('pluginname', 'report_rolcomparativa'));

echo $OUTPUT->header();

// 6. Generación de Pestañas (Tabs)
$tabs = [];
foreach ($available_reports as $key => $info) {
    // Solo mostramos la pestaña si el usuario tiene el permiso específico
    if (has_capability($info['capability'], $context)) {
        $tabs[] = new tabobject(
            $key, 
            new moodle_url('/report/rolcomparativa/index.php', ['type' => $key]), 
            $info['title']
        );
    }
}

// Si el usuario no tiene permisos para ninguno de los informes
if (empty($tabs)) {
    echo $OUTPUT->notification('No tienes permisos para ver ningún informe.', 'warning');
    echo $OUTPUT->footer();
    die();
}

// Renderizamos la barra de pestañas
echo $OUTPUT->tabtree($tabs, $reporttype);

// 7. Lógica del Informe Seleccionado
if ($reporttype && isset($available_reports[$reporttype])) {
    
    // Doble verificación de seguridad antes de ejecutar cualquier función
    require_capability($available_reports[$reporttype]['capability'], $context);

    // Título del informe actual
    echo $OUTPUT->heading($available_reports[$reporttype]['title']);

    // SWITCH: Decide qué función de locallib.php ejecutar
    switch ($reporttype) {
        case 'coursestatus':
            // Informe original (Certificados Free)
            report_rolcomparativa_print_coursestatus($searchterm);
            break;

        case 'paidcoursestatus':
            // Nuevo Informe (Certificados Pay + Enlace)
            report_rolcomparativa_print_paidcoursestatus($searchterm);
            break;
            
        default:
            echo $OUTPUT->notification('Informe no encontrado.', 'error');
    }

} else {
    // Caso raro donde el parámetro type no coincide con ningún reporte
    echo $OUTPUT->notification('Seleccione una pestaña válida.', 'info');
}

echo $OUTPUT->footer();