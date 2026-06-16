<?php
defined( 'ABSPATH' ) || exit;

class TimeOff_Export {

    /**
     * Genera y descarga un CSV con todas las solicitudes del año indicado.
     * Llamar desde un handler admin que ya haya verificado nonce y capability.
     */
    public static function download_csv( $year ) {
        $year     = intval( $year );
        $requests = TimeOff_Request::get_all( array( 'year' => $year ) );

        $filename = 'vacaciones-' . $year . '-' . date( 'Ymd' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // BOM para que Excel abra correctamente UTF-8
        echo "\xEF\xBB\xBF";

        $out = fopen( 'php://output', 'w' );

        fputcsv( $out, array(
            __( 'ID', 'timeoff' ),
            __( 'Empleado', 'timeoff' ),
            __( 'Año', 'timeoff' ),
            __( 'Fecha inicio', 'timeoff' ),
            __( 'Fecha fin', 'timeoff' ),
            __( 'Días', 'timeoff' ),
            __( 'Estado', 'timeoff' ),
            __( 'Nota empleado', 'timeoff' ),
            __( 'Nota admin', 'timeoff' ),
            __( 'Creada', 'timeoff' ),
        ), ';' );

        $status_labels = array(
            'pending'  => __( 'Pendiente', 'timeoff' ),
            'approved' => __( 'Aprobada', 'timeoff' ),
            'rejected' => __( 'Rechazada', 'timeoff' ),
        );

        foreach ( $requests as $r ) {
            fputcsv( $out, array(
                $r->id,
                $r->employee_name,
                $r->year,
                $r->start_date,
                $r->end_date,
                $r->days_count,
                $status_labels[ $r->status ] ?? $r->status,
                $r->employee_note,
                $r->admin_note,
                $r->created_at,
            ), ';' );
        }

        fclose( $out );
        exit;
    }

    /**
     * Genera y descarga un CSV con el resumen anual por empleado.
     */
    public static function download_summary_csv( $year ) {
        $year      = intval( $year );
        $employees = TimeOff_Employee::get_all_employees();

        $filename = 'resumen-vacaciones-' . $year . '-' . date( 'Ymd' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo "\xEF\xBB\xBF";

        $out = fopen( 'php://output', 'w' );

        fputcsv( $out, array(
            __( 'Empleado', 'timeoff' ),
            __( 'Email', 'timeoff' ),
            __( 'Días totales', 'timeoff' ),
            __( 'Período fijado (días)', 'timeoff' ),
            __( 'Inicio período fijado', 'timeoff' ),
            __( 'Fin período fijado', 'timeoff' ),
            __( 'Días aprobados', 'timeoff' ),
            __( 'Días pendientes', 'timeoff' ),
            __( 'Días disponibles', 'timeoff' ),
        ), ';' );

        foreach ( $employees as $emp ) {
            $s = TimeOff_Employee::summary( $emp->ID, $year );
            fputcsv( $out, array(
                $emp->display_name,
                $emp->user_email,
                $s['total'],
                $s['fixed'],
                $s['period_start'] ?? '',
                $s['period_end']   ?? '',
                $s['approved'],
                $s['pending'],
                $s['free_left'],
            ), ';' );
        }

        fclose( $out );
        exit;
    }
}
