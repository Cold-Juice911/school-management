<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$pageTitle = 'Students';

if (is_post()) {
    $pdo = null;
    try {
        verify_csrf();
        $action = post_string('action');
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('Student record not found.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        if ($action === 'set_status') {
            $activeRaw = post_string('is_active', 1);
            $isActive = filter_var($activeRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
            if ($isActive === false) {
                throw new InvalidArgumentException('Choose a valid student account status.');
            }
            $studentQuery = $pdo->prepare("SELECT u.is_active FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = :id AND u.role = 'student' FOR UPDATE");
            $studentQuery->execute(['id' => $id]);
            $currentStatus = $studentQuery->fetchColumn();
            if ($currentStatus === false) {
                throw new InvalidArgumentException('Student record not found.');
            }
            if ((int) $currentStatus !== $isActive) {
                $update = $pdo->prepare("UPDATE users u JOIN students s ON s.user_id = u.id SET u.is_active = :is_active WHERE s.id = :id AND u.role = 'student'");
                $update->execute(['is_active' => $isActive, 'id' => $id]);
            }
            $pdo->commit();
            flash('success', $isActive === 1 ? 'Student account activated.' : 'Student account deactivated.');
            redirect('/admin/students.php');
        }

        if ($action !== 'delete') {
            throw new InvalidArgumentException('Unknown student action.');
        }
        $studentQuery = $pdo->prepare("SELECT s.user_id, u.name FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = :id AND u.role = 'student' FOR UPDATE");
        $studentQuery->execute(['id' => $id]);
        $student = $studentQuery->fetch();
        if (!is_array($student)) {
            throw new InvalidArgumentException('Student record not found.');
        }
        $userId = (int) $student['user_id'];
        $deleteAttendance = $pdo->prepare('DELETE FROM attendance WHERE student_id = :student_id OR marked_by = :user_id');
        $deleteAttendance->execute(['student_id' => $id, 'user_id' => $userId]);
        $deletePayments = $pdo->prepare('DELETE fp FROM fee_payments AS fp LEFT JOIN fees AS f ON f.id = fp.fee_id WHERE f.student_id = :student_id OR fp.received_by = :user_id');
        $deletePayments->execute(['student_id' => $id, 'user_id' => $userId]);
        $deleteFees = $pdo->prepare('DELETE FROM fees WHERE student_id = :student_id');
        $deleteFees->execute(['student_id' => $id]);
        $deleteStudent = $pdo->prepare('DELETE FROM students WHERE id = :id AND user_id = :user_id');
        $deleteStudent->execute(['id' => $id, 'user_id' => $userId]);
        if ($deleteStudent->rowCount() !== 1) {
            throw new InvalidArgumentException('Student record not found.');
        }
        $deleteUser = $pdo->prepare("DELETE FROM users WHERE id = :user_id AND role = 'student'");
        $deleteUser->execute(['user_id' => $userId]);
        if ($deleteUser->rowCount() !== 1) {
            throw new RuntimeException('The student account could not be removed.');
        }
        $pdo->commit();
        flash('success', 'Student account and related records permanently deleted.');
        redirect('/admin/students.php');
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        form_error($exception);
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$classFilter = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selectedClass = $classFilter === false ? null : $classFilter;
$pattern = '%' . $search . '%';
$statement = db()->prepare('SELECT s.id, s.admission_no, s.class_id, u.name, u.email, u.is_active, c.class_name, c.section FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN classes c ON c.id = s.class_id WHERE (:search = \'%%\' OR u.name LIKE :name_search OR s.admission_no LIKE :number_search) AND (:class_id IS NULL OR s.class_id = :class_filter) ORDER BY u.name');
$statement->execute(['search' => $search === '' ? '%%' : $pattern, 'name_search' => $pattern, 'number_search' => $pattern, 'class_id' => $selectedClass, 'class_filter' => $selectedClass]);
$students = $statement->fetchAll();
$studentCount = (int) db()->query('SELECT COUNT(*) FROM students')->fetchColumn();
$classes = class_options();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Students</h1><p class="muted">Manage student accounts and enrollment records.</p></div><?= add_page_link('/admin/student-form.php', 'student') ?></section>
<?php if ($studentCount === 0): ?>
    <?= render_empty_records('student', 'students', 'profile', '/admin/student-form.php') ?>
<?php else: ?>
    <section class="card content-card"><h2>Student directory</h2><form method="get" class="filter-form"><label>Search<input name="q" value="<?= h($search) ?>" placeholder="Name or admission number"></label><label>Class<select name="class_id"><option value="">All classes</option><?php foreach ($classes as $class): ?><option value="<?= h($class['id']) ?>"<?= selected((string) ($selectedClass ?? ''), $class['id']) ?>><?= h($class['class_name'] . ' · ' . $class['section']) ?></option><?php endforeach; ?></select></label><button class="btn btn-secondary" type="submit">Filter</button></form>
        <?php if ($students === []): ?><p class="empty-state">No students match these filters.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Name</th><th>Admission no.</th><th>Class</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($students as $row): $isActive = (int) $row['is_active'] === 1; $detailHref = app_url('/admin/student-view.php?id=' . (int) $row['id']); ?><tr data-row-href="<?= h($detailHref) ?>"><td><a class="table-row-link" href="<?= h($detailHref) ?>"><?= h($row['name']) ?></a></td><td><?= h($row['admission_no']) ?></td><td><?= h(($row['class_name'] ?? 'Unassigned') . (isset($row['section']) ? ' · ' . $row['section'] : '')) ?></td><td><?= h($row['email']) ?></td><td><?= status_badge($isActive ? 'active' : 'inactive') ?></td><td><div class="table-actions"><form method="post" class="inline-form" data-confirm="<?= h(($isActive ? 'Deactivate account for ' : 'Activate account for ') . $row['name'] . '?') ?>" data-confirm-title="<?= $isActive ? 'Deactivate student?' : 'Activate student?' ?>" data-confirm-label="<?= $isActive ? 'Deactivate' : 'Activate' ?>" data-confirm-variant="<?= $isActive ? 'warning' : 'success' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= h($row['id']) ?>"><input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>"><button class="btn btn-<?= $isActive ? 'warning' : 'success' ?> btn-table-action" type="submit"><?= $isActive ? 'Deactivate' : 'Activate' ?></button></form><form method="post" class="inline-form" data-confirm="Permanently delete <?= h($row['name']) ?> and remove their student profile, attendance, fee records, and related payment history? This cannot be undone." data-confirm-title="Delete student permanently?" data-confirm-label="Delete permanently" data-confirm-variant="danger"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($row['id']) ?>"><button class="btn btn-danger btn-table-action" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
