<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
$teacher = require_roles('teacher');
$teacherId = teacher_id_for_user((int) $teacher['id']);
if ($teacherId === null) render_error_page(403, 'Teacher profile unavailable', 'Your account does not have a linked teacher profile. Contact a school administrator.');
$pageTitle = 'Class attendance';
$statement = db()->prepare('SELECT DISTINCT c.id, c.class_name, c.section FROM timetable t JOIN classes c ON c.id = t.class_id WHERE t.teacher_id = :teacher_id ORDER BY c.class_name, c.section');
$statement->execute(['teacher_id' => $teacherId]);
$classes = $statement->fetchAll();
$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (valid_date($date) === null) $date = date('Y-m-d');
if (is_post()) {
    try {
        verify_csrf();
        $postedClassId = filter_var($_POST['class_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $postedDate = is_string($_POST['attendance_date'] ?? null) ? (string) $_POST['attendance_date'] : '';
        $statuses = $_POST['status'] ?? null;
        if ($postedClassId === false || !teacher_can_access_class($teacherId, $postedClassId) || valid_date($postedDate) === null || !is_array($statuses)) throw new InvalidArgumentException('You cannot mark attendance for this class or date.');
        $pdo = db();
        $pdo->beginTransaction();
        $query = $pdo->prepare('SELECT s.id FROM students s JOIN users u ON u.id = s.user_id WHERE s.class_id = :class_id AND u.is_active = 1 ORDER BY s.id');
        $query->execute(['class_id' => $postedClassId]);
        $studentIds = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
        $statusIds = array_map('intval', array_keys($statuses));
        sort($studentIds); sort($statusIds);
        if ($studentIds === [] || $studentIds !== $statusIds) throw new InvalidArgumentException('The roster changed. Reload the page before saving.');
        $save = $pdo->prepare('INSERT INTO attendance (student_id, attendance_date, status, marked_by) VALUES (:student_id, :attendance_date, :status, :marked_by) ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)');
        foreach ($statuses as $studentId => $status) {
            if (!is_string($status) || !in_array($status, ['present', 'absent', 'late'], true)) throw new InvalidArgumentException('Select a valid status for every student.');
            $save->execute(['student_id' => (int) $studentId, 'attendance_date' => $postedDate, 'status' => $status, 'marked_by' => $teacher['id']]);
        }
        $pdo->commit();
        flash('success', 'Attendance saved.');
        redirect('/teacher/attendance.php?class_id=' . $postedClassId . '&date=' . rawurlencode($postedDate));
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        form_error($exception);
    }
}
$roster = [];
if ($classId !== null && $classId !== false && teacher_can_access_class($teacherId, $classId)) {
    $query = db()->prepare('SELECT s.id, s.admission_no, u.name, a.status FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN attendance a ON a.student_id = s.id AND a.attendance_date = :date WHERE s.class_id = :class_id AND u.is_active = 1 ORDER BY u.name');
    $query->execute(['date' => $date, 'class_id' => $classId]);
    $roster = $query->fetchAll();
} elseif ($classId !== null && $classId !== false) { render_error_page(403, 'Class access denied', 'You are not assigned to this class.'); }
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">TEACHER TOOLS</span><h1>Class attendance</h1><p class="muted">Only classes on your assigned timetable are available here.</p></div></section>
<section class="card content-card"><form method="get" class="filter-form"><label>Class<select name="class_id" required><option value="">Choose a class</option><?php foreach ($classes as $class): ?><option value="<?= h($class['id']) ?>"<?= selected((string) ($classId ?: ''), $class['id']) ?>><?= h($class['class_name'] . ' · ' . $class['section']) ?></option><?php endforeach; ?></select></label><label>Date<input type="date" name="date" value="<?= h($date) ?>" required></label><button class="btn btn-secondary" type="submit">Load class</button></form></section>
<?php if ($classId !== null && $classId !== false): ?><section class="card content-card"><h2>Class roll · <?= h($date) ?></h2><?php if ($roster === []): ?><p class="empty-state">No students are enrolled in this class.</p><?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="class_id" value="<?= h($classId) ?>"><input type="hidden" name="attendance_date" value="<?= h($date) ?>"><div class="table-scroll"><table><thead><tr><th>Student</th><th>Admission no.</th><th>Status</th></tr></thead><tbody><?php foreach ($roster as $student): ?><tr><td><?= h($student['name']) ?></td><td><?= h($student['admission_no']) ?></td><td><label><span class="sr-only">Attendance for <?= h($student['name']) ?></span><select name="status[<?= h($student['id']) ?>]"><?php foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late'] as $value => $label): ?><option value="<?= h($value) ?>"<?= selected((string) ($student['status'] ?? 'present'), $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></label></td></tr><?php endforeach; ?></tbody></table></div><div class="action-list attendance-form-actions"><button class="btn btn-primary" type="submit">Save attendance</button></div></form><?php endif; ?></section><?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
