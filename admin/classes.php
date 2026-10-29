<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$pageTitle = 'Classes';

if (is_post()) {
    $pdo = null;
    try {
        verify_csrf();
        if (post_string('action') !== 'delete') {
            throw new InvalidArgumentException('Unknown class action.');
        }
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('Class not found.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        $exists = $pdo->prepare('SELECT id FROM classes WHERE id = :id FOR UPDATE');
        $exists->execute(['id' => $id]);
        if ($exists->fetchColumn() === false) {
            throw new InvalidArgumentException('Class not found.');
        }
        $deletePeriods = $pdo->prepare('DELETE FROM timetable WHERE class_id = :class_id');
        $deletePeriods->execute(['class_id' => $id]);
        $statement = $pdo->prepare('DELETE FROM classes WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Class not found.');
        }
        $pdo->commit();
        flash('success', 'Class and its timetable periods deleted. Students remain in their accounts and are now unassigned.');
        redirect('/admin/classes.php');
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        form_error($exception);
    }
}

$classes = db()->query('SELECT c.id, c.class_name, c.section, c.created_at, COUNT(s.id) AS student_count, (SELECT COUNT(*) FROM timetable t WHERE t.class_id = c.id) AS timetable_count FROM classes c LEFT JOIN students s ON s.class_id = c.id GROUP BY c.id, c.class_name, c.section, c.created_at ORDER BY c.class_name, c.section')->fetchAll();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Classes and sections</h1><p class="muted">Class groups used for enrollment, attendance, and timetables.</p></div><?= add_page_link('/admin/class-form.php', 'class') ?></section>
<?php if ($classes === []): ?>
    <?= render_empty_records('class', 'school', 'sched', '/admin/class-form.php') ?>
<?php else: ?>
    <section class="card content-card"><h2>Class directory</h2><div class="table-scroll"><table><thead><tr><th>Class</th><th>Section</th><th>Students</th><th>Actions</th></tr></thead><tbody><?php foreach ($classes as $class): ?><tr><td><?= h($class['class_name']) ?></td><td><?= h($class['section']) ?></td><td><?= h($class['student_count']) ?></td><td><div class="table-actions"><a class="btn btn-secondary btn-table-action" href="<?= h(app_url('/admin/class-form.php?edit=' . (int) $class['id'])) ?>">Edit</a><form method="post" class="inline-form" data-confirm="<?= h('Delete ' . $class['class_name'] . ' · ' . $class['section'] . '? This removes ' . $class['timetable_count'] . ' timetable periods and leaves ' . $class['student_count'] . ' students unassigned.') ?>" data-confirm-title="Delete class?" data-confirm-label="Delete class" data-confirm-variant="danger"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($class['id']) ?>"><button class="btn btn-danger btn-table-action" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
