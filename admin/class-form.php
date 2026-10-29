<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_roles('admin');
$activeNavigationHref = '/admin/classes.php';
$edit = null;
$editId = null;
if (array_key_exists('edit', $_GET)) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId === false) {
        render_error_page(404, 'Class not found', 'This class record is not available.');
    }
}

if (is_post()) {
    try {
        verify_csrf();
        $action = post_string('action');
        if (!in_array($action, ['create', 'edit'], true)) {
            throw new InvalidArgumentException('Unknown class action.');
        }
        if (($editId === null && $action !== 'create') || ($editId !== null && $action !== 'edit')) {
            throw new InvalidArgumentException('Reload the class form and try again.');
        }
        $className = post_string('class_name', 80);
        $section = post_string('section', 40);
        if ($action === 'create') {
            $statement = db()->prepare('INSERT INTO classes (class_name, section) VALUES (:class_name, :section)');
            $statement->execute(['class_name' => $className, 'section' => $section]);
            flash('success', 'Class and section created.');
        } else {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || $id !== $editId) {
                throw new InvalidArgumentException('Class not found.');
            }
            $exists = db()->prepare('SELECT 1 FROM classes WHERE id = :id');
            $exists->execute(['id' => $id]);
            if ($exists->fetchColumn() === false) {
                throw new InvalidArgumentException('Class not found.');
            }
            $statement = db()->prepare('UPDATE classes SET class_name = :class_name, section = :section WHERE id = :id');
            $statement->execute(['class_name' => $className, 'section' => $section, 'id' => $id]);
            flash('success', 'Class and section updated.');
        }
        redirect('/admin/classes.php');
    } catch (Throwable $exception) {
        form_error($exception);
    }
}

if ($editId !== null) {
    $statement = db()->prepare('SELECT id, class_name, section FROM classes WHERE id = :id');
    $statement->execute(['id' => $editId]);
    $edit = $statement->fetch() ?: null;
    if (!is_array($edit)) {
        render_error_page(404, 'Class not found', 'This class record is not available.');
    }
}
$isEditing = is_array($edit);
$pageTitle = $isEditing ? 'Edit class' : 'Add class';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading form-page-heading"><div><span class="eyebrow">ADMINISTRATION · CLASSES</span><h1><?= $isEditing ? 'Edit class' : 'Add class' ?></h1><p class="muted">Enter a class name and section.</p></div><a class="btn btn-secondary" href="<?= h(app_url('/admin/classes.php')) ?>"><?= icon_svg('arrow-left') ?>Back to classes</a></section>
<section class="card content-card"><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $isEditing ? 'edit' : 'create' ?>"><?php if ($isEditing): ?><input type="hidden" name="id" value="<?= h($edit['id']) ?>"><?php endif; ?><label>Class name<input name="class_name" maxlength="80" required placeholder="Grade 8" value="<?= h(form_value('class_name', $edit['class_name'] ?? '')) ?>"></label><label>Section<input name="section" maxlength="40" required placeholder="A" value="<?= h(form_value('section', $edit['section'] ?? '')) ?>"></label><div class="action-list span-2"><a class="btn btn-secondary" href="<?= h(app_url('/admin/classes.php')) ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= $isEditing ? 'Save changes' : 'Save class' ?></button></div></form></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
