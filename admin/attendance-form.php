<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
$actor = require_roles('admin');
$activeNavigationHref = '/admin/attendance.php';
$classes = class_options();
$selectedClassRaw = $_GET['class_id'] ?? null;
$classId = $selectedClassRaw === null ? null : filter_var($selectedClassRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($classId === false) {
    render_error_page(400, 'Invalid class', 'Choose a valid class to mark attendance.');
}
$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (valid_date($date) === null) {
    $date = date('Y-m-d');
}
$roster = [];
if ($classId !== null) {
    $knownClass = false;
    foreach ($classes as $class) {
        if ((int) $class['id'] === $classId) {
            $knownClass = true;
            break;
        }
    }
    if (!$knownClass) {
        render_error_page(404, 'Class not found', 'This class is not available.');
    }
    $statement = db()->prepare('SELECT s.id, s.admission_no, u.name, a.status FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN attendance a ON a.student_id = s.id AND a.attendance_date = :attendance_date WHERE s.class_id = :class_id AND u.is_active = 1 ORDER BY u.name');
    $statement->execute(['attendance_date' => $date, 'class_id' => $classId]);
    $roster = $statement->fetchAll();
}

if (is_post()) {
    $pdo = null;
    try {
        verify_csrf();
        if (post_string('action') !== 'save') {
            throw new InvalidArgumentException('Unknown attendance action.');
        }
        $postedClassId = filter_var($_POST['class_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $postedDate = is_string($_POST['attendance_date'] ?? null) ? (string) $_POST['attendance_date'] : '';
        $statuses = $_POST['status'] ?? null;
        if ($postedClassId === false || valid_date($postedDate) === null || !is_array($statuses)) {
            throw new InvalidArgumentException('Choose a class, date, and attendance status for every student.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        $rosterStatement = $pdo->prepare('SELECT s.id FROM students s JOIN users u ON u.id = s.user_id WHERE s.class_id = :class_id AND u.is_active = 1 ORDER BY s.id');
        $rosterStatement->execute(['class_id' => $postedClassId]);
        $studentIds = array_map('intval', $rosterStatement->fetchAll(PDO::FETCH_COLUMN));
        $statusIds = array_map('intval', array_keys($statuses));
        sort($studentIds);
        sort($statusIds);
        if ($studentIds === [] || $studentIds !== $statusIds) {
            throw new InvalidArgumentException('The attendance form does not match the current class roster. Reload and try again.');
        }
        $save = $pdo->prepare('INSERT INTO attendance (student_id, attendance_date, status, marked_by) VALUES (:student_id, :attendance_date, :status, :marked_by) ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)');
        foreach ($statuses as $studentId => $status) {
            if (!is_string($status) || !in_array($status, ['present', 'absent', 'late'], true)) {
                throw new InvalidArgumentException('Choose a valid attendance status for every student.');
            }
            $save->execute(['student_id' => (int) $studentId, 'attendance_date' => $postedDate, 'status' => $status, 'marked_by' => $actor['id']]);
        }
        $pdo->commit();
        flash('success', 'Attendance saved for ' . count($studentIds) . ' students.');
        redirect('/admin/attendance.php?report_class_id=' . $postedClassId . '&report_from=' . rawurlencode($postedDate) . '&report_to=' . rawurlencode($postedDate));
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        form_error($exception);
    }
}

$pageTitle = 'Mark attendance';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading form-page-heading"><div><span class="eyebrow">SCHOOL RECORDS</span><h1>Mark attendance</h1><p class="muted">Choose a class and date to load its active student roster.</p></div><a class="btn btn-secondary" href="<?= h(app_url('/admin/attendance.php')) ?>"><?= icon_svg('arrow-left') ?>Back to attendance</a></section>
<?php if ($classes === []): ?><div class="notice notice-warning" role="status">Add a class before marking attendance. <a href="<?= h(app_url('/admin/class-form.php')) ?>">Add a class</a></div><?php endif; ?>
<section class="card content-card"><form method="get" class="filter-form"><label>Class<select name="class_id" required><option value="">Choose a class</option><?php foreach ($classes as $class): ?><option value="<?= h($class['id']) ?>"<?= selected((string) ($classId ?? ''), $class['id']) ?>><?= h($class['class_name'] . ' · ' . $class['section']) ?></option><?php endforeach; ?></select></label><label>Date<input type="date" name="date" value="<?= h($date) ?>" required></label><button class="btn btn-secondary" type="submit"<?= $classes === [] ? ' disabled' : '' ?>>Load roster</button></form></section>
<?php if ($classId !== null): ?>
    <section class="card content-card"><h2>Class roll · <?= h($date) ?></h2>
        <?php if ($roster === []): ?><p class="empty-state">No active students are assigned to this class.</p><a href="<?= h(app_url('/admin/students.php')) ?>">View students</a>
        <?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="class_id" value="<?= h($classId) ?>"><input type="hidden" name="attendance_date" value="<?= h($date) ?>"><div class="table-scroll"><table><thead><tr><th>Student</th><th>Admission no.</th><th>Status</th></tr></thead><tbody><?php foreach ($roster as $student): $currentStatus = form_selection_value('status', $student['id'], (string) ($student['status'] ?? 'present')); ?><tr><td><?= h($student['name']) ?></td><td><?= h($student['admission_no']) ?></td><td><label><span class="sr-only">Attendance for <?= h($student['name']) ?></span><select name="status[<?= h($student['id']) ?>]"><?php foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late'] as $value => $label): ?><option value="<?= h($value) ?>"<?= selected($currentStatus, $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></label></td></tr><?php endforeach; ?></tbody></table></div><div class="action-list"><a class="btn btn-secondary" href="<?= h(app_url('/admin/attendance.php')) ?>">Cancel</a><button class="btn btn-primary" type="submit">Save attendance</button></div></form><?php endif; ?>
    </section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
