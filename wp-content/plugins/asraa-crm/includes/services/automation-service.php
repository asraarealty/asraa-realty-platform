<?php
if (!defined('ABSPATH')) exit;

class Asraa_Automation_Service {

    private $repo;
    private $messaging;

    public function __construct() {
        $this->repo      = new Asraa_CRM_Automation_Repository();
        $this->messaging = new Asraa_Messaging_Service();
    }

    /**
     * Fire automation rules by trigger event
     */
    public function fire($trigger_event, array $context = []) {
        $rules = $this->repo->get_by_trigger($trigger_event);

        if (empty($rules)) {
            return;
        }

        foreach ($rules as $rule) {
            $this->execute_rule($rule, $context);
        }
    }

    /**
     * Execute single rule
     */
    private function execute_rule(array $rule, array $context) {

        $conditions = json_decode($rule['conditions'] ?? '[]', true);
        $conditions = is_array($conditions) ? $conditions : [];

        if (!empty($conditions) && !$this->evaluate_conditions($conditions, $context)) {
            return;
        }

        $actions = json_decode($rule['actions'] ?? '[]', true);
        $actions = is_array($actions) ? $actions : [];

        if (empty($actions)) {
            return;
        }

        $status = 'success';
        $note   = '';

        foreach ($actions as $action) {
            try {
                $this->execute_action($action, $context);
            } catch (\Throwable $e) {
                $status = 'error';
                $note   = $e->getMessage();
            }
        }

        // FIXED: trigger -> trigger_event
        $this->repo->log_execution([
            'rule_id'       => (int) $rule['id'],
            'trigger_event' => $rule['trigger_event'] ?? '',
            'context'       => wp_json_encode($context),
            'status'        => $status,
            'notes'         => $note,
        ]);
    }

    /**
     * Evaluate rule conditions
     */
    private function evaluate_conditions(array $conditions, array $context) {
        foreach ($conditions as $cond) {

            $field    = $cond['field'] ?? '';
            $operator = $cond['operator'] ?? '=';
            $value    = $cond['value'] ?? '';
            $actual   = $context[$field] ?? '';

            switch ($operator) {
                case '=':
                    if ($actual !== $value) return false;
                    break;

                case '!=':
                    if ($actual === $value) return false;
                    break;

                case '>':
                    if ((float)$actual <= (float)$value) return false;
                    break;

                case '<':
                    if ((float)$actual >= (float)$value) return false;
                    break;

                case 'contains':
                    if (stripos((string)$actual, (string)$value) === false) return false;
                    break;
            }
        }

        return true;
    }

    /**
     * Execute action
     */
    private function execute_action(array $action, array $context) {

        $type = $action['type'] ?? '';

        switch ($type) {

            case 'send_email':
                $lead = $context['lead'] ?? [];

                if (!empty($lead['email'])) {
                    wp_mail(
                        $lead['email'],
                        $action['subject'] ?? 'Message from Asraa Realty',
                        wp_kses_post($action['body'] ?? '')
                    );
                }
                break;

            case 'send_whatsapp':
                do_action('asraa_crm_automation_whatsapp', $action, $context);
                break;

            case 'assign_agent':
                $lead_id = $context['lead_id'] ?? ($context['lead']['id'] ?? null);

                if ($lead_id && !empty($action['agent_id'])) {
                    global $wpdb;

                    $wpdb->update(
                        $wpdb->prefix . 'asraa_crm_leads',
                        [
                            'assigned_to' => (int) $action['agent_id']
                        ],
                        [
                            'id' => (int) $lead_id
                        ]
                    );
                }
                break;

            case 'change_stage':
                $lead_id = $context['lead_id'] ?? ($context['lead']['id'] ?? null);

                if ($lead_id && !empty($action['stage_id'])) {
                    global $wpdb;

                    $wpdb->update(
                        $wpdb->prefix . 'asraa_crm_leads',
                        [
                            'stage_id' => (int) $action['stage_id']
                        ],
                        [
                            'id' => (int) $lead_id
                        ]
                    );
                }
                break;

            case 'add_followup':
                $lead_id = $context['lead_id'] ?? ($context['lead']['id'] ?? null);

                if ($lead_id) {
                    global $wpdb;

                    $days = (int) ($action['days_from_now'] ?? 1);

                    $wpdb->insert(
                        $wpdb->prefix . 'asraa_crm_followups',
                        [
                            'lead_id'     => (int) $lead_id,
                            'agent_id'    => get_current_user_id(),
                            'follow_date' => date('Y-m-d', strtotime("+{$days} days")),
                            'note'        => sanitize_text_field($action['note'] ?? 'Auto follow-up'),
                            'is_done'     => 0,
                            'created_at'  => current_time('mysql'),
                        ]
                    );
                }
                break;

            default:
                do_action(
                    'asraa_crm_automation_action_' . $type,
                    $action,
                    $context
                );
                break;
        }
    }

    public function get_all_rules() {
        return $this->repo->get_all();
    }

    public function create_rule(array $data) {
        return $this->repo->create($data);
    }

    public function update_rule($id, array $data) {
        return $this->repo->update($id, $data);
    }

    public function delete_rule($id) {
        return $this->repo->delete($id);
    }

    public function get_logs($limit = 50) {
        return $this->repo->get_logs(null, $limit);
    }
}