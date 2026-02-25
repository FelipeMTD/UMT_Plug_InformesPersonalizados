<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // 1. Permiso base para entrar al plugin
    'report/rolcomparativa:view' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],

    // 2. Permiso EXCLUSIVO para el Informe A (Ej: Comparativa de Notas)
    'report/rolcomparativa:view_report_grades' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW, // Solo profesores y managers
            'manager' => CAP_ALLOW,
        ],
    ],

    // 3. Permiso EXCLUSIVO para el Informe B (Ej: Comparativa de Logs/Admin)
    'report/rolcomparativa:view_report_logs' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW, // Solo managers (profesores NO)
        ],
    ],
];