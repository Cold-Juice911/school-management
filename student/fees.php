<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
$user = require_roles('student');
$studentId = student_id_for_user((int) $user['id']);
$pageTitle = 'My fees';
$pdo = null;
if (is_post()) {
    try {
        verify_csrf();
        if (post_string('action') !== 'demo_pay') throw new InvalidArgumentException('Unknown payment action.');
        $feeId = filter_var($_POST['fee_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($feeId === false || $studentId === null) throw new InvalidArgumentException('This fee is not available for your account.');
        $pdo = db();
        $pdo->beginTransaction();
        $feeQuery = $pdo->prepare('SELECT amount FROM fees WHERE id = :fee_id AND student_id = :student_id FOR UPDATE');
        $feeQuery->execute(['fee_id' => $feeId, 'student_id' => $studentId]);
        $feeAmount = $feeQuery->fetchColumn();
        if ($feeAmount === false) throw new InvalidArgumentException('This fee is not available for your account.');
        $paidQuery = $pdo->prepare('SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE fee_id = :fee_id');
        $paidQuery->execute(['fee_id' => $feeId]);
        $balanceCents = (int) round(((float) $feeAmount - (float) $paidQuery->fetchColumn()) * 100);
        if ($balanceCents <= 0) throw new InvalidArgumentException('This fee has already been paid.');
        $reference = 'DEMO-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $payment = $pdo->prepare('INSERT INTO fee_payments (fee_id, amount_paid, payment_date, receipt_no, received_by) VALUES (:fee_id, :amount_paid, :payment_date, :receipt_no, :received_by)');
        $payment->execute(['fee_id' => $feeId, 'amount_paid' => number_format($balanceCents / 100, 2, '.', ''), 'payment_date' => date('Y-m-d'), 'receipt_no' => $reference, 'received_by' => $user['id']]);
        $pdo->commit();
        flash('success', 'Demo payment recorded for ' . money($balanceCents / 100) . '. No real payment was processed.');
        redirect('/student/fees.php');
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        form_error($exception);
    }
}
$fees = [];
$totalBalance = 0.0;
$totalFees = 0.0;
$totalPaid = 0.0;
if ($studentId !== null) {
    $statement = db()->prepare('SELECT f.id, f.description, f.amount, f.due_date, COALESCE(p.paid, 0) AS paid FROM fees f LEFT JOIN (SELECT fee_id, SUM(amount_paid) AS paid FROM fee_payments GROUP BY fee_id) p ON p.fee_id = f.id WHERE f.student_id = :student_id ORDER BY f.due_date IS NULL, f.due_date DESC');
    $statement->execute(['student_id' => $studentId]);
    $fees = $statement->fetchAll();
    foreach ($fees as $fee) {
        $totalFees += (float) $fee['amount'];
        $totalPaid += (float) $fee['paid'];
        $totalBalance += max(0.0, (float) $fee['amount'] - (float) $fee['paid']);
    }
}
$payments = [];
if ($studentId !== null) {
    $statement = db()->prepare('SELECT p.id, p.receipt_no, p.payment_date, p.amount_paid, f.description FROM fee_payments p JOIN fees f ON f.id = p.fee_id WHERE f.student_id = :student_id ORDER BY p.payment_date DESC, p.id DESC');
    $statement->execute(['student_id' => $studentId]);
    $payments = $statement->fetchAll();
}
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">MY ACCOUNT</span><h1>My fees</h1><p class="muted">Your fee balance and demo-payment history are visible only to your account.</p></div></section>
<section class="stat-grid"><article class="card stat-card"><span class="eyebrow">Total fees</span><strong class="stat-value"><?= h(money($totalFees)) ?></strong></article><article class="card stat-card"><span class="eyebrow">Total paid</span><strong class="stat-value accent-success"><?= h(money($totalPaid)) ?></strong></article><article class="card stat-card"><span class="eyebrow">Outstanding balance</span><strong class="stat-value accent-warning"><?= h(money($totalBalance)) ?></strong></article></section>
<section class="card content-card"><h2>Fee records</h2><p class="muted">Use the demo payment button to record the full remaining fee balance. This does not contact a bank or payment service.</p><?php if ($fees === []): ?><p class="empty-state">No fee records are available.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Description</th><th>Due date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($fees as $fee): $balance = max(0.0, (float) $fee['amount'] - (float) $fee['paid']); $status = (float) $fee['paid'] <= 0 ? 'pending' : ($balance <= 0 ? 'paid' : 'partial'); ?><tr><td><?= h($fee['description']) ?></td><td><?= h($fee['due_date'] ?? '—') ?></td><td><?= h(money($fee['amount'])) ?></td><td><?= h(money($fee['paid'])) ?></td><td><?= h(money($balance)) ?></td><td><?= status_badge($status) ?></td><td><?php if ($balance > 0): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="demo_pay"><input type="hidden" name="fee_id" value="<?= h($fee['id']) ?>"><button class="btn btn-primary" type="submit" data-confirm="Record a demo payment of <?= h(money($balance)) ?>? No real payment will be processed.">Pay balance (demo)</button></form><?php else: ?><span class="accent-success">Paid</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="card content-card"><h2>Payment history</h2><?php if ($payments === []): ?><p class="empty-state">No payments have been recorded for you.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Reference</th><th>Fee</th><th>Date</th><th>Amount</th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td><a href="<?= h(app_url('/student/receipt.php?receipt_no=' . rawurlencode((string) $payment['receipt_no']))) ?>"><?= h($payment['receipt_no']) ?></a></td><td><?= h($payment['description']) ?></td><td><?= h($payment['payment_date']) ?></td><td><?= h(money($payment['amount_paid'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
