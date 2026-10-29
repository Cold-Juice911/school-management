<?php
declare(strict_types=1);
$balanceAfterPayment = max(0.0, (float) $receipt['fee_amount'] - (float) $receipt['paid_to_date']);
?>
<article class="receipt card">
    <span class="eyebrow">FEE PAYMENT RECEIPT</span>
    <div class="detail-heading-title receipt-title-row"><a class="btn btn-secondary detail-back-link" href="<?= h(app_url($user['role'] === 'admin' ? '/admin/fees.php' : '/student/fees.php')) ?>" aria-label="Back to fees"><?= icon_svg('arrow-left') ?></a><h1>School Management System</h1></div>
    <p class="muted">Reference <?= h($receipt['receipt_no']) ?></p>
    <?php if (str_starts_with((string) $receipt['receipt_no'], 'DEMO-')): ?><p class="demo-payment-note">This is an educational payment simulation. No funds were transferred.</p><?php endif; ?>
    <div class="table-scroll"><table><tbody>
        <tr><th>Student</th><td><?= h($receipt['student_name']) ?></td></tr>
        <tr><th>Admission number</th><td><?= h($receipt['admission_no']) ?></td></tr>
        <tr><th>Class</th><td><?= h(($receipt['class_name'] ?? 'Unassigned') . (isset($receipt['section']) ? ' · ' . $receipt['section'] : '')) ?></td></tr>
        <tr><th>Fee description</th><td><?= h($receipt['description']) ?></td></tr>
        <tr><th>Payment date</th><td><?= h($receipt['payment_date']) ?></td></tr>
        <tr><th>Amount received</th><td><strong><?= h(money($receipt['amount_paid'])) ?></strong></td></tr>
        <tr><th>Balance after payment</th><td><?= h(money($balanceAfterPayment)) ?></td></tr>
        <tr><th>Recorded by</th><td><?= h($receipt['recorded_by']) ?></td></tr>
    </tbody></table></div>
    <div class="receipt-actions"><button class="btn btn-primary" type="button" data-print>Print receipt</button></div>
</article>
