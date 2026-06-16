<?php defined( 'ABSPATH' ) || exit;
$default_days    = intval( get_option( 'timeoff_default_days', TIMEOFF_MIN_DAYS ) );
$august_min      = intval( get_option( 'timeoff_august_min_present', 1 ) );
?>
<div class="wrap timeoff-wrap">
    <h1><?php esc_html_e( 'Ajustes del plugin', 'timeoff' ); ?></h1>

    <form method="post">
        <?php wp_nonce_field( 'timeoff_settings', 'timeoff_settings_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Días de vacaciones por defecto', 'timeoff' ); ?></th>
                <td>
                    <input type="number" name="timeoff_default_days"
                           value="<?php echo esc_attr( $default_days ); ?>"
                           min="<?php echo TIMEOFF_MIN_DAYS; ?>" max="365" class="small-text">
                    <p class="description">
                        <?php printf(
                            esc_html__( 'Mínimo legal %d días naturales (Art. 38 ET). Se aplicará a nuevos empleados; los existentes conservan su configuración individual.', 'timeoff' ),
                            TIMEOFF_MIN_DAYS
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>

            <tr>
                <th><?php esc_html_e( 'Mínimo personal en agosto', 'timeoff' ); ?></th>
                <td>
                    <input type="number" name="timeoff_august_min_present"
                           value="<?php echo esc_attr( $august_min ); ?>"
                           min="0" max="20" class="small-text">
                    <p class="description">
                        <?php esc_html_e( 'Número mínimo de empleados que deben estar trabajando (no de vacaciones) en cualquier día de agosto. Pon 0 para desactivar esta restricción.', 'timeoff' ); ?>
                        <br><strong><?php esc_html_e( 'Solo abrimos por las tardes en agosto → recomendado: 1.', 'timeoff' ); ?></strong>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Shortcodes disponibles', 'timeoff' ); ?></h2>
        <table class="widefat" style="max-width:600px">
            <thead><tr><th>Shortcode</th><th><?php esc_html_e( 'Descripción', 'timeoff' ); ?></th></tr></thead>
            <tbody>
                <tr><td><code>[timeoff_request_form]</code></td><td><?php esc_html_e( 'Formulario de solicitud para el empleado', 'timeoff' ); ?></td></tr>
                <tr><td><code>[timeoff_my_calendar]</code></td><td><?php esc_html_e( 'Calendario personal del empleado', 'timeoff' ); ?></td></tr>
                <tr><td><code>[timeoff_my_summary]</code></td><td><?php esc_html_e( 'Resumen de días del empleado (tabla)', 'timeoff' ); ?></td></tr>
            </tbody>
        </table>

        <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar ajustes', 'timeoff' ); ?></button></p>
    </form>
</div>
