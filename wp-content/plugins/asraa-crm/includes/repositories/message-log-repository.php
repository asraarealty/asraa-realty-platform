<?php
if (!defined('ABSPATH')) exit;

class Asraa_CRM_Message_Log_Repository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'asraa_crm_message_log';
    }

    /**
     * Log a sent message.
     *
     * @param array $data {
     *   lead_id, message_type, template_id, recipient, subject, content, sent_by, status
     * }
     * @return int|false Inserted ID or false on failure.
     */
    public function log( array $data ) {
        global $wpdb;
        $data['sent_at'] = current_time('mysql');
        $wpdb->insert($this->table, $data);
        return $wpdb->insert_id ?: false;
    }

    /**
     * Get message history for a specific lead.
     *
     * @param int $lead_id
     * @return array
     */
    public function get_for_lead( $lead_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ml.*, u.display_name AS sent_by_name
                 FROM {$this->table} ml
                 LEFT JOIN {$wpdb->users} u ON u.ID = ml.sent_by
                 WHERE ml.lead_id = %d
                 ORDER BY ml.sent_at DESC",
                (int) $lead_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get message log for a bulk campaign.
     *
     * @param int $campaign_id
     * @return array
     */
    public function get_for_campaign( $campaign_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ml.*, u.display_name AS sent_by_name
                 FROM {$this->table} ml
                 LEFT JOIN {$wpdb->users} u ON u.ID = ml.sent_by
                 WHERE ml.campaign_id = %d
                 ORDER BY ml.sent_at DESC",
                (int) $campaign_id
            ),
            ARRAY_A
        );
    }
}
