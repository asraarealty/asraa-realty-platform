<?php
if (!defined('ABSPATH')) exit;

class Asraa_Notification_Service {

    /**
     * Send a notification email to an agent.
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject.
     * @param string $message Email body (HTML supported).
     * @return bool
     */
    public function send_agent_notification( $to, $subject, $message ) {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Notify agent when a new lead is assigned to them.
     *
     * @param array $lead      Lead data.
     * @param int   $agent_id  WordPress user ID.
     * @return bool
     */
    public function notify_lead_assigned( array $lead, $agent_id ) {
        $agent = get_userdata($agent_id);
        if (!$agent || empty($agent->user_email)) return false;

        $subject = 'New Lead Assigned: ' . $lead['name'];
        $message = sprintf(
            '<p>A new lead has been assigned to you.</p>
             <p><strong>Name:</strong> %s<br>
             <strong>Phone:</strong> %s<br>
             <strong>Email:</strong> %s</p>
             <p><a href="%s">View Lead</a></p>',
            esc_html($lead['name']),
            esc_html($lead['phone'] ?? ''),
            esc_html($lead['email'] ?? ''),
            admin_url('admin.php?page=asraa-crm-lead-view&lead_id=' . (int) $lead['id'])
        );

        return $this->send_agent_notification($agent->user_email, $subject, $message);
    }
}
