<?php defined( 'ABSPATH' ) || exit;
$year      = intval( $_GET['year'] ?? date( 'Y' ) );
$employees = TimeOff_Employee::get_all_employees();
$pending   = TimeOff_Request::get_all( array( 'year' => $year, 'status' => 'pending' ) );
$approved  = TimeOff_Request::get_all( array( 'year' => $year, 'status' => 'approved' ) );
?>
<div class="wrap timeoff-wrap">
    <h1><?php esc_html_e( 'Gestión de Vacaciones', 'timeoff' ); ?> — <?php echo esc_html( $year ); ?></h1>

    <div class="timeoff-year-nav">
        <a href="<?php echo esc_url( add_query_arg( 'year', $year - 1 ) ); ?>">&laquo; <?php echo esc_html( $year - 1 ); ?></a>
        <strong><?php echo esc_html( $year ); ?></strong>
        <a href="<?php echo esc_url( add_query_arg( 'year', $year + 1 ) ); ?>"><?php echo esc_html( $year + 1 ); ?> &raquo;</a>
    </div>

    <!-- KPIs -->
    <div class="timeoff-kpis">
        <div class="timeoff-kpi kpi-pending">
            <span class="kpi-number"><?php echo count( $pending ); ?></span>
            <span class="kpi-label"><?php esc_html_e( 'Solicitudes pendientes', 'timeoff' ); ?></span>
        </div>
        <div class="timeoff-kpi kpi-approved">
            <span class="kpi-number"><?php echo count( $approved ); ?></span>
            <span class="kpi-label"><?php esc_html_e( 'Solicitudes aprobadas', 'timeoff' ); ?></span>
        </div>
        <div class="timeoff-kpi kpi-employees">
            <span class="kpi-number"><?php echo count( $employees ); ?></span>
            <span class="kpi-label"><?php esc_html_e( 'Empleados', 'timeoff' ); ?></span>
        </div>
    </div>

    <!-- Solicitudes pendientes -->
    <?php if ( $pending ) : ?>
    <h2><?php esc_html_e( 'Solicitudes pendientes de revisión', 'timeoff' ); ?></h2>
    <table class="wp-list-table widefat fixed striped timeoff-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Empleado', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Desde', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Hasta', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Días', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Nota', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Acciones', 'timeoff' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $pending as $req ) : ?>
            <tr data-id="<?php echo esc_attr( $req->id ); ?>">
                <td><?php echo esc_html( $req->employee_name ); ?></td>
                <td><?php echo esc_html( $req->start_date ); ?></td>
                <td><?php echo esc_html( $req->end_date ); ?></td>
                <td><?php echo esc_html( $req->days_count ); ?></td>
                <td><?php echo esc_html( $req->employee_note ); ?></td>
                <td>
                    <button class="button button-primary js-approve" data-id="<?php echo esc_attr( $req->id ); ?>"><?php esc_html_e( 'Aprobar', 'timeoff' ); ?></button>
                    <button class="button button-secondary js-reject" data-id="<?php echo esc_attr( $req->id ); ?>"><?php esc_html_e( 'Rechazar', 'timeoff' ); ?></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p class="timeoff-empty"><?php esc_html_e( 'No hay solicitudes pendientes.', 'timeoff' ); ?></p>
    <?php endif; ?>

    <!-- Resumen por empleado -->
    <h2><?php esc_html_e( 'Resumen por empleado', 'timeoff' ); ?></h2>
    <table class="wp-list-table widefat fixed striped timeoff-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Empleado', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Total días', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Período fijado', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Aprobados', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Pendientes', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Disponibles', 'timeoff' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $employees as $emp ) :
            $s = TimeOff_Employee::summary( $emp->ID, $year );
        ?>
            <tr>
                <td><?php echo esc_html( $emp->display_name ); ?></td>
                <td><?php echo esc_html( $s['total'] ); ?></td>
                <td>
                    <?php if ( $s['fixed'] > 0 ) : ?>
                        <?php echo esc_html( $s['fixed'] ); ?> <?php esc_html_e( 'días', 'timeoff' ); ?>
                        (<?php echo esc_html( $s['period_start'] ); ?> – <?php echo esc_html( $s['period_end'] ); ?>)
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( $s['approved'] ); ?></td>
                <td><?php echo esc_html( $s['pending'] ); ?></td>
                <td class="<?php echo $s['free_left'] <= 0 ? 'timeoff-zero' : ''; ?>">
                    <?php echo esc_html( $s['free_left'] ); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
