<?php
defined( 'ABSPATH' ) || exit;

class TimeOff_Public {

    public function __construct() {
        add_action( 'wp_enqueue_scripts',     array( $this, 'enqueue_assets' ) );
        add_shortcode( 'timeoff_request_form', array( $this, 'sc_request_form' ) );
        add_shortcode( 'timeoff_my_calendar',  array( $this, 'sc_my_calendar' ) );
        add_shortcode( 'timeoff_my_summary',   array( $this, 'sc_my_summary' ) );

        add_action( 'wp_ajax_timeoff_submit_request',        array( $this, 'ajax_submit_request' ) );
        add_action( 'wp_ajax_timeoff_delete_own_request',    array( $this, 'ajax_delete_own_request' ) );
        add_action( 'wp_ajax_timeoff_get_my_events',         array( $this, 'ajax_get_my_events' ) );
    }

    /* ------------------------------------------------------------------ */
    /* ASSETS                                                              */
    /* ------------------------------------------------------------------ */

    public function enqueue_assets() {
        if ( ! is_user_logged_in() ) return;

        wp_enqueue_style(  'timeoff-public',  TIMEOFF_PLUGIN_URL . 'public/css/public.css',  array(), TIMEOFF_VERSION );
        wp_enqueue_style(  'fullcalendar',    'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css', array(), '6.1.11' );
        wp_enqueue_script( 'fullcalendar',    'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js', array(), '6.1.11', true );
        wp_enqueue_script( 'timeoff-public',  TIMEOFF_PLUGIN_URL . 'public/js/public.js', array( 'jquery', 'fullcalendar' ), TIMEOFF_VERSION, true );

        $year = intval( date( 'Y' ) );
        $uid  = get_current_user_id();
        $s    = TimeOff_Employee::summary( $uid, $year );

        wp_localize_script( 'timeoff-public', 'timeoffPublic', array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'timeoff_public' ),
            'year'       => $year,
            'summary'    => $s,
            'i18n'       => array(
                'confirm_delete' => __( '¿Cancelar esta solicitud?', 'timeoff' ),
                'error'          => __( 'Error al procesar la solicitud.', 'timeoff' ),
                'success'        => __( 'Solicitud enviada correctamente.', 'timeoff' ),
                'no_days'        => __( 'No tienes días disponibles para el período seleccionado.', 'timeoff' ),
                'overlap'        => __( 'Ya tienes una solicitud en ese período.', 'timeoff' ),
            ),
        ) );
    }

    /* ------------------------------------------------------------------ */
    /* SHORTCODES                                                          */
    /* ------------------------------------------------------------------ */

    public function sc_request_form( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Debes iniciar sesión para solicitar vacaciones.', 'timeoff' ) . '</p>';
        }
        ob_start();
        require TIMEOFF_PLUGIN_DIR . 'public/views/request-form.php';
        return ob_get_clean();
    }

    public function sc_my_calendar( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Debes iniciar sesión para ver tu calendario.', 'timeoff' ) . '</p>';
        }
        ob_start();
        require TIMEOFF_PLUGIN_DIR . 'public/views/my-calendar.php';
        return ob_get_clean();
    }

    public function sc_my_summary() {
        if ( ! is_user_logged_in() ) return '';
        $uid  = get_current_user_id();
        $year = intval( date( 'Y' ) );
        $s    = TimeOff_Employee::summary( $uid, $year );

        ob_start(); ?>
        <div class="timeoff-summary-widget">
            <h3><?php printf( esc_html__( 'Mis vacaciones %d', 'timeoff' ), $year ); ?></h3>
            <ul>
                <li><?php printf( esc_html__( 'Total: <strong>%d días</strong>', 'timeoff' ), $s['total'] ); ?></li>
                <?php if ( $s['fixed'] ) : ?>
                <li><?php printf( esc_html__( 'Período fijado: <strong>%d días</strong> (%s – %s)', 'timeoff' ), $s['fixed'], esc_html( $s['period_start'] ), esc_html( $s['period_end'] ) ); ?></li>
                <?php endif; ?>
                <li><?php printf( esc_html__( 'Aprobados: <strong>%d días</strong>', 'timeoff' ), $s['approved'] ); ?></li>
                <li><?php printf( esc_html__( 'Pendientes: <strong>%d días</strong>', 'timeoff' ), $s['pending'] ); ?></li>
                <li><?php printf( esc_html__( 'Disponibles: <strong>%d días</strong>', 'timeoff' ), $s['free_left'] ); ?></li>
            </ul>
        </div>
        <?php return ob_get_clean();
    }

    /* ------------------------------------------------------------------ */
    /* AJAX                                                                */
    /* ------------------------------------------------------------------ */

    private function verify_nonce() {
        if ( ! check_ajax_referer( 'timeoff_public', 'nonce', false ) || ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Sin permiso.', 'timeoff' ) ), 403 );
        }
    }

    public function ajax_submit_request() {
        $this->verify_nonce();

        $uid   = get_current_user_id();
        $start = sanitize_text_field( $_POST['start_date'] ?? '' );
        $end   = sanitize_text_field( $_POST['end_date']   ?? '' );
        $note  = sanitize_textarea_field( $_POST['note']   ?? '' );
        $year  = intval( substr( $start, 0, 4 ) );

        if ( ! $start || ! $end || $start > $end ) {
            wp_send_json_error( array( 'message' => __( 'Fechas no válidas.', 'timeoff' ) ) );
        }

        // Comprobar días disponibles
        $days      = TimeOff_Request::count_natural_days( $start, $end );
        $available = TimeOff_Request::days_available( $uid, $year );

        if ( $days > $available ) {
            wp_send_json_error( array( 'message' => sprintf(
                __( 'Solo tienes %d días disponibles y estás solicitando %d.', 'timeoff' ),
                $available, $days
            ) ) );
        }

        // Comprobar solapamiento
        if ( TimeOff_Request::has_overlap( $uid, $start, $end ) ) {
            wp_send_json_error( array( 'message' => __( 'Ya tienes una solicitud en ese período.', 'timeoff' ) ) );
        }

        // Validar cobertura (grupos + regla agosto)
        $coverage = TimeOff_Coverage::validate_request( $uid, $start, $end );
        if ( is_wp_error( $coverage ) ) {
            wp_send_json_error( array( 'message' => $coverage->get_error_message() ) );
        }

        $id = TimeOff_Request::create( array(
            'employee_id'   => $uid,
            'year'          => $year,
            'start_date'    => $start,
            'end_date'      => $end,
            'employee_note' => $note,
        ) );

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Error al guardar.', 'timeoff' ) ) );
        }

        // Notificar al administrador por email
        $admin_email = get_option( 'admin_email' );
        $user        = wp_get_current_user();
        wp_mail(
            $admin_email,
            sprintf( __( '[Vacaciones] Nueva solicitud de %s', 'timeoff' ), $user->display_name ),
            sprintf(
                __( '%s ha solicitado vacaciones del %s al %s (%d días).', 'timeoff' ),
                $user->display_name, $start, $end, $days
            )
        );

        wp_send_json_success( array(
            'id'        => $id,
            'days'      => $days,
            'available' => TimeOff_Request::days_available( $uid, $year ),
        ) );
    }

    public function ajax_delete_own_request() {
        $this->verify_nonce();
        $uid = get_current_user_id();
        $id  = absint( $_POST['id'] ?? 0 );

        $req = TimeOff_Request::get( $id );
        if ( ! $req || (int) $req->employee_id !== $uid ) {
            wp_send_json_error( array( 'message' => __( 'Solicitud no encontrada.', 'timeoff' ) ) );
        }
        if ( $req->status === 'approved' ) {
            wp_send_json_error( array( 'message' => __( 'No puedes cancelar una solicitud ya aprobada. Contacta con tu responsable.', 'timeoff' ) ) );
        }

        TimeOff_Request::delete( $id );
        wp_send_json_success();
    }

    public function ajax_get_my_events() {
        $this->verify_nonce();
        $uid  = get_current_user_id();
        $year = intval( $_GET['year'] ?? date( 'Y' ) );

        $rows   = TimeOff_Request::get_all( array( 'employee_id' => $uid, 'year' => $year ) );
        $colors = array( 'pending' => '#f0ad4e', 'approved' => '#5cb85c', 'rejected' => '#d9534f' );
        $labels = array(
            'pending'  => __( 'Pendiente', 'timeoff' ),
            'approved' => __( 'Aprobada', 'timeoff' ),
            'rejected' => __( 'Rechazada', 'timeoff' ),
        );

        $events = array();
        foreach ( $rows as $r ) {
            $events[] = array(
                'id'    => 'req-' . $r->id,
                'title' => $labels[ $r->status ] ?? $r->status,
                'start' => $r->start_date,
                'end'   => date( 'Y-m-d', strtotime( $r->end_date . ' +1 day' ) ),
                'color' => $colors[ $r->status ] ?? '#777',
                'extendedProps' => array(
                    'request_id' => $r->id,
                    'status'     => $r->status,
                    'days'       => $r->days_count,
                    'note'       => $r->admin_note,
                ),
            );
        }

        // Período fijado
        $period = TimeOff_Employee::get_fixed_period( $uid, $year );
        if ( $period ) {
            $events[] = array(
                'id'    => 'fixed-' . $uid,
                'title' => __( 'Período fijado', 'timeoff' ),
                'start' => $period->start_date,
                'end'   => date( 'Y-m-d', strtotime( $period->end_date . ' +1 day' ) ),
                'color' => '#337ab7',
                'extendedProps' => array( 'type' => 'fixed', 'days' => $period->days_count ),
            );
        }

        wp_send_json_success( $events );
    }
}
