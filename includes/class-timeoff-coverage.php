<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gestiona grupos de cobertura y valida que las solicitudes de vacaciones
 * no dejen ningún grupo por debajo del mínimo de personas presentes.
 *
 * Regla de agosto: validación global independiente de los grupos.
 * Configurada en Ajustes → "Mínimo personal en agosto".
 */
class TimeOff_Coverage {

    /* ------------------------------------------------------------------ */
    /* CRUD DE GRUPOS                                                       */
    /* ------------------------------------------------------------------ */

    public static function get_groups() {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT g.*, COUNT(m.id) AS member_count
             FROM ' . TimeOff_DB::get_coverage_groups_table() . ' g
             LEFT JOIN ' . TimeOff_DB::get_group_members_table() . ' m ON m.group_id = g.id
             GROUP BY g.id ORDER BY g.name'
        );
    }

    public static function get_group( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . TimeOff_DB::get_coverage_groups_table() . ' WHERE id = %d', $id
        ) );
    }

    public static function save_group( $data, $member_ids = array() ) {
        global $wpdb;
        $t = TimeOff_DB::get_coverage_groups_table();

        $fields = array(
            'name'        => sanitize_text_field( $data['name'] ),
            'min_present' => absint( $data['min_present'] ?? 1 ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
        );

        if ( ! empty( $data['id'] ) ) {
            $id = absint( $data['id'] );
            $wpdb->update( $t, $fields, array( 'id' => $id ), array( '%s', '%d', '%s' ), array( '%d' ) );
        } else {
            $fields['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $t, $fields, array( '%s', '%d', '%s', '%s' ) );
            $id = $wpdb->insert_id;
        }

        if ( $id ) {
            self::set_members( $id, $member_ids );
        }

        return $id;
    }

    public static function delete_group( $id ) {
        global $wpdb;
        $id = absint( $id );
        $wpdb->delete( TimeOff_DB::get_group_members_table(),   array( 'group_id' => $id ), array( '%d' ) );
        $wpdb->delete( TimeOff_DB::get_coverage_groups_table(), array( 'id'       => $id ), array( '%d' ) );
    }

    /* ------------------------------------------------------------------ */
    /* MIEMBROS                                                             */
    /* ------------------------------------------------------------------ */

    public static function get_group_members( $group_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT m.employee_id, u.display_name
             FROM ' . TimeOff_DB::get_group_members_table() . ' m
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID = m.employee_id
             WHERE m.group_id = %d ORDER BY u.display_name',
            $group_id
        ) );
    }

    public static function get_employee_groups( $employee_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT g.* FROM ' . TimeOff_DB::get_coverage_groups_table() . ' g
             INNER JOIN ' . TimeOff_DB::get_group_members_table() . ' m ON m.group_id = g.id
             WHERE m.employee_id = %d',
            $employee_id
        ) );
    }

    public static function set_members( $group_id, $employee_ids ) {
        global $wpdb;
        $t  = TimeOff_DB::get_group_members_table();
        $gid = absint( $group_id );

        $wpdb->delete( $t, array( 'group_id' => $gid ), array( '%d' ) );

        foreach ( array_unique( array_map( 'absint', (array) $employee_ids ) ) as $eid ) {
            if ( ! $eid ) continue;
            $wpdb->insert( $t, array( 'group_id' => $gid, 'employee_id' => $eid ), array( '%d', '%d' ) );
        }
    }

    /* ------------------------------------------------------------------ */
    /* VALIDACIÓN                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Cuántos de los $employee_ids tienen vacaciones aprobadas/pendientes
     * el día $date (formato Y-m-d), excluyendo opcionalmente una solicitud.
     */
    private static function count_on_vacation_on_date( $employee_ids, $date, $exclude_id = 0 ) {
        global $wpdb;
        if ( empty( $employee_ids ) ) return 0;

        $placeholders = implode( ',', array_fill( 0, count( $employee_ids ), '%d' ) );
        $values       = array_merge( array_values( $employee_ids ), array( absint( $exclude_id ), $date, $date ) );

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT employee_id)
             FROM " . TimeOff_DB::get_requests_table() . "
             WHERE employee_id IN ($placeholders)
               AND id != %d
               AND status IN ('pending','approved')
               AND start_date <= %s
               AND end_date   >= %s",
            $values
        ) );
    }

    /**
     * Valida una solicitud contra:
     *  1. Los grupos de cobertura a los que pertenece el empleado.
     *  2. La regla global de agosto (mínimo 1 persona de guardia).
     *
     * @return true|WP_Error
     */
    public static function validate_request( $employee_id, $start_date, $end_date, $exclude_id = 0 ) {

        $days = self::date_range( $start_date, $end_date );

        /* --- 1. Grupos de cobertura --- */
        $groups = self::get_employee_groups( $employee_id );

        foreach ( $groups as $group ) {
            $members     = self::get_group_members( $group->id );
            $member_ids  = array_column( (array) $members, 'employee_id' );
            $group_size  = count( $member_ids );
            $min_present = (int) $group->min_present;

            // Si el grupo tiene menos miembros que el mínimo requerido la regla no tiene sentido
            if ( $group_size <= $min_present ) continue;

            // Otros miembros del grupo (excluimos al propio solicitante del conteo)
            $other_ids = array_values( array_diff( $member_ids, array( (int) $employee_id ) ) );

            foreach ( $days as $date ) {
                $others_on_vacation = self::count_on_vacation_on_date( $other_ids, $date, $exclude_id );
                // Miembros presentes = (total - 1 por el solicitante) - los otros que ya están de vacaciones
                $will_be_present = ( $group_size - 1 ) - $others_on_vacation;

                if ( $will_be_present < $min_present ) {
                    return new WP_Error(
                        'coverage_group',
                        sprintf(
                            /* translators: 1: nombre del grupo, 2: fecha, 3: mínimo */
                            __( 'No hay cobertura suficiente en el grupo «%1$s» el %2$s (mínimo %3$d persona/s presente/s).', 'timeoff' ),
                            esc_html( $group->name ),
                            date_i18n( get_option( 'date_format', 'd/m/Y' ), strtotime( $date ) ),
                            $min_present
                        )
                    );
                }
            }
        }

        /* --- 2. Regla global de agosto --- */
        $august_min = (int) get_option( 'timeoff_august_min_present', 1 );

        if ( $august_min > 0 ) {
            $all_employees = TimeOff_Employee::get_all_employees();
            $all_ids       = array_map( function ( $e ) { return (int) $e->ID; }, $all_employees );
            $total         = count( $all_ids );
            $other_all     = array_values( array_diff( $all_ids, array( (int) $employee_id ) ) );

            foreach ( $days as $date ) {
                if ( date( 'm', strtotime( $date ) ) !== '08' ) continue;  // solo agosto

                $others_on_vacation = self::count_on_vacation_on_date( $other_all, $date, $exclude_id );
                // Personas presentes si se aprueba esta solicitud
                $will_be_present = ( $total - 1 ) - $others_on_vacation;

                if ( $will_be_present < $august_min ) {
                    return new WP_Error(
                        'august_coverage',
                        sprintf(
                            __( 'En agosto debe quedar al menos %1$d persona/s de guardia. El %2$s no habría suficiente cobertura.', 'timeoff' ),
                            $august_min,
                            date_i18n( get_option( 'date_format', 'd/m/Y' ), strtotime( $date ) )
                        )
                    );
                }
            }
        }

        return true;
    }

    /**
     * Devuelve un array de fechas (Y-m-d) entre $start y $end inclusive.
     */
    private static function date_range( $start, $end ) {
        $dates   = array();
        $current = new DateTime( $start );
        $last    = new DateTime( $end );
        while ( $current <= $last ) {
            $dates[] = $current->format( 'Y-m-d' );
            $current->modify( '+1 day' );
        }
        return $dates;
    }

    /* ------------------------------------------------------------------ */
    /* VISTA: conflictos de una solicitud (para mostrar al admin)           */
    /* ------------------------------------------------------------------ */

    /**
     * Devuelve los empleados de los mismos grupos que $employee_id
     * que ya tienen vacaciones solapadas con [$start, $end].
     * Útil para mostrar al admin quién tiene conflicto potencial.
     */
    public static function get_conflicts( $employee_id, $start_date, $end_date ) {
        global $wpdb;
        $groups = self::get_employee_groups( $employee_id );
        $conflicts = array();

        foreach ( $groups as $group ) {
            $members    = self::get_group_members( $group->id );
            $member_ids = array_column( (array) $members, 'employee_id' );
            $other_ids  = array_diff( $member_ids, array( (int) $employee_id ) );
            if ( empty( $other_ids ) ) continue;

            $placeholders = implode( ',', array_fill( 0, count( $other_ids ), '%d' ) );
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT r.*, u.display_name
                 FROM " . TimeOff_DB::get_requests_table() . " r
                 LEFT JOIN {$wpdb->users} u ON u.ID = r.employee_id
                 WHERE r.employee_id IN ($placeholders)
                   AND r.status IN ('pending','approved')
                   AND r.start_date <= %s
                   AND r.end_date   >= %s",
                array_merge( array_values( $other_ids ), array( $end_date, $start_date ) )
            ) );

            foreach ( $rows as $row ) {
                $row->group_name = $group->name;
                $conflicts[]     = $row;
            }
        }

        return $conflicts;
    }
}
