<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
$user = require_roles('student');
$classId = student_class_id((int) $user['id']);
$pageTitle = 'My timetable';
$periods = [];
if ($classId !== null) {
    $statement = db()->prepare('SELECT t.day_of_week, t.subject, t.start_time, t.end_time, t.room, u.name AS teacher_name FROM timetable t JOIN teachers tr ON tr.id = t.teacher_id JOIN users u ON u.id = tr.user_id WHERE t.class_id = :class_id ORDER BY t.day_of_week, t.start_time');
    $statement->execute(['class_id' => $classId]);
    $periods = $statement->fetchAll();
}
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">MY CLASS</span><h1>My timetable</h1><p class="muted">Your schedule is shown for your current class.</p></div></section>
<section class="card content-card"><?php if ($classId === null || $periods === []): ?><p class="empty-state">No timetable is available for your class yet.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Teacher</th><th>Room</th></tr></thead><tbody><?php foreach ($periods as $period): ?><tr><td><span class="badge badge-sched"><?= h($days[(int) $period['day_of_week']] ?? 'Unknown') ?></span></td><td><?= h(substr((string) $period['start_time'], 0, 5) . '–' . substr((string) $period['end_time'], 0, 5)) ?></td><td><?= h($period['subject']) ?></td><td><?= h($period['teacher_name']) ?></td><td><?= h($period['room'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
