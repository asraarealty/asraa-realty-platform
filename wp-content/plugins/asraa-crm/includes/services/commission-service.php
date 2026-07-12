<?php
if (!defined('ABSPATH')) exit;

class Asraa_Commission_Service {

    private $repo;

    public function __construct() {
        $this->repo = new Asraa_CRM_Commission_Repository();
    }

    /**
     * Calculate commission amount given deal value and plan.
     */
    public function calculate( $deal_value, $plan_id ) {
        $plan = $this->repo->get_plan($plan_id);
        if (!$plan) return 0;

        $rate = (float) $plan['rate'];
        if ($plan['type'] === 'flat') {
            return $rate;
        }
        return round($deal_value * $rate / 100, 2);
    }

    public function mark_paid( $commission_id ) {
        return $this->repo->update($commission_id, [
            'status'  => 'paid',
            'paid_at' => current_time('mysql'),
        ]);
    }

    public function get_agent_summary( $agent_id = null ) {
        return $this->repo->get_agent_summary($agent_id);
    }

    public function get_all( $args = [] ) {
        return $this->repo->get_all($args);
    }

    public function create( array $data ) {
        return $this->repo->create($data);
    }

    public function get_plans() {
        return $this->repo->get_all_plans();
    }

    public function create_plan( array $data ) {
        return $this->repo->create_plan($data);
    }

    public function update_plan( $id, array $data ) {
        return $this->repo->update_plan($id, $data);
    }

    public function delete_plan( $id ) {
        return $this->repo->delete_plan($id);
    }
}
