<?php
// Metabox UI skeleton
<?php
if (!defined('ABSPATH')) exit;

function asraa_property_offer_fields($post) {
    $developer_name   = get_post_meta($post->ID, '_developer_name', true);
    $monthly_scheme   = get_post_meta($post->ID, '_monthly_scheme', true);
    $discount_offer   = get_post_meta($post->ID, '_discount_offer', true);
    $inventory_status = get_post_meta($post->ID, '_inventory_status', true);
    $offer_popup_text = get_post_meta($post->ID, '_offer_popup_text', true);

    wp_nonce_field('asraa_save_offer_fields', 'asraa_offer_nonce');
    ?>

    <div class="asraa-meta-wrapper">

        <h3>Developer Details</h3>
        <p>
            <label><strong>Developer Name</strong></label>
            <input type="text"
                   name="developer_name"
                   value="<?php echo esc_attr($developer_name); ?>"
                   style="width:100%;" />
        </p>

        <h3>Monthly Scheme</h3>
        <p>
            <label><strong>Monthly Payment Plan</strong></label>
            <textarea name="monthly_scheme"
                      rows="4"
                      style="width:100%;"><?php echo esc_textarea($monthly_scheme); ?></textarea>
        </p>

        <h3>Discount Offer</h3>
        <p>
            <label><strong>Current Discount</strong></label>
            <input type="text"
                   name="discount_offer"
                   value="<?php echo esc_attr($discount_offer); ?>"
                   style="width:100%;" />
        </p>

        <h3>Inventory Status</h3>
        <p>
            <label><strong>Inventory Available</strong></label>
            <textarea name="inventory_status"
                      rows="4"
                      style="width:100%;"><?php echo esc_textarea($inventory_status); ?></textarea>
        </p>

        <h3>Popup Offer CTA</h3>
        <p>
            <label><strong>Popup Message</strong></label>
            <textarea name="offer_popup_text"
                      rows="4"
                      style="width:100%;"><?php echo esc_textarea($offer_popup_text); ?></textarea>
        </p>

    </div>

    <?php
}