<?php
declare(strict_types=1);

function h(string|int|float|null $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path), true, 303);
    exit;
}

function render_error_page(int $statusCode, string $title, string $message): never
{
    http_response_code($statusCode);
    $stylesheet = h(app_url('/assets/css/styles.css'));
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($title) . ' · School Management</title><link rel="stylesheet" href="' . $stylesheet . '"></head><body><header class="site-header card"><a class="brand" href="' . h(app_url('/')) . '">School Management</a></header><main class="error-content"><section class="card content-card"><span class="eyebrow">' . $statusCode . '</span><h1>' . h($title) . '</h1><p>' . h($message) . '</p><a class="btn btn-primary" href="' . h(app_url('/')) . '">Return to the portal</a></section></main></body></html>';
    exit;
}

function app_url(string $path): string
{
    $currentDirectory = str_replace('\\', '/', dirname(defined('APP_REQUEST_PATH') ? APP_REQUEST_PATH : (string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $isRoleDirectory = in_array(basename($currentDirectory), ['admin', 'teacher', 'student', 'scripts'], true);
    $relativePrefix = $isRoleDirectory ? '../' : './';

    return $relativePrefix . ltrim($path, '/');
}

function icon_svg(string $name): string
{
    $icons = [
        'students' => '<circle cx="9" cy="8" r="3.25"/><path d="M3.5 19v-1a5.5 5.5 0 0 1 11 0v1z"/><path d="M16 5.2a3.2 3.2 0 0 1 0 6.2M18 14a4.4 4.4 0 0 1 2.5 4v1"/>',
        'teachers' => '<path d="M3 5.5A2.5 2.5 0 0 1 5.5 3H12v17H5.5A2.5 2.5 0 0 0 3 22z"/><path d="M21 5.5A2.5 2.5 0 0 0 18.5 3H12v17h6.5A2.5 2.5 0 0 1 21 22z"/><path d="M6.5 7H9M15 7h2.5"/>',
        'school' => '<path d="m3 9 9-5 9 5-9 5z"/><path d="M5 10.5V16c3.8 3 10.2 3 14 0v-5.5M21 9v7"/><path d="M21 16h-2"/>',
        'attendance' => '<rect x="4" y="3.5" width="16" height="17" rx="2"/><path d="M8 8h8M8 12h3M8 16h2"/><path d="m14 15 1.5 1.5L19 13"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h2M14 14h2M8 17h2"/>',
        'wallet' => '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 9h18M16 14h2"/><path d="M3 5.5 17 3"/>',
        'class' => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v18H6.5A2.5 2.5 0 0 0 4 22z"/><path d="M4 4.5V22M8 7h8M8 11h8"/>',
        'progress' => '<path d="M4 20V11M10 20V5M16 20v-8M22 20H2"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow-left' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    ];
    $paths = $icons[$name] ?? $icons['students'];

    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
}

function form_value(string $key, string|int|float|null $fallback = null): string
{
    $value = $_POST[$key] ?? null;
    if (is_string($value) || is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return (string) ($fallback ?? '');
}

function form_selection_value(string $key, int|string $index, string $fallback): string
{
    $values = $_POST[$key] ?? null;
    if (is_array($values)) {
        $value = $values[$index] ?? null;
        if (is_string($value)) {
            return $value;
        }
    }
    return $fallback;
}

function add_page_link(string $href, string $recordLabel): string
{
    return '<a class="btn btn-primary page-add-button" href="' . h(app_url($href)) . '">' . icon_svg('plus') . '<span>Add ' . h($recordLabel) . '</span></a>';
}

function render_empty_records(string $recordName, string $icon, string $tone, string $formHref): string
{
    $allowedTones = ['profile', 'success', 'warning', 'alert', 'sched'];
    $toneClass = in_array($tone, $allowedTones, true) ? $tone : 'profile';
    return '<section class="card content-card empty-records"><span class="empty-records-icon accent-' . $toneClass . '">' . icon_svg($icon) . '</span><h2>No records found</h2>' . add_page_link($formHref, $recordName) . '</section>';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? null;
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        throw new RuntimeException('Your form session expired. Please go back and try again.');
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function post_string(string $key, int $maxLength = 255, bool $required = true): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException('Please check the submitted form values.');
    }
    $value = trim($value);
    if (($required && $value === '') || strlen($value) > $maxLength) {
        throw new InvalidArgumentException('Please complete the required fields with valid values.');
    }
    return $value;
}

function valid_date(string $date, bool $allowEmpty = false): ?string
{
    if ($allowEmpty && $date === '') {
        return null;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date ? $date : null;
}

function money(float|string|int $amount): string
{
    return '₹' . number_format((float) $amount, 2, '.', ',');
}

function selected(string|int $value, string|int $option): string
{
    return (string) $value === (string) $option ? ' selected' : '';
}

function status_badge(string $status): string
{
    $allowed = ['present', 'absent', 'late', 'pending', 'partial', 'paid', 'active', 'inactive'];
    $normalized = in_array(strtolower($status), $allowed, true) ? strtolower($status) : 'pending';
    return '<span class="badge badge-' . h($normalized) . '">' . h(ucfirst($normalized)) . '</span>';
}

function is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

function form_error(Throwable $exception): void
{
    $driverCode = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
    if ($exception instanceof PDOException && $driverCode === 1062) {
        flash('error', 'That record conflicts with existing information. Check for a duplicate email, admission number, or schedule.');
        return;
    }
    if ($exception instanceof PDOException && $driverCode === 1451) {
        flash('error', 'This record is still referenced by attendance, timetable, or fee history and cannot be removed.');
        return;
    }
    flash('error', $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The request could not be completed. Please check the information and try again.');
}

function fetch_user_profile(int $userId): ?array
{
    $statement = db()->prepare('SELECT id, name, email, role, is_active FROM users WHERE id = :id');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();
    return is_array($user) ? $user : null;
}

function class_options(): array
{
    return db()->query('SELECT id, class_name, section FROM classes ORDER BY class_name, section')->fetchAll();
}

function teacher_options(): array
{
    return db()->query('SELECT t.id, u.name FROM teachers t JOIN users u ON u.id = t.user_id WHERE u.is_active = 1 ORDER BY u.name')->fetchAll();
}

function student_class_id(int $userId): ?int
{
    $statement = db()->prepare('SELECT class_id FROM students WHERE user_id = :user_id');
    $statement->execute(['user_id' => $userId]);
    $value = $statement->fetchColumn();
    return $value === false || $value === null ? null : (int) $value;
}

function teacher_id_for_user(int $userId): ?int
{
    $statement = db()->prepare('SELECT id FROM teachers WHERE user_id = :user_id');
    $statement->execute(['user_id' => $userId]);
    $value = $statement->fetchColumn();
    return $value === false ? null : (int) $value;
}

function teacher_can_access_class(int $teacherId, int $classId): bool
{
    $statement = db()->prepare('SELECT 1 FROM timetable WHERE teacher_id = :teacher_id AND class_id = :class_id LIMIT 1');
    $statement->execute(['teacher_id' => $teacherId, 'class_id' => $classId]);
    return $statement->fetchColumn() !== false;
}

function attendance_percentage(int $studentId): float
{
    $statement = db()->prepare("SELECT COUNT(*) AS total, SUM(status = 'present') AS present FROM attendance WHERE student_id = :student_id");
    $statement->execute(['student_id' => $studentId]);
    $row = $statement->fetch();
    $total = (int) ($row['total'] ?? 0);
    return $total === 0 ? 0.0 : ((int) ($row['present'] ?? 0) / $total) * 100;
}

function student_id_for_user(int $userId): ?int
{
    $statement = db()->prepare('SELECT id FROM students WHERE user_id = :user_id');
    $statement->execute(['user_id' => $userId]);
    $value = $statement->fetchColumn();
    return $value === false ? null : (int) $value;
}

function fee_totals(int $feeId): array
{
    $statement = db()->prepare('SELECT f.amount, COALESCE(SUM(p.amount_paid), 0) AS amount_paid FROM fees f LEFT JOIN fee_payments p ON p.fee_id = f.id WHERE f.id = :fee_id GROUP BY f.id, f.amount');
    $statement->execute(['fee_id' => $feeId]);
    $row = $statement->fetch();
    if (!is_array($row)) {
        throw new InvalidArgumentException('The selected fee record was not found.');
    }
    $total = (float) $row['amount'];
    $paid = (float) $row['amount_paid'];
    return ['amount' => $total, 'paid' => $paid, 'balance' => max(0.0, $total - $paid), 'status' => $paid <= 0.0 ? 'pending' : ($paid >= $total ? 'paid' : 'partial')];
}

function load_payment_receipt(string $receiptNo, ?int $studentUserId = null): ?array
{
    $sql = 'SELECT p.id, p.receipt_no, p.payment_date, p.amount_paid, f.description, f.amount AS fee_amount, s.admission_no, u.name AS student_name, c.class_name, c.section, recorder.name AS recorded_by, (SELECT COALESCE(SUM(previous.amount_paid), 0) FROM fee_payments previous WHERE previous.fee_id = p.fee_id AND previous.id <= p.id) AS paid_to_date FROM fee_payments p JOIN fees f ON f.id = p.fee_id JOIN students s ON s.id = f.student_id JOIN users u ON u.id = s.user_id LEFT JOIN classes c ON c.id = s.class_id JOIN users recorder ON recorder.id = p.received_by WHERE p.receipt_no = :receipt_no';
    $parameters = ['receipt_no' => $receiptNo];
    if ($studentUserId !== null) {
        $sql .= ' AND s.user_id = :student_user_id';
        $parameters['student_user_id'] = $studentUserId;
    }
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    $receipt = $statement->fetch();
    return is_array($receipt) ? $receipt : null;
}

function active_user_count(string $role): int
{
    $statement = db()->prepare('SELECT COUNT(*) FROM users WHERE role = :role AND is_active = 1');
    $statement->execute(['role' => $role]);
    return (int) $statement->fetchColumn();
}

function render_schedule_rows(array $rows, bool $showClass): string
{
    if ($rows === []) {
        return '<p class="empty-state">No more periods are scheduled for today.</p>';
    }
    $html = '<ul class="schedule-list">';
    foreach ($rows as $row) {
        $label = h(substr((string) $row['start_time'], 0, 5) . '–' . substr((string) $row['end_time'], 0, 5) . ' · ' . $row['subject']);
        if ($showClass) {
            $label .= ' <span class="muted">· ' . h($row['class_name'] . ' ' . $row['section']) . '</span>';
        }
        $html .= '<li>' . $label . '</li>';
    }
    return $html . '</ul>';
}
