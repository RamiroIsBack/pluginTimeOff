<?php
defined( 'ABSPATH' ) || exit;

class TimeOff_Request {

    /* ------------------------------------------------------------------ */
    /* LECTURA                                                              */
    /* ------------------------------------------------------------------ */

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . TimeOff_DB::get_requests_table() . ' WHERE id = %d', $id
        ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $t = TimeOff_DB::get_requests_table();

        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $args['employee_id'] ) ) {
            $where[]  = 'employee_id = %d';
            $values[] = $args['employee_id'];
        }
        if ( ! empty( $args['year'] ) ) {
            $where[]  = 'year = %d';
            $values[] = $args['year'];
        }
        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }

        $sql = 'SELECT r.*, u.display_name AS employee_name
                FROM ' . $t . ' r
                LEFT JOIN ' . $wpdb->users . ' u ON u.ID = r.employee_id
                WHERE ' . implode( ' AND ', $where ) . '
                ORDER BY r.created_at DESC';

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values );
        }

        return $wpdb->get_results( $sql );
    }

    /* ------------------------------------------------------------------ */
    /* ESCRITURA                                                            */
    /* ------------------------------------------------------------------ */

    public static function create( $data ) {
        global $wpdb;

        $days = self::count_natural_days( $data['start_date'], $data['end_date'] );

        $result = $wpdb->insert(
            TimeOff_DB::get_requests_table(),
            array(
                'employee_id'   => absint( $data['employee_id'] ),
                'year'          => intval( $data['year'] ),
                'start_date'    => sanitize_text_field( $data['start_date'] ),
                'end_date'      => sanitize_text_field( $data['end_date'] ),
                'days_count'    => $days,
                'status'        => 'pending',
                'employee_note' => sanitize_textarea_field( $data['employee_note'] ?? '' ),
                'admin_note'    => '',
                'created_at'    => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    public static function update_status( $id, $status, $admin_note = '' ) {
        global $wpdb;
        return $wpdb->update(
            TimeOff_DB::get_requests_table(),
            array(
                'status'     => $status,
                'admin_note' => sanitize_textarea_field( $admin_note ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => absint( $id ) ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete(
            TimeOff_DB::get_requests_table(),
            array( 'id' => absint( $id ) ),
            array( '%d' )
        );
    }

    /* ------------------------------------------------------------------ */
    /* UTILIDADES                                                           */
    /* ------------------------------------------------------------------ */

    /** Días naturales entre dos fechas (ambos extremos incluidos). */
    public static function count_natural_days( $start, $end ) {
        $s = new DateTime( $start );
        $e = new DateTime( $end );
        return (int) $s->diff( $e )->days + 1;
    }

    /**
     * Días aprobados + pendientes que un empleado ha consumido en un año,
     * incluyendo el período fijado por empresa.
     */
    public static function days_used( $employee_id, $year ) {
        global $wpdb;
        $t = TimeOff_DB::get_requests_table();

        $from_requests = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(days_count),0) FROM $t
             WHERE employee_id = %d AND year = %d AND status IN ('pending','approved')",
            $employee_id, $year
        ) );

        // Sumar período fijado si existe
        $period = TimeOff_Employee::get_fixed_period( $employee_id, $year );
        $from_period = $period ? (int) $period->days_count : 0;

        return $from_requests + $from_period;
    }

    /**
     * Días disponibles que el empleado puede pedir libremente,
     * descontando el período fijado.
     */
    public static function days_available( $employee_id, $year ) {
        $config = TimeOff_Employee::get_settings( $employee_id, $year );
        $total  = $config ? (int) $config->total_days : TIMEOFF_MIN_DAYS;

        $period = TimeOff_Employee::get_fixed_period( $employee_id, $year );
        $fixed  = $period ? (int) $period->days_count : 0;

        $free_pool = $total - $fixed;  // días de libre disposición

        // Solicitudes ya enviadas
        global $wpdb;
        $t    = TimeOff_DB::get_requests_table();
        $used = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(days_count),0) FROM $t
             WHERE employee_id = %d AND year = %d AND status IN ('pending','approved')",
            $employee_id, $year
        ) );

        return max( 0, $free_pool - $used );
    }

    /** Devuelve solicitudes en formato evento para FullCalendar. */
    public static function get_calendar_events( $year = null ) {
        $year  = $year ?: intval( date( 'Y' ) );
        $rows  = self::get_all( array( 'year' => $year ) );
        $events = array();

        $colors = array(
            'pending'  => '#f0ad4e',
            'approved' => '#5cb85c',
            'rejected' => '#d9534f',
        );

        foreach ( $rows as $r ) {
            $events[] = array(
                'id'    => 'req-' . $r->id,
                'title' => $r->employee_name . ' (' . __( $r->status, 'timeoff' ) . ')',
                'start' => $r->start_date,
                'end'   => date( 'Y-m-d', strtotime( $r->end_date . ' +1 day' ) ),
                'color' => $colors[ $r->status ] ?? '#777',
                'extendedProps' => array(
                    'request_id' => $r->id,
                    'status'     => $r->status,
                    'employee'   => $r->employee_name,
                    'note'       => $r->employee_note,
                ),
            );
        }

        return $events;
    }

    /** Comprueba si las fechas solapan con otra solicitud aprobada/pendiente del mismo empleado. */
    public static function has_overlap( $employee_id, $start, $end, $exclude_id = 0 ) {
        global $wpdb;
        $t = TimeOff_DB::get_requests_table();
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $t
             WHERE employee_id = %d
               AND id != %d
               AND status IN ('pending','approved')
               AND start_date <= %s
               AND end_date   >= %s",
            $employee_id, $exclude_id, $end, $start
        ) );
    }
}
