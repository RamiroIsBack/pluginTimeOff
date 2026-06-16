<?php
defined( 'ABSPATH' ) || exit;

class TimeOff_Employee {

    /* ------------------------------------------------------------------ */
    /* CONFIGURACIÓN POR EMPLEADO (días totales anuales)                   */
    /* ------------------------------------------------------------------ */

    public static function get_settings( $employee_id, $year ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . TimeOff_DB::get_settings_table() . ' WHERE employee_id = %d AND year = %d',
            $employee_id, $year
        ) );
    }

    public static function save_settings( $employee_id, $year, $total_days ) {
        global $wpdb;
        $t = TimeOff_DB::get_settings_table();

        $existing = self::get_settings( $employee_id, $year );
        if ( $existing ) {
            return $wpdb->update(
                $t,
                array( 'total_days' => absint( $total_days ) ),
                array( 'employee_id' => absint( $employee_id ), 'year' => intval( $year ) ),
                array( '%d' ),
                array( '%d', '%d' )
            );
        }

        return $wpdb->insert(
            $t,
            array(
                'employee_id' => absint( $employee_id ),
                'year'        => intval( $year ),
                'total_days'  => absint( $total_days ),
            ),
            array( '%d', '%d', '%d' )
        );
    }

    public static function get_total_days( $employee_id, $year ) {
        $s = self::get_settings( $employee_id, $year );
        return $s ? (int) $s->total_days : TIMEOFF_MIN_DAYS;
    }

    /* ------------------------------------------------------------------ */
    /* PERÍODO FIJADO POR LA EMPRESA (Art. 38 ET)                          */
    /* ------------------------------------------------------------------ */

    public static function get_fixed_period( $employee_id, $year ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . TimeOff_DB::get_periods_table() . ' WHERE employee_id = %d AND year = %d',
            $employee_id, $year
        ) );
    }

    /**
     * Guarda el período fijado por empresa para un empleado y año.
     * Valida que no supere la mitad del total de días (Art. 38 ET).
     *
     * @return true|WP_Error
     */
    public static function save_fixed_period( $employee_id, $year, $start_date, $end_date ) {
        global $wpdb;

        $days  = TimeOff_Request::count_natural_days( $start_date, $end_date );
        $total = self::get_total_days( $employee_id, $year );
        $max   = (int) ceil( $total / 2 );  // la empresa puede fijar hasta la mitad

        if ( $days > $max ) {
            return new WP_Error(
                'period_too_long',
                sprintf(
                    __( 'El período fijado (%d días) supera la mitad del total (%d días). Máximo permitido: %d días (Art. 38 ET).', 'timeoff' ),
                    $days, $total, $max
                )
            );
        }

        $t        = TimeOff_DB::get_periods_table();
        $existing = self::get_fixed_period( $employee_id, $year );

        $data = array(
            'start_date'  => sanitize_text_field( $start_date ),
            'end_date'    => sanitize_text_field( $end_date ),
            'days_count'  => $days,
            'notified_at' => current_time( 'mysql' ),
        );
        $fmt = array( '%s', '%s', '%d', '%s' );

        if ( $existing ) {
            $wpdb->update( $t, $data, array( 'employee_id' => $employee_id, 'year' => $year ), $fmt, array( '%d', '%d' ) );
        } else {
            $data['employee_id'] = absint( $employee_id );
            $data['year']        = intval( $year );
            $data['created_at']  = current_time( 'mysql' );
            $wpdb->insert( $t, $data, array_merge( $fmt, array( '%d', '%d', '%s' ) ) );
        }

        return true;
    }

    public static function delete_fixed_period( $employee_id, $year ) {
        global $wpdb;
        return $wpdb->delete(
            TimeOff_DB::get_periods_table(),
            array( 'employee_id' => absint( $employee_id ), 'year' => intval( $year ) ),
            array( '%d', '%d' )
        );
    }

    /* ------------------------------------------------------------------ */
    /* LISTADO DE EMPLEADOS (usuarios WordPress)                           */
    /* ------------------------------------------------------------------ */

    public static function get_all_employees() {
        $users = get_users( array(
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'fields'  => array( 'ID', 'display_name', 'user_email' ),
        ) );
        // Excluir al propio admin si se desea; por ahora devuelve todos
        return $users;
    }

    /**
     * Resumen de días para un empleado y año:
     * total, fijados, disponibles libres, usados (solicitudes), pendientes.
     */
    public static function summary( $employee_id, $year ) {
        $total  = self::get_total_days( $employee_id, $year );
        $period = self::get_fixed_period( $employee_id, $year );
        $fixed  = $period ? (int) $period->days_count : 0;

        global $wpdb;
        $t = TimeOff_DB::get_requests_table();

        $approved = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(days_count),0) FROM $t WHERE employee_id=%d AND year=%d AND status='approved'",
            $employee_id, $year
        ) );
        $pending = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(days_count),0) FROM $t WHERE employee_id=%d AND year=%d AND status='pending'",
            $employee_id, $year
        ) );

        $free_pool  = $total - $fixed;
        $free_left  = max( 0, $free_pool - $approved - $pending );

        return array(
            'total'        => $total,
            'fixed'        => $fixed,
            'free_pool'    => $free_pool,
            'approved'     => $approved,
            'pending'      => $pending,
            'free_left'    => $free_left,
            'period_start' => $period ? $period->start_date : null,
            'period_end'   => $period ? $period->end_date   : null,
            'notified_at'  => $period ? $period->notified_at : null,
        );
    }

    /** Eventos del período fijado para FullCalendar. */
    public static function get_fixed_period_events( $year = null ) {
        $year   = $year ?: intval( date( 'Y' ) );
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT p.*, u.display_name FROM ' . TimeOff_DB::get_periods_table() . ' p
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = p.employee_id
             WHERE p.year = %d', $year
        ) );

        $events = array();
        foreach ( $rows as $r ) {
            $events[] = array(
                'id'    => 'fixed-' . $r->id,
                'title' => $r->display_name . ' — ' . __( 'Período fijado', 'timeoff' ),
                'start' => $r->start_date,
                'end'   => date( 'Y-m-d', strtotime( $r->end_date . ' +1 day' ) ),
                'color' => '#337ab7',
                'extendedProps' => array(
                    'type'       => 'fixed',
                    'employee'   => $r->display_name,
                    'days_count' => $r->days_count,
                ),
            );
        }
        return $events;
    }
}
