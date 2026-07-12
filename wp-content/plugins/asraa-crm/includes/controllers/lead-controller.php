<?php
if (!defined('ABSPATH')) exit;

class Asraa_Lead_Controller {

    public function leads_page() {
        include ASRAA_CRM_PATH . 'admin/pages/leads.php';
    }

    public function lead_view_page() {
        include ASRAA_CRM_PATH . 'admin/pages/lead-view.php';
    }
}
