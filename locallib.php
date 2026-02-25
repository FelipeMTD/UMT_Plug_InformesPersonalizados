<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Función para generar el Informe de Calificaciones (Para Profesores/Managers)
 * Ejemplo: Muestra usuarios y su calificación final en un curso.
 */
function report_rolcomparativa_print_grades() {
    global $DB, $OUTPUT; // Usamos $COURSE si queremos filtrar por el curso actual

    // 1. La Consulta SQL Personalizada
    // Muestra usuarios, su rol y su nota final en el curso actual (ejemplo genérico)
    $sql = "SELECT u.id, u.firstname, u.lastname, r.shortname as rolename, gg.finalgrade
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            LEFT JOIN {grade_grades} gg ON gg.userid = u.id 
            WHERE u.deleted = 0 
            AND r.shortname = 'student'
            LIMIT 50"; // Limitado por seguridad en el ejemplo

    $records = $DB->get_records_sql($sql);

    // 2. Renderizar Tabla
    if (empty($records)) {
        echo $OUTPUT->notification(get_string('nodatafound', 'report_rolcomparativa'), 'info');
        return;
    }

    $table = new html_table();
    $table->head = ['Nombre', 'Rol', 'Nota Final'];
    $table->data = [];

    foreach ($records as $rec) {
        $table->data[] = [
            fullname($rec),
            $rec->rolename,
            $rec->finalgrade ? number_format($rec->finalgrade, 2) : '-'
        ];
    }

    echo html_writer::table($table);
}

/**
 * Función para generar el Informe de Logs (Solo Managers)
 * Ejemplo: Usuarios con más accesos en el sistema.
 */
function report_rolcomparativa_print_logs() {
    global $DB, $OUTPUT;

    // Consulta: Conteo de logins por usuario
    $sql = "SELECT u.id, u.firstname, u.lastname, COUNT(l.id) as logincount
            FROM {user} u
            JOIN {logstore_standard_log} l ON l.userid = u.id
            WHERE l.action = 'loggedin'
            GROUP BY u.id, u.firstname, u.lastname
            ORDER BY logincount DESC
            LIMIT 20";

    $records = $DB->get_records_sql($sql);

    // Renderizar Tabla
    $table = new html_table();
    $table->head = ['Usuario', 'Cantidad de Accesos'];
    
    foreach ($records as $rec) {
        $table->data[] = [fullname($rec), $rec->logincount];
    }
    echo html_writer::table($table);


    /**
 * Informe de Estado del Curso y Certificados "Free"
 */
function report_rolcomparativa_print_coursestatus() {
    global $DB, $OUTPUT;

    // CONSULTA SQL
    // Nota: Asumimos uso de mod_customcert.
    // Calculamos fecha de fin sumando el periodo de protección a la fecha de emisión.
    
    $sql = "SELECT 
                u.id AS userid,
                u.firstname,
                u.lastname,
                c.fullname AS coursename,
                
                -- Progreso (usamos fecha de completado como indicador)
                cc.timecompleted AS course_completed_date,
                
                -- Calificación (Gradebook final del curso)
                ROUND(gg.finalgrade, 2) AS finalgrade,
                
                -- Datos del Certificado
                ci.timecreated AS cert_issue_date,
                ci.code AS cert_code,
                mc.protection_period, -- Para calcular vencimiento
                
                -- Solo para debug si lo necesitas
                ct.name AS template_name

            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = 50 -- Contexto Curso
            
            -- Join para Calificaciones
            LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
            LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
            
            -- Join para Completitud del Curso
            LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id

            -- JOIN CRÍTICO: Certificados Custom (mod_customcert)
            JOIN {customcert} mc ON mc.course = c.id
            JOIN {customcert_templates} ct ON ct.id = mc.templateid
            LEFT JOIN {customcert_issues} ci ON ci.customcertid = mc.id AND ci.userid = u.id

            WHERE u.deleted = 0
            AND ct.name LIKE :templateprefix -- FILTRO 'Free'
            
            ORDER BY c.fullname, u.lastname
            LIMIT 100"; // Paginación recomendada para producción

    $params = ['templateprefix' => 'Free%'];
    $records = $DB->get_records_sql($sql, $params);

    // Si no hay datos
    if (empty($records)) {
        echo $OUTPUT->notification(get_string('nodatafound', 'report_rolcomparativa'), 'info');
        return;
    }

    // Configurar Tabla
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-bordered table-striped';
    $table->head = [
        get_string('col_fullname', 'report_rolcomparativa'),
        get_string('col_course', 'report_rolcomparativa'),
        get_string('col_progress', 'report_rolcomparativa'),
        get_string('col_grade', 'report_rolcomparativa'),
        get_string('col_date_completed', 'report_rolcomparativa'),
        get_string('col_date_issue', 'report_rolcomparativa'),
        get_string('col_date_expiry', 'report_rolcomparativa'),
        get_string('col_verifycode', 'report_rolcomparativa'),
    ];

    $table->data = [];

    foreach ($records as $rec) {
        
        // 1. Lógica de Fechas
        $date_completed = $rec->course_completed_date ? userdate($rec->course_completed_date, '%d/%m/%Y') : '-';
        $date_issue     = $rec->cert_issue_date ? userdate($rec->cert_issue_date, '%d/%m/%Y') : 'No emitido';
        
        // 2. Cálculo Fecha Fin Certificado
        // Si hay fecha de emisión y el certificado tiene periodo de protección (en segundos)
        $date_expiry = '-';
        if ($rec->cert_issue_date && $rec->protection_period > 0) {
            $expiry_timestamp = $rec->cert_issue_date + $rec->protection_period;
            $date_expiry = userdate($expiry_timestamp, '%d/%m/%Y');
        }

        // 3. Lógica de Estado
        $status = ($rec->course_completed_date) ? 
            '<span class="badge badge-success">Completado</span>' : 
            '<span class="badge badge-warning">En Progreso</span>';

        // 4. Construir Fila (TEXTO PLANO, sin enlaces)
        $row = [
            fullname($rec),             // Nombre Usuario
            $rec->coursename,           // Nombre Curso
            $status,                    // Progreso
            $rec->finalgrade ?? '-',    // Nota
            $date_completed,            // Fecha Fin Curso
            $date_issue,                // Fecha Emisión Cert
            $date_expiry,               // Fecha Fin Cert
            $rec->cert_code ?? '-'      // Código
        ];

        $table->data[] = $row;
    }

    echo html_writer::table($table);
    }
}