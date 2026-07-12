<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$user_id  = get_current_user_id();
$is_admin = current_user_can('administrator');

$leads_table     = $wpdb->prefix . 'asraa_crm_leads';
$followups_table = $wpdb->prefix . 'asraa_crm_followups';

/* ============================================================
   COUNTS (SOFT DELETE SAFE)
============================================================ */

// TOTAL LEADS
$total_leads = $is_admin
    ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $leads_table WHERE is_deleted = 0")
    : (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $leads_table WHERE assigned_to = %d AND is_deleted = 0",
        $user_id
    ));

// NEW LEADS TODAY
$new_today = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM $leads_table 
     WHERE DATE(created_at) = CURDATE() AND is_deleted = 0"
);

// PENDING FOLLOW-UPS
$pending_followups = $is_admin
    ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $followups_table WHERE is_done = 0")
    : (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $followups_table WHERE agent_id = %d AND is_done = 0",
        $user_id
    ));

// TODAY FOLLOW-UPS
$today_followups = $is_admin
    ? (int) $wpdb->get_var("
        SELECT COUNT(*) FROM $followups_table
        WHERE follow_date = CURDATE() AND is_done = 0
    ")
    : (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $followups_table
         WHERE agent_id = %d AND follow_date = CURDATE() AND is_done = 0",
        $user_id
    ));

// CONVERTED LEADS
$converted = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM $leads_table 
     WHERE status = 'converted' AND is_deleted = 0"
);

// UNASSIGNED (ADMIN ONLY)
$unassigned = $is_admin
    ? (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $leads_table 
         WHERE (assigned_to IS NULL OR assigned_to = 0) AND is_deleted = 0"
    )
    : 0;

/* ============================================================
   FOLLOW-UP ALERT LISTS
============================================================ */

$overdue_followups = $is_admin
    ? $wpdb->get_results("
        SELECT f.follow_date, f.note, l.id AS lead_id, l.name AS lead_name
        FROM $followups_table f
        JOIN $leads_table l ON l.id = f.lead_id
        WHERE f.is_done = 0 
          AND f.follow_date < CURDATE()
          AND l.is_deleted = 0
        ORDER BY f.follow_date ASC
        LIMIT 5
    ")
    : $wpdb->get_results($wpdb->prepare("
        SELECT f.follow_date, f.note, l.id AS lead_id, l.name AS lead_name
        FROM $followups_table f
        JOIN $leads_table l ON l.id = f.lead_id
        WHERE f.is_done = 0
          AND f.follow_date < CURDATE()
          AND f.agent_id = %d
          AND l.is_deleted = 0
        ORDER BY f.follow_date ASC
        LIMIT 5
    ", $user_id));

$today_followups_list = $is_admin
    ? $wpdb->get_results("
        SELECT f.follow_date, f.note, l.id AS lead_id, l.name AS lead_name
        FROM $followups_table f
        JOIN $leads_table l ON l.id = f.lead_id
        WHERE f.is_done = 0 
          AND f.follow_date = CURDATE()
          AND l.is_deleted = 0
        ORDER BY f.follow_date ASC
        LIMIT 5
    ")
    : $wpdb->get_results($wpdb->prepare("
        SELECT f.follow_date, f.note, l.id AS lead_id, l.name AS lead_name
        FROM $followups_table f
        JOIN $leads_table l ON l.id = f.lead_id
        WHERE f.is_done = 0
          AND f.follow_date = CURDATE()
          AND f.agent_id = %d
          AND l.is_deleted = 0
        ORDER BY f.follow_date ASC
        LIMIT 5
    ", $user_id));
?>

<style>
.asraa-dashboard{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;margin-top:20px
}
.asraa-card{
    background:#fff;border-radius:10px;padding:22px;
    border-left:6px solid #2271b1;
    box-shadow:0 2px 6px rgba(0,0,0,.06);
    text-decoration:none;color:inherit
}
.asraa-card.green{border-color:#46b450}
.asraa-card.orange{border-color:#ffb900}
.asraa-card.red{border-color:#dc3232}
.asraa-card h2{margin:0;font-size:34px}
.asraa-card p{margin:6px 0 0;color:#555}
.asraa-reminder{background:#fff;padding:14px;margin-bottom:12px;border-left:5px solid}
.asraa-reminder.overdue{border-color:#dc3232}
.asraa-reminder.today{border-color:#ffb900}
</style>

<div class="wrap">
<h1>Asraa CRM Dashboard</h1>

<div class="asraa-dashboard">

<a class="asraa-card" href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-leads') ); ?>">
<h2><?php echo $total_leads; ?></h2>
<p><?php echo $is_admin ? 'Total Leads' : 'My Leads'; ?></p>
</a>

<div class="asraa-card green">
<h2><?php echo $new_today; ?></h2>
<p>New Leads Today</p>
</div>

<a class="asraa-card orange" href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-followups') ); ?>">
<h2><?php echo $pending_followups; ?></h2>
<p>Pending Follow-ups</p>
</a>

<a class="asraa-card orange" href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-followups') ); ?>">
<h2><?php echo $today_followups; ?></h2>
<p>Follow-ups Today</p>
</a>

<a class="asraa-card green" href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-leads&type=converted') ); ?>">
<h2><?php echo $converted; ?></h2>
<p>Converted Leads</p>
</a>

<?php if ($is_admin): ?>
<a class="asraa-card red" href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-leads&type=unassigned') ); ?>">
<h2><?php echo $unassigned; ?></h2>
<p>Unassigned Leads</p>
</a>
<?php endif; ?>

</div>

<hr>

<h2>🔔 Follow-up Alerts</h2>

<?php foreach ($overdue_followups as $f): ?>
<div class="asraa-reminder overdue">
<strong><?php echo esc_html($f->lead_name); ?></strong><br>
Due: <?php echo esc_html( wp_date( 'd M Y', strtotime( $f->follow_date ) ) ); ?><br>
<a class="button button-small"
href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-leads&lead_id=' . $f->lead_id) ); ?>">
Open Lead
</a>
</div>
<?php endforeach; ?>

<?php foreach ($today_followups_list as $f): ?>
<div class="asraa-reminder today">
<strong><?php echo esc_html($f->lead_name); ?></strong><br>
<a class="button button-small"
href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-leads&lead_id=' . $f->lead_id) ); ?>">
Open Lead
</a>
</div>
<?php endforeach; ?>

</div>
