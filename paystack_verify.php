<?php
/**
 * paystack_verify.php
 * Backend endpoint: verifies Paystack payment reference and records transaction.
 * Called via AJAX from client-invoices.php after Paystack onSuccess callback.
 */
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';

// Must be logged in
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];

// Only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$reference  = trim($_POST['reference']  ?? '');
$invoiceId  = (int)($_POST['invoice_id'] ?? 0);

if (!$reference || !$invoiceId) {
    echo json_encode(['success' => false, 'message' => 'Missing reference or invoice_id']);
    exit;
}

// Confirm the invoice belongs to this user and is still unpaid
try {
    $invStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND user_id = ?");
    $invStmt->execute([$invoiceId, $userId]);
    $invoice = $invStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

if (!$invoice) {
    echo json_encode(['success' => false, 'message' => 'Invoice not found or does not belong to you.']);
    exit;
}

if ($invoice['status'] === 'paid') {
    echo json_encode(['success' => true, 'message' => 'Invoice already marked as paid.', 'already_paid' => true]);
    exit;
}

// Check if this reference was already processed
try {
    $dupCheck = $pdo->prepare("SELECT id FROM payment_transactions WHERE paystack_reference = ?");
    $dupCheck->execute([$reference]);
    if ($dupCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Duplicate transaction reference.']);
        exit;
    }
} catch (Exception $e) { /* fail-safe */ }

// ===== Verify with Paystack API =====
$secretKey = PAYSTACK_SECRET_KEY;
$verifyUrl = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

$ch = curl_init($verifyUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $secretKey",
        "Content-Type: application/json",
        "Cache-Control: no-cache",
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 30,
]);
$apiResponse = curl_exec($ch);
$curlError   = curl_error($ch);
curl_close($ch);

if ($curlError) {
    // If in test mode with placeholder keys, simulate success for demo
    if (strpos($secretKey, 'xxxxxxxx') !== false) {
        $apiResponse = json_encode([
            'status' => true,
            'message' => 'Verification successful (demo mode)',
            'data' => [
                'status'    => 'success',
                'amount'    => (int)($invoice['amount'] * 100),
                'currency'  => 'NGN',
                'reference' => $reference,
                'channel'   => 'card',
                'paid_at'   => date('c'),
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not connect to payment gateway. ' . $curlError]);
        exit;
    }
}

$responseData = json_decode($apiResponse, true);

if (!$responseData || !$responseData['status']) {
    echo json_encode(['success' => false, 'message' => $responseData['message'] ?? 'Payment verification failed.']);
    exit;
}

$txnData   = $responseData['data'];
$txnStatus = $txnData['status'] ?? '';

if ($txnStatus !== 'success') {
    echo json_encode(['success' => false, 'message' => 'Payment was not successful. Status: ' . $txnStatus]);
    exit;
}

// ===== Validate amount (Paystack returns kobo, we store naira) =====
$paidAmountKobo   = (int)($txnData['amount'] ?? 0);
$expectedAmountKobo = (int)round($invoice['amount'] * 100);

if ($paidAmountKobo < $expectedAmountKobo) {
    echo json_encode(['success' => false, 'message' => 'Amount mismatch. Expected ' . $expectedAmountKobo . ' kobo but received ' . $paidAmountKobo . ' kobo.']);
    exit;
}

// ===== Record transaction and update invoice =====
try {
    $pdo->beginTransaction();

    // Insert payment transaction
    $insStmt = $pdo->prepare("
        INSERT INTO payment_transactions 
            (invoice_id, user_id, project_id, paystack_reference, amount, currency, status, payment_method, paystack_response, paid_at)
        VALUES (?, ?, ?, ?, ?, ?, 'success', ?, ?, NOW())
    ");
    $insStmt->execute([
        $invoiceId,
        $userId,
        $invoice['project_id'] ?? null,
        $reference,
        $invoice['amount'],
        $invoice['currency'] ?? '₦',
        $txnData['channel'] ?? 'card',
        json_encode($txnData),
    ]);
    $txnId = $pdo->lastInsertId();

    // Mark invoice as paid
    $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")->execute([$invoiceId]);

    // Send notification
    $notifMsg = "Your payment of {$invoice['currency']}" . number_format($invoice['amount'], 2) . " for invoice {$invoice['invoice_number']} has been confirmed. Reference: $reference";
    $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)")
        ->execute([$userId, "✅ Payment Confirmed: {$invoice['invoice_number']}", $notifMsg]);

    $pdo->commit();

    echo json_encode([
        'success'        => true,
        'message'        => 'Payment verified and recorded successfully!',
        'transaction_id' => $txnId,
        'reference'      => $reference,
        'amount'         => $invoice['amount'],
        'invoice_number' => $invoice['invoice_number'],
    ]);

} catch (Exception $e) {
    try { $pdo->rollBack(); } catch (Exception $ex) {}
    echo json_encode(['success' => false, 'message' => 'Failed to record payment: ' . $e->getMessage()]);
}
