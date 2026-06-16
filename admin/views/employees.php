<?php defined( 'ABSPATH' ) || exit;
$year      = intval( $_GET['year'] ?? date( 'Y' ) );
$employees = TimeOff_Employee::get_all_employees();

$export_url = wp_nonce_url(
    add_query_arg( array( 'timeoff_export' => 'summary', 'year' => $year ), admin_url() ),
    'timeoff_export'
);
?>
<div class="wrap timeoff-wrap">
    <h1><?php esc_html_e( 'Empleados y períodos fijados', 'timeoff' ); ?></h1>

    <div class="timeoff-toolbar">
        <form method="get" class="timeoff-filters">
            <input type="hidden" name="page" value="timeoff-employees">
            <select name="year" onchange="this.form.submit()">
                <?php for ( $y = date('Y')-1; $y <= date('Y')+1; $y++ ) : ?>
                <option value="<?php echo $y; ?>" <?php selected( $y, $year ); ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
            ⬇ <?php esc_html_e( 'Exportar resumen CSV', 'timeoff' ); ?>
        </a>
    </div>

    <p class="description">
        <?php esc_html_e( 'Puedes fijar hasta la mitad de los días totales como período obligatorio (Art. 38 ET). El empleado recibirá notificación con antelación mínima de 2 meses.', 'timeoff' ); ?>
    </p>

    <table class="wp-list-table widefat fixed striped timeoff-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Empleado', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Email', 'timeoff' ); ?></th>
                <th style="width:90px"><?php esc_html_e( 'Días totales', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Período fijado', 'timeoff' ); ?></th>
                <th style="width:90px"><?php esc_html_e( 'Días fijados', 'timeoff' ); ?></th>
                <th style="width:90px"><?php esc_html_e( 'Disponibles', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Acciones', 'timeoff' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $employees as $emp ) :
            $s      = TimeOff_Employee::summary( $emp->ID, $year );
            $period = TimeOff_Employee::get_fixed_period( $emp->ID, $year );
            $max_fixed = (int) ceil( $s['total'] / 2 );
        ?>
            <tr data-emp="<?php echo esc_attr( $emp->ID ); ?>">
                <td><?php echo esc_html( $emp->display_name ); ?></td>
                <td><?php echo esc_html( $emp->user_email ); ?></td>
                <td>
                    <input type="number" class="js-total-days small-text"
                           value="<?php echo esc_attr( $s['total'] ); ?>"
                           min="<?php echo TIMEOFF_MIN_DAYS; ?>" max="365" style="width:60px">
                </td>
                <td>
                    <input type="date" class="js-period-start"
                           value="<?php echo esc_attr( $s['period_start'] ?? '' ); ?>"
                           min="<?php echo $year; ?>-01-01" max="<?php echo $year; ?>-12-31">
                    &nbsp;–&nbsp;
                    <input type="date" class="js-period-end"
                           value="<?php echo esc_attr( $s['period_end'] ?? '' ); ?>"
                           min="<?php echo $year; ?>-01-01" max="<?php echo $year; ?>-12-31">
                    <span class="description js-max-hint">
                        <?php printf( esc_html__( 'Máx. %d días', 'timeoff' ), $max_fixed ); ?>
                    </span>
                </td>
                <td class="js-fixed-days"><?php echo esc_html( $s['fixed'] ?: '—' ); ?></td>
                <td class="<?php echo $s['free_left'] <= 0 ? 'timeoff-zero' : ''; ?> js-free-left">
                    <?php echo esc_html( $s['free_left'] ); ?>
                </td>
                <td>
                    <button class="button button-primary js-save-employee"
                            data-emp="<?php echo esc_attr( $emp->ID ); ?>"
                            data-year="<?php echo esc_attr( $year ); ?>">
                        <?php esc_html_e( 'Guardar', 'timeoff' ); ?>
                    </button>
                    <?php if ( $period ) : ?>
                    <button class="button button-link-delete js-delete-period"
                            data-emp="<?php echo esc_attr( $emp->ID ); ?>"
                            data-year="<?php echo esc_attr( $year ); ?>">
                        <?php esc_html_e( 'Quitar período', 'timeoff' ); ?>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p class="description">
        <strong><?php esc_html_e( 'Período fijado:', 'timeoff' ); ?></strong>
        <?php esc_html_e( 'Al guardar, la fecha queda registrada como notificación a efectos del plazo de 2 meses del Art. 38 ET. Recomendado: fijar antes de Abril para períodos de Julio/Agosto.', 'timeoff' ); ?>
    </p>
</div>
