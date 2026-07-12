<?php
if ( ! current_user_can( 'manage_options' ) ) {
    return;
}
global $wpdb;
$leads_table = $wpdb->prefix . 'asraa_svs_leads';

if ( isset( $_POST['asraa_svs_delete_leads'], $_POST['lead_ids'] ) && check_admin_referer( 'asraa_svs_delete_leads', 'asraa_svs_delete_leads_nonce' ) ) {
    $ids = array_map( 'intval', (array) $_POST['lead_ids'] );
    if ( $ids ) {
        $wpdb->query( "DELETE FROM {$leads_table} WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore
        echo '<div class="updated"><p>Selected leads deleted.</p></div>';
    }
}

/* Download CSV */
if ( isset( $_GET['asraa_svs_leads_csv'] ) && check_admin_referer( 'asraa_svs_leads_csv' ) ) {
    $leads = $wpdb->get_results( "SELECT * FROM {$leads_table} ORDER BY created_at DESC", ARRAY_A );
    header( 'Content-Type: text/csv' );
    header( 'Content-Disposition: attachment; filename=\"asraa-leads.csv\"' );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'Name', 'Email', 'Phone', 'City', 'Area', 'Building', 'Config', 'Sqft', 'Rate Used', 'Total', 'Created' ) );
    foreach ( $leads as $l ) {
        fputcsv(
            $out,
            array(
                $l['name'],
                $l['email'],
                $l['phone'],
                $l['city'],
                $l['area'],
                $l['building'],
                $l['config'],
                $l['sqft'],
                $l['rate_used'],
                $l['total_price'],
                $l['created_at'],
            )
        );
    }
    fclose( $out );
    exit;
}

$leads = $wpdb->get_results( "SELECT * FROM {$leads_table} ORDER BY created_at DESC LIMIT 300" );
?>
<div class="wrap">
    <h1>Valuation Leads</h1>
    <p>
        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=asraa-svs-leads&asraa_svs_leads_csv=1' ), 'asraa_svs_leads_csv' ) ); ?>" class="button">Download Leads CSV</a>
    </p>

    <form method="post">
        <?php wp_nonce_field( 'asraa_svs_delete_leads', 'asraa_svs_delete_leads_nonce' ); ?>
        <table class="widefat striped">
            <thead>
            <tr>
                <th style="width:30px;"><input type="checkbox" id="asraa-svs-check-all-leads"></th>
                <th>Date</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Location</th>
                <th>Config</th>
                <th>Sq.ft</th>
                <th>Rate Used</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <?php if ( $leads ) : ?>
                <?php foreach ( $leads as $l ) : ?>
                    <tr>
                        <td><input type="checkbox" name="lead_ids[]" value="<?php echo esc_attr( $l->id ); ?>"></td>
                        <td><?php echo esc_html( $l->created_at ); ?></td>
                        <td><?php echo esc_html( $l->name ); ?></td>
                        <td><?php echo esc_html( $l->email ); ?></td>
                        <td><?php echo esc_html( $l->phone ); ?></td>
                        <td><?php echo esc_html( trim( $l->area . ', ' . $l->city, ', ' ) ); ?></td>
                        <td><?php echo esc_html( $l->config ); ?></td>
                        <td><?php echo esc_html( $l->sqft ); ?></td>
                        <td><?php echo esc_html( $l->rate_used ); ?></td>
                        <td><?php echo esc_html( $l->total_price ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="10">No leads yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <p><?php submit_button( 'Delete Selected', 'delete', 'asraa_svs_delete_leads', false ); ?></p>
    </form>

    <script>
        document.getElementById('asraa-svs-check-all-leads')?.addEventListener('change', function(e){
            document.querySelectorAll('input[name="lead_ids[]"]').forEach(function(cb){
                cb.checked = e.target.checked;
            });
        });
    </script>
</div>
