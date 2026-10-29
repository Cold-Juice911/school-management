<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$pageTitle = 'Fees';
$students = db()->query('SELECT s.id, s.admission_no, u.name FROM students s JOIN users u ON u.id = s.user_id WHERE u.is_active = 1 ORDER BY u.name')->fetchAll();
$studentFilter = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selectedStudent = $studentFilter === false ? null : $studentFilter;
$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'pending', 'partial', 'paid'], true)) {
    $statusFilter = 'all';
}
$feesStatement = db()->prepare("SELECT f.id, f.student_id, f.description, f.amount, f.due_date, s.admission_no, u.name, COALESCE(p.paid, 0) AS paid FROM fees f JOIN students s ON s.id = f.student_id JOIN users u ON u.id = s.user_id LEFT JOIN (SELECT fee_id, SUM(amount_paid) AS paid FROM fee_payments GROUP BY fee_id) p ON p.fee_id = f.id WHERE (:student_id IS NULL OR f.student_id = :student_filter) AND (:status_value = 'all' OR CASE WHEN COALESCE(p.paid, 0) <= 0 THEN 'pending' WHEN COALESCE(p.paid, 0) >= f.amount THEN 'paid' ELSE 'partial' END = :status_filter) ORDER BY f.due_date IS NULL, f.due_date DESC, f.id DESC");
$feesStatement->execute(['student_id' => $selectedStudent, 'student_filter' => $selectedStudent, 'status_value' => $statusFilter, 'status_filter' => $statusFilter]);
$fees = $feesStatement->fetchAll();
$feeCount = (int) db()->query('SELECT COUNT(*) FROM fees')->fetchColumn();
$summary = db()->query('SELECT COALESCE(SUM(f.amount), 0) AS total_fees, COALESCE(SUM(LEAST(f.amount, COALESCE(p.paid, 0))), 0) AS total_paid, COALESCE(SUM(GREATEST(f.amount - COALESCE(p.paid, 0), 0)), 0) AS balance FROM fees f LEFT JOIN (SELECT fee_id, SUM(amount_paid) AS paid FROM fee_payments GROUP BY fee_id) p ON p.fee_id = f.id')->fetch();
$historyFeeId = filter_input(INPUT_GET, 'fee_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$historyFee = null;
$historyPayments = [];
if ($historyFeeId !== null && $historyFeeId !== false) {
    $historyFeeQuery = db()->prepare('SELECT f.id, f.description, f.amount, s.admission_no, u.name FROM fees f JOIN students s ON s.id = f.student_id JOIN users u ON u.id = s.user_id WHERE f.id = :id');
    $historyFeeQuery->execute(['id' => $historyFeeId]);
    $historyFee = $historyFeeQuery->fetch() ?: null;
    if (is_array($historyFee)) {
        $historyQuery = db()->prepare('SELECT id, receipt_no, payment_date, amount_paid FROM fee_payments WHERE fee_id = :fee_id ORDER BY id DESC');
        $historyQuery->execute(['fee_id' => $historyFeeId]);
        $historyPayments = $historyQuery->fetchAll();
    }
}
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">SCHOOL FINANCE</span><h1>Fees</h1><p class="muted">Balances and payment status are calculated from recorded payments.</p></div><?= add_page_link('/admin/fee-form.php', 'fee record') ?></section>
<section class="stat-grid"><article class="card stat-card"><span class="eyebrow">Total fees</span><strong class="stat-value"><?= h(money($summary['total_fees'])) ?></strong></article><article class="card stat-card"><span class="eyebrow">Total paid</span><strong class="stat-value accent-success"><?= h(money($summary['total_paid'])) ?></strong></article><article class="card stat-card"><span class="eyebrow">Outstanding</span><strong class="stat-value accent-warning"><?= h(money($summary['balance'])) ?></strong></article></section>
<?php if ($feeCount === 0): ?>
    <?= render_empty_records('fee record', 'wallet', 'warning', '/admin/fee-form.php') ?>
<?php else: ?>
    <section class="card content-card"><h2>Fee register</h2><form method="get" class="filter-form"><label>Student<select name="student_id"><option value="">All students</option><?php foreach ($students as $student): ?><option value="<?= h($student['id']) ?>"<?= selected((string) ($selectedStudent ?? ''), $student['id']) ?>><?= h($student['name'] . ' · ' . $student['admission_no']) ?></option><?php endforeach; ?></select></label><label>Status<select name="status"><?php foreach (['all' => 'All statuses', 'pending' => 'Pending', 'partial' => 'Partially paid', 'paid' => 'Paid'] as $value => $label): ?><option value="<?= h($value) ?>"<?= selected($statusFilter, $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></label><button class="btn btn-secondary" type="submit">Filter fees</button></form>
        <?php if ($fees === []): ?><p class="empty-state">No fee records match these filters.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Student</th><th>Description</th><th>Due</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($fees as $fee): $balance = max(0.0, (float) $fee['amount'] - (float) $fee['paid']); $status = (float) $fee['paid'] <= 0 ? 'pending' : ($balance <= 0 ? 'paid' : 'partial'); ?><tr><td><?= h($fee['name'] . ' · ' . $fee['admission_no']) ?></td><td><?= h($fee['description']) ?></td><td><?= h($fee['due_date'] ?? '—') ?></td><td><?= h(money($fee['amount'])) ?></td><td><?= h(money($fee['paid'])) ?></td><td><?= h(money($balance)) ?></td><td><?= status_badge($status) ?></td><td><a class="btn btn-secondary btn-table-action" href="<?= h(app_url('/admin/fees.php?fee_id=' . (int) $fee['id'])) ?>">View Payment</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php endif; ?>
<?php if (is_array($historyFee)): ?><section class="card content-card"><div class="section-heading"><div><h2>Payment history</h2><p class="muted"><?= h($historyFee['name'] . ' · ' . $historyFee['admission_no'] . ' · ' . $historyFee['description']) ?></p></div><a href="<?= h(app_url('/admin/fees.php')) ?>">Close history</a></div><?php if ($historyPayments === []): ?><p class="empty-state">No payments have been recorded for this fee.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Receipt</th><th>Date</th><th>Amount</th></tr></thead><tbody><?php foreach ($historyPayments as $payment): ?><tr><td><a href="<?= h(app_url('/admin/receipt.php?receipt_no=' . rawurlencode((string) $payment['receipt_no']))) ?>"><?= h($payment['receipt_no']) ?></a></td><td><?= h($payment['payment_date']) ?></td><td><?= h(money($payment['amount_paid'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section><?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
