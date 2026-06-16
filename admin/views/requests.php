<?php defined( 'ABSPATH' ) || exit;
$year     = intval( $_GET['year'] ?? date( 'Y' ) );
$status   = sanitize_text_field( $_GET['filter_status'] ?? '' );
$requests = TimeOff_Request::get_all( array_filter( array( 'year' => $year, 'status' => $status ) ) );

$export_url = wp_nonce_url(
    add_query_arg( array( 'timeoff_export' => 'requests', 'year' => $year ), admin_url() ),
    'timeoff_export'
);
?>
<div class="wrap timeoff-wrap">
    <h1><?php esc_html_e( 'Solicitudes de vacaciones', 'timeoff' ); ?></h1>

    <div class="timeoff-toolbar">
        <!-- Filtros -->
        <form method="get" class="timeoff-filters">
            <input type="hidden" name="page" value="timeoff-requests">
            <select name="year">
                <?php for ( $y = date('Y')-1; $y <= date('Y')+1; $y++ ) : ?>
                <option value="<?php echo $y; ?>" <?php selected( $y, $year ); ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
            <select name="filter_status">
                <option value=""><?php esc_html_e( 'Todos los estados', 'timeoff' ); ?></option>
                <option value="pending"  <?php selected( $status, 'pending' );  ?>><?php esc_html_e( 'Pendiente', 'timeoff' ); ?></option>
                <option value="approved" <?php selected( $status, 'approved' ); ?>><?php esc_html_e( 'Aprobada', 'timeoff' ); ?></option>
                <option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rechazada', 'timeoff' ); ?></option>
            </select>
            <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'timeoff' ); ?></button>
        </form>

        <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
            ⬇ <?php esc_html_e( 'Exportar CSV', 'timeoff' ); ?>
        </a>
    </div>

    <table class="wp-list-table widefat fixed striped timeoff-table">
        <thead>
            <tr>
                <th style="width:30px">ID</th>
                <th><?php esc_html_e( 'Empleado', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Desde', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Hasta', 'timeoff' ); ?></th>
                <th style="width:50px"><?php esc_html_e( 'Días', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Estado', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Nota empleado', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Nota admin', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Acciones', 'timeoff' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( $requests ) : foreach ( $requests as $r ) : ?>
            <tr class="timeoff-row-<?php echo esc_attr( $r->status ); ?>" data-id="<?php echo esc_attr( $r->id ); ?>">
                <td><?php echo esc_html( $r->id ); ?></td>
                <td><?php echo esc_html( $r->employee_name ); ?></td>
                <td><?php echo esc_html( $r->start_date ); ?></td>
                <td><?php echo esc_html( $r->end_date ); ?></td>
                <td><?php echo esc_html( $r->days_count ); ?></td>
                <td><span class="timeoff-badge timeoff-badge-<?php echo esc_attr( $r->status ); ?>">
                    <?php echo esc_html( ucfirst( $r->status ) ); ?>
                </span></td>
                <td><?php echo esc_html( $r->employee_note ); ?></td>
                <td class="js-admin-note-cell"><?php echo esc_html( $r->admin_note ); ?></td>
                <td>
                    <?php if ( $r->status === 'pending' ) : ?>
                    <button class="button button-primary js-approve" data-id="<?php echo esc_attr( $r->id ); ?>"><?php esc_html_e( 'Aprobar', 'timeoff' ); ?></button>
                    <button class="button js-reject" data-id="<?php echo esc_attr( $r->id ); ?>"><?php esc_html_e( 'Rechazar', 'timeoff' ); ?></button>
                    <?php endif; ?>
                    <button class="button button-link-delete js-delete" data-id="<?php echo esc_attr( $r->id ); ?>"><?php esc_html_e( 'Eliminar', 'timeoff' ); ?></button>
                </td>
            </tr>
        <?php endforeach; else : ?>
            <tr><td colspan="9"><?php esc_html_e( 'No hay solicitudes.', 'timeoff' ); ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal para nota de rechazo -->
<div id="timeoff-reject-modal" style="display:none;">
    <div class="timeoff-modal-box">
        <h3><?php esc_html_e( 'Motivo del rechazo', 'timeoff' ); ?></h3>
        <textarea id="timeoff-reject-note" rows="4" style="width:100%"></textarea>
        <p>
            <button id="timeoff-reject-confirm" class="button button-primary"><?php esc_html_e( 'Confirmar rechazo', 'timeoff' ); ?></button>
            <button id="timeoff-reject-cancel" class="button"><?php esc_html_e( 'Cancelar', 'timeoff' ); ?></button>
        </p>
    </div>
</div>
