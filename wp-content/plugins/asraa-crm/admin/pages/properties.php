<?php
if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| BULK DELETE
|--------------------------------------------------------------------------
*/
if (
    isset($_POST['delete_selected']) &&
    !empty($_POST['bulk_delete']) &&
    check_admin_referer('bulk_delete_properties')
) {
    global $wpdb;

    $table = $wpdb->prefix . 'asraa_crm_properties';

    foreach ($_POST['bulk_delete'] as $id) {
        $wpdb->delete($table, ['id' => intval($id)]);
    }

    echo '<div class="notice notice-success"><p>Selected properties deleted successfully.</p></div>';
}

/** @var array $properties */
$properties = isset($properties) && is_array($properties) ? $properties : [];
?>

<div class="wrap">

    <h1 class="wp-heading-inline">🏢 Properties</h1>
    <button id="asraa-add-property-btn" class="page-title-action">+ Add Property</button>
    <hr class="wp-header-end">

    <!-- FILTER -->
    <div style="margin:15px 0; display:flex; gap:10px;">
        <input type="text" id="filter-city" placeholder="Filter by city">
        <select id="filter-status">
            <option value="">All Status</option>
            <option value="available">Available</option>
            <option value="sold">Sold</option>
            <option value="hold">Hold</option>
        </select>
    </div>

    <form method="post">
        <?php wp_nonce_field('bulk_delete_properties'); ?>

        <p>
            <button type="submit"
                    name="delete_selected"
                    class="button button-secondary"
                    onclick="return confirm('Delete selected properties?');">
                Bulk Delete
            </button>
        </p>

        <table id="asraa-properties-table" class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th width="40">
                    <input type="checkbox" id="select-all-properties">
                </th>
                <th width="60">ID</th>
                <th>Image</th>
                <th>Title</th>
                <th>Transaction</th>
                <th>Type</th>
                <th>City</th>
                <th>Price</th>
                <th>Status</th>
                <th width="140">Actions</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($properties as $prop):

                $id          = is_array($prop) ? ($prop['id'] ?? 0) : ($prop->id ?? 0);
                $title       = is_array($prop) ? ($prop['title'] ?? '') : ($prop->title ?? '');
                $transaction = is_array($prop) ? ($prop['transaction_type'] ?? '') : ($prop->transaction_type ?? '');
                $type        = is_array($prop) ? ($prop['property_type'] ?? '') : ($prop->property_type ?? '');
                $builder     = is_array($prop) ? ($prop['builder_name'] ?? '') : ($prop->builder_name ?? '');
                $city        = is_array($prop) ? ($prop['city'] ?? '') : ($prop->city ?? '');
                $price       = is_array($prop) ? ($prop['price'] ?? 0) : ($prop->price ?? 0);
                $status      = is_array($prop) ? ($prop['status'] ?? '') : ($prop->status ?? '');
                $image       = is_array($prop) ? ($prop['image_url'] ?? '') : ($prop->image_url ?? '');
            ?>

            <tr
                data-id="<?php echo esc_attr($id); ?>"
                data-title="<?php echo esc_attr($title); ?>"
                data-transaction="<?php echo esc_attr($transaction); ?>"
                data-type="<?php echo esc_attr($type); ?>"
                data-builder="<?php echo esc_attr($builder); ?>"
                data-city="<?php echo esc_attr($city); ?>"
                data-price="<?php echo esc_attr($price); ?>"
                data-status="<?php echo esc_attr($status); ?>"
            >
                <td>
                    <input type="checkbox" name="bulk_delete[]" value="<?php echo esc_attr($id); ?>">
                </td>

                <td><?php echo esc_html($id); ?></td>

                <td>
                    <?php if (!empty($image)) : ?>
                        <img src="<?php echo esc_url($image); ?>" width="50">
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>

                <td class="col-title"><?php echo esc_html($title ?: '-'); ?></td>
                <td><?php echo esc_html($transaction ? ucfirst($transaction) : '-'); ?></td>
                <td><?php echo esc_html($type ?: '-'); ?></td>
                <td class="col-city"><?php echo esc_html($city ?: '-'); ?></td>
                <td>₹<?php echo esc_html(number_format((float)$price)); ?></td>
                <td class="col-status"><?php echo esc_html($status ? ucfirst($status) : '-'); ?></td>

                <td>
                    <button class="button button-small asraa-edit">✏️ Edit</button>
                    <button class="button button-small asraa-delete">🗑 Delete</button>
                </td>
            </tr>

            <?php endforeach; ?>
            </tbody>
        </table>
    </form>
</div>

<!-- MODAL -->
<div id="asraa-property-modal" style="display:none;">
    <div class="asraa-modal-overlay"></div>

    <div class="asraa-modal-box">
        <h2 id="asraa-modal-title">➕ Add Property</h2>

        <form id="asraa-property-form">

            <input type="hidden" name="action" value="asraa_save_property">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('asraa_crm_nonce')); ?>">
            <input type="hidden" name="id" id="prop-id">

            <div class="asraa-grid">
                <input type="text" name="title" placeholder="Property Title" required>

                <select name="transaction_type">
                    <option value="sale">Sale</option>
                    <option value="rent">Rent</option>
                </select>

                <input type="text" name="property_type" placeholder="Type">
                <input type="text" name="builder_name" placeholder="Builder">
                <input type="text" name="city" placeholder="City">
                <input type="number" name="price" placeholder="Price">

                <select name="status">
                    <option value="available">Available</option>
                    <option value="sold">Sold</option>
                    <option value="hold">Hold</option>
                </select>
            </div>

            <div style="margin-top:15px;">
                <button type="submit" class="button button-primary">💾 Save</button>
                <button type="button" id="asraa-close-modal" class="button">Cancel</button>
            </div>

            <div id="asraa-property-msg"></div>
        </form>
    </div>
</div>

<style>
.asraa-modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.55);
    z-index:9998;
}
.asraa-modal-box{
    position:fixed;
    top:50%;
    left:50%;
    transform:translate(-50%,-50%);
    background:#fff;
    padding:28px;
    width:560px;
    border-radius:10px;
    z-index:9999;
}
.asraa-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}
</style>

<script>
document.getElementById('select-all-properties').addEventListener('click', function() {
    let checkboxes = document.querySelectorAll('input[name="bulk_delete[]"]');
    checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>