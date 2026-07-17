<?php
if (!defined('ABSPATH')) exit;

/* ============================================================
   AJAX CONTROLLER
   Registers all wp_ajax_* handlers for the CRM plugin.
   Uses asraa_crm_verify_ajax_nonce() and asraa_crm_require_ajax_cap()
   from includes/helpers.php for consistent security checks.
============================================================ */

/* ------------------------------------------------------------
   LEAD UPDATE
------------------------------------------------------------ */
add_action('wp_ajax_asraa_update_lead', 'asraa_ajax_update_lead');

function asraa_ajax_update_lead() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_ajax_cap();

    global $wpdb;
    $table   = $wpdb->prefix . 'asraa_crm_leads';
    $lead_id = intval($_POST['lead_id'] ?? 0);
    if (!$lead_id) {
        wp_send_json_error(['message' => 'Invalid lead ID.']);
    }

    $existing = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $lead_id ),
        ARRAY_A
    );
    if ( ! $existing ) {
        wp_send_json_error(['message' => 'Lead not found.']);
    }

    $lead_stage = asraa_crm_sanitize_lead_stage($_POST['lead_stage'] ?? ($existing['lead_stage'] ?? 'new'));
    $assigned_agent = isset($_POST['assigned_agent']) ? (int) $_POST['assigned_agent'] : (int) ($existing['assigned_agent'] ?? 0);
    $budget = asraa_crm_parse_budget_value($_POST['budget'] ?? ($existing['budget'] ?? ''));

    $update_data = [
        'name'          => sanitize_text_field($_POST['name'] ?? $existing['name']),
        'email'         => sanitize_email($_POST['email'] ?? $existing['email']),
        'phone'         => sanitize_text_field($_POST['phone'] ?? $existing['phone']),
        'intent'        => sanitize_key($_POST['intent'] ?? ($existing['intent'] ?? '')),
        'location'      => sanitize_text_field($_POST['location'] ?? ($existing['location'] ?? '')),
        'budget'        => $budget > 0 ? $budget : null,
        'property_type' => sanitize_text_field($_POST['property_type'] ?? ($existing['property_type'] ?? '')),
        'assigned_agent'=> $assigned_agent ?: null,
        'assigned_to'   => $assigned_agent ?: null,
        'lead_stage'    => $lead_stage,
        'status'        => asraa_crm_get_status_for_stage($lead_stage),
    ];

    $update_data['lead_score'] = asraa_crm_calculate_ai_lead_score([
        'budget'        => $update_data['budget'],
        'property_type' => $update_data['property_type'],
        'location'      => $update_data['location'],
        'phone'         => $update_data['phone'],
    ]);
    $update_data['whatsapp_link'] = asraa_crm_build_agent_whatsapp_link($update_data, $assigned_agent);

    if (
        $lead_stage !== ($existing['lead_stage'] ?? 'new')
        || (int) ($existing['assigned_agent'] ?? 0) !== $assigned_agent
    ) {
        $update_data['last_activity'] = current_time('mysql');
    }

    $updated = $wpdb->update($table, $update_data, ['id' => $lead_id]);

    if ($updated === false) {
        error_log('Asraa CRM: asraa_update_lead DB update failed for lead_id=' . $lead_id . ' error=' . $wpdb->last_error);
        wp_send_json_error(['message' => 'Database update failed. Please try again.']);
    }

    asraa_crm_debug_log('Asraa CRM: lead updated id=' . $lead_id);
    wp_send_json_success(['message' => 'Lead updated successfully.']);
}


/* ------------------------------------------------------------
   NOTE DELETE
------------------------------------------------------------ */
add_action('wp_ajax_asraa_delete_note', 'asraa_ajax_delete_note');

function asraa_ajax_delete_note() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_ajax_cap();

    $note_id = intval($_POST['note_id'] ?? 0);
    if (!$note_id) {
        wp_send_json_error(['message' => 'Invalid note ID.']);
    }

    $note_repo = new Asraa_CRM_Note_Repository();
    $deleted = $note_repo->delete($note_id);

    if ($deleted === false) {
        error_log('Asraa CRM: asraa_delete_note DB delete failed for note_id=' . $note_id);
        wp_send_json_error(['message' => 'Database delete failed. Please try again.']);
    }

    asraa_crm_debug_log('Asraa CRM: note deleted id=' . $note_id);
    wp_send_json_success(['message' => 'Note deleted successfully.']);
}


/* ------------------------------------------------------------
   WEBSITE LEAD SUBMISSION
------------------------------------------------------------ */
add_action('wp_ajax_nopriv_asraa_submit_website_lead','asraa_ajax_submit_website_lead');
add_action('wp_ajax_asraa_submit_website_lead','asraa_ajax_submit_website_lead');

function asraa_ajax_submit_website_lead() {
    global $wpdb;

    if (!empty($_POST['website'])) {
        wp_send_json_error(['message'=>'Spam detected.']);
    }

    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $country = sanitize_text_field($_POST['country_code'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $requirement = sanitize_text_field($_POST['requirement'] ?? '');

    if (empty($name) || empty($phone)) {
        wp_send_json_error(['message'=>'Name and phone are required.']);
    }

    $table = $wpdb->prefix . 'asraa_crm_leads';

    $wpdb->insert($table,[
        'name'=>$name,
        'email'=>$email,
        'phone'=>$country.$phone,
        'intent'=>$requirement,
        'lead_stage'=>'new',
        'status'=>'new',
        'created_at'=>current_time('mysql')
    ]);

    $subject = 'New Website Property Enquiry';
    $body = "Name: {$name}\nEmail: {$email}\nPhone: {$country}{$phone}\nRequirement: {$requirement}";
    wp_mail('contact@asraarealty.com',$subject,$body);

    wp_send_json_success(['message'=>'Lead submitted successfully.']);
}


/* ------------------------------------------------------------
   MESSAGE TEMPLATES
------------------------------------------------------------ */
add_action('wp_ajax_get_message_templates', 'asraa_ajax_get_message_templates');

function asraa_ajax_get_message_templates() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_ajax_cap();

    $type = sanitize_text_field($_POST['type'] ?? 'email');

    if ($type === 'whatsapp') {
        $repo      = new Asraa_Whatsapp_Template_Repository();
        $templates = $repo->get_all(true);
        wp_send_json_success($templates);
    }

    $repo      = new Asraa_Email_Template_Repository();
    $templates = $repo->get_all(true);
    wp_send_json_success($templates);
}

/* ------------------------------------------------------------
   MESSAGE HISTORY
------------------------------------------------------------ */
add_action('wp_ajax_get_message_history', 'asraa_ajax_get_message_history');

function asraa_ajax_get_message_history() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_ajax_cap();

    $lead_id = intval($_POST['lead_id'] ?? 0);
    if (!$lead_id) wp_send_json_error(['message' => 'Invalid lead ID.']);

    $repo    = new Asraa_CRM_Message_Log_Repository();
    $history = $repo->get_for_lead($lead_id);
    wp_send_json_success($history);
}

/* ------------------------------------------------------------
   SINGLE WHATSAPP – log + return the WA URL
------------------------------------------------------------ */
add_action('wp_ajax_send_single_whatsapp', 'asraa_ajax_send_single_whatsapp');

function asraa_ajax_send_single_whatsapp() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_ajax_cap();

    $lead_id     = intval($_POST['lead_id'] ?? 0);
    $message_tpl = sanitize_textarea_field($_POST['message'] ?? '');
    $template_id = intval($_POST['template_id'] ?? 0);
    $image_url   = esc_url_raw($_POST['image_url'] ?? '');

    if (!$lead_id)     wp_send_json_error(['message' => 'Invalid lead ID.']);
    if (!$message_tpl) wp_send_json_error(['message' => 'Message is required.']);

    $lead_repo = new Asraa_CRM_Lead_Repository();
    $lead      = $lead_repo->get_by_id($lead_id);
    if (!$lead) wp_send_json_error(['message' => 'Lead not found.']);

    $phone = preg_replace('/\D/', '', $lead['phone'] ?? '');
    if (empty($phone)) wp_send_json_error(['message' => 'Lead has no valid phone number.']);

    $service = new Asraa_Messaging_Service();
    $message = $service->replace_variables($message_tpl, $lead);

    // Append image URL as a line in the message if provided
    if ($image_url) {
        $message = $image_url . "\n\n" . $message;
    }

    $wa_url  = $service->build_whatsapp_url($lead, $message);

    if (!$wa_url) wp_send_json_error(['message' => 'Could not build WhatsApp URL.']);

    $service->log_whatsapp($lead_id, $message, $template_id);

    asraa_crm_debug_log('Asraa CRM: WhatsApp sent to lead_id=' . $lead_id);
    wp_send_json_success(['url' => $wa_url, 'message' => $message]);
}

/* ------------------------------------------------------------
   BULK WHATSAPP – log + return per-lead WA URLs
------------------------------------------------------------ */
add_action('wp_ajax_send_bulk_whatsapp', 'asraa_ajax_send_bulk_whatsapp');

function asraa_ajax_send_bulk_whatsapp() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_ajax_cap();

    $lead_ids    = array_map('intval', (array) ($_POST['lead_ids'] ?? []));
    $message_tpl = sanitize_textarea_field($_POST['message'] ?? '');
    $template_id = intval($_POST['template_id'] ?? 0);
    $image_url   = esc_url_raw($_POST['image_url'] ?? '');

    if (empty($lead_ids))  wp_send_json_error(['message' => 'No leads selected.']);
    if (!$message_tpl)     wp_send_json_error(['message' => 'Message is required.']);

    // Rate limiting: max 100 recipients per bulk action
    if (count($lead_ids) > 100) wp_send_json_error(['message' => 'Maximum 100 recipients per bulk send.']);

    $lead_repo    = new Asraa_CRM_Lead_Repository();
    $campaign_repo = new Asraa_CRM_Bulk_Campaign_Repository();
    $service      = new Asraa_Messaging_Service();

    $leads = $lead_repo->get_by_ids($lead_ids);
    if (empty($leads)) wp_send_json_error(['message' => 'No valid leads found.']);

    // Create campaign record
    $campaign_id = $campaign_repo->create([
        'campaign_name' => 'WhatsApp Bulk – ' . current_time('mysql'),
        'message_type'  => 'whatsapp',
        'template_id'   => $template_id,
        'status'        => 'in_progress',
        'leads_count'   => count($leads),
        'sent_count'    => 0,
        'created_by'    => get_current_user_id(),
    ]);

    $results = [];
    $sent    = 0;
    $skipped = 0;

    foreach ($leads as $lead) {
        $phone = preg_replace('/\D/', '', $lead['phone'] ?? '');
        if (empty($phone)) {
            $skipped++;
            $results[] = [
                'lead_id'   => $lead['id'],
                'lead_name' => $lead['name'],
                'status'    => 'skipped',
                'reason'    => 'No phone number',
            ];
            continue;
        }

        $message = $service->replace_variables($message_tpl, $lead);
        if ($image_url) {
            $message = $image_url . "\n\n" . $message;
        }
        $wa_url  = $service->build_whatsapp_url($lead, $message);

        $service->log_bulk_whatsapp($lead['id'], $message, $template_id, $campaign_id);

        $sent++;
        $results[] = [
            'lead_id'   => $lead['id'],
            'lead_name' => $lead['name'],
            'url'       => $wa_url,
            'message'   => $message,
            'status'    => 'sent',
        ];
    }

    // Update campaign with final counts
    $campaign_repo->update($campaign_id, [
        'status'     => 'completed',
        'sent_count' => $sent,
    ]);

    asraa_crm_debug_log('Asraa CRM: bulk WhatsApp campaign_id=' . $campaign_id . ' sent=' . $sent . ' skipped=' . $skipped);
    asraa_crm_fire_trigger('campaign_completed', ['id' => $campaign_id, 'type' => 'whatsapp', 'sent' => $sent]);

    wp_send_json_success([
        'campaign_id' => $campaign_id,
        'sent'        => $sent,
        'skipped'     => $skipped,
        'results'     => $results,
    ]);
}

/* ------------------------------------------------------------
   SINGLE EMAIL
------------------------------------------------------------ */
add_action('wp_ajax_send_single_email', 'asraa_ajax_send_single_email');

function asraa_ajax_send_single_email() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_ajax_cap();

    $lead_id     = intval($_POST['lead_id'] ?? 0);
    $subject_tpl = sanitize_text_field($_POST['subject'] ?? '');
    $body_tpl    = wp_kses_post($_POST['body'] ?? '');
    $template_id = intval($_POST['template_id'] ?? 0);

    if (!$lead_id)     wp_send_json_error(['message' => 'Invalid lead ID.']);
    if (!$subject_tpl) wp_send_json_error(['message' => 'Subject is required.']);
    if (!$body_tpl)    wp_send_json_error(['message' => 'Message body is required.']);

    $lead_repo = new Asraa_CRM_Lead_Repository();
    $lead      = $lead_repo->get_by_id($lead_id);
    if (!$lead) wp_send_json_error(['message' => 'Lead not found.']);

    $to = sanitize_email($lead['email'] ?? '');
    if (!is_email($to)) wp_send_json_error(['message' => 'Lead has no valid email address.']);

    $service = new Asraa_Messaging_Service();
    $subject = $service->replace_variables($subject_tpl, $lead);
    $body    = $service->replace_variables($body_tpl, $lead);
    $result  = $service->send_email($lead, $subject, $body, $template_id);

    if (is_wp_error($result)) {
        error_log('Asraa CRM: send_single_email WP_Error lead_id=' . $lead_id . ' – ' . $result->get_error_message());
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    if (!$result) {
        error_log('Asraa CRM: send_single_email failed lead_id=' . $lead_id);
        wp_send_json_error(['message' => 'Email sending failed. Please check your server mail configuration.']);
    }

    asraa_crm_debug_log('Asraa CRM: email sent to lead_id=' . $lead_id);
    wp_send_json_success(['message' => 'Email sent successfully.']);
}

/* ------------------------------------------------------------
   BULK EMAIL
------------------------------------------------------------ */
add_action('wp_ajax_send_bulk_email', 'asraa_ajax_send_bulk_email');

function asraa_ajax_send_bulk_email() {
    asraa_crm_verify_ajax_nonce();
    asraa_crm_require_ajax_cap();

    $lead_ids    = array_map('intval', (array) ($_POST['lead_ids'] ?? []));
    $subject_tpl = sanitize_text_field($_POST['subject'] ?? '');
    $body_tpl    = wp_kses_post($_POST['body'] ?? '');
    $template_id = intval($_POST['template_id'] ?? 0);

    if (empty($lead_ids))  wp_send_json_error(['message' => 'No leads selected.']);
    if (!$subject_tpl)     wp_send_json_error(['message' => 'Subject is required.']);
    if (!$body_tpl)        wp_send_json_error(['message' => 'Message body is required.']);

    // Rate limiting: max 200 recipients per bulk action
    if (count($lead_ids) > 200) wp_send_json_error(['message' => 'Maximum 200 recipients per bulk send.']);

    $lead_repo     = new Asraa_CRM_Lead_Repository();
    $campaign_repo = new Asraa_CRM_Bulk_Campaign_Repository();
    $service       = new Asraa_Messaging_Service();

    $leads = $lead_repo->get_by_ids($lead_ids);
    if (empty($leads)) wp_send_json_error(['message' => 'No valid leads found.']);

    // Create campaign record
    $campaign_id = $campaign_repo->create([
        'campaign_name' => 'Email Bulk – ' . current_time('mysql'),
        'message_type'  => 'email',
        'template_id'   => $template_id,
        'status'        => 'in_progress',
        'leads_count'   => count($leads),
        'sent_count'    => 0,
        'created_by'    => get_current_user_id(),
    ]);

    $result = $service->send_bulk_email($leads, $subject_tpl, $body_tpl, $template_id, $campaign_id);

    // Update campaign with final counts
    $campaign_repo->update($campaign_id, [
        'status'     => 'completed',
        'sent_count' => $result['sent'],
    ]);

    asraa_crm_debug_log('Asraa CRM: bulk email campaign_id=' . $campaign_id . ' sent=' . $result['sent'] . ' failed=' . $result['failed'] . ' skipped=' . $result['skipped']);
    asraa_crm_fire_trigger('campaign_completed', ['id' => $campaign_id, 'type' => 'email', 'sent' => $result['sent']]);

    wp_send_json_success([
        'campaign_id' => $campaign_id,
        'sent'        => $result['sent'],
        'failed'      => $result['failed'],
        'skipped'     => $result['skipped'],
    ]);
}
