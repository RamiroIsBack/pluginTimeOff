<?php
defined( 'ABSPATH' ) || exit;

/**
 * API REST del plugin Time Off.
 *
 * Namespace: /wp-json/timeoff/v1/
 *
 * Endpoints públicos (requieren solo estar logueado con Application Password o cookie):
 *   GET  /summary                    → resumen del empleado autenticado
 *   GET  /requests                   → solicitudes del empleado autenticado
 *   POST /requests                   → nueva solicitud
 *   DELETE /requests/{id}            → cancelar solicitud propia (solo pending)
 *   GET  /calendar                   → eventos para FullCalendar (propio)
 *
 * Endpoints de admin (requieren capability manage_timeoff):
 *   GET  /admin/requests             → todas las solicitudes (?year=&status=&employee_id=)
 *   POST /admin/requests/{id}/approve
 *   POST /admin/requests/{id}/reject
 *   DELETE /admin/requests/{id}
 *   GET  /admin/employees            → listado de empleados con resumen
 *   GET  /admin/employees/{id}/summary
 *   POST /admin/employees/{id}/settings
 *   POST /admin/employees/{id}/fixed-period
 *   DELETE /admin/employees/{id}/fixed-period
 *   GET  /admin/calendar             → todos los eventos (solicitudes + períodos fijados)
 *   GET  /admin/coverage-groups      → grupos de cobertura
 *   POST /admin/coverage-groups
 *   PUT  /admin/coverage-groups/{id}
 *   DELETE /admin/coverage-groups/{id}
 *   GET  /admin/export/requests      → CSV solicitudes (stream)
 *   GET  /admin/export/summary       → CSV resumen (stream)
 */
class TimeOff_API {

    const NS = 'timeoff/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /* ------------------------------------------------------------------ */
    /* REGISTRO DE RUTAS                                                    */
    /* ------------------------------------------------------------------ */

    public function register_routes() {

        /* ---- Empleado autenticado ---- */
        register_rest_route( self::NS, '/summary', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_my_summary' ),
            'permission_callback' => array( $this, 'is_logged_in' ),
        ) );

        register_rest_route( self::NS, '/requests', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_my_requests' ),
                'permission_callback' => array( $this, 'is_logged_in' ),
                'args'                => array(
                    'year' => array( 'type' => 'integer', 'default' => (int) date('Y') ),
                ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_request' ),
                'permission_callback' => array( $this, 'is_logged_in' ),
                'args'                => array(
                    'start_date' => array( 'required' => true, 'type' => 'string', 'format' => 'date' ),
                    'end_date'   => array( 'required' => true, 'type' => 'string', 'format' => 'date' ),
                    'note'       => array( 'type' => 'string', 'default' => '' ),
                ),
            ),
        ) );

        register_rest_route( self::NS, '/requests/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'cancel_request' ),
            'permission_callback' => array( $this, 'is_logged_in' ),
        ) );

        register_rest_route( self::NS, '/calendar', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_my_calendar' ),
            'permission_callback' => array( $this, 'is_logged_in' ),
            'args'                => array(
                'year' => array( 'type' => 'integer', 'default' => (int) date('Y') ),
            ),
        ) );

        /* ---- Admin ---- */
        register_rest_route( self::NS, '/admin/requests', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'admin_get_requests' ),
            'permission_callback' => array( $this, 'is_admin' ),
            'args'                => array(
                'year'        => array( 'type' => 'integer', 'default' => (int) date('Y') ),
                'status'      => array( 'type' => 'string',  'default' => '' ),
                'employee_id' => array( 'type' => 'integer', 'default' => 0 ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/requests/(?P<id>\d+)/approve', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'admin_approve_request' ),
            'permission_callback' => array( $this, 'is_admin' ),
        ) );

        register_rest_route( self::NS, '/admin/requests/(?P<id>\d+)/reject', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'admin_reject_request' ),
            'permission_callback' => array( $this, 'is_admin' ),
        ) );

        register_rest_route( self::NS, '/admin/requests/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'admin_delete_request' ),
            'permission_callback' => array( $this, 'is_admin' ),
        ) );

        register_rest_route( self::NS, '/admin/employees', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'admin_get_employees' ),
            'permission_callback' => array( $this, 'is_admin' ),
            'args'                => array(
                'year' => array( 'type' => 'integer', 'default' => (int) date('Y') ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/employees/(?P<id>\d+)/summary', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'admin_get_employee_summary' ),
            'permission_callback' => array( $this, 'is_admin' ),
            'args'                => array(
                'year' => array( 'type' => 'integer', 'default' => (int) date('Y') ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/employees/(?P<id>\d+)/settings', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'admin_save_employee_settings' ),
            'permission_callback' => array( $this, 'is_admin' ),
        ) );

        register_rest_route( self::NS, '/admin/employees/(?P<id>\d+)/fixed-period', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'admin_save_fixed_period' ),
                'permission_callback' => array( $this, 'is_admin' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'admin_delete_fixed_period' ),
                'permission_callback' => array( $this, 'is_admin' ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/calendar', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'admin_get_calendar' ),
            'permission_callback' => array( $this, 'is_admin' ),
            'args'                => array(
                'year' => array( 'type' => 'integer', 'default' => (int) date('Y') ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/coverage-groups', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'admin_get_coverage_groups' ),
                'permission_callback' => array( $this, 'is_admin' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'admin_create_coverage_group' ),
                'permission_callback' => array( $this, 'is_admin' ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/coverage-groups/(?P<id>\d+)', array(
            array(
                'methods'             => 'PUT',
                'callback'            => array( $this, 'admin_update_coverage_group' ),
                'permission_callback' => array( $this, 'is_admin' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'admin_delete_coverage_group' ),
                'permission_callback' => array( $this, 'is_admin' ),
            ),
        ) );

        /* Exportación CSV vía REST (devuelve stream) */
        register_rest_route( self::NS, '/admin/export/(?P<type>requests|summary)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'admin_export' ),
            'permission_callback' => array( $this, 'is_admin' ),
            'args'                => array(
                'year' => array( 'type' => 'integer', 'default' => (int) date('Y') ),
            ),
        ) );
    }

    /* ------------------------------------------------------------------ */
    /* PERMISOS                                                             */
    /* ------------------------------------------------------------------ */

    public function is_logged_in() {
        return is_user_logged_in();
    }

    public function is_admin() {
        return current_user_can( 'manage_timeoff' );
    }

    /* ------------------------------------------------------------------ */
    /* ENDPOINTS DE EMPLEADO                                               */
    /* ------------------------------------------------------------------ */

    public function get_my_summary( WP_REST_Request $req ) {
        $uid  = get_current_user_id();
        $year = $req->get_param( 'year' ) ?: (int) date( 'Y' );
        return rest_ensure_response( TimeOff_Employee::summary( $uid, $year ) );
    }

    public function get_my_requests( WP_REST_Request $req ) {
        $uid  = get_current_user_id();
        $year = $req->get_param( 'year' );
        $rows = TimeOff_Request::get_all( array( 'employee_id' => $uid, 'year' => $year ) );
        return rest_ensure_response( array_values( $rows ) );
    }

    public function create_request( WP_REST_Request $req ) {
        $uid   = get_current_user_id();
        $start = sanitize_text_field( $req->get_param( 'start_date' ) );
        $end   = sanitize_text_field( $req->get_param( 'end_date' ) );
        $note  = sanitize_textarea_field( $req->get_param( 'note' ) ?? '' );
        $year  = (int) substr( $start, 0, 4 );

        if ( $start > $end ) {
            return new WP_Error( 'invalid_dates', __( 'La fecha de inicio debe ser anterior a la de fin.', 'timeoff' ), array( 'status' => 400 ) );
        }

        $days      = TimeOff_Request::count_natural_days( $start, $end );
        $available = TimeOff_Request::days_available( $uid, $year );

        if ( $days > $available ) {
            return new WP_Error( 'no_days', sprintf(
                __( 'Solo tienes %d días disponibles y estás solicitando %d.', 'timeoff' ), $available, $days
            ), array( 'status' => 409 ) );
        }

        if ( TimeOff_Request::has_overlap( $uid, $start, $end ) ) {
            return new WP_Error( 'overlap', __( 'Ya tienes una solicitud en ese período.', 'timeoff' ), array( 'status' => 409 ) );
        }

        $coverage = TimeOff_Coverage::validate_request( $uid, $start, $end );
        if ( is_wp_error( $coverage ) ) {
            return new WP_Error( $coverage->get_error_code(), $coverage->get_error_message(), array( 'status' => 409 ) );
        }

        $id = TimeOff_Request::create( array(
            'employee_id'   => $uid,
            'year'          => $year,
            'start_date'    => $start,
            'end_date'      => $end,
            'employee_note' => $note,
        ) );

        if ( ! $id ) {
            return new WP_Error( 'db_error', __( 'Error al guardar.', 'timeoff' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( array(
            'id'        => $id,
            'days'      => $days,
            'available' => TimeOff_Request::days_available( $uid, $year ),
        ) );
    }

    public function cancel_request( WP_REST_Request $req ) {
        $uid = get_current_user_id();
        $id  = (int) $req->get_param( 'id' );
        $r   = TimeOff_Request::get( $id );

        if ( ! $r || (int) $r->employee_id !== $uid ) {
            return new WP_Error( 'not_found', __( 'Solicitud no encontrada.', 'timeoff' ), array( 'status' => 404 ) );
        }
        if ( $r->status === 'approved' ) {
            return new WP_Error( 'forbidden', __( 'No puedes cancelar una solicitud ya aprobada.', 'timeoff' ), array( 'status' => 403 ) );
        }

        TimeOff_Request::delete( $id );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    public function get_my_calendar( WP_REST_Request $req ) {
        $uid    = get_current_user_id();
        $year   = $req->get_param( 'year' );
        $rows   = TimeOff_Request::get_all( array( 'employee_id' => $uid, 'year' => $year ) );
        $period = TimeOff_Employee::get_fixed_period( $uid, $year );
        $colors = array( 'pending' => '#f0ad4e', 'approved' => '#5cb85c', 'rejected' => '#d9534f' );

        $events = array();
        foreach ( $rows as $r ) {
            $events[] = array(
                'id'    => 'req-' . $r->id,
                'title' => ucfirst( $r->status ),
                'start' => $r->start_date,
                'end'   => date( 'Y-m-d', strtotime( $r->end_date . ' +1 day' ) ),
                'color' => $colors[ $r->status ] ?? '#777',
                'extendedProps' => array(
                    'request_id' => $r->id,
                    'status'     => $r->status,
                    'days'       => $r->days_count,
                    'admin_note' => $r->admin_note,
                ),
            );
        }

        if ( $period ) {
            $events[] = array(
                'id'    => 'fixed-' . $uid,
                'title' => 'Período fijado',
                'start' => $period->start_date,
                'end'   => date( 'Y-m-d', strtotime( $period->end_date . ' +1 day' ) ),
                'color' => '#337ab7',
                'extendedProps' => array( 'type' => 'fixed', 'days' => $period->days_count ),
            );
        }

        return rest_ensure_response( $events );
    }

    /* ------------------------------------------------------------------ */
    /* ENDPOINTS DE ADMIN – SOLICITUDES                                     */
    /* ------------------------------------------------------------------ */

    public function admin_get_requests( WP_REST_Request $req ) {
        $args = array_filter( array(
            'year'        => $req->get_param( 'year' ),
            'status'      => $req->get_param( 'status' ),
            'employee_id' => $req->get_param( 'employee_id' ),
        ) );
        return rest_ensure_response( array_values( TimeOff_Request::get_all( $args ) ) );
    }

    public function admin_approve_request( WP_REST_Request $req ) {
        $id   = (int) $req->get_param( 'id' );
        $note = sanitize_textarea_field( $req->get_param( 'note' ) ?? '' );
        TimeOff_Request::update_status( $id, 'approved', $note );
        return rest_ensure_response( array( 'status' => 'approved' ) );
    }

    public function admin_reject_request( WP_REST_Request $req ) {
        $id   = (int) $req->get_param( 'id' );
        $note = sanitize_textarea_field( $req->get_param( 'note' ) ?? '' );
        TimeOff_Request::update_status( $id, 'rejected', $note );
        return rest_ensure_response( array( 'status' => 'rejected' ) );
    }

    public function admin_delete_request( WP_REST_Request $req ) {
        TimeOff_Request::delete( (int) $req->get_param( 'id' ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    /* ------------------------------------------------------------------ */
    /* ENDPOINTS DE ADMIN – EMPLEADOS                                      */
    /* ------------------------------------------------------------------ */

    public function admin_get_employees( WP_REST_Request $req ) {
        $year      = $req->get_param( 'year' );
        $employees = TimeOff_Employee::get_all_employees();
        $data      = array();

        foreach ( $employees as $emp ) {
            $s         = TimeOff_Employee::summary( $emp->ID, $year );
            $groups    = TimeOff_Coverage::get_employee_groups( $emp->ID );
            $data[]    = array(
                'id'           => $emp->ID,
                'name'         => $emp->display_name,
                'email'        => $emp->user_email,
                'summary'      => $s,
                'groups'       => array_values( $groups ),
            );
        }

        return rest_ensure_response( $data );
    }

    public function admin_get_employee_summary( WP_REST_Request $req ) {
        $emp_id = (int) $req->get_param( 'id' );
        $year   = $req->get_param( 'year' );
        return rest_ensure_response( TimeOff_Employee::summary( $emp_id, $year ) );
    }

    public function admin_save_employee_settings( WP_REST_Request $req ) {
        $emp_id     = (int) $req->get_param( 'id' );
        $year       = (int) $req->get_param( 'year' ) ?: (int) date( 'Y' );
        $total_days = (int) $req->get_param( 'total_days' );

        if ( $total_days < TIMEOFF_MIN_DAYS ) {
            return new WP_Error( 'invalid', sprintf(
                __( 'Mínimo %d días (Art. 38 ET).', 'timeoff' ), TIMEOFF_MIN_DAYS
            ), array( 'status' => 400 ) );
        }

        TimeOff_Employee::save_settings( $emp_id, $year, $total_days );
        return rest_ensure_response( TimeOff_Employee::summary( $emp_id, $year ) );
    }

    public function admin_save_fixed_period( WP_REST_Request $req ) {
        $emp_id = (int) $req->get_param( 'id' );
        $year   = (int) $req->get_param( 'year' ) ?: (int) date( 'Y' );
        $start  = sanitize_text_field( $req->get_param( 'start_date' ) );
        $end    = sanitize_text_field( $req->get_param( 'end_date' ) );

        $result = TimeOff_Employee::save_fixed_period( $emp_id, $year, $start, $end );
        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 409 ) );
        }

        return rest_ensure_response( TimeOff_Employee::summary( $emp_id, $year ) );
    }

    public function admin_delete_fixed_period( WP_REST_Request $req ) {
        TimeOff_Employee::delete_fixed_period( (int) $req->get_param( 'id' ), (int) $req->get_param( 'year' ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    /* ------------------------------------------------------------------ */
    /* ENDPOINTS DE ADMIN – CALENDARIO                                      */
    /* ------------------------------------------------------------------ */

    public function admin_get_calendar( WP_REST_Request $req ) {
        $year   = $req->get_param( 'year' );
        $events = array_merge(
            TimeOff_Request::get_calendar_events( $year ),
            TimeOff_Employee::get_fixed_period_events( $year )
        );
        return rest_ensure_response( $events );
    }

    /* ------------------------------------------------------------------ */
    /* ENDPOINTS DE ADMIN – GRUPOS DE COBERTURA                            */
    /* ------------------------------------------------------------------ */

    public function admin_get_coverage_groups() {
        $groups = TimeOff_Coverage::get_groups();
        $data   = array();

        foreach ( $groups as $g ) {
            $members = TimeOff_Coverage::get_group_members( $g->id );
            $data[]  = array(
                'id'           => $g->id,
                'name'         => $g->name,
                'min_present'  => (int) $g->min_present,
                'description'  => $g->description,
                'member_count' => (int) $g->member_count,
                'members'      => array_values( (array) $members ),
            );
        }

        return rest_ensure_response( $data );
    }

    public function admin_create_coverage_group( WP_REST_Request $req ) {
        return $this->_upsert_coverage_group( $req );
    }

    public function admin_update_coverage_group( WP_REST_Request $req ) {
        return $this->_upsert_coverage_group( $req, (int) $req->get_param( 'id' ) );
    }

    private function _upsert_coverage_group( WP_REST_Request $req, $id = null ) {
        $data = array(
            'id'          => $id,
            'name'        => sanitize_text_field( $req->get_param( 'name' ) ?? '' ),
            'min_present' => absint( $req->get_param( 'min_present' ) ?? 1 ),
            'description' => sanitize_textarea_field( $req->get_param( 'description' ) ?? '' ),
        );
        $members = array_map( 'absint', (array) ( $req->get_param( 'members' ) ?? array() ) );

        if ( ! $data['name'] ) {
            return new WP_Error( 'invalid', __( 'El nombre es obligatorio.', 'timeoff' ), array( 'status' => 400 ) );
        }

        $saved_id = TimeOff_Coverage::save_group( $data, $members );
        $g        = TimeOff_Coverage::get_group( $saved_id );
        $m        = TimeOff_Coverage::get_group_members( $saved_id );

        return rest_ensure_response( array(
            'id'          => $saved_id,
            'name'        => $g->name,
            'min_present' => (int) $g->min_present,
            'description' => $g->description,
            'members'     => array_values( (array) $m ),
        ) );
    }

    public function admin_delete_coverage_group( WP_REST_Request $req ) {
        TimeOff_Coverage::delete_group( (int) $req->get_param( 'id' ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    /* ------------------------------------------------------------------ */
    /* EXPORTACIÓN                                                          */
    /* ------------------------------------------------------------------ */

    public function admin_export( WP_REST_Request $req ) {
        $type = $req->get_param( 'type' );
        $year = $req->get_param( 'year' );

        // La exportación hace exit(), así que salimos directamente del REST handler
        if ( $type === 'requests' ) {
            TimeOff_Export::download_csv( $year );
        }
        TimeOff_Export::download_summary_csv( $year );
    }
}

// Registrar la instancia
add_action( 'plugins_loaded', function () {
    new TimeOff_API();
}, 20 );
