<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$activeNavigationHref = '/admin/teachers.php';
$edit = null;
$editId = null;
if (array_key_exists('edit', $_GET)) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId === false) {
        render_error_page(404, 'Teacher not found', 'This teacher record is not available.');
    }
}

if (is_post()) {
    $pdo = null;
    try {
        verify_csrf();
        if (post_string('action') !== 'save') {
            throw new InvalidArgumentException('Unknown teacher action.');
        }
        $idRaw = post_string('id', 20, false);
        $id = $idRaw === '' ? null : filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id !== $editId) {
            throw new InvalidArgumentException('Teacher record not found.');
        }
        $name = post_string('name', 160);
        $email = strtolower(post_string('email', 190));
        $phone = post_string('phone', 30, false);
        $qualification = post_string('qualification', 160, false);
        $joinedRaw = post_string('joining_date', 10, false);
        $joined = $joinedRaw === '' ? null : valid_date($joinedRaw);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || ($joinedRaw !== '' && $joined === null)) {
            throw new InvalidArgumentException('Enter a valid email address and joining date.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        if ($id === null) {
            $password = post_string('password', 1024);
            if (strlen($password) < 10) {
                throw new InvalidArgumentException('Choose an initial password with at least 10 characters.');
            }
            $insertUser = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, \'teacher\')');
            $insertUser->execute(['name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
            $insertTeacher = $pdo->prepare('INSERT INTO teachers (user_id, phone, qualification, joining_date) VALUES (:user_id, :phone, :qualification, :joining_date)');
            $insertTeacher->execute(['user_id' => (int) $pdo->lastInsertId(), 'phone' => $phone ?: null, 'qualification' => $qualification ?: null, 'joining_date' => $joined]);
        } else {
            $find = $pdo->prepare('SELECT user_id FROM teachers WHERE id = :id FOR UPDATE');
            $find->execute(['id' => $id]);
            $userId = $find->fetchColumn();
            if ($userId === false) {
                throw new InvalidArgumentException('Teacher record not found.');
            }
            $updateUser = $pdo->prepare('UPDATE users SET name = :name, email = :email WHERE id = :user_id AND role = \'teacher\'');
            $updateUser->execute(['name' => $name, 'email' => $email, 'user_id' => $userId]);
            $updateTeacher = $pdo->prepare('UPDATE teachers SET phone = :phone, qualification = :qualification, joining_date = :joining_date WHERE id = :id');
            $updateTeacher->execute(['phone' => $phone ?: null, 'qualification' => $qualification ?: null, 'joining_date' => $joined, 'id' => $id]);
        }
        $pdo->commit();
        flash('success', $id === null ? 'Teacher account created.' : 'Teacher details updated.');
        redirect('/admin/teachers.php');
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        form_error($exception);
    }
}

if ($editId !== null) {
    $statement = db()->prepare('SELECT t.*, u.name, u.email FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.id = :id');
    $statement->execute(['id' => $editId]);
    $edit = $statement->fetch() ?: null;
    if (!is_array($edit)) {
        render_error_page(404, 'Teacher not found', 'This teacher record is not available.');
    }
}
$pageTitle = is_array($edit) ? 'Edit teacher' : 'Add teacher';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading form-page-heading"><div><span class="eyebrow">ADMINISTRATION · TEACHERS</span><h1><?= is_array($edit) ? 'Edit teacher' : 'Add teacher' ?></h1><p class="muted">Enter the teacher account and contact information.</p></div><a class="btn btn-secondary" href="<?= h(app_url('/admin/teachers.php')) ?>"><?= icon_svg('arrow-left') ?>Back to teachers</a></section>
<section class="card content-card"><form method="post" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
    <label>Name<input name="name" maxlength="160" required value="<?= h(form_value('name', $edit['name'] ?? '')) ?>"></label>
    <label>Email<input type="email" name="email" maxlength="190" required value="<?= h(form_value('email', $edit['email'] ?? '')) ?>"></label>
    <?php if (!is_array($edit)): ?><label>Initial password<input type="password" name="password" minlength="10" autocomplete="new-password" required></label><?php endif; ?>
    <label>Phone<input name="phone" maxlength="30" value="<?= h(form_value('phone', $edit['phone'] ?? '')) ?>"></label>
    <label>Qualification<input name="qualification" maxlength="160" value="<?= h(form_value('qualification', $edit['qualification'] ?? '')) ?>"></label>
    <label>Joining date<input type="date" name="joining_date" value="<?= h(form_value('joining_date', $edit['joining_date'] ?? '')) ?>"></label>
    <div class="action-list span-2"><a class="btn btn-secondary" href="<?= h(app_url('/admin/teachers.php')) ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= is_array($edit) ? 'Save changes' : 'Save teacher' ?></button></div>
</form></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
