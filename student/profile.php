<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
$user = require_roles('student');
$pageTitle = 'My profile';
$statement = db()->prepare('SELECT s.admission_no, s.date_of_birth, s.guardian_name, s.phone, s.address, s.admission_date, c.class_name, c.section FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.user_id = :user_id');
$statement->execute(['user_id' => $user['id']]);
$profile = $statement->fetch();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">MY ACCOUNT</span><h1>My profile</h1><p class="muted">Only you can view this student information.</p></div></section>
<section class="card content-card"><?php if (!is_array($profile)): ?><p class="empty-state">Your student profile has not been linked yet. Please contact the school office.</p><?php else: ?><div class="table-scroll"><table><tbody><tr><th>Name</th><td><?= h($user['name']) ?></td></tr><tr><th>Email</th><td><?= h($user['email']) ?></td></tr><tr><th>Admission number</th><td><?= h($profile['admission_no']) ?></td></tr><tr><th>Class</th><td><?= h(($profile['class_name'] ?? 'Unassigned') . (isset($profile['section']) ? ' · ' . $profile['section'] : '')) ?></td></tr><tr><th>Date of birth</th><td><?= h($profile['date_of_birth'] ?? '—') ?></td></tr><tr><th>Guardian</th><td><?= h($profile['guardian_name'] ?? '—') ?></td></tr><tr><th>Phone</th><td><?= h($profile['phone'] ?? '—') ?></td></tr><tr><th>Address</th><td><?= nl2br(h($profile['address'] ?? '—')) ?></td></tr><tr><th>Admission date</th><td><?= h($profile['admission_date'] ?? '—') ?></td></tr></tbody></table></div><?php endif; ?></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
