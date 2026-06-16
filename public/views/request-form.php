<?php defined( 'ABSPATH' ) || exit;
$uid  = get_current_user_id();
$year = intval( date( 'Y' ) );
$s    = TimeOff_Employee::summary( $uid, $year );
$reqs = TimeOff_Request::get_all( array( 'employee_id' => $uid, 'year' => $year ) );

$status_labels = array(
    'pending'  => __( 'Pendiente', 'timeoff' ),
    'approved' => __( 'Aprobada', 'timeoff' ),
    'rejected' => __( 'Rechazada', 'timeoff' ),
);
?>
<div class="timeoff-public-wrap">

    <!-- Contador de días -->
    <div class="timeoff-counter-bar">
        <div class="counter-item">
            <span class="counter-num"><?php echo esc_html( $s['total'] ); ?></span>
            <span class="counter-lbl"><?php esc_html_e( 'Total', 'timeoff' ); ?></span>
        </div>
        <?php if ( $s['fixed'] ) : ?>
        <div class="counter-item counter-fixed">
            <span class="counter-num"><?php echo esc_html( $s['fixed'] ); ?></span>
            <span class="counter-lbl"><?php esc_html_e( 'Fijados', 'timeoff' ); ?></span>
        </div>
        <?php endif; ?>
        <div class="counter-item counter-approved">
            <span class="counter-num"><?php echo esc_html( $s['approved'] ); ?></span>
            <span class="counter-lbl"><?php esc_html_e( 'Aprobados', 'timeoff' ); ?></span>
        </div>
        <div class="counter-item counter-pending">
            <span class="counter-num"><?php echo esc_html( $s['pending'] ); ?></span>
            <span class="counter-lbl"><?php esc_html_e( 'Pendientes', 'timeoff' ); ?></span>
        </div>
        <div class="counter-item counter-available <?php echo $s['free_left'] <= 0 ? 'counter-zero' : ''; ?>">
            <span class="counter-num"><?php echo esc_html( $s['free_left'] ); ?></span>
            <span class="counter-lbl"><?php esc_html_e( 'Disponibles', 'timeoff' ); ?></span>
        </div>
    </div>

    <?php if ( $s['period_start'] ) : ?>
    <div class="timeoff-notice timeoff-notice-info">
        <?php printf(
            esc_html__( 'Tu empresa ha fijado el período del %s al %s (%d días). Estos días ya están descontados de tu cupo.', 'timeoff' ),
            esc_html( $s['period_start'] ), esc_html( $s['period_end'] ), esc_html( $s['fixed'] )
        ); ?>
    </div>
    <?php endif; ?>

    <!-- Formulario de solicitud -->
    <?php if ( $s['free_left'] > 0 ) : ?>
    <div class="timeoff-form-box">
        <h3><?php esc_html_e( 'Nueva solicitud', 'timeoff' ); ?></h3>
        <div id="timeoff-form-message"></div>
        <form id="timeoff-request-form">
            <div class="timeoff-field-row">
                <label for="timeoff-start"><?php esc_html_e( 'Fecha de inicio', 'timeoff' ); ?></label>
                <input type="date" id="timeoff-start" name="start_date" required
                       min="<?php echo $year; ?>-01-01" max="<?php echo $year; ?>-12-31">
            </div>
            <div class="timeoff-field-row">
                <label for="timeoff-end"><?php esc_html_e( 'Fecha de fin', 'timeoff' ); ?></label>
                <input type="date" id="timeoff-end" name="end_date" required
                       min="<?php echo $year; ?>-01-01" max="<?php echo $year; ?>-12-31">
            </div>
            <div class="timeoff-field-row">
                <label><?php esc_html_e( 'Días naturales seleccionados:', 'timeoff' ); ?></label>
                <strong id="timeoff-days-preview">—</strong>
            </div>
            <div class="timeoff-field-row">
                <label for="timeoff-note"><?php esc_html_e( 'Nota (opcional)', 'timeoff' ); ?></label>
                <textarea id="timeoff-note" name="note" rows="2"></textarea>
            </div>
            <button type="submit" class="timeoff-btn timeoff-btn-primary">
                <?php esc_html_e( 'Enviar solicitud', 'timeoff' ); ?>
            </button>
        </form>
    </div>
    <?php else : ?>
    <div class="timeoff-notice timeoff-notice-warning">
        <?php esc_html_e( 'No te quedan días disponibles para solicitar.', 'timeoff' ); ?>
    </div>
    <?php endif; ?>

    <!-- Mis solicitudes -->
    <h3><?php esc_html_e( 'Mis solicitudes', 'timeoff' ); ?></h3>
    <div id="timeoff-requests-list">
    <?php if ( $reqs ) : ?>
    <table class="timeoff-table-front">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Desde', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Hasta', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Días', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Estado', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Nota admin', 'timeoff' ); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $reqs as $r ) : ?>
            <tr data-id="<?php echo esc_attr( $r->id ); ?>">
                <td><?php echo esc_html( $r->start_date ); ?></td>
                <td><?php echo esc_html( $r->end_date ); ?></td>
                <td><?php echo esc_html( $r->days_count ); ?></td>
                <td><span class="timeoff-badge timeoff-badge-<?php echo esc_attr( $r->status ); ?>">
                    <?php echo esc_html( $status_labels[ $r->status ] ?? $r->status ); ?>
                </span></td>
                <td><?php echo esc_html( $r->admin_note ); ?></td>
                <td>
                    <?php if ( $r->status === 'pending' ) : ?>
                    <button class="timeoff-btn timeoff-btn-danger js-cancel-request" data-id="<?php echo esc_attr( $r->id ); ?>">
                        <?php esc_html_e( 'Cancelar', 'timeoff' ); ?>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p><?php esc_html_e( 'Aún no has enviado solicitudes este año.', 'timeoff' ); ?></p>
    <?php endif; ?>
    </div>

</div>
