<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$pageTitle = 'Teachers';

if (is_post()) {
    $pdo = null;
    try {
        verify_csrf();
        $action = post_string('action');
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('Teacher record not found.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        if ($action === 'set_status') {
            $activeRaw = post_string('is_active', 1);
            $isActive = filter_var($activeRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
            if ($isActive === false) {
                throw new InvalidArgumentException('Choose a valid teacher account status.');
            }
            $teacherQuery = $pdo->prepare("SELECT u.is_active FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.id = :id AND u.role = 'teacher' FOR UPDATE");
            $teacherQuery->execute(['id' => $id]);
            $currentStatus = $teacherQuery->fetchColumn();
            if ($currentStatus === false) {
                throw new InvalidArgumentException('Teacher record not found.');
            }
            if ((int) $currentStatus !== $isActive) {
                $update = $pdo->prepare("UPDATE users u JOIN teachers t ON t.user_id = u.id SET u.is_active = :is_active WHERE t.id = :id AND u.role = 'teacher'");
                $update->execute(['is_active' => $isActive, 'id' => $id]);
            }
            $pdo->commit();
            flash('success', $isActive === 1 ? 'Teacher account activated.' : 'Teacher account deactivated.');
            redirect('/admin/teachers.php');
        }

        if ($action !== 'delete') {
            throw new InvalidArgumentException('Unknown teacher action.');
        }
        $teacherQuery = $pdo->prepare("SELECT t.user_id FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.id = :id AND u.role = 'teacher' FOR UPDATE");
        $teacherQuery->execute(['id' => $id]);
        $userId = $teacherQuery->fetchColumn();
        if ($userId === false) {
            throw new InvalidArgumentException('Teacher record not found.');
        }
        $userId = (int) $userId;
        $deletePeriods = $pdo->prepare('DELETE FROM timetable WHERE teacher_id = :teacher_id');
        $deletePeriods->execute(['teacher_id' => $id]);
        $deleteAttendance = $pdo->prepare('DELETE FROM attendance WHERE marked_by = :user_id');
        $deleteAttendance->execute(['user_id' => $userId]);
        $deletePayments = $pdo->prepare('DELETE FROM fee_payments WHERE received_by = :user_id');
        $deletePayments->execute(['user_id' => $userId]);
        $deleteTeacher = $pdo->prepare('DELETE FROM teachers WHERE id = :id AND user_id = :user_id');
        $deleteTeacher->execute(['id' => $id, 'user_id' => $userId]);
        if ($deleteTeacher->rowCount() !== 1) {
            throw new InvalidArgumentException('Teacher record not found.');
        }
        $deleteUser = $pdo->prepare("DELETE FROM users WHERE id = :user_id AND role = 'teacher'");
        $deleteUser->execute(['user_id' => $userId]);
        if ($deleteUser->rowCount() !== 1) {
            throw new RuntimeException('The teacher account could not be removed.');
        }
        $pdo->commit();
        flash('success', 'Teacher account and related records permanently deleted.');
        redirect('/admin/teachers.php');
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        form_error($exception);
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$pattern = '%' . $search . '%';
$statement = db()->prepare('SELECT t.id, t.phone, t.qualification, t.joining_date, u.name, u.email, u.is_active FROM teachers t JOIN users u ON u.id = t.user_id WHERE (:search = \'%%\' OR u.name LIKE :name_search OR u.email LIKE :email_search) ORDER BY u.name');
$statement->execute(['search' => $search === '' ? '%%' : $pattern, 'name_search' => $pattern, 'email_search' => $pattern]);
$teachers = $statement->fetchAll();
$teacherCount = (int) db()->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Teachers</h1><p class="muted">Manage teacher accounts and contact information.</p></div><?= add_page_link('/admin/teacher-form.php', 'teacher') ?></section>
<?php if ($teacherCount === 0): ?>
    <?= render_empty_records('teacher', 'teachers', 'success', '/admin/teacher-form.php') ?>
<?php else: ?>
    <section class="card content-card"><h2>Teacher directory</h2><form method="get" class="filter-form"><label>Search by name or email<input name="q" value="<?= h($search) ?>" placeholder="Name or email"></label><button class="btn btn-secondary" type="submit">Search</button></form>
        <?php if ($teachers === []): ?><p class="empty-state">No teachers match this search.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Qualification</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($teachers as $teacher): $isActive = (int) $teacher['is_active'] === 1; $detailHref = app_url('/admin/teacher-view.php?id=' . (int) $teacher['id']); ?><tr data-row-href="<?= h($detailHref) ?>"><td><a class="table-row-link" href="<?= h($detailHref) ?>"><?= h($teacher['name']) ?></a></td><td><?= h($teacher['email']) ?></td><td><?= h($teacher['phone']) ?></td><td><?= h($teacher['qualification']) ?></td><td><?= status_badge($isActive ? 'active' : 'inactive') ?></td><td><div class="table-actions"><form method="post" class="inline-form" data-confirm="<?= h(($isActive ? 'Deactivate account for ' : 'Activate account for ') . $teacher['name'] . '?') ?>" data-confirm-title="<?= $isActive ? 'Deactivate teacher?' : 'Activate teacher?' ?>" data-confirm-label="<?= $isActive ? 'Deactivate' : 'Activate' ?>" data-confirm-variant="<?= $isActive ? 'warning' : 'success' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= h($teacher['id']) ?>"><input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>"><button class="btn btn-<?= $isActive ? 'warning' : 'success' ?> btn-table-action" type="submit"><?= $isActive ? 'Deactivate' : 'Activate' ?></button></form><form method="post" class="inline-form" data-confirm="Permanently delete <?= h($teacher['name']) ?>? This also removes their timetable periods, attendance they marked, and payment entries they recorded." data-confirm-title="Delete teacher permanently?" data-confirm-label="Delete permanently" data-confirm-variant="danger"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($teacher['id']) ?>"><button class="btn btn-danger btn-table-action" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
