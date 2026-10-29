<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$pageTitle = 'Attendance';
$classes = class_options();
$reportClassInput = filter_input(INPUT_GET, 'report_class_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$reportClassId = $reportClassInput === false ? null : $reportClassInput;
$defaultFrom = (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');
$reportFrom = (string) ($_GET['report_from'] ?? $defaultFrom);
$reportTo = (string) ($_GET['report_to'] ?? date('Y-m-d'));
if (valid_date($reportFrom) === null || valid_date($reportTo) === null || $reportFrom > $reportTo || (new DateTimeImmutable($reportFrom))->diff(new DateTimeImmutable($reportTo))->days > 365) {
    $reportFrom = $defaultFrom;
    $reportTo = date('Y-m-d');
    flash('warning', 'Choose a valid report date range of up to one year.');
}
$reportQuery = db()->prepare('SELECT a.attendance_date, a.status, s.admission_no, u.name, c.class_name, c.section, marker.name AS marked_by FROM attendance a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id LEFT JOIN classes c ON c.id = s.class_id JOIN users marker ON marker.id = a.marked_by WHERE a.attendance_date BETWEEN :date_from AND :date_to AND (:class_id IS NULL OR s.class_id = :class_filter) ORDER BY a.attendance_date DESC, u.name');
$reportQuery->execute(['date_from' => $reportFrom, 'date_to' => $reportTo, 'class_id' => $reportClassId, 'class_filter' => $reportClassId]);
$attendanceReport = $reportQuery->fetchAll();
$attendanceCount = (int) db()->query('SELECT COUNT(*) FROM attendance')->fetchColumn();
$reportCounts = ['present' => 0, 'absent' => 0, 'late' => 0];
$countQuery = db()->prepare('SELECT status, COUNT(*) AS total FROM attendance a JOIN students s ON s.id = a.student_id WHERE a.attendance_date BETWEEN :date_from AND :date_to AND (:class_id IS NULL OR s.class_id = :class_filter) GROUP BY status');
$countQuery->execute(['date_from' => $reportFrom, 'date_to' => $reportTo, 'class_id' => $reportClassId, 'class_filter' => $reportClassId]);
foreach ($countQuery->fetchAll() as $countRow) {
    if (isset($reportCounts[$countRow['status']])) {
        $reportCounts[$countRow['status']] = (int) $countRow['total'];
    }
}
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">SCHOOL RECORDS</span><h1>Attendance</h1><p class="muted">Review saved attendance records by class and date.</p></div><?= add_page_link('/admin/attendance-form.php', 'attendance') ?></section>
<?php if ($attendanceCount === 0): ?>
    <?= render_empty_records('attendance record', 'attendance', 'success', '/admin/attendance-form.php') ?>
<?php else: ?>
    <section class="card content-card"><h2>Attendance history</h2><form method="get" class="filter-form"><label>Class<select name="report_class_id"><option value="">All classes</option><?php foreach ($classes as $class): ?><option value="<?= h($class['id']) ?>"<?= selected((string) ($reportClassId ?: ''), $class['id']) ?>><?= h($class['class_name'] . ' · ' . $class['section']) ?></option><?php endforeach; ?></select></label><label>From<input type="date" name="report_from" value="<?= h($reportFrom) ?>" required></label><label>To<input type="date" name="report_to" value="<?= h($reportTo) ?>" required></label><button class="btn btn-secondary" type="submit">Filter history</button></form>
        <div class="stat-grid"><article class="card stat-card"><span class="eyebrow">Present</span><strong class="stat-value accent-success"><?= $reportCounts['present'] ?></strong></article><article class="card stat-card"><span class="eyebrow">Absent</span><strong class="stat-value accent-alert"><?= $reportCounts['absent'] ?></strong></article><article class="card stat-card"><span class="eyebrow">Late</span><strong class="stat-value accent-warning"><?= $reportCounts['late'] ?></strong></article></div>
        <?php if ($attendanceReport === []): ?><p class="empty-state">No attendance records match this date range.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Date</th><th>Student</th><th>Admission no.</th><th>Class</th><th>Status</th><th>Marked by</th></tr></thead><tbody><?php foreach ($attendanceReport as $record): ?><tr><td><?= h($record['attendance_date']) ?></td><td><?= h($record['name']) ?></td><td><?= h($record['admission_no']) ?></td><td><?= h(($record['class_name'] ?? 'Unassigned') . (isset($record['section']) ? ' · ' . $record['section'] : '')) ?></td><td><?= status_badge($record['status']) ?></td><td><?= h($record['marked_by']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
