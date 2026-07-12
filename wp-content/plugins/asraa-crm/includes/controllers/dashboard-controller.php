<?php
if (!defined('ABSPATH')) exit;

function asraa_crm_dashboard() {
    include ASRAA_CRM_PATH . 'admin/pages/dashboard.php';
}

function asraa_crm_leads() {
    $page = (int) ($_GET['lead_id'] ?? 0);
    if ($page) {
        include ASRAA_CRM_PATH . 'admin/pages/lead-view.php';
    } else {
        include ASRAA_CRM_PATH . 'admin/pages/leads.php';
    }
}
