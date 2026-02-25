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

}
    /**
 * Informe de Estado del Curso y Certificados "Free"
 */
/**
 * Informe de Estado del Curso con Buscador (Filtrado por Usuario)
 * @param string $searchterm Término de búsqueda opcional
 */
function report_rolcomparativa_print_coursestatus($searchterm = '') {
    global $DB, $OUTPUT, $PAGE;

    // 1. Renderizar Formulario de Búsqueda
    // Usamos un formulario GET simple que recarga la misma página
    echo '<div class="box generalbox p-3" style="background:#f8f9fa; border:1px solid #dee2e6; margin-bottom:20px;">';
    echo '<form method="get" action="'.$PAGE->url->out(false).'" class="form-inline">';
    echo '<input type="hidden" name="type" value="coursestatus">'; // Mantenernos en la pestaña correcta
    
    echo '<label for="searchUser" class="mr-2"><strong>'.get_string('search_label', 'report_rolcomparativa').': </strong></label>';
    echo '<input type="text" name="searchUser" id="searchUser" class="form-control mr-2" size="40" 
                 placeholder="'.get_string('search_placeholder', 'report_rolcomparativa').'" 
                 value="'.s($searchterm).'">';
    
    echo '<button type="submit" class="btn btn-primary">'.get_string('search_btn', 'report_rolcomparativa').'</button>';
    echo '</form>';
    echo '</div>';

    // 2. Validar si hay búsqueda
    if (empty($searchterm) || strlen(trim($searchterm)) < 3) {
        if (!empty($searchterm)) {
            echo $OUTPUT->notification(get_string('search_help', 'report_rolcomparativa'), 'warning');
        } else {
            echo $OUTPUT->notification(get_string('enter_search_term', 'report_rolcomparativa'), 'info');
        }
        return; // DETENEMOS AQUÍ: No cargamos la tabla masiva
    }

    // 3. Preparar SQL con Filtros Dinámicos
    // Concatenamos nombre y apellido para búsqueda natural
    $db_fullname = $DB->sql_fullname('u.firstname', 'u.lastname');

    $sql = "SELECT 
                u.id AS userid,
                u.firstname,
                u.lastname,
                u.username,
                u.email,
                c.fullname AS coursename,
                cc.timecompleted AS course_completed_date,
                ROUND(gg.finalgrade, 2) AS finalgrade,
                ci.timecreated AS cert_issue_date,
                ci.code AS cert_code,
                mc.protection_period
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = 50
            
            -- Joins de datos
            LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
            LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
            LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id

            -- Certificados (Custom Cert)
            JOIN {customcert} mc ON mc.course = c.id
            JOIN {customcert_templates} ct ON ct.id = mc.templateid
            LEFT JOIN {customcert_issues} ci ON ci.customcertid = mc.id AND ci.userid = u.id

            WHERE u.deleted = 0
            AND ct.name LIKE :templateprefix
            -- FILTRO DE BÚSQUEDA
            AND (
                u.username LIKE :search1 OR 
                u.email LIKE :search2 OR 
                $db_fullname LIKE :search3
            )
            
            ORDER BY u.lastname, c.fullname";

    // Parámetros seguros
    $params = [
        'templateprefix' => 'Free%',
        'search1' => '%' . $searchterm . '%',
        'search2' => '%' . $searchterm . '%',
        'search3' => '%' . $searchterm . '%'
    ];

    $records = $DB->get_records_sql($sql, $params);

    // 4. Renderizar Tabla
    if (empty($records)) {
        echo $OUTPUT->notification(get_string('search_no_results', 'report_rolcomparativa'), 'warning');
        return;
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-bordered table-striped';
    $table->head = [
        get_string('col_fullname', 'report_rolcomparativa'), // Nombre
        'Usuario / Email',                                   // Columna combinada para ahorrar espacio
        get_string('col_course', 'report_rolcomparativa'),
        get_string('col_progress', 'report_rolcomparativa'),
        get_string('col_grade', 'report_rolcomparativa'),
        get_string('col_date_issue', 'report_rolcomparativa'),
        get_string('col_verifycode', 'report_rolcomparativa'),
    ];

    $table->data = [];

    foreach ($records as $rec) {
        
        // Formateo de fechas y datos
        $date_issue = $rec->cert_issue_date ? userdate($rec->cert_issue_date, '%d/%m/%Y') : '-';
        
        $status = ($rec->course_completed_date) ? 
            '<span class="badge badge-success">Completado</span>' : 
            '<span class="badge badge-secondary">En Progreso</span>';

        // Pequeño detalle: Link al perfil del usuario (opcional, pero útil)
        $user_link = html_writer::link(new moodle_url('/user/view.php', ['id' => $rec->userid, 'course' => 1]), fullname($rec));

        $row = [
            $user_link,
            '<small>' . $rec->username . '<br>' . $rec->email . '</small>',
            $rec->coursename,
            $status,
            $rec->finalgrade ?? '-',
            $date_issue,
            $rec->cert_code ? '<code>'.$rec->cert_code.'</code>' : '-'
        ];

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}