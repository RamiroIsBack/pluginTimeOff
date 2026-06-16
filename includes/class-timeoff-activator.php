<?php
defined( 'ABSPATH' ) || exit;

class TimeOff_Activator {

    public static function activate() {
        TimeOff_DB::create_tables();
        self::add_capabilities();
        add_option( 'timeoff_version', TIMEOFF_VERSION );
    }

    public static function deactivate() {
        // No eliminamos tablas al desactivar para no perder datos
    }

    private static function add_capabilities() {
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( 'manage_timeoff' );
            $admin->add_cap( 'approve_timeoff' );
        }
        // Todos los usuarios logueados pueden solicitar
        $roles = array( 'editor', 'author', 'contributor', 'subscriber' );
        foreach ( $roles as $role_slug ) {
            $role = get_role( $role_slug );
            if ( $role ) {
                $role->add_cap( 'request_timeoff' );
            }
        }
    }
}
