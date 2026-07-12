<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) return;

global $wpdb;
$rates_table = $wpdb->prefix . 'asraa_svs_rates';

/* ===============================
   Helper sanitize function
================================ */
function asraa_svs_clean( $value ) {
    return trim( sanitize_text_field( wp_unslash( $value ) ) );
}

/* =====================================================
   EXPORT CSV
===================================================== */
if ( isset($_GET['asraa_svs_rates_csv']) && check_admin_referer('asraa_svs_rates_csv') ) {

    $where = [];
    $params = [];

    if (!empty($_GET['f_city'])) {
        $where[] = 'city LIKE %s';
        $params[] = '%' . $wpdb->esc_like(asraa_svs_clean($_GET['f_city'])) . '%';
    }
    if (!empty($_GET['f_area'])) {
        $where[] = 'area LIKE %s';
        $params[] = '%' . $wpdb->esc_like(asraa_svs_clean($_GET['f_area'])) . '%';
    }
    if (!empty($_GET['f_config'])) {
        $where[] = 'config LIKE %s';
        $params[] = '%' . $wpdb->esc_like(strtoupper(asraa_svs_clean($_GET['f_config']))) . '%';
    }

    $sql = "SELECT * FROM {$rates_table}";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY id ASC';

    $rates = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="asraa-rates.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','City','Area','Building','Config','Base Rate','Created At']);

    foreach ($rates as $r) {
        fputcsv($out, [
            $r['id'],
            $r['city'],
            $r['area'],
            $r['building'],
            $r['config'],
            $r['base_rate'],
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

/* =====================================================
   ADD NEW RATE
===================================================== */
if ( isset($_POST['asraa_svs_add_rate']) && check_admin_referer('asraa_svs_add_rate','asraa_svs_add_rate_nonce') ) {

    $city     = asraa_svs_clean($_POST['city'] ?? '');
    $area     = asraa_svs_clean($_POST['area'] ?? '');
    $building = asraa_svs_clean($_POST['building'] ?? '');
    $config   = strtoupper(asraa_svs_clean($_POST['config'] ?? ''));
    $rate     = floatval($_POST['base_rate'] ?? 0);

    if ($city && $rate > 0) {

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$rates_table}
                 WHERE city=%s AND area=%s AND building=%s AND config=%s LIMIT 1",
                $city, $area, $building, $config
            )
        );

        if ($existing_id) {
            echo '<div class="error"><p>' . esc_html( sprintf( 'Duplicate entry exists (ID: %d)', $existing_id ) ) . '</p></div>';
        } else {
            $wpdb->insert(
                $rates_table,
                [
                    'city'      => $city,
                    'area'      => $area,
                    'building'  => $building,
                    'config'    => $config,
                    'base_rate' => $rate,
                ],
                ['%s','%s','%s','%s','%f']
            );
            echo '<div class="updated"><p>Rate Added.</p></div>';
        }

    } else {
        echo '<div class="error"><p>City & Base Rate required.</p></div>';
    }
}

/* =====================================================
   DELETE MULTIPLE
===================================================== */
if ( isset($_POST['asraa_svs_delete_rates'], $_POST['rate_ids']) 
     && check_admin_referer('asraa_svs_delete_rates','asraa_svs_delete_rates_nonce') ) {

    $ids = array_map('intval', $_POST['rate_ids']);
    if ($ids) {
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$rates_table} WHERE id IN ($placeholders)", ...$ids ) );
        echo '<div class="updated"><p>Selected entries deleted.</p></div>';
    }
}

/* =====================================================
   FILTERS
===================================================== */

$f_city   = $_GET['f_city']   ?? '';
$f_area   = $_GET['f_area']   ?? '';
$f_config = $_GET['f_config'] ?? '';

$where = [];
$params = [];

if ($f_city !== '') {
    $where[] = 'city LIKE %s';
    $params[] = '%' . $wpdb->esc_like(asraa_svs_clean($f_city)) . '%';
}
if ($f_area !== '') {
    $where[] = 'area LIKE %s';
    $params[] = '%' . $wpdb->esc_like(asraa_svs_clean($f_area)) . '%';
}
if ($f_config !== '') {
    $where[] = 'config LIKE %s';
    $params[] = '%' . $wpdb->esc_like(strtoupper($f_config)) . '%';
}

$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

/* =====================================================
   LOAD RATES
===================================================== */

$sql = "SELECT * FROM {$rates_table} {$where_sql} ORDER BY id ASC";
$rates = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);

?>
<div class="wrap">
    <h1>Rate Manager</h1>

    <h2>Add New Rate</h2>
    <form method="post">
        <?php wp_nonce_field('asraa_svs_add_rate','asraa_svs_add_rate_nonce'); ?>
        <table class="form-table">
            <tr><th>City *</th><td><input name="city" required></td></tr>
            <tr><th>Area</th><td><input name="area"></td></tr>
            <tr><th>Building</th><td><input name="building"></td></tr>
            <tr><th>Config</th><td><input name="config"></td></tr>
            <tr><th>Base Rate *</th><td><input type="number" name="base_rate" required></td></tr>
        </table>
        <?php submit_button('Save Rate','primary','asraa_svs_add_rate'); ?>
    </form>

    <hr>

    <h2>Existing Rates</h2>

    <form method="get">
        <input type="hidden" name="page" value="asraa-svs-rates">
        <table class="form-table">
            <tr><th>City</th><td><input name="f_city" value="<?php echo esc_attr($f_city); ?>"></td></tr>
            <tr><th>Area</th><td><input name="f_area" value="<?php echo esc_attr($f_area); ?>"></td></tr>
            <tr><th>Config</th><td><input name="f_config" value="<?php echo esc_attr($f_config); ?>"></td></tr>
        </table>
        <?php submit_button('Apply Filters'); ?>
        <a class="button" href="admin.php?page=asraa-svs-rates">Reset</a>
        <a class="button" href="<?php echo wp_nonce_url('admin.php?page=asraa-svs-rates&asraa_svs_rates_csv=1','asraa_svs_rates_csv'); ?>">Download CSV</a>
    </form>


    <form method="post">
        <?php wp_nonce_field('asraa_svs_delete_rates','asraa_svs_delete_rates_nonce'); ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><input type="checkbox" id="check-all"></th>
                    <th>ID</th><th>City</th><th>Area</th><th>Building</th><th>Config</th><th>Base Rate</th><th>Created</th><th>Edit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rates as $r): ?>
                <tr>
                    <td><input type="checkbox" name="rate_ids[]" value="<?php echo esc_attr( $r->id ); ?>"></td>
                    <td><?php echo esc_html( $r->id ); ?></td>
                    <td><?php echo esc_html( $r->city ); ?></td>
                    <td><?php echo esc_html( $r->area ); ?></td>
                    <td><?php echo esc_html( $r->building ); ?></td>
                    <td><?php echo esc_html( $r->config ); ?></td>
                    <td><?php echo esc_html( $r->base_rate ); ?></td>
                    <td><?php echo esc_html( $r->created_at ); ?></td>
                    <td>
                        <button type="button"
                            class="button button-small edit-btn"
                            data-id="<?php echo esc_attr( $r->id ); ?>"
                            data-city="<?php echo esc_attr( $r->city ); ?>"
                            data-area="<?php echo esc_attr( $r->area ); ?>"
                            data-building="<?php echo esc_attr( $r->building ); ?>"
                            data-config="<?php echo esc_attr( $r->config ); ?>"
                            data-rate="<?php echo esc_attr( $r->base_rate ); ?>"
                        >Edit</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button('Delete Selected','delete','asraa_svs_delete_rates'); ?>
    </form>
</div>

<!-- EDIT MODAL -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99999;justify-content:center;align-items:center;">
    <div style="background:#fff;padding:25px;width:500px;position:relative;border-radius:8px;">
        <span id="modal-close" style="position:absolute;right:15px;top:10px;cursor:pointer;font-size:20px;">×</span>

        <h2>Edit Rate</h2>

        <form id="edit-form">
            <?php wp_nonce_field('asraa_svs_edit_rate','asraa_svs_edit_rate_nonce'); ?>
            <input type="hidden" name="id" id="edit-id">

            <table class="form-table">
                <tr><th>City</th><td><input name="city" id="edit-city" required></td></tr>
                <tr><th>Area</th><td><input name="area" id="edit-area"></td></tr>
                <tr><th>Building</th><td><input name="building" id="edit-building"></td></tr>
                <tr><th>Config</th><td><input name="config" id="edit-config"></td></tr>

                <tr><th>Base Rate</th><td><input type="number" name="base_rate" id="edit-rate" required></td></tr>
            </table>

            <button class="button button-primary">Save Changes</button>
        </form>
    </div>
</div>

<script>
jQuery(function($){

    $('#check-all').on('change', function(){
        $('input[name="rate_ids[]"]').prop('checked', this.checked);
    });

    $('.edit-btn').on('click', function(){
        $('#edit-id').val($(this).data('id'));
        $('#edit-city').val($(this).data('city'));
        $('#edit-area').val($(this).data('area'));
        $('#edit-building').val($(this).data('building'));
        $('#edit-config').val($(this).data('config'));
        $('#edit-rate').val($(this).data('rate'));
        $('#edit-modal').fadeIn(150);
    });

    $('#modal-close').on('click', function(){
        $('#edit-modal').fadeOut(150);
    });

    $('#edit-form').on('submit', function(e){
        e.preventDefault();

        let data = $(this).serialize();
        data += '&action=asraa_update_rate';

        $.post(ajaxurl, data, function(res){
            if(res.success){
                alert('Rate Updated!');
                location.reload();
            } else {
                alert(res.data || 'Error updating rate.');
            }
        });
    });
});
</script>

<?php
/* =====================================================
   AJAX UPDATE HANDLER
===================================================== */
add_action('wp_ajax_asraa_update_rate', function() use($wpdb,$rates_table){

    if( !current_user_can('manage_options') )
        wp_send_json_error('Permission denied.');

    if( !isset($_POST['asraa_svs_edit_rate_nonce'])
        || !wp_verify_nonce($_POST['asraa_svs_edit_rate_nonce'], 'asraa_svs_edit_rate') )
        wp_send_json_error('Invalid token.');

    $id       = intval($_POST['id'] ?? 0);
    $city     = asraa_svs_clean($_POST['city'] ?? '');
    $area     = asraa_svs_clean($_POST['area'] ?? '');
    $building = asraa_svs_clean($_POST['building'] ?? '');
    $config   = strtoupper(asraa_svs_clean($_POST['config'] ?? ''));
    $rate     = floatval($_POST['base_rate'] ?? 0);

    if(!$id || !$city || $rate <= 0)
        wp_send_json_error('City & Base Rate are required.');

    // Duplicate detection
    $duplicate = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$rates_table}
             WHERE city=%s AND area=%s AND building=%s AND config=%s AND id!=%d LIMIT 1",
            $city, $area, $building, $config, $id
        )
    );

    if($duplicate)
        wp_send_json_error('Duplicate exists (ID ' . $duplicate . ')');

    $wpdb->update(
        $rates_table,
        [
            'city'      => $city,
            'area'      => $area,
            'building'  => $building,
            'config'    => $config,
            'base_rate' => $rate,
        ],
        ['id'=>$id],
        ['%s','%s','%s','%s','%f'],
        ['%d']
    );

    wp_send_json_success();
});
