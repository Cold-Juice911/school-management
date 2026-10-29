<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$pageTitle = 'Timetable';

if (is_post()) {
    try {
        verify_csrf();
        if (post_string('action') !== 'delete') {
            throw new InvalidArgumentException('Unknown timetable action.');
        }
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('Timetable period not found.');
        }
        $statement = db()->prepare('DELETE FROM timetable WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Timetable period not found.');
        }
        flash('success', 'Timetable period removed.');
        redirect('/admin/timetable.php');
    } catch (Throwable $exception) {
        form_error($exception);
    }
}

$periods = db()->query('SELECT t.*, c.class_name, c.section, u.name AS teacher_name FROM timetable t JOIN classes c ON c.id = t.class_id JOIN teachers tr ON tr.id = t.teacher_id JOIN users u ON u.id = tr.user_id ORDER BY t.day_of_week, t.start_time')->fetchAll();
$periodCount = (int) db()->query('SELECT COUNT(*) FROM timetable')->fetchColumn();
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">SCHOOL SCHEDULE</span><h1>Timetable</h1><p class="muted">Periods cannot overlap for the same class or teacher.</p></div><?= add_page_link('/admin/timetable-form.php', 'period') ?></section>
<?php if ($periodCount === 0): ?>
    <?= render_empty_records('timetable period', 'calendar', 'sched', '/admin/timetable-form.php') ?>
<?php elseif ($periods === []): ?>
    <section class="card content-card"><p class="empty-state">No timetable periods are available.</p></section>
<?php else: ?>
    <section class="card content-card"><h2>Weekly schedule</h2><div class="table-scroll"><table><thead><tr><th>Day</th><th>Time</th><th>Class</th><th>Subject</th><th>Teacher</th><th>Room</th><th>Actions</th></tr></thead><tbody><?php foreach ($periods as $period): ?><tr><td><span class="badge badge-sched"><?= h($days[(int) $period['day_of_week']] ?? 'Unknown') ?></span></td><td class="no-wrap"><?= h(substr((string) $period['start_time'], 0, 5) . '–' . substr((string) $period['end_time'], 0, 5)) ?></td><td><?= h($period['class_name'] . ' · ' . $period['section']) ?></td><td><?= h($period['subject']) ?></td><td><?= h($period['teacher_name']) ?></td><td><?= h($period['room'] ?? '—') ?></td><td><div class="table-actions"><a class="btn btn-secondary btn-table-action" href="<?= h(app_url('/admin/timetable-form.php?edit=' . (int) $period['id'])) ?>">Edit</a><form method="post" class="inline-form" data-confirm="Delete this timetable period?" data-confirm-title="Delete timetable period?" data-confirm-label="Delete period" data-confirm-variant="danger"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($period['id']) ?>"><button class="btn btn-danger btn-table-action" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
