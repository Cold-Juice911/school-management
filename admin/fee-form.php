<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$activeNavigationHref = '/admin/fees.php';
$students = db()->query('SELECT s.id, s.admission_no, u.name FROM students s JOIN users u ON u.id = s.user_id WHERE u.is_active = 1 ORDER BY u.name')->fetchAll();

if (is_post()) {
    try {
        verify_csrf();
        if (post_string('action') !== 'create_fee') {
            throw new InvalidArgumentException('Unknown fee action.');
        }
        $studentId = filter_var($_POST['student_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $description = post_string('description', 180);
        $amountRaw = post_string('amount', 20);
        if ($studentId === false || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $amountRaw) || (float) $amountRaw <= 0) {
            throw new InvalidArgumentException('Select a student and enter a positive amount up to two decimal places.');
        }
        $dueRaw = post_string('due_date', 10, false);
        $dueDate = $dueRaw === '' ? null : valid_date($dueRaw);
        if ($dueRaw !== '' && $dueDate === null) {
            throw new InvalidArgumentException('Enter a valid due date.');
        }
        $statement = db()->prepare('INSERT INTO fees (student_id, description, amount, due_date) SELECT s.id, :description, :amount, :due_date FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = :student_id AND u.role = \'student\' AND u.is_active = 1');
        $statement->execute(['student_id' => $studentId, 'description' => $description, 'amount' => $amountRaw, 'due_date' => $dueDate]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Choose an active student account.');
        }
        flash('success', 'Fee record created.');
        redirect('/admin/fees.php');
    } catch (Throwable $exception) {
        form_error($exception);
    }
}

$canCreate = $students !== [];
$pageTitle = 'Add fee record';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading form-page-heading"><div><span class="eyebrow">SCHOOL FINANCE</span><h1>Add fee record</h1><p class="muted">Assign a fee to an active student account.</p></div><a class="btn btn-secondary" href="<?= h(app_url('/admin/fees.php')) ?>"><?= icon_svg('arrow-left') ?>Back to fees</a></section>
<?php if (!$canCreate): ?><div class="notice notice-warning" role="status">Add a student account before creating a fee record. <a href="<?= h(app_url('/admin/student-form.php')) ?>">Add a student</a></div><?php endif; ?>
<section class="card content-card"><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="create_fee"><label>Student<select name="student_id" required><option value="">Choose a student</option><?php foreach ($students as $student): ?><option value="<?= h($student['id']) ?>"<?= selected(form_value('student_id'), $student['id']) ?>><?= h($student['name'] . ' · ' . $student['admission_no']) ?></option><?php endforeach; ?></select></label><label>Description<input name="description" maxlength="180" required placeholder="Term 1 tuition" value="<?= h(form_value('description')) ?>"></label><label>Total amount<input type="number" name="amount" min="0.01" max="99999999.99" step="0.01" required value="<?= h(form_value('amount')) ?>"></label><label>Due date<input type="date" name="due_date" value="<?= h(form_value('due_date')) ?>"></label><div class="action-list span-2"><a class="btn btn-secondary" href="<?= h(app_url('/admin/fees.php')) ?>">Cancel</a><button class="btn btn-primary" type="submit"<?= $canCreate ? '' : ' disabled' ?>>Save fee record</button></div></form></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
