<?php
/**
 * Plugin Name: Time Off – Gestión de Vacaciones
 * Plugin URI:  https://example.com/timeoff
 * Description: Gestión completa de vacaciones para empleados: solicitudes, aprobación, período fijado por empresa y calendario visual. Cumple Art. 38 ET.
 * Version:     1.0.0
 * Author:      Ramiro Santamaría
 * Text Domain: timeoff
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'TIMEOFF_VERSION',   '1.0.0' );
define( 'TIMEOFF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TIMEOFF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TIMEOFF_MIN_DAYS',   30 );   // Art. 38 ET: mínimo 30 días naturales

require_once TIMEOFF_PLUGIN_DIR . 'includes/class-timeoff-db.php';
require_once TIMEOFF_PLUGIN_DIR . 'includes/class-timeoff-activator.php';
require_once TIMEOFF_PLUGIN_DIR . 'includes/class-timeoff-request.php';
require_once TIMEOFF_PLUGIN_DIR . 'includes/class-timeoff-employee.php';
require_once TIMEOFF_PLUGIN_DIR . 'includes/class-timeoff-export.php';
require_once TIMEOFF_PLUGIN_DIR . 'includes/class-timeoff-coverage.php';
require_once TIMEOFF_PLUGIN_DIR . 'includes/class-timeoff-api.php';
require_once TIMEOFF_PLUGIN_DIR . 'admin/class-timeoff-admin.php';
require_once TIMEOFF_PLUGIN_DIR . 'public/class-timeoff-public.php';

register_activation_hook( __FILE__,   array( 'TimeOff_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TimeOff_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', 'timeoff_init' );

function timeoff_init() {
    load_plugin_textdomain( 'timeoff', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    if ( is_admin() ) {
        new TimeOff_Admin();
    }
    new TimeOff_Public();
}
