<?php
if (!defined('ABSPATH')) exit;

/**
 * Messaging Service
 *
 * Handles WhatsApp URL generation, email sending,
 * variable replacement and activity logging.
 */
class Asraa_Messaging_Service {

    private $message_log_repo;
    private $note_repo;

    public function __construct() {
        $this->message_log_repo = new Asraa_CRM_Message_Log_Repository();
        $this->note_repo        = new Asraa_CRM_Note_Repository();
    }

    /* ================================================================
       VARIABLE REPLACEMENT
    ================================================================ */

    /**
     * Replace template variables with lead data.
     *
     * @param string $text  Template text with {variable} placeholders.
     * @param array  $lead  Lead row as associative array.
     * @return string
     */
    public function replace_variables( $text, array $lead ) {
        $user = get_userdata($lead['assigned_to'] ?? 0);

        $replacements = [
            '{name}'                => $lead['name'] ?? '',
            '{email}'               => $lead['email'] ?? '',
            '{phone}'               => $lead['phone'] ?? '',
            '{budget}'              => $this->format_budget($lead),
            '{budget_min}'          => $lead['budget_min'] ?? '',
            '{budget_max}'          => $lead['budget_max'] ?? '',
            '{property_type}'       => $lead['property_type'] ?? '',
            '{preferred_locations}' => $lead['preferred_locations'] ?? '',
            '{timeline}'            => $lead['timeline'] ?? '',
            '{status}'              => ucfirst($lead['status'] ?? 'new'),
            '{agent_name}'          => $user ? $user->display_name : '',
            '{agent_email}'         => $user ? $user->user_email   : '',
            '{agent_phone}'         => $user ? get_user_meta($user->ID, 'phone', true) : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Format budget as a readable string.
     *
     * @param array $lead
     * @return string
     */
    private function format_budget( array $lead ) {
        $min = !empty($lead['budget_min']) ? '₹' . number_format((float) $lead['budget_min']) : '';
        $max = !empty($lead['budget_max']) ? '₹' . number_format((float) $lead['budget_max']) : '';

        if ($min && $max) return "$min – $max";
        return $min ?: $max ?: '';
    }

    /* ================================================================
       WHATSAPP
    ================================================================ */

    /**
     * Build a WhatsApp Web URL for a lead.
     *
     * @param array  $lead    Lead row.
     * @param string $message Message text (variables already replaced).
     * @return string|false WhatsApp URL or false if no valid phone number.
     */
    public function build_whatsapp_url( array $lead, $message ) {
        $phone = preg_replace('/\D/', '', $lead['phone'] ?? '');
        if (empty($phone)) return false;

        // Ensure Indian 10-digit numbers have the +91 country code prepended.
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    /**
     * Log a sent WhatsApp message and add a note on the lead.
     *
     * @param int    $lead_id    Lead ID.
     * @param string $message    Sent message text.
     * @param int    $template_id Template ID (0 for custom).
     * @return void
     */
    public function log_whatsapp( $lead_id, $message, $template_id = 0 ) {
        $user_id = get_current_user_id();

        $this->message_log_repo->log([
            'lead_id'      => (int) $lead_id,
            'message_type' => 'whatsapp',
            'template_id'  => (int) $template_id,
            'recipient'    => '',
            'subject'      => '',
            'content'      => $message,
            'sent_by'      => $user_id,
            'status'       => 'sent',
        ]);

        $this->note_repo->create([
            'lead_id'    => (int) $lead_id,
            'note'       => '[WhatsApp] ' . wp_trim_words($message, 20),
            'user_id'    => $user_id,
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Log a WhatsApp message for a bulk campaign.
     *
     * @param int    $lead_id     Lead ID.
     * @param string $message     Sent message text.
     * @param int    $template_id Template ID.
     * @param int    $campaign_id Bulk campaign ID.
     * @return void
     */
    public function log_bulk_whatsapp( $lead_id, $message, $template_id, $campaign_id ) {
        $user_id = get_current_user_id();

        $this->message_log_repo->log([
            'lead_id'      => (int) $lead_id,
            'campaign_id'  => (int) $campaign_id,
            'message_type' => 'whatsapp',
            'template_id'  => (int) $template_id,
            'recipient'    => '',
            'subject'      => '',
            'content'      => $message,
            'sent_by'      => $user_id,
            'status'       => 'sent',
        ]);

        $this->note_repo->create([
            'lead_id'    => (int) $lead_id,
            'note'       => '[WhatsApp Bulk] ' . wp_trim_words($message, 20),
            'user_id'    => $user_id,
            'created_at' => current_time('mysql'),
        ]);
    }

    /* ================================================================
       EMAIL
    ================================================================ */

    /**
     * Send an email to a lead.
     *
     * @param array  $lead       Lead row.
     * @param string $subject    Email subject (variables already replaced).
     * @param string $body       Email body (HTML allowed).
     * @param int    $template_id Template ID (0 for custom).
     * @return bool|WP_Error True on success, error description on failure.
     */
    public function send_email( array $lead, $subject, $body, $template_id = 0 ) {
        $to = sanitize_email($lead['email'] ?? '');
        if (!is_email($to)) {
            return new WP_Error('invalid_email', 'Invalid or missing email address.');
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent    = wp_mail($to, $subject, $body, $headers);

        $status = $sent ? 'sent' : 'failed';

        $this->message_log_repo->log([
            'lead_id'      => (int) $lead['id'],
            'message_type' => 'email',
            'template_id'  => (int) $template_id,
            'recipient'    => $to,
            'subject'      => $subject,
            'content'      => $body,
            'sent_by'      => get_current_user_id(),
            'status'       => $status,
        ]);

        $this->note_repo->create([
            'lead_id'    => (int) $lead['id'],
            'note'       => '[Email] Subject: ' . $subject,
            'user_id'    => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);

        return $sent;
    }

    /**
     * Send a bulk email campaign to multiple leads.
     *
     * @param array  $leads       Array of lead rows.
     * @param string $subject_tpl Subject template.
     * @param string $body_tpl    Body template.
     * @param int    $template_id Template ID.
     * @param int    $campaign_id Bulk campaign ID.
     * @return array { sent: int, failed: int, skipped: int }
     */
    public function send_bulk_email( array $leads, $subject_tpl, $body_tpl, $template_id, $campaign_id ) {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        // Deduplicate by email to avoid multiple sends to the same address.
        $seen_emails = [];

        foreach ($leads as $lead) {
            $to = sanitize_email($lead['email'] ?? '');

            if (!is_email($to)) {
                $result['skipped']++;
                continue;
            }

            if (in_array($to, $seen_emails, true)) {
                $result['skipped']++;
                continue;
            }

            $seen_emails[] = $to;

            $subject = $this->replace_variables($subject_tpl, $lead);
            $body    = $this->replace_variables($body_tpl, $lead);
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent    = wp_mail($to, $subject, $body, $headers);

            $status = $sent ? 'sent' : 'failed';

            $this->message_log_repo->log([
                'lead_id'      => (int) $lead['id'],
                'campaign_id'  => (int) $campaign_id,
                'message_type' => 'email',
                'template_id'  => (int) $template_id,
                'recipient'    => $to,
                'subject'      => $subject,
                'content'      => $body,
                'sent_by'      => get_current_user_id(),
                'status'       => $status,
            ]);

            $this->note_repo->create([
                'lead_id'    => (int) $lead['id'],
                'note'       => '[Email Bulk] Subject: ' . $subject,
                'user_id'    => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ]);

            if ($sent) {
                $result['sent']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }
}
