<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$activeNavigationHref = '/admin/students.php';
$edit = null;
$editId = null;
if (array_key_exists('edit', $_GET)) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId === false) {
        render_error_page(404, 'Student not found', 'This student record is not available.');
    }
}

if (is_post()) {
    $pdo = null;
    try {
        verify_csrf();
        if (post_string('action') !== 'save') {
            throw new InvalidArgumentException('Unknown student action.');
        }
        $idRaw = post_string('id', 20, false);
        $id = $idRaw === '' ? null : filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id !== $editId) {
            throw new InvalidArgumentException('Student record not found.');
        }
        $name = post_string('name', 160);
        $email = strtolower(post_string('email', 190));
        $admissionNo = post_string('admission_no', 60);
        $classRaw = post_string('class_id', 20, false);
        $classId = $classRaw === '' ? null : filter_var($classRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($classRaw !== '' && $classId === false) {
            throw new InvalidArgumentException('Choose a valid class.');
        }
        $dobRaw = post_string('date_of_birth', 10, false);
        $admissionRaw = post_string('admission_date', 10, false);
        $dob = $dobRaw === '' ? null : valid_date($dobRaw);
        $admissionDate = $admissionRaw === '' ? null : valid_date($admissionRaw);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || ($dobRaw !== '' && $dob === null) || ($admissionRaw !== '' && $admissionDate === null)) {
            throw new InvalidArgumentException('Enter a valid email address and valid dates.');
        }
        $phone = post_string('phone', 30, false);
        $guardian = post_string('guardian_name', 160, false);
        $address = post_string('address', 4000, false);
        $pdo = db();
        $pdo->beginTransaction();
        if ($id === null) {
            $password = post_string('password', 1024);
            if (strlen($password) < 10) {
                throw new InvalidArgumentException('Choose an initial password with at least 10 characters.');
            }
            $userInsert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, \'student\')');
            $userInsert->execute(['name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
            $profileInsert = $pdo->prepare('INSERT INTO students (admission_no, user_id, class_id, date_of_birth, guardian_name, phone, address, admission_date) VALUES (:admission_no, :user_id, :class_id, :date_of_birth, :guardian_name, :phone, :address, :admission_date)');
            $profileInsert->execute(['admission_no' => $admissionNo, 'user_id' => (int) $pdo->lastInsertId(), 'class_id' => $classId, 'date_of_birth' => $dob, 'guardian_name' => $guardian ?: null, 'phone' => $phone ?: null, 'address' => $address ?: null, 'admission_date' => $admissionDate]);
        } else {
            $studentQuery = $pdo->prepare('SELECT user_id FROM students WHERE id = :id FOR UPDATE');
            $studentQuery->execute(['id' => $id]);
            $userId = $studentQuery->fetchColumn();
            if ($userId === false) {
                throw new InvalidArgumentException('Student record not found.');
            }
            $userUpdate = $pdo->prepare('UPDATE users SET name = :name, email = :email WHERE id = :user_id AND role = \'student\'');
            $userUpdate->execute(['name' => $name, 'email' => $email, 'user_id' => $userId]);
            $profileUpdate = $pdo->prepare('UPDATE students SET admission_no = :admission_no, class_id = :class_id, date_of_birth = :date_of_birth, guardian_name = :guardian_name, phone = :phone, address = :address, admission_date = :admission_date WHERE id = :id');
            $profileUpdate->execute(['admission_no' => $admissionNo, 'class_id' => $classId, 'date_of_birth' => $dob, 'guardian_name' => $guardian ?: null, 'phone' => $phone ?: null, 'address' => $address ?: null, 'admission_date' => $admissionDate, 'id' => $id]);
        }
        $pdo->commit();
        flash('success', $id === null ? 'Student account created.' : 'Student details updated.');
        redirect('/admin/students.php');
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        form_error($exception);
    }
}

if ($editId !== null) {
    $statement = db()->prepare('SELECT s.*, u.name, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = :id');
    $statement->execute(['id' => $editId]);
    $edit = $statement->fetch() ?: null;
    if (!is_array($edit)) {
        render_error_page(404, 'Student not found', 'This student record is not available.');
    }
}
$classes = class_options();
$pageTitle = is_array($edit) ? 'Edit student' : 'Add student';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading form-page-heading"><div><span class="eyebrow">ADMINISTRATION · STUDENTS</span><h1><?= is_array($edit) ? 'Edit student' : 'Add student' ?></h1><p class="muted">Enter the student account and enrollment details.</p></div><a class="btn btn-secondary" href="<?= h(app_url('/admin/students.php')) ?>"><?= icon_svg('arrow-left') ?>Back to students</a></section>
<section class="card content-card"><form method="post" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
    <label>Name<input name="name" maxlength="160" required value="<?= h(form_value('name', $edit['name'] ?? '')) ?>"></label>
    <label>Email<input type="email" name="email" maxlength="190" required value="<?= h(form_value('email', $edit['email'] ?? '')) ?>"></label>
    <label>Admission number<input name="admission_no" maxlength="60" required value="<?= h(form_value('admission_no', $edit['admission_no'] ?? '')) ?>"></label>
    <?php if (!is_array($edit)): ?><label>Initial password<input type="password" name="password" minlength="10" autocomplete="new-password" required></label><?php endif; ?>
    <label>Class and section<select name="class_id"><option value="">Unassigned</option><?php foreach ($classes as $class): ?><option value="<?= h($class['id']) ?>"<?= selected(form_value('class_id', $edit['class_id'] ?? ''), $class['id']) ?>><?= h($class['class_name'] . ' · ' . $class['section']) ?></option><?php endforeach; ?></select></label>
    <label>Date of birth<input type="date" name="date_of_birth" value="<?= h(form_value('date_of_birth', $edit['date_of_birth'] ?? '')) ?>"></label>
    <label>Admission date<input type="date" name="admission_date" value="<?= h(form_value('admission_date', $edit['admission_date'] ?? '')) ?>"></label>
    <label>Guardian name<input name="guardian_name" maxlength="160" value="<?= h(form_value('guardian_name', $edit['guardian_name'] ?? '')) ?>"></label>
    <label>Phone<input name="phone" maxlength="30" value="<?= h(form_value('phone', $edit['phone'] ?? '')) ?>"></label>
    <label class="span-2">Address<textarea name="address" maxlength="4000"><?= h(form_value('address', $edit['address'] ?? '')) ?></textarea></label>
    <div class="action-list span-2"><a class="btn btn-secondary" href="<?= h(app_url('/admin/students.php')) ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= is_array($edit) ? 'Save changes' : 'Save student' ?></button></div>
</form></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
