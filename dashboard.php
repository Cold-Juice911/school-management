<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
$user = require_login();
$pageTitle = 'Dashboard';
$stats = [];
$student = null;
$upcoming = [];
$balance = 0.0;
$quickAccessLinks = [
    ['label' => 'Add student', 'href' => '/admin/student-form.php', 'icon' => 'students', 'tone' => 'profile'],
    ['label' => 'Add teacher', 'href' => '/admin/teacher-form.php', 'icon' => 'teachers', 'tone' => 'success'],
    ['label' => 'Add attendance', 'href' => '/admin/attendance-form.php', 'icon' => 'attendance', 'tone' => 'success'],
    ['label' => 'Add timetable period', 'href' => '/admin/timetable-form.php', 'icon' => 'calendar', 'tone' => 'sched'],
    ['label' => 'Add fee record', 'href' => '/admin/fee-form.php', 'icon' => 'wallet', 'tone' => 'warning'],
];

if ($user['role'] === 'admin') {
    $stats = [
        ['Active students', active_user_count('student'), 'profile', 'students'],
        ['Active teachers', active_user_count('teacher'), 'success', 'teachers'],
        ['Classes', (int) db()->query('SELECT COUNT(*) FROM classes')->fetchColumn(), 'sched', 'school'],
    ];
    $attendanceStatement = db()->prepare("SELECT COUNT(*) AS total, SUM(status = 'present') AS present FROM attendance WHERE attendance_date = :today");
    $attendanceStatement->execute(['today' => date('Y-m-d')]);
    $todayAttendance = $attendanceStatement->fetch();
    $stats[] = ['Present today', (int) ($todayAttendance['present'] ?? 0) . ' / ' . (int) ($todayAttendance['total'] ?? 0), 'success', 'attendance'];
    $balance = (float) db()->query('SELECT COALESCE(SUM(f.amount - COALESCE(p.paid, 0)), 0) FROM fees f LEFT JOIN (SELECT fee_id, SUM(amount_paid) AS paid FROM fee_payments GROUP BY fee_id) p ON p.fee_id = f.id')->fetchColumn();
} elseif ($user['role'] === 'student') {
    $statement = db()->prepare('SELECT s.id, c.class_name, c.section FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.user_id = :user_id');
    $statement->execute(['user_id' => $user['id']]);
    $student = $statement->fetch() ?: null;
    if (is_array($student)) {
        $balanceStatement = db()->prepare('SELECT COALESCE(SUM(f.amount - COALESCE(p.paid, 0)), 0) FROM fees f LEFT JOIN (SELECT fee_id, SUM(amount_paid) AS paid FROM fee_payments GROUP BY fee_id) p ON p.fee_id = f.id WHERE f.student_id = :student_id');
        $balanceStatement->execute(['student_id' => $student['id']]);
        $balance = (float) $balanceStatement->fetchColumn();
        $attPercent = attendance_percentage((int) $student['id']);
        $day = (int) date('N');
        $schedule = db()->prepare('SELECT subject, start_time, end_time FROM timetable WHERE class_id = (SELECT class_id FROM students WHERE id = :student_id) AND day_of_week = :day AND start_time >= :now ORDER BY start_time LIMIT 4');
        $schedule->execute(['student_id' => $student['id'], 'day' => $day, 'now' => date('H:i:s')]);
        $upcoming = $schedule->fetchAll();
    } else {
        $attPercent = 0.0;
    }
} else {
    $teacherId = teacher_id_for_user((int) $user['id']);
    $classesStatement = db()->prepare('SELECT DISTINCT c.id, c.class_name, c.section FROM timetable t JOIN classes c ON c.id = t.class_id WHERE t.teacher_id = :teacher_id ORDER BY c.class_name, c.section');
    $classesStatement->execute(['teacher_id' => $teacherId ?? 0]);
    $teacherClasses = $classesStatement->fetchAll();
    $schedule = db()->prepare('SELECT tt.subject, tt.start_time, tt.end_time, c.class_name, c.section FROM timetable tt JOIN classes c ON c.id = tt.class_id WHERE tt.teacher_id = :teacher_id AND tt.day_of_week = :day AND tt.start_time >= :now ORDER BY tt.start_time LIMIT 5');
    $schedule->execute(['teacher_id' => $teacherId ?? 0, 'day' => (int) date('N'), 'now' => date('H:i:s')]);
    $upcoming = $schedule->fetchAll();
}
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">OVERVIEW</span><h1>Hello, <?= h($user['name']) ?></h1><p class="muted">Here is what is happening at your school today.</p></div></section>
<?php if ($user['role'] === 'admin'): ?>
    <section class="stat-grid" aria-label="School statistics">
        <?php foreach ($stats as [$label, $value, $tone, $icon]): ?>
            <article class="card stat-card dashboard-stat-card"><span class="dashboard-stat-title"><?= h($label) ?></span><strong class="stat-value accent-<?= h($tone) ?>"><?= h($value) ?></strong><span class="stat-icon accent-<?= h($tone) ?>"><?= icon_svg($icon) ?></span></article>
        <?php endforeach; ?>
        <article class="card stat-card dashboard-stat-card"><span class="dashboard-stat-title">Outstanding fees</span><strong class="stat-value accent-warning"><?= h(money($balance)) ?></strong><span class="stat-icon accent-warning"><?= icon_svg('wallet') ?></span></article>
    </section>
    <section class="card content-card"><h2>Quick Actions</h2><nav class="quick-access-grid" aria-label="Administrator quick actions">
        <?php foreach ($quickAccessLinks as $link): ?>
            <a class="card quick-access-card" href="<?= h(app_url($link['href'])) ?>"><span class="quick-access-icon accent-<?= h($link['tone']) ?>"><?= icon_svg($link['icon']) ?></span><span class="quick-access-label"><?= h($link['label']) ?></span></a>
        <?php endforeach; ?>
    </nav></section>
<?php elseif ($user['role'] === 'teacher'): ?>
    <section class="card content-card"><h2>Assigned classes</h2>
        <?php if ($teacherClasses === []): ?><p class="empty-state">No classes are assigned yet. Ask an administrator to add your timetable periods.</p>
        <?php else: ?><ul class="class-list"><?php foreach ($teacherClasses as $class): ?><li><?= h($class['class_name'] . ' · ' . $class['section']) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <a class="btn btn-primary" href="<?= h(app_url('/teacher/attendance.php')) ?>">Take attendance</a>
    </section>
    <section class="card content-card"><h2>Today's upcoming periods</h2><?= render_schedule_rows($upcoming, true) ?></section>
<?php else: ?>
    <section class="stat-grid">
        <article class="card stat-card dashboard-stat-card"><span class="dashboard-stat-title">My class</span><strong class="stat-value"><?= h(is_array($student) && $student['class_name'] !== null ? $student['class_name'] . ' · ' . $student['section'] : 'Not assigned') ?></strong><span class="stat-icon accent-profile"><?= icon_svg('class') ?></span></article>
        <article class="card stat-card dashboard-stat-card"><span class="dashboard-stat-title">Attendance</span><strong class="stat-value accent-success"><?= h(number_format($attPercent, 1)) ?>%</strong><span class="stat-icon accent-success"><?= icon_svg('progress') ?></span></article>
        <article class="card stat-card dashboard-stat-card"><span class="dashboard-stat-title">Fee balance</span><strong class="stat-value accent-warning"><?= h(money($balance)) ?></strong><span class="stat-icon accent-warning"><?= icon_svg('wallet') ?></span></article>
    </section>
    <section class="card content-card"><h2>Today's upcoming periods</h2><?= render_schedule_rows($upcoming, false) ?></section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
