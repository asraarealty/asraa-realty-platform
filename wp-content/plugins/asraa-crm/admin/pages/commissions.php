<?php
if (!defined('ABSPATH')) exit;

$service = new Asraa_Commission_Service();
$tab     = sanitize_text_field($_GET['tab'] ?? 'commissions');

$commissions = $service->get_all();
$plans       = $service->get_plans();
$summary     = $service->get_agent_summary();
?>

<div class="wrap">

<?php if (!empty($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>Saved successfully.</p></div><?php endif; ?>
<?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>Commission updated.</p></div><?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><div class="notice notice-success is-dismissible"><p>Deleted successfully.</p></div><?php endif; ?>

<!-- TABS -->
<nav class="nav-tab-wrapper">
<a class="nav-tab <?php echo $tab === 'commissions' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url('admin.php?page=asraa-crm-commissions&tab=commissions'); ?>">Commissions</a>
<a class="nav-tab <?php echo $tab === 'summary' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url('admin.php?page=asraa-crm-commissions&tab=summary'); ?>">Agent Summary</a>
<a class="nav-tab <?php echo $tab === 'plans' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url('admin.php?page=asraa-crm-commissions&tab=plans'); ?>">Commission Plans</a>
</nav>

<?php if ($tab === 'commissions'): ?>

<div class="leads-table-wrapper">
<table class="leads-table" style="margin-top:16px;">
<thead><tr>
<th>Deal</th><th>Agent</th><th>Plan</th><th>Deal Value</th><th>Rate</th><th>Commission</th><th>Status</th><th>Created</th><th>Actions</th>
</tr></thead>
<tbody>
<?php foreach ($commissions as $c): ?>
<tr>
<td><?php echo esc_html($c['deal_title'] ?? '-'); ?></td>
<td><?php echo esc_html($c['agent_name'] ?? '-'); ?></td>
<td><?php echo esc_html($c['plan_name'] ?? 'Default'); ?></td>
<td>₹<?php echo number_format($c['deal_value']); ?></td>
<td><?php echo $c['commission_rate']; ?>%</td>
<td><strong>₹<?php echo number_format($c['commission_amount'], 2); ?></strong></td>
<td>
<?php if ($c['status'] === 'paid'): ?>
<span style="color:#16a34a;font-weight:600;">✓ Paid</span>
<?php else: ?>
<span style="color:#f59e0b;font-weight:600;">⏳ Pending</span>
<?php endif; ?>
</td>
<td><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
<td>
<?php if ($c['status'] !== 'paid'): ?>
<span class="row-actions">
<form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
<?php wp_nonce_field('asraa_crm_mark_paid'); ?>
<input type="hidden" name="action" value="asraa_crm_mark_paid">
<input type="hidden" name="commission_id" value="<?php echo $c['id']; ?>">
<button type="submit" class="button button-small button-primary">Mark Paid</button>
</form>
</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php elseif ($tab === 'summary'): ?>

<h2 style="margin-top:16px;">Agent Commission Summary</h2>
<div class="leads-table-wrapper">
<table class="leads-table">
<thead><tr>
<th>Agent</th><th>Deals</th><th>Total Earned</th><th>Paid</th><th>Pending</th>
</tr></thead>
<tbody>
<?php foreach ($summary as $row): ?>
<tr>
<td><?php echo esc_html($row['agent_name']); ?></td>
<td><?php echo $row['deal_count']; ?></td>
<td>₹<?php echo number_format($row['total_earned'], 2); ?></td>
<td style="color:#16a34a;">₹<?php echo number_format($row['paid'], 2); ?></td>
<td style="color:#f59e0b;">₹<?php echo number_format($row['pending'], 2); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php elseif ($tab === 'plans'): ?>

<h2 style="margin-top:16px;">Commission Plans
<a href="#add-plan" class="page-title-action">+ Add Plan</a>
</h2>

<div class="leads-table-wrapper">
<table class="leads-table" style="margin-bottom:24px;">
<thead><tr><th>Plan Name</th><th>Type</th><th>Rate</th><th>Description</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($plans as $p): ?>
<tr>
<td><?php echo esc_html($p['plan_name']); ?></td>
<td><?php echo ucfirst($p['type']); ?></td>
<td><?php echo $p['type'] === 'flat' ? '₹' . number_format($p['rate']) : $p['rate'] . '%'; ?></td>
<td><?php echo esc_html($p['description'] ?? '-'); ?></td>
<td>
<span class="row-actions">
<a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=asraa_crm_delete_commission_plan&plan_id='.$p['id']), 'asraa_crm_delete_plan'); ?>" class="button button-small" onclick="return confirm('Delete this plan?')">Delete</a>
</span>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div id="add-plan" style="background:#fff;padding:24px;border:1px solid #ddd;border-radius:8px;max-width:500px;">
<h3>Add Commission Plan</h3>
<form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
<?php wp_nonce_field('asraa_crm_commission_plan_nonce'); ?>
<input type="hidden" name="action" value="asraa_crm_save_commission_plan">
<table class="form-table">
<tr><th>Plan Name</th><td><input class="regular-text" name="plan_name" required></td></tr>
<tr><th>Type</th><td>
<select name="type">
<option value="percentage">Percentage (%)</option>
<option value="flat">Flat Amount (₹)</option>
</select></td></tr>
<tr><th>Rate / Amount</th><td><input type="number" step="0.01" name="rate" required></td></tr>
<tr><th>Description</th><td><input class="regular-text" name="description"></td></tr>
</table>
<button type="submit" class="button button-primary">Add Plan</button>
</form>
</div>

<?php endif; ?>
</div>
