<?php defined( 'ABSPATH' ) || exit;

$groups    = TimeOff_Coverage::get_groups();
$employees = TimeOff_Employee::get_all_employees();

// Grupo seleccionado para editar
$editing = null;
$editing_members = array();
if ( ! empty( $_GET['edit_group'] ) ) {
    $editing = TimeOff_Coverage::get_group( absint( $_GET['edit_group'] ) );
    if ( $editing ) {
        $editing_members = array_column(
            (array) TimeOff_Coverage::get_group_members( $editing->id ),
            'employee_id'
        );
    }
}
?>
<div class="wrap timeoff-wrap">
    <h1><?php esc_html_e( 'Grupos de cobertura', 'timeoff' ); ?></h1>

    <p class="description">
        <?php esc_html_e( 'Define grupos de empleados que no pueden estar todos de vacaciones a la vez. Por ejemplo: "Recepción" con mínimo 1 persona presente.', 'timeoff' ); ?>
    </p>

    <!-- Formulario de grupo -->
    <div class="timeoff-form-box" style="max-width:640px; margin-bottom:28px;">
        <h2 style="margin-top:0"><?php echo $editing ? esc_html__( 'Editar grupo', 'timeoff' ) : esc_html__( 'Nuevo grupo', 'timeoff' ); ?></h2>

        <div id="timeoff-coverage-msg"></div>

        <form id="timeoff-coverage-form">
            <?php if ( $editing ) : ?>
                <input type="hidden" name="id" value="<?php echo esc_attr( $editing->id ); ?>">
            <?php endif; ?>

            <table class="form-table" style="margin:0">
                <tr>
                    <th style="width:160px"><label for="cov-name"><?php esc_html_e( 'Nombre del grupo', 'timeoff' ); ?></label></th>
                    <td><input type="text" id="cov-name" name="name" class="regular-text" required
                               value="<?php echo $editing ? esc_attr( $editing->name ) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="cov-min"><?php esc_html_e( 'Mínimo presentes', 'timeoff' ); ?></label></th>
                    <td>
                        <input type="number" id="cov-min" name="min_present" min="1" max="20"
                               value="<?php echo $editing ? esc_attr( $editing->min_present ) : '1'; ?>"
                               class="small-text">
                        <p class="description"><?php esc_html_e( 'Personas del grupo que deben estar trabajando (no de vacaciones) en todo momento.', 'timeoff' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="cov-desc"><?php esc_html_e( 'Descripción', 'timeoff' ); ?></label></th>
                    <td><textarea id="cov-desc" name="description" rows="2" class="large-text"><?php echo $editing ? esc_textarea( $editing->description ) : ''; ?></textarea></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Empleados del grupo', 'timeoff' ); ?></th>
                    <td>
                        <div class="timeoff-employee-checklist">
                        <?php foreach ( $employees as $emp ) :
                            $checked = in_array( (int) $emp->ID, array_map( 'intval', $editing_members ) );
                        ?>
                            <label class="timeoff-check-label">
                                <input type="checkbox" name="members[]" value="<?php echo esc_attr( $emp->ID ); ?>"
                                    <?php checked( $checked ); ?>>
                                <?php echo esc_html( $emp->display_name ); ?>
                                <small style="color:#888">(<?php echo esc_html( $emp->user_email ); ?>)</small>
                            </label>
                        <?php endforeach; ?>
                        </div>
                        <p class="description"><?php esc_html_e( 'Marca todos los empleados que pertenecen a este grupo.', 'timeoff' ); ?></p>
                    </td>
                </tr>
            </table>

            <p style="margin-top:16px">
                <button type="submit" class="button button-primary" id="cov-save-btn">
                    <?php echo $editing ? esc_html__( 'Actualizar grupo', 'timeoff' ) : esc_html__( 'Crear grupo', 'timeoff' ); ?>
                </button>
                <?php if ( $editing ) : ?>
                <a href="<?php echo esc_url( remove_query_arg( 'edit_group' ) ); ?>" class="button">
                    <?php esc_html_e( 'Cancelar', 'timeoff' ); ?>
                </a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <!-- Lista de grupos existentes -->
    <h2><?php esc_html_e( 'Grupos configurados', 'timeoff' ); ?></h2>

    <?php if ( $groups ) : ?>
    <table class="wp-list-table widefat fixed striped timeoff-table" style="max-width:900px">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Grupo', 'timeoff' ); ?></th>
                <th style="width:110px"><?php esc_html_e( 'Mín. presentes', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Miembros', 'timeoff' ); ?></th>
                <th><?php esc_html_e( 'Descripción', 'timeoff' ); ?></th>
                <th style="width:140px"><?php esc_html_e( 'Acciones', 'timeoff' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $groups as $g ) :
            $members = TimeOff_Coverage::get_group_members( $g->id );
        ?>
            <tr>
                <td><strong><?php echo esc_html( $g->name ); ?></strong></td>
                <td style="text-align:center"><?php echo esc_html( $g->min_present ); ?></td>
                <td>
                    <?php if ( $members ) : ?>
                        <ul style="margin:0; padding:0; list-style:none">
                        <?php foreach ( $members as $m ) : ?>
                            <li style="font-size:13px">• <?php echo esc_html( $m->display_name ); ?></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <em style="color:#aaa"><?php esc_html_e( 'Sin miembros', 'timeoff' ); ?></em>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( $g->description ); ?></td>
                <td>
                    <a href="<?php echo esc_url( add_query_arg( 'edit_group', $g->id ) ); ?>" class="button button-small">
                        <?php esc_html_e( 'Editar', 'timeoff' ); ?>
                    </a>
                    <button class="button button-small button-link-delete js-delete-group"
                            data-id="<?php echo esc_attr( $g->id ); ?>">
                        <?php esc_html_e( 'Eliminar', 'timeoff' ); ?>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php else : ?>
    <p class="timeoff-empty"><?php esc_html_e( 'No hay grupos creados todavía.', 'timeoff' ); ?></p>
    <?php endif; ?>

    <!-- Nota sobre la regla de agosto -->
    <div class="notice notice-info inline" style="margin-top:24px; max-width:640px">
        <p>
            <strong><?php esc_html_e( 'Regla de agosto:', 'timeoff' ); ?></strong>
            <?php printf(
                esc_html__( 'La cobertura mínima en agosto (%d persona/s) se configura en %sAjustes%s.', 'timeoff' ),
                (int) get_option( 'timeoff_august_min_present', 1 ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=timeoff-settings' ) ) . '">',
                '</a>'
            ); ?>
        </p>
    </div>
</div>
