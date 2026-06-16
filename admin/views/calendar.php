<?php defined( 'ABSPATH' ) || exit;
$year = intval( $_GET['year'] ?? date( 'Y' ) );
?>
<div class="wrap timeoff-wrap">
    <h1><?php esc_html_e( 'Calendario de vacaciones', 'timeoff' ); ?> — <?php echo esc_html( $year ); ?></h1>

    <div class="timeoff-toolbar">
        <form method="get" class="timeoff-filters">
            <input type="hidden" name="page" value="timeoff-calendar">
            <select name="year" onchange="this.form.submit()">
                <?php for ( $y = date('Y')-1; $y <= date('Y')+1; $y++ ) : ?>
                <option value="<?php echo $y; ?>" <?php selected( $y, $year ); ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <!-- Leyenda -->
    <div class="timeoff-legend">
        <span class="legend-item"><span class="legend-dot" style="background:#f0ad4e"></span><?php esc_html_e( 'Pendiente', 'timeoff' ); ?></span>
        <span class="legend-item"><span class="legend-dot" style="background:#5cb85c"></span><?php esc_html_e( 'Aprobada', 'timeoff' ); ?></span>
        <span class="legend-item"><span class="legend-dot" style="background:#d9534f"></span><?php esc_html_e( 'Rechazada', 'timeoff' ); ?></span>
        <span class="legend-item"><span class="legend-dot" style="background:#337ab7"></span><?php esc_html_e( 'Período fijado', 'timeoff' ); ?></span>
    </div>

    <div id="timeoff-calendar" data-year="<?php echo esc_attr( $year ); ?>"></div>

    <!-- Tooltip flotante al hacer click en evento -->
    <div id="timeoff-event-popover" style="display:none;">
        <div class="popover-inner">
            <strong id="popover-title"></strong>
            <p id="popover-meta"></p>
            <p id="popover-note"></p>
        </div>
    </div>
</div>
