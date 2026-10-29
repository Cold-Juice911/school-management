<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
$teacher = require_roles('teacher');
$teacherId = teacher_id_for_user((int) $teacher['id']);
if ($teacherId === null) render_error_page(403, 'Teacher profile unavailable', 'Your account does not have a linked teacher profile. Contact a school administrator.');
$pageTitle = 'My timetable';
$statement = db()->prepare('SELECT t.*, c.class_name, c.section FROM timetable t JOIN classes c ON c.id = t.class_id WHERE t.teacher_id = :teacher_id ORDER BY t.day_of_week, t.start_time');
$statement->execute(['teacher_id' => $teacherId]);
$periods = $statement->fetchAll();
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">TEACHER TOOLS</span><h1>My timetable</h1><p class="muted">Your assigned class periods for the week.</p></div></section>
<section class="card content-card"><?php if ($periods === []): ?><p class="empty-state">No periods are assigned to you yet.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Class</th><th>Room</th></tr></thead><tbody><?php foreach ($periods as $period): ?><tr><td><span class="badge badge-sched"><?= h($days[(int) $period['day_of_week']] ?? 'Unknown') ?></span></td><td><?= h(substr((string) $period['start_time'], 0, 5) . '–' . substr((string) $period['end_time'], 0, 5)) ?></td><td><?= h($period['subject']) ?></td><td><?= h($period['class_name'] . ' · ' . $period['section']) ?></td><td><?= h($period['room']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
