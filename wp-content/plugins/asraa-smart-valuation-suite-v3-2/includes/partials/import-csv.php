<?php
if ( ! current_user_can( 'manage_options' ) ) {
    return;
}
global $wpdb;
$rates_table = $wpdb->prefix . 'asraa_svs_rates';

if ( isset( $_POST['asraa_import_csv'] ) && check_admin_referer( 'asraa_import_csv', 'asraa_import_csv_nonce' ) ) {
    if ( ! empty( $_FILES['csv_file']['tmp_name'] ) ) {
        $file = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
        $row  = 0;
        while ( ( $data = fgetcsv( $file ) ) !== false ) {
            $row++;
            if ( 1 === $row ) {
                continue; // header
            }
            $city     = strtolower( trim( $data[0] ?? '' ) );
            $area     = strtolower( trim( $data[1] ?? '' ) );
            $building = strtolower( trim( $data[2] ?? '' ) );
            $config   = strtoupper( trim( $data[3] ?? '' ) );
            $rate     = floatval( $data[4] ?? 0 );

            if ( $city && $rate > 0 ) {
                $wpdb->insert(
                    $rates_table,
                    array(
                        'city'      => $city,
                        'area'      => $area,
                        'building'  => $building,
                        'config'    => $config,
                        'base_rate' => $rate,
                    ),
                    array( '%s', '%s', '%s', '%s', '%f' )
                );
            }
        }
        fclose( $file );
        echo '<div class="updated"><p>CSV imported successfully.</p></div>';
    } else {
        echo '<div class="error"><p>Please choose a CSV file.</p></div>';
    }
}
?>
<div class="wrap">
    <h1>Import Rates via CSV</h1>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'asraa_import_csv', 'asraa_import_csv_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th>CSV File</th>
                <td><input type="file" name="csv_file" accept=".csv" required></td>
            </tr>
        </table>
        <?php submit_button( 'Import CSV', 'primary', 'asraa_import_csv' ); ?>
    </form>

    <h2>CSV Format</h2>
    <p>The first row must be headers in this order:</p>
    <pre>City,Area,Building,Config,Base Rate</pre>
</div>
