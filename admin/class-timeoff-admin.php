<?php
defined( 'ABSPATH' ) || exit;

class TimeOff_Admin {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX: gestión de solicitudes
        add_action( 'wp_ajax_timeoff_approve_request', array( $this, 'ajax_approve_request' ) );
        add_action( 'wp_ajax_timeoff_reject_request',  array( $this, 'ajax_reject_request' ) );
        add_action( 'wp_ajax_timeoff_delete_request',  array( $this, 'ajax_delete_request' ) );

        // AJAX: calendario
        add_action( 'wp_ajax_timeoff_get_events',      array( $this, 'ajax_get_events' ) );

        // AJAX: configuración de empleado + período fijado
        add_action( 'wp_ajax_timeoff_save_employee',   array( $this, 'ajax_save_employee' ) );
        add_action( 'wp_ajax_timeoff_delete_period',   array( $this, 'ajax_delete_period' ) );

        // AJAX: grupos de cobertura
        add_action( 'wp_ajax_timeoff_save_coverage_group',   array( $this, 'ajax_save_coverage_group' ) );
        add_action( 'wp_ajax_timeoff_delete_coverage_group', array( $this, 'ajax_delete_coverage_group' ) );

        // Exportación CSV
        add_action( 'admin_init', array( $this, 'handle_export' ) );
    }

    /* ------------------------------------------------------------------ */
    /* MENÚS                                                               */
    /* ------------------------------------------------------------------ */

    public function register_menus() {
        add_menu_page(
            __( 'Vacaciones', 'timeoff' ),
            __( 'Vacaciones', 'timeoff' ),
            'manage_timeoff',
            'timeoff',
            array( $this, 'page_dashboard' ),
            'dashicons-calendar-alt',
            58
        );

        add_submenu_page( 'timeoff', __( 'Dashboard', 'timeoff' ),   __( 'Dashboard', 'timeoff' ),   'manage_timeoff', 'timeoff',                array( $this, 'page_dashboard' ) );
        add_submenu_page( 'timeoff', __( 'Solicitudes', 'timeoff' ), __( 'Solicitudes', 'timeoff' ), 'manage_timeoff', 'timeoff-requests',        array( $this, 'page_requests' ) );
        add_submenu_page( 'timeoff', __( 'Empleados', 'timeoff' ),   __( 'Empleados', 'timeoff' ),   'manage_timeoff', 'timeoff-employees',       array( $this, 'page_employees' ) );
        add_submenu_page( 'timeoff', __( 'Cobertura', 'timeoff' ),   __( 'Cobertura', 'timeoff' ),   'manage_timeoff', 'timeoff-coverage',        array( $this, 'page_coverage' ) );
        add_submenu_page( 'timeoff', __( 'Calendario', 'timeoff' ),  __( 'Calendario', 'timeoff' ),  'manage_timeoff', 'timeoff-calendar',        array( $this, 'page_calendar' ) );
        add_submenu_page( 'timeoff', __( 'Ajustes', 'timeoff' ),     __( 'Ajustes', 'timeoff' ),     'manage_options', 'timeoff-settings',        array( $this, 'page_settings' ) );
    }

    /* ------------------------------------------------------------------ */
    /* ASSETS                                                              */
    /* ------------------------------------------------------------------ */

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'timeoff' ) === false ) return;

        wp_enqueue_style(  'timeoff-admin', TIMEOFF_PLUGIN_URL . 'admin/css/admin.css', array(), TIMEOFF_VERSION );

        // FullCalendar 6 (CDN)
        wp_enqueue_style(  'fullcalendar',  'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css', array(), '6.1.11' );
        wp_enqueue_script( 'fullcalendar',  'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js', array(), '6.1.11', true );

        wp_enqueue_script( 'timeoff-admin', TIMEOFF_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery', 'fullcalendar' ), TIMEOFF_VERSION, true );

        wp_localize_script( 'timeoff-admin', 'timeoffAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'timeoff_admin' ),
            'year'    => intval( $_GET['year'] ?? date( 'Y' ) ),
            'i18n'    => array(
                'confirm_approve' => __( '¿Aprobar esta solicitud?', 'timeoff' ),
                'confirm_reject'  => __( '¿Rechazar esta solicitud?', 'timeoff' ),
                'confirm_delete'  => __( '¿Eliminar definitivamente?', 'timeoff' ),
                'saved'           => __( 'Guardado correctamente.', 'timeoff' ),
                'error'           => __( 'Error al procesar la solicitud.', 'timeoff' ),
            ),
        ) );
    }

    /* ------------------------------------------------------------------ */
    /* PÁGINAS                                                             */
    /* ------------------------------------------------------------------ */

    public function page_dashboard() {
        require_once TIMEOFF_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    public function page_requests() {
        require_once TIMEOFF_PLUGIN_DIR . 'admin/views/requests.php';
    }
    public function page_employees() {
        require_once TIMEOFF_PLUGIN_DIR . 'admin/views/employees.php';
    }
    public function page_coverage() {
        require_once TIMEOFF_PLUGIN_DIR . 'admin/views/coverage.php';
    }
    public function page_calendar() {
        require_once TIMEOFF_PLUGIN_DIR . 'admin/views/calendar.php';
    }
    public function page_settings() {
        if ( isset( $_POST['timeoff_settings_nonce'] ) && wp_verify_nonce( $_POST['timeoff_settings_nonce'], 'timeoff_settings' ) ) {
            update_option( 'timeoff_default_days',         absint( $_POST['timeoff_default_days'] ?? 30 ) );
            update_option( 'timeoff_august_min_present',   absint( $_POST['timeoff_august_min_present'] ?? 1 ) );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Ajustes guardados.', 'timeoff' ) . '</p></div>';
        }
        require_once TIMEOFF_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /* ------------------------------------------------------------------ */
    /* AJAX                                                                */
    /* ------------------------------------------------------------------ */

    private function verify_nonce() {
        if ( ! check_ajax_referer( 'timeoff_admin', 'nonce', false ) || ! current_user_can( 'manage_timeoff' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permiso.', 'timeoff' ) ), 403 );
        }
    }

    public function ajax_approve_request() {
        $this->verify_nonce();
        $id   = absint( $_POST['id'] ?? 0 );
        $note = sanitize_textarea_field( $_POST['note'] ?? '' );
        TimeOff_Request::update_status( $id, 'approved', $note );
        wp_send_json_success();
    }

    public function ajax_reject_request() {
        $this->verify_nonce();
        $id   = absint( $_POST['id'] ?? 0 );
        $note = sanitize_textarea_field( $_POST['note'] ?? '' );
        TimeOff_Request::update_status( $id, 'rejected', $note );
        wp_send_json_success();
    }

    public function ajax_delete_request() {
        $this->verify_nonce();
        $id = absint( $_POST['id'] ?? 0 );
        TimeOff_Request::delete( $id );
        wp_send_json_success();
    }

    public function ajax_get_events() {
        check_ajax_referer( 'timeoff_admin', 'nonce' );
        $year   = intval( $_GET['year'] ?? date( 'Y' ) );
        $events = array_merge(
            TimeOff_Request::get_calendar_events( $year ),
            TimeOff_Employee::get_fixed_period_events( $year )
        );
        wp_send_json_success( $events );
    }

    public function ajax_save_employee() {
        $this->verify_nonce();

        $emp_id     = absint( $_POST['employee_id'] ?? 0 );
        $year       = intval( $_POST['year'] ?? date( 'Y' ) );
        $total_days = absint( $_POST['total_days'] ?? 30 );
        $start      = sanitize_text_field( $_POST['period_start'] ?? '' );
        $end        = sanitize_text_field( $_POST['period_end'] ?? '' );

        if ( $total_days < TIMEOFF_MIN_DAYS ) {
            wp_send_json_error( array( 'message' => sprintf(
                __( 'El mínimo legal es %d días (Art. 38 ET).', 'timeoff' ), TIMEOFF_MIN_DAYS
            ) ) );
        }

        TimeOff_Employee::save_settings( $emp_id, $year, $total_days );

        if ( $start && $end ) {
            $result = TimeOff_Employee::save_fixed_period( $emp_id, $year, $start, $end );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }
        }

        wp_send_json_success( TimeOff_Employee::summary( $emp_id, $year ) );
    }

    public function ajax_delete_period() {
        $this->verify_nonce();
        $emp_id = absint( $_POST['employee_id'] ?? 0 );
        $year   = intval( $_POST['year'] ?? date( 'Y' ) );
        TimeOff_Employee::delete_fixed_period( $emp_id, $year );
        wp_send_json_success();
    }

    public function ajax_save_coverage_group() {
        $this->verify_nonce();

        $data = array(
            'id'          => absint( $_POST['id'] ?? 0 ) ?: null,
            'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
            'min_present' => absint( $_POST['min_present'] ?? 1 ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
        );

        if ( ! $data['name'] ) {
            wp_send_json_error( array( 'message' => __( 'El nombre del grupo es obligatorio.', 'timeoff' ) ) );
        }

        $members = array_map( 'absint', (array) ( $_POST['members'] ?? array() ) );
        $id      = TimeOff_Coverage::save_group( $data, $members );

        wp_send_json_success( array( 'id' => $id ) );
    }

    public function ajax_delete_coverage_group() {
        $this->verify_nonce();
        TimeOff_Coverage::delete_group( absint( $_POST['id'] ?? 0 ) );
        wp_send_json_success();
    }

    /* ------------------------------------------------------------------ */
    /* EXPORTACIÓN CSV                                                      */
    /* ------------------------------------------------------------------ */

    public function handle_export() {
        if ( empty( $_GET['timeoff_export'] ) ) return;
        if ( ! current_user_can( 'manage_timeoff' ) ) wp_die( 'Sin permiso' );
        check_admin_referer( 'timeoff_export' );

        $year = intval( $_GET['year'] ?? date( 'Y' ) );

        if ( $_GET['timeoff_export'] === 'requests' ) {
            TimeOff_Export::download_csv( $year );
        } elseif ( $_GET['timeoff_export'] === 'summary' ) {
            TimeOff_Export::download_summary_csv( $year );
        }
    }
}
