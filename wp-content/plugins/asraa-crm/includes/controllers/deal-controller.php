<?php
if (!defined('ABSPATH')) exit;

class Asraa_Deal_Controller {

    private $service;

    public function __construct() {
        $this->service = new Asraa_Deal_Service();
        add_action('admin_post_asraa_crm_save_deal',   [$this, 'handle_save']);
        add_action('admin_post_asraa_crm_delete_deal', [$this, 'handle_delete']);
    }

    public function deals_page() {
        include ASRAA_CRM_PATH . 'admin/pages/deals.php';
    }

    public function handle_save() {
        check_admin_referer('asraa_crm_deal_nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $id = isset($_POST['deal_id']) ? (int) $_POST['deal_id'] : 0;

        $data = [
            'lead_id'             => (int) sanitize_text_field($_POST['lead_id'] ?? 0),
            'property_id'         => (int) sanitize_text_field($_POST['property_id'] ?? 0),
            'agent_id'            => (int) sanitize_text_field($_POST['agent_id'] ?? get_current_user_id()),
            'title'               => sanitize_text_field($_POST['title'] ?? ''),
            'deal_value'          => (float) sanitize_text_field($_POST['deal_value'] ?? 0),
            'stage'               => sanitize_text_field($_POST['stage'] ?? 'prospect'),
            'expected_close_date' => sanitize_text_field($_POST['expected_close_date'] ?? ''),
            'commission_plan_id'  => !empty($_POST['commission_plan_id']) ? (int) $_POST['commission_plan_id'] : null,
            'notes'               => sanitize_textarea_field($_POST['notes'] ?? ''),
        ];

        if ($id) {
            $this->service->update_deal($id, $data);
        } else {
            $id = $this->service->create_deal($data);
        }

        wp_redirect(admin_url('admin.php?page=asraa-crm-deals&saved=1'));
        exit;
    }

    public function handle_delete() {
        check_admin_referer('asraa_crm_delete_deal');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $id = isset($_GET['deal_id']) ? (int) $_GET['deal_id'] : 0;
        if ($id) $this->service->delete_deal($id);

        wp_redirect(admin_url('admin.php?page=asraa-crm-deals&deleted=1'));
        exit;
    }
}
