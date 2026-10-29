<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$activeNavigationHref = '/admin/teachers.php';
$teacherId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($teacherId === null || $teacherId === false) render_error_page(404, 'Teacher not found', 'The requested teacher record does not exist.');
$statement = db()->prepare('SELECT t.id, t.phone, t.qualification, t.joining_date, t.created_at, u.name, u.email, u.is_active FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.id = :id');
$statement->execute(['id' => $teacherId]);
$teacher = $statement->fetch();
if (!is_array($teacher)) render_error_page(404, 'Teacher not found', 'The requested teacher record does not exist.');
$periodQuery = db()->prepare('SELECT t.subject, t.day_of_week, t.start_time, t.end_time, t.room, c.class_name, c.section FROM timetable t JOIN classes c ON c.id = t.class_id WHERE t.teacher_id = :teacher_id ORDER BY t.day_of_week, t.start_time');
$periodQuery->execute(['teacher_id' => $teacherId]);
$periods = $periodQuery->fetchAll();
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$pageTitle = 'Teacher details';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">TEACHER RECORD</span><div class="detail-heading-title"><a class="btn btn-secondary detail-back-link" href="<?= h(app_url('/admin/teachers.php')) ?>" aria-label="Back to teachers"><?= icon_svg('arrow-left') ?></a><h1><?= h($teacher['name']) ?></h1></div><p class="muted"><?= h($teacher['email']) ?></p></div><a class="btn btn-primary" href="<?= h(app_url('/admin/teacher-form.php?edit=' . (int) $teacher['id'])) ?>">Edit teacher</a></section>
<section class="card content-card"><div class="section-heading"><h2>Teacher details</h2><?= status_badge((int) $teacher['is_active'] === 1 ? 'active' : 'inactive') ?></div><div class="table-scroll"><table><tbody><tr><th>Phone</th><td><?= h($teacher['phone'] ?? '—') ?></td></tr><tr><th>Qualification</th><td><?= h($teacher['qualification'] ?? '—') ?></td></tr><tr><th>Joining date</th><td><?= h($teacher['joining_date'] ?? '—') ?></td></tr></tbody></table></div></section>
<section class="card content-card"><h2>Assigned periods</h2><?php if ($periods === []): ?><p class="empty-state">No timetable periods are assigned.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Class</th><th>Room</th></tr></thead><tbody><?php foreach ($periods as $period): ?><tr><td><span class="badge badge-sched"><?= h($days[(int) $period['day_of_week']] ?? 'Unknown') ?></span></td><td><?= h(substr((string) $period['start_time'], 0, 5) . '–' . substr((string) $period['end_time'], 0, 5)) ?></td><td><?= h($period['subject']) ?></td><td><?= h($period['class_name'] . ' · ' . $period['section']) ?></td><td><?= h($period['room'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
