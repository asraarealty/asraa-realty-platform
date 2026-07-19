<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$user_id  = get_current_user_id();
$is_admin = current_user_can('administrator');

$leads_table     = $wpdb->prefix . 'asraa_crm_leads';
$followups_table = $wpdb->prefix . 'asraa_crm_followups';
$groups_table    = $wpdb->prefix . 'asraa_crm_groups';

/* ============================================================
   DATE RANGE FILTER
============================================================ */
$range = sanitize_text_field($_GET['range'] ?? 'today');
if (!in_array($range, ['today', 'week', 'month', 'all'], true)) {
    $range = 'today';
}

$range_labels = [
    'today' => 'Today',
    'week'  => 'This Week',
    'month' => 'This Month',
    'all'   => 'All Time',
];

if (!function_exists('asraa_crm_dashboard_range_where')) {
    function asraa_crm_dashboard_range_where($range, $column = 'created_at') {
        switch ($range) {
            case 'week':
                return "YEARWEEK({$column}, 1) = YEARWEEK(CURDATE(), 1)";
            case 'month':
                return "YEAR({$column}) = YEAR(CURDATE()) AND MONTH({$column}) = MONTH(CURDATE())";
            case 'all':
                return '1=1';
            case 'today':
            default:
                return "DATE({$column}) = CURDATE()";
        }
    }
}

$range_where = asraa_crm_dashboard_range_where($range);

/* ============================================================
   COUNTS (SOFT DELETE SAFE)
============================================================ */

// TOTAL LEADS (all time, not range-filtered -- a running total is more
// useful here than a number that collapses to match "New Leads" whenever
// a period is selected)
$total_leads = $is_admin
    ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $leads_table WHERE is_deleted = 0")
    : (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $leads_table WHERE assigned_to = %d AND is_deleted = 0",
        $user_id
    ));

// NEW LEADS (respects range filter)
$new_in_range = $is_admin
    ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $leads_table WHERE is_deleted = 0 AND {$range_where}")
    : (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $leads_table WHERE assigned_to = %d AND is_deleted = 0 AND {$range_where}",
        $user_id
    ));

// PENDING FOLLOW-UPS (current-state urgency, not range-scoped)
$pending_followups = $is_admin
    ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $followups_table WHERE is_done = 0")
    : (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $followups_table WHERE agent_id = %d AND is_done = 0",
        $user_id
    ));

// TODAY FOLLOW-UPS (always "today" regardless of range -- it's a due-date, not a creation-date filter)
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

// CONVERTED LEADS (respects range filter)
$converted = $is_admin
    ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $leads_table WHERE status = 'converted' AND is_deleted = 0 AND {$range_where}")
    : (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $leads_table WHERE assigned_to = %d AND status = 'converted' AND is_deleted = 0 AND {$range_where}",
        $user_id
    ));

// CONVERSION RATE (converted / new leads, both scoped to the same range)
$conversion_rate = $new_in_range > 0 ? round(($converted / $new_in_range) * 100) : 0;

// SITE VISITS -- upcoming, scheduled within the current calendar week.
// The one existing row in this table has visit_date = 0000-00-00 (broken/
// placeholder data, not a real visit), so it's explicitly excluded.
$site_visits_table = $wpdb->prefix . 'asraa_crm_site_visits';
$site_visits_this_week = $is_admin
    ? (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $site_visits_table
         WHERE visit_date > '1970-01-01 00:00:01'
           AND visit_date >= NOW()
           AND YEARWEEK(visit_date, 1) = YEARWEEK(CURDATE(), 1)"
    )
    : (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $site_visits_table
         WHERE sales_agent = %d
           AND visit_date > '1970-01-01 00:00:01'
           AND visit_date >= NOW()
           AND YEARWEEK(visit_date, 1) = YEARWEEK(CURDATE(), 1)",
        $user_id
    ));

// UNASSIGNED (ADMIN ONLY, not range-scoped -- it's a current backlog, not a period metric)
$unassigned = $is_admin
    ? (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $leads_table
         WHERE (assigned_to IS NULL OR assigned_to = 0) AND is_deleted = 0"
    )
    : 0;

/* ============================================================
   PIPELINE / SOURCE / GROUPS / AGENT BREAKDOWNS (admin only --
   same visibility boundary as Unassigned above: company-wide
   portfolio views, not a single agent's own numbers)
============================================================ */
$stage_counts   = [];
$source_counts  = [];
$group_counts   = [];
$agent_stats    = [];

if ($is_admin) {
    // PIPELINE (fixed stage order, zero-filled so every stage renders even with no leads in it)
    $stage_counts = array_fill_keys(
        function_exists('asraa_crm_get_lead_stages') ? asraa_crm_get_lead_stages() : ['new', 'contacted', 'interested', 'visit_scheduled', 'closed'],
        0
    );
    $stage_rows = $wpdb->get_results(
        "SELECT lead_stage, COUNT(*) as cnt FROM $leads_table WHERE is_deleted = 0 AND {$range_where} GROUP BY lead_stage",
        ARRAY_A
    );
    foreach ($stage_rows as $row) {
        if (isset($stage_counts[$row['lead_stage']])) {
            $stage_counts[$row['lead_stage']] = (int) $row['cnt'];
        }
    }

    // SOURCE
    $source_rows = $wpdb->get_results(
        "SELECT source, COUNT(*) as cnt FROM $leads_table WHERE is_deleted = 0 AND {$range_where} GROUP BY source ORDER BY cnt DESC",
        ARRAY_A
    );
    foreach ($source_rows as $row) {
        $label = ('' !== trim((string) $row['source'])) ? $row['source'] : 'Not set';
        $source_counts[$label] = (int) $row['cnt'];
    }

    // GROUPS
    $group_range_where = asraa_crm_dashboard_range_where($range, 'l.created_at');
    $group_counts = $wpdb->get_results(
        "SELECT g.group_name, COUNT(l.id) as cnt
         FROM {$groups_table} g
         LEFT JOIN {$leads_table} l ON l.group_id = g.id AND l.is_deleted = 0 AND {$group_range_where}
         GROUP BY g.id
         ORDER BY g.group_name ASC",
        ARRAY_A
    );

    // BY AGENT
    $agent_rows = $wpdb->get_results(
        "SELECT assigned_to, COUNT(*) as total, SUM(status = 'converted') as converted
         FROM $leads_table
         WHERE is_deleted = 0 AND assigned_to IS NOT NULL AND assigned_to > 0 AND {$range_where}
         GROUP BY assigned_to
         ORDER BY total DESC",
        ARRAY_A
    );
    foreach ($agent_rows as $row) {
        $user = get_userdata((int) $row['assigned_to']);
        $agent_stats[] = [
            'name'      => $user ? $user->display_name : ('User #' . $row['assigned_to']),
            'total'     => (int) $row['total'],
            'converted' => (int) $row['converted'],
        ];
    }
}

$stage_max  = $stage_counts ? max(1, max($stage_counts)) : 1;
$source_max = $source_counts ? max(1, max($source_counts)) : 1;
$group_max  = 1;
foreach ($group_counts as $g) {
    $group_max = max($group_max, (int) $g['cnt']);
}

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

<div class="wrap">

<div class="asraa-quick-actions">
    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=asraa-crm-lead-add')); ?>">+ Add Lead</a>
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=asraa-crm-leads-import')); ?>">Import Leads</a>
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=asraa-crm-campaigns')); ?>">Bulk Message</a>

    <span class="asraa-range-filter">
        <?php foreach ($range_labels as $key => $label): ?>
        <a class="button button-small<?php echo $range === $key ? ' button-primary' : ''; ?>"
           href="<?php echo esc_url(add_query_arg('range', $key)); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </span>
</div>

<div class="asraa-dashboard-layout">
<div class="asraa-dashboard-main">

<div class="asraa-dashboard">

<a class="asraa-card" href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-leads') ); ?>">
<h2><?php echo $total_leads; ?></h2>
<p><?php echo $is_admin ? 'Total Leads' : 'My Leads'; ?></p>
</a>

<div class="asraa-card green">
<h2><?php echo $new_in_range; ?></h2>
<p>New Leads &mdash; <?php echo esc_html($range_labels[$range]); ?></p>
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
<h2><?php echo $converted; ?> <span class="asraa-card-rate"><?php echo $conversion_rate; ?>%</span></h2>
<p>Converted &mdash; <?php echo esc_html($range_labels[$range]); ?></p>
</a>

<a class="asraa-card orange" href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-site-visits') ); ?>">
<h2><?php echo $site_visits_this_week; ?></h2>
<p>Site Visits This Week</p>
</a>

<?php if ($is_admin): ?>
<a class="asraa-card red" href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-leads&type=unassigned') ); ?>">
<h2><?php echo $unassigned; ?></h2>
<p>Unassigned Leads</p>
</a>
<?php endif; ?>

</div>

<?php if ($is_admin): ?>

<div class="asraa-dashboard-section">
    <h2>Pipeline</h2>
    <div class="asraa-bar-list">
        <?php foreach ($stage_counts as $stage => $count): ?>
        <div class="asraa-bar-row">
            <span class="asraa-bar-label"><?php echo esc_html(ucwords(str_replace('_', ' ', $stage))); ?></span>
            <span class="asraa-bar-track">
                <span class="asraa-bar-fill" style="width:<?php echo esc_attr(round(($count / $stage_max) * 100)); ?>%;"></span>
            </span>
            <span class="asraa-bar-count"><?php echo (int) $count; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="asraa-dashboard-section">
    <h2>Source</h2>
    <?php if (empty($source_counts)): ?>
    <p class="asraa-dashboard-empty">No leads in this period.</p>
    <?php else: ?>
    <div class="asraa-bar-list">
        <?php foreach ($source_counts as $source => $count): ?>
        <div class="asraa-bar-row">
            <span class="asraa-bar-label"><?php echo esc_html($source); ?></span>
            <span class="asraa-bar-track">
                <span class="asraa-bar-fill orange" style="width:<?php echo esc_attr(round(($count / $source_max) * 100)); ?>%;"></span>
            </span>
            <span class="asraa-bar-count"><?php echo (int) $count; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="asraa-dashboard-section">
    <h2>Groups</h2>
    <?php if (empty($group_counts)): ?>
    <p class="asraa-dashboard-empty">No groups yet.</p>
    <?php else: ?>
    <div class="asraa-groups-snapshot">
        <?php foreach ($group_counts as $g): ?>
        <div class="asraa-groups-snapshot__item">
            <span class="asraa-groups-snapshot__count"><?php echo (int) $g['cnt']; ?></span>
            <span class="asraa-groups-snapshot__label"><?php echo esc_html($g['group_name']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="asraa-dashboard-section">
    <h2>By Agent</h2>
    <?php if (empty($agent_stats)): ?>
    <p class="asraa-dashboard-empty">No assigned leads in this period.</p>
    <?php else: ?>
    <table class="widefat striped">
        <thead>
            <tr><th>Agent</th><th>Leads Assigned</th><th>Converted</th></tr>
        </thead>
        <tbody>
            <?php foreach ($agent_stats as $a): ?>
            <tr>
                <td><?php echo esc_html($a['name']); ?></td>
                <td><?php echo (int) $a['total']; ?></td>
                <td><?php echo (int) $a['converted']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php endif; ?>

</div>

<div class="asraa-dashboard-sidebar">

<h2>🔔 Follow-up Alerts</h2>

<?php if (empty($overdue_followups) && empty($today_followups_list)): ?>
<p class="asraa-dashboard-empty">Nothing due right now.</p>
<?php endif; ?>

<?php foreach ($overdue_followups as $f): ?>
<div class="asraa-reminder overdue">
<strong><?php echo esc_html($f->lead_name); ?></strong><br>
Due: <?php echo esc_html( wp_date( 'd M Y', strtotime( $f->follow_date ) ) ); ?><br>
<a class="button button-small"
href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-lead-view&lead_id=' . $f->lead_id) ); ?>">
Open Lead
</a>
</div>
<?php endforeach; ?>

<?php foreach ($today_followups_list as $f): ?>
<div class="asraa-reminder today">
<strong><?php echo esc_html($f->lead_name); ?></strong><br>
<a class="button button-small"
href="<?php echo esc_url( admin_url('admin.php?page=asraa-crm-lead-view&lead_id=' . $f->lead_id) ); ?>">
Open Lead
</a>
</div>
<?php endforeach; ?>

</div>

</div>

</div>
