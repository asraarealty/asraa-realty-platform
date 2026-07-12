<?php
if (!defined('ABSPATH')) exit;

class Asraa_Automation_Controller {

    private $service;

    public function __construct() {
        $this->service = new Asraa_Automation_Service();
        add_action('admin_post_asraa_crm_save_automation',   [$this, 'handle_save']);
        add_action('admin_post_asraa_crm_toggle_automation', [$this, 'handle_toggle']);
        add_action('admin_post_asraa_crm_delete_automation', [$this, 'handle_delete']);

        // Wire automation triggers to WordPress actions
        add_action('asraa_crm_lead_created',      [$this, 'on_lead_created'], 10, 2);
        add_action('asraa_crm_deal_created',       [$this, 'on_deal_created'], 10, 2);
        add_action('asraa_crm_deal_stage_changed', [$this, 'on_deal_stage_changed'], 10, 3);
    }

    public function automation_page() {
        include ASRAA_CRM_PATH . 'admin/pages/automation.php';
    }

    public function on_lead_created( $lead_id, array $lead_data ) {
        $this->service->fire('lead_created', ['lead_id' => $lead_id, 'lead' => $lead_data]);
    }

    public function on_deal_created( $deal_id, array $deal_data ) {
        $this->service->fire('deal_created', ['deal_id' => $deal_id, 'deal' => $deal_data]);
    }

    public function on_deal_stage_changed( $deal_id, $old_stage, $new_stage ) {
        $this->service->fire('deal_stage_changed', [
            'deal_id'   => $deal_id,
            'old_stage' => $old_stage,
            'new_stage' => $new_stage,
        ]);
        if ($new_stage === 'closed_won') {
            $this->service->fire('deal_won', ['deal_id' => $deal_id]);
        }
    }

    public function handle_save() {
        check_admin_referer('asraa_crm_automation_nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $id = isset($_POST['rule_id']) ? (int) $_POST['rule_id'] : 0;

        $actions_raw    = $_POST['actions_json'] ?? '[]';
        $conditions_raw = $_POST['conditions_json'] ?? '[]';

        $decoded_actions    = json_decode(wp_unslash($actions_raw), true);
        $decoded_conditions = json_decode(wp_unslash($conditions_raw), true);

        $data = [
            'rule_name'     => sanitize_text_field($_POST['rule_name'] ?? ''),
            'trigger_event' => sanitize_text_field($_POST['trigger_event'] ?? ''),
            'conditions'    => wp_json_encode(is_array($decoded_conditions) ? $decoded_conditions : []),
            'actions'       => wp_json_encode(is_array($decoded_actions)    ? $decoded_actions    : []),
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id) {
            $this->service->update_rule($id, $data);
        } else {
            $this->service->create_rule($data);
        }

        wp_redirect(admin_url('admin.php?page=asraa-crm-automation&saved=1'));
        exit;
    }

    public function handle_toggle() {
        check_admin_referer('asraa_crm_toggle_automation');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $id   = isset($_POST['rule_id']) ? (int) $_POST['rule_id'] : 0;
        $repo = new Asraa_CRM_Automation_Repository();
        $rule = $repo->get_by_id($id);
        if ($rule) {
            $repo->update($id, ['is_active' => $rule['is_active'] ? 0 : 1]);
        }

        wp_redirect(admin_url('admin.php?page=asraa-crm-automation&updated=1'));
        exit;
    }

    public function handle_delete() {
        check_admin_referer('asraa_crm_delete_automation');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $id = isset($_GET['rule_id']) ? (int) $_GET['rule_id'] : 0;
        if ($id) $this->service->delete_rule($id);

        wp_redirect(admin_url('admin.php?page=asraa-crm-automation&deleted=1'));
        exit;
    }
}
