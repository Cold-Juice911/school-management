<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
$user = require_roles('student');
$studentId = student_id_for_user((int) $user['id']);
$pageTitle = 'My attendance';
$rows = [];
$percentage = 0.0;
$summary = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];
if ($studentId !== null) {
    $percentage = attendance_percentage($studentId);
    $summaryQuery = db()->prepare("SELECT COUNT(*) AS total, SUM(status = 'present') AS present, SUM(status = 'absent') AS absent, SUM(status = 'late') AS late FROM attendance WHERE student_id = :student_id");
    $summaryQuery->execute(['student_id' => $studentId]);
    $summary = array_map('intval', $summaryQuery->fetch() ?: $summary);
    $statement = db()->prepare('SELECT attendance_date, status FROM attendance WHERE student_id = :student_id ORDER BY attendance_date DESC');
    $statement->execute(['student_id' => $studentId]);
    $rows = $statement->fetchAll();
}
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">MY RECORDS</span><h1>Attendance history</h1><p class="muted">Your overall attendance percentage is based on all recorded school days.</p></div></section>
<section class="stat-grid"><article class="card stat-card"><span class="eyebrow">Attendance percentage</span><strong class="stat-value accent-success"><?= h(number_format($percentage, 1)) ?>%</strong></article><article class="card stat-card"><span class="eyebrow">Days recorded</span><strong class="stat-value"><?= h($summary['total']) ?></strong></article><article class="card stat-card"><span class="eyebrow">Present</span><strong class="stat-value accent-success"><?= h($summary['present']) ?></strong></article><article class="card stat-card"><span class="eyebrow">Absent</span><strong class="stat-value accent-alert"><?= h($summary['absent']) ?></strong></article><article class="card stat-card"><span class="eyebrow">Late</span><strong class="stat-value accent-warning"><?= h($summary['late']) ?></strong></article></section>
<section class="card content-card"><h2>Recent records</h2><?php if ($studentId === null || $rows === []): ?><p class="empty-state">No attendance records are available yet.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Date</th><th>Status</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= h($row['attendance_date']) ?></td><td><?= status_badge($row['status']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
