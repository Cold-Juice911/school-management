<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$activeNavigationHref = '/admin/students.php';
$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($studentId === null || $studentId === false) render_error_page(404, 'Student not found', 'The requested student record does not exist.');
$statement = db()->prepare('SELECT s.id, s.admission_no, s.date_of_birth, s.guardian_name, s.phone, s.address, s.admission_date, c.class_name, c.section, u.name, u.email, u.is_active FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = :id');
$statement->execute(['id' => $studentId]);
$student = $statement->fetch();
if (!is_array($student)) render_error_page(404, 'Student not found', 'The requested student record does not exist.');
$attendanceQuery = db()->prepare("SELECT COUNT(*) AS total, SUM(status = 'present') AS present, SUM(status = 'absent') AS absent, SUM(status = 'late') AS late FROM attendance WHERE student_id = :student_id");
$attendanceQuery->execute(['student_id' => $studentId]);
$attendance = array_map('intval', $attendanceQuery->fetch() ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0]);
$attendancePercent = $attendance['total'] === 0 ? 0.0 : ($attendance['present'] / $attendance['total']) * 100;
$feeSummaryQuery = db()->prepare('SELECT COALESCE(SUM(f.amount), 0) AS total, COALESCE(SUM(p.paid), 0) AS paid, COALESCE(SUM(GREATEST(f.amount - COALESCE(p.paid, 0), 0)), 0) AS balance FROM fees f LEFT JOIN (SELECT fee_id, SUM(amount_paid) AS paid FROM fee_payments GROUP BY fee_id) p ON p.fee_id = f.id WHERE f.student_id = :student_id');
$feeSummaryQuery->execute(['student_id' => $studentId]);
$feeSummary = $feeSummaryQuery->fetch();
$feeRowsQuery = db()->prepare('SELECT f.description, f.amount, f.due_date, COALESCE(p.paid, 0) AS paid FROM fees f LEFT JOIN (SELECT fee_id, SUM(amount_paid) AS paid FROM fee_payments GROUP BY fee_id) p ON p.fee_id = f.id WHERE f.student_id = :student_id ORDER BY f.due_date DESC, f.id DESC');
$feeRowsQuery->execute(['student_id' => $studentId]);
$feeRows = $feeRowsQuery->fetchAll();
$pageTitle = 'Student details';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">STUDENT RECORD</span><div class="detail-heading-title"><a class="btn btn-secondary detail-back-link" href="<?= h(app_url('/admin/students.php')) ?>" aria-label="Back to students"><?= icon_svg('arrow-left') ?></a><h1><?= h($student['name']) ?></h1></div><p class="muted">Admission number <?= h($student['admission_no']) ?></p></div><a class="btn btn-primary" href="<?= h(app_url('/admin/student-form.php?edit=' . (int) $student['id'])) ?>">Edit student</a></section>
<section class="card content-card"><div class="section-heading"><h2>Student details</h2><?= status_badge((int) $student['is_active'] === 1 ? 'active' : 'inactive') ?></div><div class="table-scroll"><table><tbody><tr><th>Email</th><td><?= h($student['email']) ?></td></tr><tr><th>Class</th><td><?= h(($student['class_name'] ?? 'Unassigned') . (isset($student['section']) ? ' · ' . $student['section'] : '')) ?></td></tr><tr><th>Date of birth</th><td><?= h($student['date_of_birth'] ?? '—') ?></td></tr><tr><th>Guardian</th><td><?= h($student['guardian_name'] ?? '—') ?></td></tr><tr><th>Phone</th><td><?= h($student['phone'] ?? '—') ?></td></tr><tr><th>Address</th><td><?= nl2br(h($student['address'] ?? '—')) ?></td></tr><tr><th>Admission date</th><td><?= h($student['admission_date'] ?? '—') ?></td></tr></tbody></table></div></section>
<section class="stat-grid"><article class="card stat-card"><span class="eyebrow">Attendance</span><strong class="stat-value accent-success"><?= h(number_format($attendancePercent, 1)) ?>%</strong></article><article class="card stat-card"><span class="eyebrow">Present / absent / late</span><strong class="stat-value"><?= h($attendance['present'] . ' / ' . $attendance['absent'] . ' / ' . $attendance['late']) ?></strong></article><article class="card stat-card"><span class="eyebrow">Total fees</span><strong class="stat-value"><?= h(money($feeSummary['total'])) ?></strong></article><article class="card stat-card"><span class="eyebrow">Paid</span><strong class="stat-value accent-success"><?= h(money($feeSummary['paid'])) ?></strong></article><article class="card stat-card"><span class="eyebrow">Outstanding</span><strong class="stat-value accent-warning"><?= h(money($feeSummary['balance'])) ?></strong></article></section>
<section class="card content-card"><h2>Recent fees</h2><?php if ($feeRows === []): ?><p class="empty-state">No fees have been assigned to this student.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Description</th><th>Due date</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead><tbody><?php foreach ($feeRows as $feeRow): $balance = max(0.0, (float) $feeRow['amount'] - (float) $feeRow['paid']); ?><tr><td><?= h($feeRow['description']) ?></td><td><?= h($feeRow['due_date'] ?? '—') ?></td><td><?= h(money($feeRow['amount'])) ?></td><td><?= h(money($feeRow['paid'])) ?></td><td><?= h(money($balance)) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
