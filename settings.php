<?php
defined('MOODLE_INTERNAL') || die;

// Solo agregamos la página si el usuario tiene permisos de configuración.
if ($hassiteconfig) {
    // Agregamos una página externa al nodo de "Informes" (reports).
    $ADMIN->add('reports', new admin_externalpage(
        'report_rolcomparativa',                             // ID de la página (debe coincidir con index.php)
        get_string('pluginname', 'report_rolcomparativa'),   // Nombre que aparecerá en el menú
        new moodle_url('/report/rolcomparativa/index.php'), // URL del archivo principal
        'report/rolcomparativa:view'                         // Capacidad requerida para verlo
    ));
}