<?php
if (!defined('ABSPATH')) exit;

class Asraa_Deal_Service {

    private $deal_repo;
    private $commission_repo;

    public function __construct() {
        $this->deal_repo       = new Asraa_CRM_Deal_Repository();
        $this->commission_repo = new Asraa_CRM_Commission_Repository();
    }

    public function create_deal( array $data ) {
        $deal_id = $this->deal_repo->create($data);
        if ($deal_id) {
            $this->deal_repo->add_activity([
                'deal_id'     => $deal_id,
                'user_id'     => get_current_user_id(),
                'action'      => 'created',
                'description' => 'Deal created',
            ]);
            do_action('asraa_crm_deal_created', $deal_id, $data);
        }
        return $deal_id;
    }

    public function update_deal( $id, array $data ) {
        $old = $this->deal_repo->get_by_id($id);
        $result = $this->deal_repo->update($id, $data);

        if ($result !== false && $old && isset($data['stage']) && $old['stage'] !== $data['stage']) {
            $this->deal_repo->add_activity([
                'deal_id'     => $id,
                'user_id'     => get_current_user_id(),
                'action'      => 'stage_changed',
                'description' => sprintf('Stage changed from %s to %s', $old['stage'], $data['stage']),
            ]);
            do_action('asraa_crm_deal_stage_changed', $id, $old['stage'], $data['stage']);

            // Auto-create commission on closed_won; fetch fresh record so all
            // fields (including any deal_value update in the same request) are current.
            if ($data['stage'] === 'closed_won') {
                $fresh = $this->deal_repo->get_by_id($id);
                if ($fresh && !empty($fresh['deal_value'])) {
                    $this->maybe_create_commission($id, $fresh);
                }
            }
        }
        return $result;
    }

    private function maybe_create_commission( $deal_id, array $deal ) {
        global $wpdb;
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}asraa_crm_commissions WHERE deal_id = %d",
                (int) $deal_id
            )
        );
        if ($existing) return;

        $plan_id  = $deal['commission_plan_id'] ?? null;
        $rate     = 2.0; // default 2%
        if ($plan_id) {
            $plan = $this->commission_repo->get_plan($plan_id);
            if ($plan) $rate = (float) $plan['rate'];
        }

        $amount = round((float) $deal['deal_value'] * $rate / 100, 2);
        $this->commission_repo->create([
            'deal_id'           => $deal_id,
            'agent_id'          => $deal['agent_id'],
            'plan_id'           => $plan_id,
            'commission_amount' => $amount,
            'commission_rate'   => $rate,
            'deal_value'        => $deal['deal_value'],
            'status'            => 'pending',
        ]);
    }

    public function delete_deal( $id ) {
        return $this->deal_repo->delete($id);
    }

    public function get_all( $args = [] ) {
        return $this->deal_repo->get_all($args);
    }

    public function get_by_id( $id ) {
        return $this->deal_repo->get_by_id($id);
    }

    public function get_pipeline_summary() {
        return $this->deal_repo->get_pipeline_summary();
    }
}
