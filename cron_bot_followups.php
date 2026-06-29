<?php
/**
 * Cron: Bot Auto Follow-Up Messages & Payment Reminders
 * Schedule: Run daily via cron or Windows Task Scheduler
 * Command: php C:\xampp\htdocs\dashboard\wqs\cron_bot_followups.php
 * Secret: wqs_bot_followups_cron_2026_SecretKey!
 */

$secret = 'wqs_bot_followups_cron_2026_SecretKey!';
if (php_sapi_name() !== 'cli') {
    if (($_GET['secret'] ?? '') !== $secret) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/config.php';

$logFile = __DIR__ . '/logs/bot_followups_' . date('Y-m-d') . '.log';
@mkdir(__DIR__ . '/logs', 0755, true);

function flog($msg) {
    global $logFile;
    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND | LOCK_EX);
}

flog("=== Bot Follow-Up Cron Started ===");

$sentCount = 0;

// 1. Process pending follow-ups
try {
    $stmt = $pdo->prepare("SELECT * FROM bot_follow_ups WHERE status = 'pending' AND scheduled_at <= NOW() LIMIT 50");
    $stmt->execute();
    $followUps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($followUps as $fu) {
        try {
            if ($fu['user_id']) {
                add_notification($fu['user_id'], "WQS Reminder", $fu['message'], "info", "../user/dashboard.php");
                $sentCount++;
            }
            $pdo->prepare("UPDATE bot_follow_ups SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$fu['id']]);
            flog("Follow-up #{$fu['id']} sent to user {$fu['user_id']}");
        } catch (Exception $e) {
            flog("Follow-up #{$fu['id']} failed: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    flog("Follow-up query error: " . $e->getMessage());
}

// 2. Auto follow-up for inactive users (no messages in 48 hours)
try {
    $inactiveStmt = $pdo->query("
        SELECT DISTINCT bc.user_id, u.name, u.email, MAX(bc.created_at) as last_msg
        FROM bot_chats bc
        JOIN users u ON bc.user_id = u.id
        WHERE bc.user_id IS NOT NULL
        AND bc.created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND bc.user_id NOT IN (
            SELECT user_id FROM bot_follow_ups
            WHERE follow_up_type = 'inactive_reengage'
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        )
        GROUP BY bc.user_id
        HAVING last_msg > DATE_SUB(NOW(), INTERVAL 7 DAY)
        LIMIT 20
    ");
    $inactiveUsers = $inactiveStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($inactiveUsers as $iu) {
        try {
            $name = htmlspecialchars($iu['name'] ?? 'there');
            $msg = "Hi {$name}! 👋 We noticed you haven't visited in a while. Need help with your project or have questions? I'm here for you!";
            add_notification($iu['user_id'], "We miss you!", $msg, "info", "../user/dashboard.php");
            $pdo->prepare("INSERT INTO bot_follow_ups (user_id, follow_up_type, message, scheduled_at, sent_at, status) VALUES (?, 'inactive_reengage', ?, NOW(), NOW(), 'sent')")
                ->execute([$iu['user_id'], $msg]);
            $sentCount++;
            flog("Inactive re-engagement sent to user {$iu['user_id']}");
        } catch (Exception $e) {
            flog("Inactive follow-up failed for user {$iu['user_id']}: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    flog("Inactive user query error: " . $e->getMessage());
}

// 3. Payment reminders for overdue invoices
try {
    $overdueStmt = $pdo->query("
        SELECT ip.*, u.name, u.email
        FROM invoices_payments ip
        JOIN users u ON ip.user_id = u.id
        WHERE ip.status IN ('unpaid', 'overdue')
        AND ip.due_date < CURDATE()
        AND (ip.last_reminder_sent IS NULL OR ip.last_reminder_sent < DATE_SUB(NOW(), INTERVAL 3 DAY))
        LIMIT 20
    ");
    $overdueInvoices = $overdueStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($overdueInvoices as $inv) {
        try {
            $daysOverdue = (int)((time() - strtotime($inv['due_date'])) / 86400);
            $name = htmlspecialchars($inv['name'] ?? 'there');
            $amount = number_format($inv['amount'] ?? 0, 2);
            $invNum = $inv['invoice_number'] ?? 'N/A';
            $msg = "Hi {$name}, friendly reminder: Invoice **{$invNum}** of ₦{$amount} is {$daysOverdue} days overdue. Please submit payment at your earliest convenience.";
            add_notification($inv['user_id'], "Payment Overdue: {$invNum}", $msg, "warning", "../user/invoices_payments.php");
            try {
                $pdo->prepare("UPDATE invoices_payments SET last_reminder_sent = NOW() WHERE id = ?")->execute([$inv['id']]);
            } catch (Exception $e) {}
            $sentCount++;
            flog("Payment reminder sent for invoice {$invNum} to user {$inv['user_id']}");
        } catch (Exception $e) {
            flog("Payment reminder failed for invoice {$inv['invoice_number']}: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    flog("Overdue invoice query error: " . $e->getMessage());
}

flog("=== Bot Follow-Up Cron Completed. Sent: {$sentCount} ===");
echo "Done. Sent {$sentCount} follow-ups.\n";
