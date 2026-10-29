<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$activeNavigationHref = '/admin/timetable.php';
$edit = null;
$editId = null;
if (array_key_exists('edit', $_GET)) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId === false) {
        render_error_page(404, 'Timetable period not found', 'This timetable period is not available.');
    }
}

if (is_post()) {
    try {
        verify_csrf();
        if (post_string('action') !== 'save') {
            throw new InvalidArgumentException('Unknown timetable action.');
        }
        $idRaw = post_string('id', 20, false);
        $id = $idRaw === '' ? null : filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $classId = filter_var($_POST['class_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $teacherId = filter_var($_POST['teacher_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $day = filter_var($_POST['day_of_week'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
        $start = post_string('start_time', 5);
        $end = post_string('end_time', 5);
        if ($id === false || $id !== $editId || $classId === false || $teacherId === false || $day === false || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) || $start >= $end) {
            throw new InvalidArgumentException('Choose a class, teacher, day, and valid time range.');
        }
        $subject = post_string('subject', 120);
        $room = post_string('room', 60, false);
        $assignment = db()->prepare('SELECT 1 FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.id = :teacher_id AND u.role = \'teacher\' AND u.is_active = 1');
        $assignment->execute(['teacher_id' => $teacherId]);
        if ($assignment->fetchColumn() === false) {
            throw new InvalidArgumentException('Choose an active teacher account.');
        }
        $conflict = db()->prepare('SELECT id FROM timetable WHERE day_of_week = :day AND id <> :id AND (class_id = :class_id OR teacher_id = :teacher_id) AND start_time < :end_time AND end_time > :start_time LIMIT 1');
        $conflict->execute(['day' => $day, 'id' => $id ?? 0, 'class_id' => $classId, 'teacher_id' => $teacherId, 'end_time' => $end . ':00', 'start_time' => $start . ':00']);
        if ($conflict->fetchColumn() !== false) {
            throw new InvalidArgumentException('This period overlaps another period for the class or teacher.');
        }
        if ($id === null) {
            $statement = db()->prepare('INSERT INTO timetable (class_id, teacher_id, subject, day_of_week, start_time, end_time, room) VALUES (:class_id, :teacher_id, :subject, :day, :start, :end, :room)');
        } else {
            $exists = db()->prepare('SELECT 1 FROM timetable WHERE id = :id');
            $exists->execute(['id' => $id]);
            if ($exists->fetchColumn() === false) {
                throw new InvalidArgumentException('Timetable period not found.');
            }
            $statement = db()->prepare('UPDATE timetable SET class_id = :class_id, teacher_id = :teacher_id, subject = :subject, day_of_week = :day, start_time = :start, end_time = :end, room = :room WHERE id = :id');
        }
        $parameters = ['class_id' => $classId, 'teacher_id' => $teacherId, 'subject' => $subject, 'day' => $day, 'start' => $start . ':00', 'end' => $end . ':00', 'room' => $room ?: null];
        if ($id !== null) {
            $parameters['id'] = $id;
        }
        $statement->execute($parameters);
        flash('success', $id === null ? 'Timetable period created.' : 'Timetable period updated.');
        redirect('/admin/timetable.php');
    } catch (Throwable $exception) {
        form_error($exception);
    }
}

if ($editId !== null) {
    $statement = db()->prepare('SELECT * FROM timetable WHERE id = :id');
    $statement->execute(['id' => $editId]);
    $edit = $statement->fetch() ?: null;
    if (!is_array($edit)) {
        render_error_page(404, 'Timetable period not found', 'This timetable period is not available.');
    }
}
$classes = class_options();
$teachers = teacher_options();
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$canSave = $classes !== [] && $teachers !== [];
$pageTitle = is_array($edit) ? 'Edit timetable period' : 'Add timetable period';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading form-page-heading"><div><span class="eyebrow">SCHOOL SCHEDULE</span><h1><?= is_array($edit) ? 'Edit timetable period' : 'Add timetable period' ?></h1><p class="muted">Choose the class, teacher, day, and period time.</p></div><a class="btn btn-secondary" href="<?= h(app_url('/admin/timetable.php')) ?>"><?= icon_svg('arrow-left') ?>Back to timetable</a></section>
<?php if (!$canSave): ?><div class="notice notice-warning" role="status">Add at least one class and one active teacher before scheduling a period. <a href="<?= h(app_url('/admin/classes.php')) ?>">Add a class</a> · <a href="<?= h(app_url('/admin/teachers.php')) ?>">Add a teacher</a></div><?php endif; ?>
<section class="card content-card"><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
<label>Class<select name="class_id" required><option value="">Choose</option><?php foreach ($classes as $class): ?><option value="<?= h($class['id']) ?>"<?= selected(form_value('class_id', $edit['class_id'] ?? ''), $class['id']) ?>><?= h($class['class_name'] . ' · ' . $class['section']) ?></option><?php endforeach; ?></select></label>
<label>Teacher<select name="teacher_id" required><option value="">Choose</option><?php foreach ($teachers as $teacher): ?><option value="<?= h($teacher['id']) ?>"<?= selected(form_value('teacher_id', $edit['teacher_id'] ?? ''), $teacher['id']) ?>><?= h($teacher['name']) ?></option><?php endforeach; ?></select></label>
<label>Subject<input name="subject" maxlength="120" required value="<?= h(form_value('subject', $edit['subject'] ?? '')) ?>"></label><label>Day<select name="day_of_week" required><option value="">Choose</option><?php foreach ($days as $number => $day): ?><option value="<?= $number ?>"<?= selected(form_value('day_of_week', $edit['day_of_week'] ?? ''), (string) $number) ?>><?= h($day) ?></option><?php endforeach; ?></select></label>
<label>Start time<input type="time" name="start_time" required value="<?= h(form_value('start_time', isset($edit['start_time']) ? substr((string) $edit['start_time'], 0, 5) : '')) ?>"></label><label>End time<input type="time" name="end_time" required value="<?= h(form_value('end_time', isset($edit['end_time']) ? substr((string) $edit['end_time'], 0, 5) : '')) ?>"></label><label>Room<input name="room" maxlength="60" value="<?= h(form_value('room', $edit['room'] ?? '')) ?>"></label>
<div class="action-list span-2"><a class="btn btn-secondary" href="<?= h(app_url('/admin/timetable.php')) ?>">Cancel</a><button class="btn btn-primary" type="submit"<?= $canSave ? '' : ' disabled' ?>><?= is_array($edit) ? 'Save changes' : 'Save period' ?></button></div></form></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
