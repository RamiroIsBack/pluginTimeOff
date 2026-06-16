<?php
defined( 'ABSPATH' ) || exit;

class TimeOff_DB {

    public static function get_requests_table()        { global $wpdb; return $wpdb->prefix . 'timeoff_requests'; }
    public static function get_settings_table()        { global $wpdb; return $wpdb->prefix . 'timeoff_employee_settings'; }
    public static function get_periods_table()         { global $wpdb; return $wpdb->prefix . 'timeoff_fixed_periods'; }
    public static function get_options_table()         { global $wpdb; return $wpdb->prefix . 'timeoff_options'; }
    public static function get_coverage_groups_table() { global $wpdb; return $wpdb->prefix . 'timeoff_coverage_groups'; }
    public static function get_group_members_table()   { global $wpdb; return $wpdb->prefix . 'timeoff_group_members'; }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $requests = "CREATE TABLE " . self::get_requests_table() . " (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id   BIGINT UNSIGNED NOT NULL,
            year          SMALLINT        NOT NULL,
            start_date    DATE            NOT NULL,
            end_date      DATE            NOT NULL,
            days_count    TINYINT         NOT NULL DEFAULT 0,
            status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            employee_note TEXT,
            admin_note    TEXT,
            created_at    DATETIME        NOT NULL,
            updated_at    DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY idx_employee_year (employee_id, year),
            KEY idx_status (status)
        ) $charset;";

        /* Días totales disponibles por empleado y año (default: 30, Art. 38 ET) */
        $settings = "CREATE TABLE " . self::get_settings_table() . " (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id BIGINT UNSIGNED NOT NULL,
            year        SMALLINT        NOT NULL,
            total_days  TINYINT         NOT NULL DEFAULT 30,
            PRIMARY KEY (id),
            UNIQUE KEY uq_emp_year (employee_id, year)
        ) $charset;";

        /* Período fijado por empresa (hasta la mitad del total, Art. 38 ET) */
        $periods = "CREATE TABLE " . self::get_periods_table() . " (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id   BIGINT UNSIGNED NOT NULL,
            year          SMALLINT        NOT NULL,
            start_date    DATE            NOT NULL,
            end_date      DATE            NOT NULL,
            days_count    TINYINT         NOT NULL DEFAULT 0,
            notified_at   DATETIME,
            created_at    DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_emp_year (employee_id, year)
        ) $charset;";

        /* Grupos de cobertura: trabajadores que no pueden estar todos de vacaciones a la vez */
        $groups = "CREATE TABLE " . self::get_coverage_groups_table() . " (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(100)    NOT NULL,
            min_present TINYINT         NOT NULL DEFAULT 1,
            description TEXT,
            created_at  DATETIME        NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        /* Miembros de cada grupo de cobertura */
        $members = "CREATE TABLE " . self::get_group_members_table() . " (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id    BIGINT UNSIGNED NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_group_emp (group_id, employee_id),
            KEY idx_employee (employee_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $requests );
        dbDelta( $settings );
        dbDelta( $periods );
        dbDelta( $groups );
        dbDelta( $members );
    }
}
