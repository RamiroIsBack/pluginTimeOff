<?php defined( 'ABSPATH' ) || exit;
$year = intval( date( 'Y' ) );
?>
<div class="timeoff-public-wrap">
    <h3><?php printf( esc_html__( 'Mi calendario de vacaciones %d', 'timeoff' ), $year ); ?></h3>

    <div class="timeoff-legend">
        <span class="legend-item"><span class="legend-dot" style="background:#f0ad4e"></span><?php esc_html_e( 'Pendiente', 'timeoff' ); ?></span>
        <span class="legend-item"><span class="legend-dot" style="background:#5cb85c"></span><?php esc_html_e( 'Aprobada', 'timeoff' ); ?></span>
        <span class="legend-item"><span class="legend-dot" style="background:#d9534f"></span><?php esc_html_e( 'Rechazada', 'timeoff' ); ?></span>
        <span class="legend-item"><span class="legend-dot" style="background:#337ab7"></span><?php esc_html_e( 'Período fijado', 'timeoff' ); ?></span>
    </div>

    <div id="timeoff-my-calendar" data-year="<?php echo esc_attr( $year ); ?>"></div>
</div>
