<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
$user = require_roles('admin');
$activeNavigationHref = '/admin/fees.php';
$receiptNo = $_GET['receipt_no'] ?? null;
if (!is_string($receiptNo) || $receiptNo === '' || strlen($receiptNo) > 40) render_error_page(404, 'Receipt not found', 'This receipt reference is not available.');
$receipt = load_payment_receipt($receiptNo);
if ($receipt === null) render_error_page(404, 'Receipt not found', 'This receipt reference is not available.');
$pageTitle = 'Fee receipt';
require dirname(__DIR__) . '/includes/header.php';
require dirname(__DIR__) . '/includes/receipt-view.php';
require dirname(__DIR__) . '/includes/footer.php';
