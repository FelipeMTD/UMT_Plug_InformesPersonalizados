<?php
// locallib.php
defined('MOODLE_INTERNAL') || die();

/**
 * Informe de Estado del Curso con porcentaje de progreso numérico
 */
function report_rolcomparativa_print_coursestatus($searchterm = '') {
    global $DB, $OUTPUT, $PAGE;

    // 1. Buscador HTML (Se mantiene igual)
    echo '<div class="box generalbox p-3" style="background:#f8f9fa; border:1px solid #dee2e6; margin-bottom:20px;">';
    echo '<form method="get" action="'.$PAGE->url->out(false).'" class="form-inline">';
    echo '<input type="hidden" name="type" value="coursestatus">';
    echo '<label for="searchUser" class="mr-2"><strong>'.get_string('search_label', 'report_rolcomparativa').': </strong></label>';
    echo '<input type="text" name="searchUser" id="searchUser" class="form-control mr-2" size="40" placeholder="'.get_string('search_placeholder', 'report_rolcomparativa').'" value="'.s($searchterm).'">';
    echo '<button type="submit" class="btn btn-primary">'.get_string('search_btn', 'report_rolcomparativa').'</button>';
    echo '</form></div>';

    if (empty($searchterm) || strlen(trim($searchterm)) < 3) {
        echo $OUTPUT->notification(get_string('enter_search_term', 'report_rolcomparativa'), 'info');
        return;
    }

    // 2. Consulta SQL Actualizada
    // Añadimos campos de nombre para evitar errores de debug y c.id para calcular progreso
    $db_fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    // Eliminamos $db_fullname ya que no buscaremos por nombre
    $sql = "SELECT u.id AS userid, u.firstname, u.lastname, 
                   u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, 
                   u.username, u.email, 
                   c.id AS courseid, c.fullname AS coursename, 
                   cc.timecompleted AS course_completed_date,
                   ROUND(gg.finalgrade, 2) AS finalgrade,
                   ct.name AS template_name, tci.code AS cert_code, tci.timecreated AS cert_issue_date,
                   tci.expires AS cert_expiry_date
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = 50
            LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
            LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
            LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
            JOIN {coursecertificate} mcc ON mcc.course = c.id
            JOIN {tool_certificate_templates} ct ON ct.id = mcc.template
            LEFT JOIN {tool_certificate_issues} tci ON tci.userid = u.id AND tci.templateid = ct.id 
                 AND tci.courseid = c.id AND tci.component = 'mod_coursecertificate'
            WHERE u.deleted = 0 AND ct.name LIKE :templateprefix
            -- Ajustamos la condición para que solo busque en username o email
            AND (u.username LIKE :search1 OR u.email LIKE :search2)
            ORDER BY u.lastname, c.fullname";

    // Los parámetros ahora solo incluyen search1 y search2
    $params = [
        'templateprefix' => 'Free%', 
        'search1' => '%'.$searchterm.'%', 
        'search2' => '%'.$searchterm.'%'
    ];
    $records = $DB->get_records_sql($sql, $params);

    if (empty($records)) {
        echo $OUTPUT->notification(get_string('search_no_results', 'report_rolcomparativa'), 'warning');
        return;
    }

    // 3. Renderizar Tabla
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-bordered table-striped';
    $table->head = [get_string('col_fullname', 'report_rolcomparativa'), 'Usuario / Email', get_string('col_course', 'report_rolcomparativa'), 
                    get_string('col_progress', 'report_rolcomparativa'), get_string('col_grade', 'report_rolcomparativa'), 
                    get_string('col_date_issue', 'report_rolcomparativa'), get_string('col_date_expiry', 'report_rolcomparativa'), 
                    get_string('col_verifycode', 'report_rolcomparativa')];

    foreach ($records as $rec) {
        // Cálculo del porcentaje de progreso numérico
        $courseobj = get_course($rec->courseid);
        $progress_pc = \core_completion\progress::get_course_progress_percentage($courseobj, $rec->userid);
        
        // Si el curso está marcado como completado, forzamos al 100% si el cálculo falla
        if ($rec->course_completed_date && ($progress_pc === null || $progress_pc < 100)) {
            $progress_num = "100%";
        } else {
            $progress_num = ($progress_pc !== null) ? round($progress_pc) . "%" : "0%";
        }

        $date_issue = $rec->cert_issue_date ? userdate($rec->cert_issue_date, '%d/%m/%Y') : 'No emitido';
        $date_expiry = ($rec->cert_expiry_date && $rec->cert_expiry_date > 0) ? userdate($rec->cert_expiry_date, '%d/%m/%Y') : 'Nunca';
        
        // Mostramos el número de progreso junto al badge
        $status_badge = ($rec->course_completed_date) ? 
            '<span class="badge badge-success">Completado</span>' : 
            '<span class="badge badge-secondary">En Progreso</span>';
        
        $progress_display = '<strong>' . $progress_num . '</strong><br>' . $status_badge;

        $table->data[] = [
            fullname($rec), 
            '<small>'.$rec->username.'<br>'.$rec->email.'</small>', 
            $rec->coursename, 
            $progress_display, 
            $rec->finalgrade ?? '-', 
            $date_issue, 
            $date_expiry, 
            $rec->cert_code ? '<code>'.$rec->cert_code.'</code>' : '-'
        ];
    }
    echo html_writer::table($table);
}

/**
 * Informe de Estado del Curso para Certificados "Pay" con enlace de visualización
 */
function report_rolcomparativa_print_paidcoursestatus($searchterm = '') {
    global $DB, $OUTPUT, $PAGE;

    // 1. Buscador HTML (Username / Email únicamente)
    echo '<div class="box generalbox p-3" style="background:#f1f8ff; border:1px solid #cce5ff; margin-bottom:20px;">';
    echo '<form method="get" action="'.$PAGE->url->out(false).'" class="form-inline">';
    echo '<input type="hidden" name="type" value="paidcoursestatus">';
    echo '<label for="searchUser" class="mr-2"><strong>'.get_string('search_label', 'report_rolcomparativa').': </strong></label>';
    echo '<input type="text" name="searchUser" id="searchUser" class="form-control mr-2" size="40" placeholder="Username o Email..." value="'.s($searchterm).'">';
    echo '<button type="submit" class="btn btn-primary">'.get_string('search_btn', 'report_rolcomparativa').'</button>';
    echo '</form></div>';

    if (empty($searchterm) || strlen(trim($searchterm)) < 3) {
        echo $OUTPUT->notification(get_string('enter_search_term', 'report_rolcomparativa'), 'info');
        return;
    }

    // 2. Consulta SQL con Join a course_modules para obtener el enlace de visualización
    $sql = "SELECT u.id AS userid, u.firstname, u.lastname, 
                   u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, 
                   u.username, u.email, 
                   c.id AS courseid, c.fullname AS coursename, 
                   cc.timecompleted AS course_completed_date,
                   ROUND(gg.finalgrade, 2) AS finalgrade,
                   ct.name AS template_name, tci.code AS cert_code, tci.timecreated AS cert_issue_date,
                   tci.expires AS cert_expiry_date,
                   cm.id AS cmid -- ID del módulo para el enlace
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = 50
            LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
            LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
            LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
            
            -- Lógica de Course Certificate
            JOIN {coursecertificate} mcc ON mcc.course = c.id
            JOIN {tool_certificate_templates} ct ON ct.id = mcc.template
            JOIN {course_modules} cm ON cm.instance = mcc.id 
            JOIN {modules} m ON m.id = cm.module AND m.name = 'coursecertificate'
            
            LEFT JOIN {tool_certificate_issues} tci ON tci.userid = u.id AND tci.templateid = ct.id 
                 AND tci.courseid = c.id AND tci.component = 'mod_coursecertificate'
            
            WHERE u.deleted = 0 
            AND ct.name LIKE :templateprefix
            AND (u.username LIKE :search1 OR u.email LIKE :search2)
            ORDER BY u.lastname, c.fullname";

    $params = [
        'templateprefix' => 'Pay%', 
        'search1' => '%'.$searchterm.'%', 
        'search2' => '%'.$searchterm.'%'
    ];
    
    $records = $DB->get_records_sql($sql, $params);

    if (empty($records)) {
        echo $OUTPUT->notification(get_string('search_no_results', 'report_rolcomparativa'), 'warning');
        return;
    }

    // 3. Renderizar Tabla
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-bordered table-striped';
    $table->head = [
        get_string('col_fullname', 'report_rolcomparativa'), 
        'Usuario / Email', 
        get_string('col_course', 'report_rolcomparativa'), 
        get_string('col_progress', 'report_rolcomparativa'), 
        get_string('col_grade', 'report_rolcomparativa'), 
        get_string('col_date_issue', 'report_rolcomparativa'), 
        get_string('col_verifycode', 'report_rolcomparativa')
    ];

    foreach ($records as $rec) {
        // Cálculo de progreso (numérico)
        $courseobj = get_course($rec->courseid);
        $progress_pc = \core_completion\progress::get_course_progress_percentage($courseobj, $rec->userid);
        $progress_num = ($progress_pc !== null) ? round($progress_pc) . "%" : "0%";
        
        $status_badge = ($rec->course_completed_date) ? 
            '<span class="badge badge-success">Completado</span>' : 
            '<span class="badge badge-secondary">En Progreso</span>';

        // Generar enlace al certificado (redirige a la vista del PDF para ese usuario)
        $cert_code_display = '-';
        if ($rec->cert_code) {
            $view_url = new moodle_url('/mod/coursecertificate/view.php', [
                'id' => $rec->cmid, 
                'action' => 'view', 
                'userid' => $rec->userid
            ]);
            $cert_code_display = html_writer::link($view_url, '<code>'.$rec->cert_code.'</code>', ['target' => '_blank', 'title' => 'Visualizar Certificado']);
        }

        $table->data[] = [
            fullname($rec), 
            '<small>'.$rec->username.'<br>'.$rec->email.'</small>', 
            $rec->coursename, 
            '<strong>'.$progress_num.'</strong><br>'.$status_badge, 
            $rec->finalgrade ?? '-', 
            $rec->cert_issue_date ? userdate($rec->cert_issue_date, '%d/%m/%Y') : 'No emitido', 
            $cert_code_display
        ];
    }
    echo html_writer::table($table);
}