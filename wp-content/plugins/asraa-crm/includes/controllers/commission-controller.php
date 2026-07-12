<?php
if (!defined('ABSPATH')) exit;

class Asraa_Commission_Controller {

    private $service;

    public function __construct() {
        $this->service = new Asraa_Commission_Service();
        add_action('admin_post_asraa_crm_mark_paid',              [$this, 'handle_mark_paid']);
        add_action('admin_post_asraa_crm_save_commission_plan',   [$this, 'handle_save_plan']);
        add_action('admin_post_asraa_crm_delete_commission_plan', [$this, 'handle_delete_plan']);
    }

    public function commissions_page() {
        include ASRAA_CRM_PATH . 'admin/pages/commissions.php';
    }

    public function handle_mark_paid() {
        check_admin_referer('asraa_crm_mark_paid');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $id = isset($_POST['commission_id']) ? (int) $_POST['commission_id'] : 0;
        if ($id) $this->service->mark_paid($id);

        wp_redirect(admin_url('admin.php?page=asraa-crm-commissions&updated=1'));
        exit;
    }

    public function handle_save_plan() {
        check_admin_referer('asraa_crm_commission_plan_nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $data = [
            'plan_name'   => sanitize_text_field($_POST['plan_name'] ?? ''),
            'type'        => sanitize_text_field($_POST['type'] ?? 'percentage'),
            'rate'        => (float) sanitize_text_field($_POST['rate'] ?? 0),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        ];

        $id = isset($_POST['plan_id']) ? (int) $_POST['plan_id'] : 0;
        if ($id) {
            $this->service->update_plan($id, $data);
        } else {
            $this->service->create_plan($data);
        }

        wp_redirect(admin_url('admin.php?page=asraa-crm-commissions&tab=plans&saved=1'));
        exit;
    }

    public function handle_delete_plan() {
        check_admin_referer('asraa_crm_delete_plan');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $id = isset($_GET['plan_id']) ? (int) $_GET['plan_id'] : 0;
        if ($id) $this->service->delete_plan($id);

        wp_redirect(admin_url('admin.php?page=asraa-crm-commissions&tab=plans&deleted=1'));
        exit;
    }
}
