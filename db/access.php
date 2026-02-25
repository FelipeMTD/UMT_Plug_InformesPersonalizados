<?php
// db/access.php
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

    // 2. Permiso para el informe Free
    'report/rolcomparativa:view_report_coursestatus' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],

    // 3. NUEVO PERMISO para el informe Pay (El que te falta)
    'report/rolcomparativa:view_report_paidcoursestatus' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],
];