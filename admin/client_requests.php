<?php
$path_to_root = "../";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user']['id'])) {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

// Establish DB connection using central config (provides PDO $pdo)
require_once $path_to_root . 'config.php';

// Verify role is admin
$userIdCheck = $_SESSION['user']['id'];
$roleCheckStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleCheckStmt->execute([$userIdCheck]);
$userRoleObj = $roleCheckStmt->fetch(PDO::FETCH_ASSOC);

if (!$userRoleObj || strtolower($userRoleObj['role']) !== 'admin') {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

// ====== Handle Enable Payment AJAX ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'enable_payment') {
    header('Content-Type: application/json');
    $projectId  = (int)($_POST['project_id'] ?? 0);
    $clientId   = (int)($_POST['client_id'] ?? 0);
    $amount     = (float)($_POST['amount'] ?? 0);
    $currency   = in_array($_POST['currency'] ?? '', ['₦','$','€']) ? $_POST['currency'] : '₦';
    $dueDate    = trim($_POST['due_date'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $projTitle  = trim($_POST['project_title'] ?? 'Contract Payment');

    if (!$projectId || !$clientId || $amount <= 0 || !$dueDate) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    try {
        // Check if unpaid invoice already exists for this project
        $existCheck = $pdo->prepare("SELECT id FROM invoices WHERE project_id = ? AND status = 'unpaid'");
        $existCheck->execute([$projectId]);
        if ($existCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'An unpaid invoice already exists for this project. Check Invoice Management.']);
            exit;
        }

        $invoiceNum = 'WQS-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $ins = $pdo->prepare("INSERT INTO invoices (user_id, project_id, invoice_number, amount, currency, due_date, status, notes, description, created_at) VALUES (?, ?, ?, ?, ?, ?, 'unpaid', ?, ?, NOW())");
        $ins->execute([$clientId, $projectId, $invoiceNum, $amount, $currency, $dueDate, $desc, $projTitle]);
        $invId = $pdo->lastInsertId();

        // Mark project as payment_enabled
        $pdo->prepare("UPDATE ongoing_projects SET payment_enabled = 1 WHERE id = ?")->execute([$projectId]);

        // Notify client
        add_notification($clientId,
            "💳 Payment Invoice Ready: $invoiceNum",
            "Your project '{$projTitle}' has been approved for payment. An invoice of {$currency}" . number_format($amount, 2) . " has been issued. Due: $dueDate. Please visit your Invoices & Payments page to pay.",
            'invoice', '../user/client-invoices.php'
        );

        echo json_encode(['success' => true, 'invoice_number' => $invoiceNum, 'invoice_id' => $invId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ====== Handle Disable Payment AJAX ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'disable_payment') {
    header('Content-Type: application/json');
    $projectId = (int)($_POST['project_id'] ?? 0);
    if (!$projectId) { echo json_encode(['success' => false, 'message' => 'Invalid project.']); exit; }
    try {
        // Delete unpaid invoices for this project
        $pdo->prepare("DELETE FROM invoices WHERE project_id = ? AND status = 'unpaid'")->execute([$projectId]);
        $pdo->prepare("UPDATE ongoing_projects SET payment_enabled = 0 WHERE id = ?")->execute([$projectId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ====== Handle Complete Project + Auto-Record Commission ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'complete_project') {
    header('Content-Type: application/json');
    $projectId = (int)($_POST['project_id'] ?? 0);
    if (!$projectId) { echo json_encode(['success' => false, 'message' => 'Invalid project.']); exit; }

    try {
        // Get project details
        $projStmt = $pdo->prepare("SELECT * FROM ongoing_projects WHERE id = ?");
        $projStmt->execute([$projectId]);
        $project = $projStmt->fetch(PDO::FETCH_ASSOC);
        if (!$project) { echo json_encode(['success' => false, 'message' => 'Project not found.']); exit; }
        if ($project['status'] === 'completed') { echo json_encode(['success' => false, 'message' => 'Project is already completed.']); exit; }

        $pdo->beginTransaction();

        // 1. Mark project as completed
        $pdo->prepare("UPDATE ongoing_projects SET status = 'completed', progress = 100, updated_at = NOW() WHERE id = ?")->execute([$projectId]);

        // 2. Auto-record commission for referrer if applicable
        $referrerId = $project['referred_to_user_id'] ?? null;
        $refCode = $project['referral_code_used'] ?? null;
        $commissionAmount = 0;
        $commissionPct = 0;

        if ($referrerId && (int)$referrerId > 0) {
            // Get referrer's commission rate
            $partnerStmt = $pdo->prepare("SELECT default_commission_percent FROM hr_partners WHERE user_id = ?");
            $partnerStmt->execute([$referrerId]);
            $partnerRow = $partnerStmt->fetch(PDO::FETCH_ASSOC);
            if ($partnerRow && $partnerRow['default_commission_percent'] > 0) {
                $commissionPct = (float)$partnerRow['default_commission_percent'];
            } else {
                $setStmt = $pdo->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'partner_commission_percent'");
                $setRow = $setStmt->fetch(PDO::FETCH_ASSOC);
                $commissionPct = $setRow ? (float)$setRow['setting_value'] : 10;
            }

            $commissionAmount = round($project['final_budget'] * ($commissionPct / 100), 2);

            // Insert into hr_commissions
            $insComm = $pdo->prepare("INSERT INTO hr_commissions (partner_id, project_id, title, project_value, commission_percent, commission_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $insComm->execute([$referrerId, $projectId, $project['title'], $project['final_budget'], $commissionPct, $commissionAmount]);

            // Also link the commission entry to the partner's user_id for tracking
            $pdo->prepare("UPDATE hr_commissions SET partner_id = ? WHERE project_id = ? AND commission_amount = ? AND status = 'pending'")
                ->execute([$referrerId, $projectId, $commissionAmount]);

            // Notify referrer
            add_notification($referrerId, "💰 Commission Earned!", "Your referred project '{$project['title']}' has been completed! You've earned ₦" . number_format($commissionAmount, 2) . " ($commissionPct% commission). Check your referral portal for details.", 'payment', '../user/referral_portal.php', $projectId);
        }

        // 3. Update client_requests status to completed
        $pdo->prepare("UPDATE client_requests SET status = 'completed' WHERE id = ?")->execute([$project['request_id']]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Project marked as completed!',
            'commission' => $commissionAmount,
            'commission_pct' => $commissionPct,
            'referrer_id' => $referrerId
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ====== POST ACTIONS (Status review, project approval / update) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

    if ($requestId > 0) {
        if ($action === 'status_update') {
            $status = $_POST['status'];
            if (in_array($status, ['reviewed', 'rejected', 'pending'])) {
                try {
                    // Update status in database
                    $stmt = $pdo->prepare("UPDATE client_requests SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $requestId]);

                    if ($status === 'rejected' || $status === 'reviewed') {
                        $pdo->prepare("DELETE FROM ongoing_projects WHERE request_id = ?")->execute([$requestId]);
                    }

                    // Fetch request details for notification
                    $reqDetailsStmt = $pdo->prepare("SELECT user_id, title FROM client_requests WHERE id = ?");
                    $reqDetailsStmt->execute([$requestId]);
                    $reqInfo = $reqDetailsStmt->fetch(PDO::FETCH_ASSOC);

                    if ($reqInfo) {
                        $clientUserId = (int)$reqInfo['user_id'];
                        $reqTitle = $reqInfo['title'];
                        $statusText = ($status === 'reviewed') ? "Under Review" : ucfirst($status);

                        // Add notification
                        add_notification($clientUserId, "Project Request Update", "Your project request '{$reqTitle}' status has been updated to: {$statusText}.", 'project', '../user/my_requests.php', $requestId);
                    }

                    $_SESSION['success_message'] = "Request status updated to " . ucfirst($status) . " successfully.";
                } catch (Exception $e) {
                    $_SESSION['error_message'] = "Failed to update request status: " . $e->getMessage();
                }
            }
        } elseif ($action === 'approve_cancellation') {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            try {
                // Check if project is completed — cannot cancel completed projects
                $checkStmt = $pdo->prepare("SELECT status FROM client_requests WHERE id = ?");
                $checkStmt->execute([$requestId]);
                $checkRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if ($checkRow && $checkRow['status'] === 'completed') {
                    $msg = "Completed projects cannot be cancelled.";
                    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => $msg]); exit; }
                    $_SESSION['error_message'] = $msg;
                    header("Location: client_requests.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
                    exit;
                }

                $pdo->beginTransaction();

                // Fetch details for notification
                $reqStmt = $pdo->prepare("SELECT user_id, title FROM client_requests WHERE id = ?");
                $reqStmt->execute([$requestId]);
                $reqInfo = $reqStmt->fetch(PDO::FETCH_ASSOC);

                if ($reqInfo) {
                    $clientUserId = (int)$reqInfo['user_id'];
                    $reqTitle = $reqInfo['title'];

                    // 1. Delete files from filesystem
                    $filesStmt = $pdo->prepare("SELECT file_path FROM client_request_files WHERE request_id = ?");
                    $filesStmt->execute([$requestId]);
                    while ($f = $filesStmt->fetch(PDO::FETCH_ASSOC)) {
                        $path = dirname(__DIR__) . "/user/" . $f['file_path'];
                        if (file_exists($path)) {
                            @unlink($path);
                        }
                    }

                    // 2. Delete files from database
                    $pdo->prepare("DELETE FROM client_request_files WHERE request_id = ?")->execute([$requestId]);

                    // 3. Delete ongoing projects and their team allocations
                    $projStmt = $pdo->prepare("SELECT id FROM ongoing_projects WHERE request_id = ?");
                    $projStmt->execute([$requestId]);
                    $projRow = $projStmt->fetch(PDO::FETCH_ASSOC);
                    if ($projRow) {
                        $projectId = (int)$projRow['id'];
                        $pdo->prepare("DELETE FROM project_team WHERE project_id = ?")->execute([$projectId]);
                        $pdo->prepare("DELETE FROM ongoing_projects WHERE id = ?")->execute([$projectId]);
                    }

                    // 4. Delete the client request itself
                    $pdo->prepare("DELETE FROM client_requests WHERE id = ?")->execute([$requestId]);

                    // 5. Send notification to the Client
                    add_notification($clientUserId, "Cancellation Request Approved", "Your cancellation request for approved project '{$reqTitle}' has been approved. The project has been deleted.", 'project', '../user/my_requests.php', 0);
                }

                $pdo->commit();
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Project cancellation approved and deleted successfully.']);
                    exit;
                }
                $_SESSION['success_message'] = "Project cancellation approved and request deleted successfully.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Failed to approve cancellation: ' . $e->getMessage()]);
                    exit;
                }
                $_SESSION['error_message'] = "Failed to approve cancellation: " . $e->getMessage();
            }
        } elseif ($action === 'decline_cancellation') {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            try {
                $stmt = $pdo->prepare("UPDATE client_requests SET cancel_requested = 0, cancel_reason = NULL WHERE id = ?");
                $stmt->execute([$requestId]);

                // Fetch details for notification
                $reqStmt = $pdo->prepare("SELECT user_id, title FROM client_requests WHERE id = ?");
                $reqStmt->execute([$requestId]);
                $reqInfo = $reqStmt->fetch(PDO::FETCH_ASSOC);

                if ($reqInfo) {
                    $clientUserId = (int)$reqInfo['user_id'];
                    $reqTitle = $reqInfo['title'];
                    add_notification($clientUserId, "Cancellation Request Declined", "Your cancellation request for approved project '{$reqTitle}' has been declined by the administrator.", 'project', '../user/my_requests.php', $requestId);
                }

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Cancellation request declined successfully.']);
                    exit;
                }
                $_SESSION['success_message'] = "Project cancellation request declined successfully.";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Failed to decline cancellation: ' . $e->getMessage()]);
                    exit;
                }
                $_SESSION['error_message'] = "Failed to decline cancellation: " . $e->getMessage();
            }
        } elseif ($action === 'approve_suspension') {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            try {
                // Check if project is completed — cannot suspend completed projects
                $checkStmt = $pdo->prepare("SELECT status FROM client_requests WHERE id = ?");
                $checkStmt->execute([$requestId]);
                $checkRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if ($checkRow && $checkRow['status'] === 'completed') {
                    $msg = "Completed projects cannot be suspended.";
                    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => $msg]); exit; }
                    $_SESSION['error_message'] = $msg;
                    header("Location: client_requests.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
                    exit;
                }

                // Fetch details for notification
                $reqStmt = $pdo->prepare("SELECT user_id, title, suspend_start_date, suspend_end_date, suspend_reason FROM client_requests WHERE id = ?");
                $reqStmt->execute([$requestId]);
                $reqInfo = $reqStmt->fetch(PDO::FETCH_ASSOC);

                if ($reqInfo) {
                    $clientUserId = (int)$reqInfo['user_id'];
                    $reqTitle = $reqInfo['title'];
                    $startDate = $reqInfo['suspend_start_date'];
                    $endDate = $reqInfo['suspend_end_date'];
                    $suspendReason = $reqInfo['suspend_reason'];

                    // Update ongoing_projects status to 'on-hold'
                    $projStmt = $pdo->prepare("SELECT id FROM ongoing_projects WHERE request_id = ?");
                    $projStmt->execute([$requestId]);
                    $projRow = $projStmt->fetch(PDO::FETCH_ASSOC);
                    if ($projRow) {
                        $pdo->prepare("UPDATE ongoing_projects SET status = 'on-hold' WHERE id = ?")->execute([(int)$projRow['id']]);
                    }

                    // Clear suspend_requested flag
                    $pdo->prepare("UPDATE client_requests SET suspend_requested = 0, suspend_reason = NULL, suspend_start_date = NULL, suspend_end_date = NULL WHERE id = ?")->execute([$requestId]);

                    // Notify the client
                    add_notification($clientUserId, "⏸️ Suspension Request Approved",
                        "Your suspension request for project '{$reqTitle}' has been approved. The project will be paused from {$startDate} to {$endDate}.",
                        'project', '../user/my_requests.php', $requestId);
                }

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Project suspension approved successfully.']);
                    exit;
                }
                $_SESSION['success_message'] = "Project suspension approved successfully.";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Failed to approve suspension: ' . $e->getMessage()]);
                    exit;
                }
                $_SESSION['error_message'] = "Failed to approve suspension: " . $e->getMessage();
            }
        } elseif ($action === 'decline_suspension') {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            try {
                $stmt = $pdo->prepare("UPDATE client_requests SET suspend_requested = 0, suspend_reason = NULL, suspend_start_date = NULL, suspend_end_date = NULL WHERE id = ?");
                $stmt->execute([$requestId]);

                // Fetch details for notification
                $reqStmt = $pdo->prepare("SELECT user_id, title FROM client_requests WHERE id = ?");
                $reqStmt->execute([$requestId]);
                $reqInfo = $reqStmt->fetch(PDO::FETCH_ASSOC);

                if ($reqInfo) {
                    $clientUserId = (int)$reqInfo['user_id'];
                    $reqTitle = $reqInfo['title'];
                    add_notification($clientUserId, "⏸️ Suspension Request Declined",
                        "Your suspension request for project '{$reqTitle}' has been declined by the administrator. The project will remain active.",
                        'project', '../user/my_requests.php', $requestId);
                }

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Suspension request declined successfully.']);
                    exit;
                }
                $_SESSION['success_message'] = "Project suspension request declined successfully.";
            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Failed to decline suspension: ' . $e->getMessage()]);
                    exit;
                }
                $_SESSION['error_message'] = "Failed to decline suspension: " . $e->getMessage();
            }
        } elseif ($action === 'approve_project' || $action === 'update_project') {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $finalBudget = (float)$_POST['final_budget'];
            $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $progress = (int)$_POST['progress'];
            $managerId = isset($_POST['project_manager_id']) ? (int)$_POST['project_manager_id'] : null;
            $teamMembers = isset($_POST['team_members']) ? $_POST['team_members'] : [];
            $tasks = isset($_POST['tasks']) ? $_POST['tasks'] : [];
            $autoCreateInvoice = isset($_POST['auto_create_invoice']) ? true : false;
            $paymentPlanType = $_POST['payment_plan_type'] ?? 'full';
            $invoiceCurrency = $_POST['invoice_currency'] ?? '₦';
            $invoiceDueDate = $_POST['invoice_due_date'] ?? date('Y-m-d', strtotime('+7 days'));
            $invoiceNotes = trim($_POST['invoice_notes'] ?? '');
            $liveUrl = trim($_POST['live_url'] ?? '');
            $downloadUrl = trim($_POST['download_url'] ?? '');
            $docUrl = trim($_POST['doc_url'] ?? '');

            // Fetch request user_id (the client)
            $reqUserStmt = $pdo->prepare("SELECT user_id, referral_code_used FROM client_requests WHERE id = ?");
            $reqUserStmt->execute([$requestId]);
            $reqUserRow = $reqUserStmt->fetch(PDO::FETCH_ASSOC);
            $clientUserId = $reqUserRow ? (int)$reqUserRow['user_id'] : 0;

            // Check if client was referred — apply 5% discount
            $discountPercent = 0;
            $discountAmount = 0;
            $referredToUserId = null;
            $referralCodeUsed = null;
            if ($clientUserId > 0) {
                $refCheck = $pdo->prepare("SELECT referred_by, referred_by_code FROM users WHERE id = ?");
                $refCheck->execute([$clientUserId]);
                $refRow = $refCheck->fetch(PDO::FETCH_ASSOC);
                if ($refRow && !empty($refRow['referred_by']) && (int)$refRow['referred_by'] > 0) {
                    $referredToUserId = (int)$refRow['referred_by'];
                    $referralCodeUsed = $refRow['referred_by_code'] ?? null;
                    $discountPercent = 5.00;
                    $discountAmount = round($finalBudget * ($discountPercent / 100), 2);
                }
            }

            if ($clientUserId > 0) {
                try {
                    // Begin Transaction
                    $pdo->beginTransaction();

                    // 1. Check if ongoing project exists for this request
                    $checkProj = $pdo->prepare("SELECT id FROM ongoing_projects WHERE request_id = ?");
                    $checkProj->execute([$requestId]);
                    $projectId = 0;
                    $projRow = $checkProj->fetch(PDO::FETCH_ASSOC);

                    if ($projRow) {
                        $projectId = (int)$projRow['id'];
                        // Update existing ongoing project
                        $stmt = $pdo->prepare("UPDATE ongoing_projects SET title = ?, description = ?, final_budget = ?, end_date = ?, progress = ?, project_manager_id = ?, referral_code_used = ?, referred_to_user_id = ?, discount_percent = ?, discount_amount = ?, live_url = ?, download_url = ?, doc_url = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$title, $description, $finalBudget, $endDate, $progress, $managerId, $referralCodeUsed, $referredToUserId, $discountPercent, $discountAmount, $liveUrl, $downloadUrl, $docUrl, $projectId]);
                    } else {
                        // Insert new ongoing project
                        $stmt = $pdo->prepare("INSERT INTO ongoing_projects (request_id, user_id, title, description, final_budget, end_date, progress, project_manager_id, referral_code_used, referred_to_user_id, discount_percent, discount_amount, live_url, download_url, doc_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$requestId, $clientUserId, $title, $description, $finalBudget, $endDate, $progress, $managerId, $referralCodeUsed, $referredToUserId, $discountPercent, $discountAmount, $liveUrl, $downloadUrl, $docUrl]);
                        $projectId = $pdo->lastInsertId();
                    }

                    // Update client_requests with referral info
                    if ($referralCodeUsed) {
                        $pdo->prepare("UPDATE client_requests SET referral_code_used = ?, referred_to_user_id = ?, discount_percent = ?, discount_amount = ? WHERE id = ?")
                            ->execute([$referralCodeUsed, $referredToUserId, $discountPercent, $discountAmount, $requestId]);
                    }

                    // 2. Update status of the client request to approved
                    $pdo->prepare("UPDATE client_requests SET status = 'approved' WHERE id = ?")->execute([$requestId]);

                    // 3. Clear existing team members in project_team
                    $pdo->prepare("DELETE FROM project_team WHERE project_id = ?")->execute([$projectId]);

                    // 4. Insert new team members in project_team
                    if (!empty($teamMembers)) {
                        $insertTeamStmt = $pdo->prepare("INSERT INTO project_team (project_id, user_id, role, task) VALUES (?, ?, ?, ?)");
                        foreach ($teamMembers as $uId) {
                            $uId = (int)$uId;
                            $role = ($uId === $managerId) ? 'manager' : 'member';
                            $task = isset($tasks[$uId]) ? trim($tasks[$uId]) : '';
                            $insertTeamStmt->execute([$projectId, $uId, $role, $task]);
                        }
                    }

                    // 5. Auto-create invoice if enabled
                    if ($action === 'approve_project' && $autoCreateInvoice) {
                        // Calculate invoice amount based on payment plan
                        $invoiceAmount = $finalBudget;
                        if ($paymentPlanType === 'partial') {
                            $invoiceAmount = $finalBudget * 0.5;
                        }

                        $invoiceNum = 'WQS-' . strtoupper(substr(md5(uniqid()), 0, 8));
                        $insInv = $pdo->prepare("INSERT INTO invoices (user_id, project_id, invoice_number, amount, currency, due_date, status, notes, description, payment_plan_type, created_at) VALUES (?, ?, ?, ?, ?, ?, 'unpaid', ?, ?, ?, NOW())");
                        $insInv->execute([$clientUserId, $projectId, $invoiceNum, $invoiceAmount, $invoiceCurrency, $invoiceDueDate, $invoiceNotes, $title, $paymentPlanType]);
                        $invId = $pdo->lastInsertId();

                        // Mark project as payment enabled
                        $pdo->prepare("UPDATE ongoing_projects SET payment_enabled = 1 WHERE id = ?")->execute([$projectId]);

                        // Send notification to client about invoice
                        add_notification($clientUserId, "💳 Invoice Ready: $invoiceNum", "Your project '{$title}' has been approved! An invoice of {$invoiceCurrency}" . number_format($invoiceAmount, 2) . " has been issued. Due: {$invoiceDueDate}. Please visit your dashboard to pay.", 'invoice', '../user/client-invoices.php', $projectId);
                    }

                    $pdo->commit();

                    // Send notification to Client
                    if ($action === 'approve_project') {
                        $discountMsg = ($discountAmount > 0) ? " A referral discount of {$discountPercent}% (₦" . number_format($discountAmount, 2) . ") has been applied to your project." : '';
                        add_notification($clientUserId, "Project Approved!", "Your project request '{$title}' has been approved and is now active.{$discountMsg}", 'project', '../user/my_requests.php', $requestId);

                        // Send SMS via Termii to the client
                        try {
                            $clientPhoneStmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
                            $clientPhoneStmt->execute([$clientUserId]);
                            $clientPhone = $clientPhoneStmt->fetchColumn();
                            if ($clientPhone) {
                                $smsMsg = "Dear client, your project '{$title}' has been approved by Wise Quotient Soft. Our team will begin work shortly. Thank you for choosing us.";
                                send_termii_sms($clientPhone, $smsMsg, $pdo);
                            }
                        } catch (Exception $e) { /* fail-safe */ }
                    } else {
                        add_notification($clientUserId, "Project Updated", "The configuration or progress of your project '{$title}' has been updated.", 'project', '../user/my_requests.php', $projectId);
                    }

                    // Send notification to assigned team members/PM
                    if (!empty($teamMembers)) {
                        foreach ($teamMembers as $uId) {
                            $uId = (int)$uId;
                            $role = ($uId === $managerId) ? 'manager' : 'member';
                            $task = isset($tasks[$uId]) ? trim($tasks[$uId]) : '';
                            $roleName = ($role === 'manager') ? "Project Manager" : "Team Member";
                            $taskDesc = empty($task) ? "General assignment" : $task;

                            add_notification($uId, "Assigned to Project Team", "You have been assigned as {$roleName} to project '{$title}'. Task: {$taskDesc}", 'project', '../user/my_requests.php', $projectId);
                        }
                    }

                    $_SESSION['success_message'] = ($action === 'approve_project') ? "Project approved and team allocated successfully!" : "Project configuration updated successfully!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error_message'] = "Transaction failed: " . $e->getMessage();
                }
            } else {
                $_SESSION['error_message'] = "Invalid client reference.";
            }
        }
    }
    header("Location: client_requests.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

$page_title = "Clients Project Requests";
$current_page = "client_requests.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

// ====== Inputs (Search + Filter + Pagination) ======
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : "";

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$limit = 8;
$offset = ($page - 1) * $limit;

// ====== WHERE Conditions ======
$where = " WHERE 1=1 ";
$params = [];

if (!empty($search)) {
    $where .= " AND (
        cr.title LIKE ? OR
        cr.description LIKE ? OR
        u.name LIKE ? OR
        u.email LIKE ? OR
        u.phone LIKE ?
    ) ";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

if (!empty($statusFilter)) {
    $where .= " AND cr.status = ? ";
    array_push($params, $statusFilter);
}

// ====== Count Total Requests ======
$countSql = "
    SELECT COUNT(*) AS total
    FROM client_requests cr
    INNER JOIN users u ON u.id = cr.user_id
    $where
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRows / $limit);

// ====== Fetch Requests ======
$sql = "
    SELECT 
        cr.id,
        cr.user_id,
        cr.title,
        cr.description,
        cr.categories,
        cr.software_type,
        cr.features,
        cr.recommendations,
        cr.status,
        cr.created_at,
        cr.cancel_requested,
        cr.cancel_reason,
        u.name AS user_name,
        u.email AS user_email,
        u.phone AS user_phone,
        u.picture AS user_picture,
        op.id AS ongoing_project_id,
        op.final_budget,
        op.end_date,
        op.progress,
        op.project_manager_id,
        op.payment_enabled,
        op.status AS ongoing_project_status,
        op.referral_code_used,
        op.discount_percent,
        op.discount_amount
    FROM client_requests cr
    INNER JOIN users u ON u.id = cr.user_id
    LEFT JOIN ongoing_projects op ON op.request_id = cr.id
    $where
    ORDER BY cr.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====== Fetch all users for team selection ======
$usersList = [];
$usersStmt = $pdo->query("SELECT id, name, email, profession FROM users WHERE role != 'admin' ORDER BY name ASC");
$usersList = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// ====== Helper to get ongoing project team allocation ======
function getOngoingTeam($pdo, $projectId)
{
    $projectId = (int)$projectId;
    $team = [];
    if ($projectId <= 0) return $team;

    $teamStmt = $pdo->prepare("SELECT * FROM project_team WHERE project_id = ?");
    $teamStmt->execute([$projectId]);
    $teamResults = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($teamResults as $row) {
        $team[$row['user_id']] = $row;
    }

    return $team;
}

// ====== Helper for Status Badge ======
function statusBadge($status, $cancel_requested = 0)
{
    $status = strtolower($status);
    $cls = "badge-pending";
    if ($status === "approved") $cls = "badge-approved";
    if ($status === "rejected") $cls = "badge-rejected";
    if ($status === "reviewed") $cls = "badge-reviewed";

    $badge = '<span class="badge-theme ' . $cls . '">' . htmlspecialchars(ucfirst($status)) . '</span>';
    if ($cancel_requested) {
        $badge .= ' <span class="badge bg-danger ms-1 text-white px-2 py-1 rounded-pill animate-pulse" style="font-size:0.75rem;"><i class="fas fa-exclamation-triangle me-1"></i>Cancel Requested</span>';
    }
    return $badge;
}

// ====== Fetch Files for Requests ======
function getRequestFiles($pdo, $requestId)
{
    $requestId = (int)$requestId;
    $files = [];

    $fileStmt = $pdo->prepare("SELECT * FROM client_request_files WHERE request_id = ? ORDER BY id DESC");
    $fileStmt->execute([$requestId]);
    return $fileStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
  /* Prevent horizontal scroll */
  body, .wrapper, .main-wrapper, .container-fluid {
    overflow-x: hidden !important;
  }
  
  /* Base Styles */
  .user-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--color-primary-light);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 2px 8px rgba(10, 45, 94, 0.08);
  }
  .user-avatar:hover {
      transform: scale(1.08);
      box-shadow: 0 4px 16px rgba(10, 45, 94, 0.15);
      border-color: var(--color-primary);
  }
  .desc-box {
      background: var(--color-bg);
      border: 1px solid var(--color-border);
      border-radius: 12px;
      padding: 14px 16px;
      font-size: 0.9rem;
      color: var(--color-text);
      transition: all 0.2s ease;
  }
  .desc-box:hover {
      border-color: var(--color-primary-light);
      box-shadow: 0 2px 8px rgba(10, 45, 94, 0.05);
  }
  .file-preview img {
      width: 100%;
      height: 140px;
      object-fit: cover;
      border-radius: 12px;
      border: 1px solid var(--color-border);
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  }
  .file-preview img:hover {
      transform: scale(1.04);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
      border-color: var(--color-primary);
  }
  .file-preview video {
      width: 100%;
      height: 140px;
      border-radius: 12px;
      border: 1px solid var(--color-border);
      object-fit: cover;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  }
  .pill {
      font-size: 0.75rem;
      padding: 6px 14px;
      border-radius: 50px;
      background: var(--color-primary-light);
      color: var(--color-primary);
      display: inline-block;
      margin-right: 6px;
      margin-bottom: 6px;
      font-weight: 600;
      border: 1px solid transparent;
      transition: all 0.2s ease;
  }
  .pill:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 6px rgba(10, 45, 94, 0.1);
  }
  .card-theme {
      border-radius: 16px;
      border: 1px solid var(--color-border);
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
      background: var(--color-card-bg);
      transition: all 0.3s ease;
  }
  .card-theme:hover {
      box-shadow: 0 6px 24px rgba(0, 0, 0, 0.08);
      transform: translateY(-2px);
      border-color: var(--color-primary-light);
  }
  .btn-gradient-primary {
      background: linear-gradient(135deg, var(--color-primary) 0%, #1e40af 100%);
      color: white;
      border: none;
      box-shadow: 0 4px 12px rgba(10, 45, 94, 0.2);
      transition: all 0.25s ease;
      font-weight: 600;
  }
  .btn-gradient-primary:hover {
      background: linear-gradient(135deg, #1e40af 0%, var(--color-primary) 100%);
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(10, 45, 94, 0.3);
  }
  .btn-gradient-accent {
      background: linear-gradient(135deg, var(--color-accent) 0%, #c94a00 100%);
      color: white;
      border: none;
      box-shadow: 0 4px 12px rgba(225, 85, 1, 0.25);
      transition: all 0.25s ease;
      font-weight: 600;
  }
  .btn-gradient-accent:hover {
      background: linear-gradient(135deg, #c94a00 0%, var(--color-accent) 100%);
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(225, 85, 1, 0.35);
  }
  .progress-bar {
      background: linear-gradient(90deg, var(--color-success) 0%, #34d399 100%);
  }
  
  /* Comprehensive Responsive Styles */
  @media (max-width: 991.98px) {
    .card-theme-header {
      flex-direction: column !important;
      align-items: flex-start !important;
      gap: 0.75rem !important;
    }
    .text-end {
      text-align: left !important;
    }
  }
  
  @media (max-width: 767.98px) {
    .user-avatar {
      width: 40px;
      height: 40px;
    }
    .desc-box {
      padding: 10px 12px;
      font-size: 0.85rem;
    }
    .file-preview img, .file-preview video {
      height: 120px;
    }
    .pill {
      font-size: 0.7rem;
      padding: 5px 12px;
    }
    .card-theme-header, .card-theme-body {
      padding: 1rem !important;
    }
    .btn-gradient-primary, .btn-gradient-accent {
      padding: 0.5rem 1.25rem !important;
      font-size: 0.85rem !important;
    }
  }
  
  @media (max-width: 575.98px) {
    .user-avatar {
      width: 36px;
      height: 36px;
      border-width: 1.5px;
    }
    .desc-box {
      padding: 8px 10px;
      font-size: 0.82rem;
    }
    .file-preview img, .file-preview video {
      height: 110px;
    }
    .pill {
      font-size: 0.68rem;
      padding: 4px 10px;
    }
    .card-theme-header, .card-theme-body {
      padding: 0.875rem !important;
    }
    h5.fw-bold {
      font-size: 1rem !important;
    }
    .file-preview.col-6 {
      flex: 0 0 100% !important;
      max-width: 100% !important;
    }
  }
  
  @media (max-width: 479.98px) {
    .user-avatar {
      width: 34px;
      height: 34px;
    }
    .desc-box {
      padding: 7px 9px;
      font-size: 0.8rem;
    }
    .file-preview img, .file-preview video {
      height: 100px;
    }
    .pill {
      font-size: 0.65rem;
      padding: 3.5px 9px;
    }
    .card-theme {
      border-radius: 12px;
    }
    .card-theme-header, .card-theme-body {
      padding: 0.75rem !important;
    }
    h5.fw-bold {
      font-size: 0.95rem !important;
    }
    .mb-6 {
      margin-bottom: 1.5rem !important;
    }
    .mb-5 {
      margin-bottom: 1.25rem !important;
    }
  }
  
  @media (max-width: 399.98px) {
    .user-avatar {
      width: 32px;
      height: 32px;
    }
    .desc-box {
      padding: 6px 8px;
      font-size: 0.78rem;
    }
    .file-preview img, .file-preview video {
      height: 90px;
    }
    .pill {
      font-size: 0.62rem;
      padding: 3px 8px;
    }
    .card-theme-header, .card-theme-body {
      padding: 0.625rem !important;
    }
    h5.fw-bold {
      font-size: 0.9rem !important;
    }
    .btn-gradient-primary, .btn-gradient-accent {
      width: 100% !important;
      justify-content: center !important;
    }
  }
  
  @media (max-width: 359.98px) {
    .user-avatar {
      width: 30px;
      height: 30px;
    }
    .desc-box {
      padding: 5px 7px;
      font-size: 0.75rem;
    }
    .file-preview img, .file-preview video {
      height: 80px;
    }
    .pill {
      font-size: 0.6rem;
      padding: 2.5px 7px;
    }
    .card-theme-header, .card-theme-body {
      padding: 0.5rem !important;
    }
    h5.fw-bold {
      font-size: 0.85rem !important;
    }
  }
  
  /* Make all buttons stack on tiny screens */
  @media (max-width: 575.98px) {
    .d-flex.flex-wrap.gap-2.align-items-center {
      flex-direction: column;
      align-items: stretch;
    }
    .btn, .btn-sm {
      width: 100% !important;
      justify-content: center !important;
    }
  }
</style>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 p-3 mb-4" role="alert" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #065f46;">
        <div class="d-flex align-items-center">
            <i class="fas fa-check-circle me-2 fs-5"></i>
            <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 p-3 mb-4" role="alert" style="background: linear-gradient(135deg, #fee2e2, #fca5a5); color: #7f1d1d;">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-circle me-2 fs-5"></i>
            <div><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="mb-6 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-4">
  <div>
    <h3 class="fw-bold text-body mb-1">Client Project Requests</h3>
    <p class="text-muted mb-0">View, manage, and approve project requirements submitted by clients.</p>
  </div>
  <div class="d-flex align-items-center gap-3">
      <span class="badge bg-gradient-primary px-4 py-2 rounded-pill" style="background: linear-gradient(135deg, #0f172a, #1e40af); color: white; font-weight: 700;">
          <i class="fas fa-file-alt me-2"></i> Total Requests: <?= number_format($totalRows) ?>
      </span>
  </div>
</div>

<!-- FILTER BAR -->
<div class="card-theme mb-5">
  <div class="card-theme-body p-4">
      <form method="GET" class="row g-3 align-items-center">
          <div class="col-12 col-md-5">
              <div class="position-relative">
                  <i class="fas fa-search position-absolute text-muted" style="left: 16px; top: 50%; transform: translateY(-50%);"></i>
                  <input type="text" name="search" class="form-control form-control-theme w-100 pl-5"
                         placeholder="Search by title, description, client name, email..."
                         value="<?= htmlspecialchars($search) ?>"
                         style="padding-left: 48px;">
              </div>
          </div>

          <div class="col-12 col-md-3">
              <select name="status" class="form-select form-control-theme w-100">
                  <option value="">-- All Statuses --</option>
                  <option value="pending" <?= ($statusFilter == "pending") ? "selected" : "" ?>>Pending</option>
                  <option value="reviewed" <?= ($statusFilter == "reviewed") ? "selected" : "" ?>>Reviewed</option>
                  <option value="approved" <?= ($statusFilter == "approved") ? "selected" : "" ?>>Approved</option>
                  <option value="rejected" <?= ($statusFilter == "rejected") ? "selected" : "" ?>>Rejected</option>
              </select>
          </div>

          <div class="col-12 col-md-4 d-grid gap-2 d-md-flex justify-content-md-end">
              <button class="btn btn-gradient-primary w-100 w-md-auto">
                  <i class="fas fa-filter me-2"></i> Filter Requests
              </button>
              <?php if (!empty($search) || !empty($statusFilter)): ?>
                  <a href="client_requests.php" class="btn btn-outline-secondary w-100 w-md-auto">
                      <i class="fas fa-redo me-2"></i> Reset
                  </a>
              <?php endif; ?>
          </div>
      </form>
  </div>
</div>

<!-- REQUEST LIST -->
<div class="row g-3">
    <?php if (count($result) > 0): ?>
        <?php foreach ($result as $row): ?>

            <?php
            $files = getRequestFiles($pdo, $row['id']);
            $profilePic = !empty($row['user_picture']) ? $row['user_picture'] : "https://cdn-icons-png.flaticon.com/512/149/149071.png";
            ?>

            <div class="col-12 col-lg-6">
                <div class="card-theme h-100 mb-0 d-flex flex-column">
                    <div class="card-theme-header d-flex justify-content-between align-items-center p-4" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars($profilePic) ?>" class="user-avatar" alt="Client">
                            <div>
                                <div class="fw-bold text-body" style="font-size: 1rem;"><?= htmlspecialchars($row['user_name']) ?></div>
                                <div class="text-muted" style="font-size: 0.85rem;">
                                    <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($row['user_email']) ?>
                                    <?php if (!empty($row['user_phone'])): ?>
                                        • <i class="fas fa-phone-alt ms-2 me-1"></i><?= htmlspecialchars($row['user_phone']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <div class="mb-2"><?= statusBadge($row['status'], $row['cancel_requested'] ?? 0) ?></div>
                            <div class="text-muted" style="font-size: 0.8rem;">
                                <i class="fas fa-calendar-alt me-1"></i> <?= date("d M Y, h:i A", strtotime($row['created_at'])) ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-theme-body p-4 d-flex flex-column flex-grow-1">
                        <?php if (isset($row['cancel_requested']) && $row['cancel_requested'] == 1): ?>
                            <div class="p-3 mb-3 rounded-4 border d-flex flex-column gap-2" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.06), rgba(220, 38, 38, 0.03)); border-color: rgba(239, 68, 68, 0.2) !important;">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:32px;height:32px;background:rgba(239,68,68,0.1);color:#ef4444;">
                                            <i class="fas fa-exclamation-triangle" style="font-size:0.85rem;"></i>
                                        </span>
                                        <div>
                                            <span class="text-danger fw-bold small">Cancellation Requested</span>
                                            <div class="text-muted" style="font-size:0.72rem;">Client wants to cancel this approved project</div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-danger px-3 py-1.5 rounded-pill fw-semibold shadow-sm btn-approve-cancel" style="font-size: 0.78rem;"
                                            data-request-id="<?= $row['id'] ?>"
                                            data-project-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>">
                                            <i class="fas fa-check-circle me-1"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5 rounded-pill fw-semibold btn-decline-cancel" style="font-size: 0.78rem;"
                                            data-request-id="<?= $row['id'] ?>"
                                            data-project-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>">
                                            <i class="fas fa-times-circle me-1"></i> Decline
                                        </button>
                                    </div>
                                </div>
                                <div class="p-2 rounded-3" style="background: rgba(255,255,255,0.7); border: 1px solid rgba(239, 68, 68, 0.1);">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="fas fa-quote-left text-danger mt-1" style="font-size:0.7rem;opacity:0.5;"></i>
                                        <div>
                                            <span class="text-muted small fw-semibold" style="font-size:0.72rem;">Client's Reason:</span>
                                            <p class="mb-0 mt-1 text-secondary" style="font-size:0.82rem;line-height:1.5;"><?= nl2br(htmlspecialchars($row['cancel_reason'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($row['suspend_requested']) && $row['suspend_requested'] == 1): ?>
                            <div class="p-3 mb-3 rounded-4 border d-flex flex-column gap-2" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.06), rgba(217, 119, 6, 0.03)); border-color: rgba(245, 158, 11, 0.2) !important;">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:32px;height:32px;background:rgba(245,158,11,0.1);color:#d97706;">
                                            <i class="fas fa-pause-circle" style="font-size:0.85rem;"></i>
                                        </span>
                                        <div>
                                            <span class="fw-bold small" style="color:#d97706;">Suspension Requested</span>
                                            <div class="text-muted" style="font-size:0.72rem;">Client wants to pause this approved project</div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm px-3 py-1.5 rounded-pill fw-semibold shadow-sm btn-approve-suspend" style="font-size: 0.78rem; background: #d97706; color: #fff;"
                                            data-request-id="<?= $row['id'] ?>"
                                            data-project-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>">
                                            <i class="fas fa-check-circle me-1"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5 rounded-pill fw-semibold btn-decline-suspend" style="font-size: 0.78rem;"
                                            data-request-id="<?= $row['id'] ?>"
                                            data-project-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>">
                                            <i class="fas fa-times-circle me-1"></i> Decline
                                        </button>
                                    </div>
                                </div>
                                <div class="p-2 rounded-3" style="background: rgba(255,255,255,0.7); border: 1px solid rgba(245, 158, 11, 0.1);">
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <i class="fas fa-quote-left mt-1" style="font-size:0.7rem;opacity:0.5;color:#d97706;"></i>
                                        <div>
                                            <span class="text-muted small fw-semibold" style="font-size:0.72rem;">Client's Reason:</span>
                                            <p class="mb-0 mt-1 text-secondary" style="font-size:0.82rem;line-height:1.5;"><?= nl2br(htmlspecialchars($row['suspend_reason'])) ?></p>
                                        </div>
                                    </div>
                                    <?php if (!empty($row['suspend_start_date']) && !empty($row['suspend_end_date'])): ?>
                                    <div class="d-flex align-items-center gap-2 mt-2 pt-2" style="border-top: 1px solid rgba(245, 158, 11, 0.1);">
                                        <i class="fas fa-calendar-alt" style="font-size:0.7rem;color:#d97706;opacity:0.7;"></i>
                                        <span class="fw-semibold" style="font-size:0.78rem;color:#92400e;">
                                            <?= date("d M Y", strtotime($row['suspend_start_date'])) ?> — <?= date("d M Y", strtotime($row['suspend_end_date'])) ?>
                                        </span>
                                        <span class="text-muted" style="font-size:0.7rem;">
                                            (<?= (int)((strtotime($row['suspend_end_date']) - strtotime($row['suspend_start_date'])) / 86400) ?> days)
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <h5 class="fw-bold text-body mb-3" style="font-size: 1.15rem;"><?= htmlspecialchars($row['title']) ?></h5>

                        <div class="mb-3">
                            <span class="pill"><i class="fas fa-laptop-code me-1"></i><?= htmlspecialchars($row['software_type']) ?></span>
                            <?php if (!empty($row['categories'])): ?>
                                <span class="pill"><i class="fas fa-tags me-1"></i><?= htmlspecialchars($row['categories']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['referral_code_used']) && ($row['discount_percent'] ?? 0) > 0): ?>
                                <span class="pill" style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;"><i class="fas fa-tag me-1"></i><?= $row['discount_percent'] ?>% Referral Discount (<?= htmlspecialchars($row['referral_code_used']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($row['ongoing_project_status']) && $row['ongoing_project_status'] === 'completed'): ?>
                                <span class="pill" style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;"><i class="fas fa-check-circle me-1"></i>Completed</span>
                            <?php endif; ?>
                        </div>

                        <div class="desc-box mb-3">
                            <strong class="text-body"><i class="fas fa-align-left me-2"></i>Description:</strong>
                            <p class="mb-0 mt-2 text-muted" style="font-size: 0.9rem;"><?= nl2br(htmlspecialchars($row['description'])) ?></p>
                        </div>

                        <?php if (!empty($row['features'])): ?>
                            <div class="desc-box mb-3">
                                <strong class="text-body"><i class="fas fa-star me-2"></i>Requested Features:</strong>
                                <p class="mb-0 mt-2 text-muted" style="font-size: 0.9rem;"><?= nl2br(htmlspecialchars($row['features'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($row['recommendations'])): ?>
                            <div class="desc-box mb-3">
                                <strong class="text-body"><i class="fas fa-lightbulb me-2"></i>Client Recommendations:</strong>
                                <p class="mb-0 mt-2 text-muted" style="font-size: 0.9rem;"><?= nl2br(htmlspecialchars($row['recommendations'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- FILES -->
                        <?php if (!empty($files)): ?>
                            <div class="mt-2 mb-3">
                                <div class="fw-semibold text-body mb-3" style="font-size: 0.9rem;">
                                    <i class="fas fa-paperclip me-2 text-primary"></i> Attached Client Files (<?= count($files) ?>):
                                </div>

                                <div class="row g-3">
                                    <?php foreach ($files as $file): 
                                        $fileUrl = $file['file_path'];
                                        if (!preg_match('/^(https?:)?\/\//i', $fileUrl)) {
                                            $fileUrl = "../user/" . $fileUrl;
                                        }
                                    ?>
                                        <div class="col-12 col-sm-6 file-preview">
                                            <?php if ($file['file_type'] === "image"): ?>
                                                <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank">
                                                    <img src="<?= htmlspecialchars($fileUrl) ?>" alt="Request File">
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="btn btn-outline-primary w-100 py-3 rounded-4 d-flex flex-column align-items-center justify-content-center" style="border: 2px dashed rgba(59,130,246,0.3);">
                                                    <i class="fas fa-play-circle fa-3x mb-2"></i>
                                                    <span style="font-size: 0.8rem; font-weight: 600;">View Video</span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($row['status'] === 'approved'): ?>
                            <div class="mb-3 mt-1 pt-3 border-top">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-body small fw-semibold"><i class="fas fa-chart-line me-1"></i>Current Progress:</span>
                                    <span class="text-primary small fw-bold" style="font-size: 1rem;"><?= (int)$row['progress'] ?>%</span>
                                </div>
                                <div class="progress" style="height: 10px; border-radius: 20px; background: rgba(0,0,0,0.05);">
                                    <div class="progress-bar" role="progressbar" style="width: <?= (int)$row['progress'] ?>%;" aria-valuenow="<?= (int)$row['progress'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                        $teamAllocated = [];
                        if (!empty($row['ongoing_project_id'])) {
                            $teamAllocated = getOngoingTeam($pdo, $row['ongoing_project_id']);
                        }
                        ?>
                        <?php if (!empty($teamAllocated)): ?>
                            <div class="mb-3">
                                <span class="text-body small fw-semibold d-block mb-2"><i class="fas fa-users me-1"></i>Allocated Team:</span>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($teamAllocated as $tMemberId => $tMem):
                                        $mName = '';
                                        foreach ($usersList as $u) {
                                            if ($u['id'] == $tMemberId) {
                                                $mName = $u['name'];
                                                break;
                                            }
                                        }
                                        if (empty($mName)) continue;
                                        $isPm = ($tMem['role'] === 'manager');
                                    ?>
                                        <span class="badge <?= $isPm ? 'bg-gradient-primary text-white' : 'bg-body-tertiary text-body border' ?> px-3 py-2 rounded-pill" style="font-size: 0.8rem; font-weight: 600;">
                                            <i class="fas <?= $isPm ? 'fa-user-shield' : 'fa-user' ?> me-1"></i>
                                            <?= htmlspecialchars($mName) ?>
                                            <?php if ($isPm): ?> <span style="font-size: 0.7rem;">(PM)</span><?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($row['cancel_requested'])): ?>
                        <div class="mb-3 pt-3 border-top d-flex flex-wrap gap-2 align-items-center">
                            <?php
                            $projectJson = json_encode([
                                'id' => $row['id'],
                                'title' => $row['title'],
                                'description' => $row['description'],
                                'budget' => $row['final_budget'] ?? 0,
                                'end_date' => !empty($row['end_date']) ? date("Y-m-d", strtotime($row['end_date'])) : '',
                                'progress' => $row['progress'] ?? 0,
                                'manager_id' => $row['project_manager_id'] ?? '',
                                'team' => $teamAllocated,
                                'live_url' => $row['live_url'] ?? '',
                                'download_url' => $row['download_url'] ?? '',
                                'doc_url' => $row['doc_url'] ?? ''
                            ]);
                            ?>
                            <?php if ($row['status'] === 'approved'): ?>
                                <button class="btn btn-sm btn-gradient-primary px-4 py-2 rounded-pill btn-manage-project" data-project='<?= htmlspecialchars($projectJson, ENT_QUOTES, 'UTF-8') ?>' data-bs-toggle="modal" data-bs-target="#projectModal">
                                    <i class="fas fa-tasks me-2"></i> Manage Team & Progress
                                </button>

                                <?php
                                $payEnabled = !empty($row['payment_enabled']);
                                // Check if a paid invoice exists
                                $payInvCheck = $pdo->prepare("SELECT id, status FROM invoices WHERE project_id = ? ORDER BY created_at DESC LIMIT 1");
                                $payInvCheck->execute([$row['ongoing_project_id']]);
                                $payInv = $payInvCheck->fetch();
                                $payInvStatus = $payInv['status'] ?? null;
                                ?>
                                <?php if ($payInvStatus === 'paid'): ?>
                                    <span class="btn btn-sm btn-outline-success px-4 py-2 rounded-pill" style="cursor:default;">
                                        <i class="fas fa-check-double me-2"></i> Payment Received ✓
                                    </span>
                                <?php elseif ($payEnabled || $payInv): ?>
                                    <button class="btn btn-sm btn-warning px-4 py-2 rounded-pill fw-bold"
                                        onclick="disablePayment(<?= $row['ongoing_project_id'] ?>)">
                                        <i class="fas fa-ban me-2"></i> Disable Payment
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary px-4 py-2 rounded-pill"
                                        onclick="viewInvoice(<?= $payInv['id'] ?? 0 ?>)">
                                        <i class="fas fa-file-invoice me-2"></i> View Invoice
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm px-4 py-2 rounded-pill fw-bold btn-enable-payment btn-gradient-accent"
                                        data-project-id="<?= $row['ongoing_project_id'] ?>"
                                        data-client-id="<?= $row['user_id'] ?>"
                                        data-project-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
                                        data-budget="<?= $row['final_budget'] ?? 0 ?>"
                                        data-bs-toggle="modal" data-bs-target="#enablePaymentModal">
                                        <i class="fas fa-credit-card me-2"></i> Enable Payment
                                    </button>
                                <?php endif; ?>

                                <?php if (empty($row['ongoing_project_status']) || $row['ongoing_project_status'] !== 'completed'): ?>
                                <button class="btn btn-sm btn-success px-4 py-2 rounded-pill fw-bold"
                                    onclick="completeProject(<?= $row['ongoing_project_id'] ?>, '<?= htmlspecialchars(addslashes($row['title']), ENT_QUOTES) ?>')">
                                    <i class="fas fa-check-double me-2"></i> Mark Complete
                                </button>
                                <?php else: ?>
                                <span class="btn btn-sm btn-outline-success px-4 py-2 rounded-pill" style="cursor:default;">
                                    <i class="fas fa-check-double me-2"></i> Completed
                                </span>
                                <?php endif; ?>

                            <?php else: ?>
                                <button class="btn btn-sm btn-gradient-primary px-4 py-2 rounded-pill btn-manage-project" data-project='<?= htmlspecialchars($projectJson, ENT_QUOTES, 'UTF-8') ?>' data-bs-toggle="modal" data-bs-target="#projectModal">
                                    <i class="fas fa-check-circle me-2"></i> Approve & Setup Team
                                </button>
                            <?php endif; ?>

                            <form method="POST" class="d-inline-flex gap-2" onsubmit="return confirm('Are you sure you want to update request status?');">
                                <input type="hidden" name="action" value="status_update">
                                <input type="hidden" name="request_id" value="<?= $row['id'] ?>">

                                <?php if ($row['status'] !== 'reviewed'): ?>
                                    <button type="submit" name="status" value="reviewed" class="btn btn-sm btn-outline-warning px-3 py-2 rounded-pill">
                                        <i class="fas fa-eye me-1"></i> Review
                                    </button>
                                <?php endif; ?>

                                <?php if ($row['status'] !== 'rejected'): ?>
                                    <button type="submit" name="status" value="rejected" class="btn btn-sm btn-outline-danger px-3 py-2 rounded-pill">
                                        <i class="fas fa-times-circle me-1"></i> Reject
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                        <?php else: ?>
                        <div class="mb-3 pt-3 border-top text-center text-danger small bg-danger-subtle py-2 rounded-pill" style="font-weight: 500; background: rgba(239, 68, 68, 0.05);">
                            <i class="fas fa-lock me-1"></i> Actions locked due to active cancellation request.
                        </div>
                        <?php endif; ?>

                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="text-muted small"><i class="fas fa-hashtag me-1"></i>Request ID: <strong>#<?= (int)$row['id'] ?></strong></span>

                            <div class="d-flex gap-2">
                                <a href="mailto:<?= htmlspecialchars($row['user_email']) ?>" class="btn btn-sm btn-outline-primary px-3 py-1.5 rounded-pill">
                                    <i class="fas fa-envelope me-1"></i> Email
                                </a>

                                <?php if (!empty($row['user_phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($row['user_phone']) ?>" class="btn btn-sm btn-outline-success px-3 py-1.5 rounded-pill">
                                        <i class="fas fa-phone-alt me-1"></i> Call
                                    </a>
                                <?php endif; ?>

                                <button class="btn btn-sm btn-outline-info px-3 py-1.5 rounded-pill btn-discussion"
                                    data-request-id="<?= (int)$row['id'] ?>"
                                    data-client-name="<?= htmlspecialchars($row['user_name'], ENT_QUOTES) ?>"
                                    data-bs-toggle="modal" data-bs-target="#discussionModal">
                                    <i class="fas fa-comments me-1"></i> Discussion
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-warning py-6 text-center rounded-4" style="background: linear-gradient(135deg, #fff7ed, #ffedd5); border: none;">
                <i class="fas fa-inbox fa-3x mb-4" style="color: #ea580c;"></i>
                <h5 class="fw-bold text-body mb-2">No client requests found</h5>
                <p class="text-muted mb-0">Try adjusting your filters or search query. Check back later for new requests.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
    <nav class="mt-6">
        <ul class="pagination justify-content-center flex-wrap gap-2">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? "active" : "" ?>">
                    <a class="page-link px-4 py-2 rounded-3 border"
                       href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>"
                       style="<?= ($i == $page) ? 'background: linear-gradient(135deg, var(--color-primary), #0f172a); border-color: var(--color-primary);' : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<!-- Approve / Manage Project Modal -->
<div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl rounded-4" style="background: linear-gradient(135deg, #ffffff, #f8fafc);">
            <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #0A2D5E, #0f172a); color: #ffffff; border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold p-4" id="projectModalLabel"><i class="fas fa-rocket me-2"></i>Approve & Setup Project Team</h5>
                <button type="button" class="btn-close btn-close-white me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="projectForm" method="POST" class="p-4">
                <input type="hidden" name="action" id="modal-action" value="approve_project">
                <input type="hidden" name="request_id" id="modal-request-id" value="">

                <div class="modal-body py-4">
                    <div class="row g-4">
                        <!-- Project Title (pre-filled but editable) -->
                        <div class="col-12">
                            <label class="form-label fw-semibold text-body"><i class="fas fa-heading me-2 text-primary"></i>Project Title</label>
                            <input type="text" name="title" id="modal-title" class="form-control form-control-theme" required style="padding: 14px 18px; border-radius: 12px;">
                        </div>

                        <!-- Project Description -->
                        <div class="col-12">
                            <label class="form-label fw-semibold text-body"><i class="fas fa-align-left me-2 text-primary"></i>Project Description</label>
                            <textarea name="description" id="modal-description" class="form-control form-control-theme" rows="4" required style="padding: 14px 18px; border-radius: 12px;"></textarea>
                        </div>

                        <!-- Budget & End Date -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-body"><i class="fas fa-wallet me-2 text-primary"></i>Final Budget</label>
                            <div class="input-group">
                                <select class="input-group-text bg-gradient-primary text-white" name="invoice_currency" id="modal-invoice-currency" style="border-radius: 12px 0 0 12px; border: none;">
                                    <option value="₦" selected>₦</option>
                                    <option value="$">$</option>
                                    <option value="€">€</option>
                                </select>
                                <input type="number" step="0.01" name="final_budget" id="modal-budget" class="form-control form-control-theme" placeholder="e.g. 5000.00" required style="border-radius: 0 12px 12px 0;">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-body"><i class="fas fa-calendar-check me-2 text-primary"></i>Target Completion Date</label>
                            <input type="date" name="end_date" id="modal-end-date" class="form-control form-control-theme" required style="padding: 14px 18px; border-radius: 12px;">
                        </div>

                        <!-- Progress (Slider) -->
                        <div class="col-12">
                            <label class="form-label fw-semibold text-body d-flex justify-content-between">
                                <span><i class="fas fa-chart-line me-2 text-primary"></i>Project Progress</span>
                                <span id="progress-value" class="fw-bold text-primary" style="font-size: 1.1rem;">0%</span>
                            </label>
                            <input type="range" class="form-range" name="progress" id="modal-progress" min="0" max="100" value="0" oninput="document.getElementById('progress-value').textContent = this.value + '%';">
                        </div>

                        <!-- Project URLs (for completed/delivered projects) -->
                        <div class="col-12">
                            <div class="card border-0" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 16px; padding: 20px;">
                                <h6 class="fw-bold text-body mb-3"><i class="fas fa-link me-2 text-success"></i>Project Delivery Links <small class="text-muted fw-normal">(for completed projects)</small></h6>
                                <div class="row g-3">
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-semibold text-body small"><i class="fas fa-globe me-1 text-primary"></i>Live Preview URL</label>
                                        <input type="url" name="live_url" id="modal-live-url" class="form-control form-control-theme" placeholder="https://example.com" style="padding: 10px 14px; border-radius: 10px;">
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-semibold text-body small"><i class="fas fa-download me-1 text-success"></i>Download URL</label>
                                        <input type="url" name="download_url" id="modal-download-url" class="form-control form-control-theme" placeholder="https://download-link.com" style="padding: 10px 14px; border-radius: 10px;">
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label fw-semibold text-body small"><i class="fas fa-file-alt me-1 text-info"></i>Documentation URL</label>
                                        <input type="url" name="doc_url" id="modal-doc-url" class="form-control form-control-theme" placeholder="https://docs.example.com" style="padding: 10px 14px; border-radius: 10px;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Project Manager -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-body"><i class="fas fa-user-shield me-2 text-primary"></i>Project Manager</label>
                            <select name="project_manager_id" id="modal-manager" class="form-select form-control-theme" style="padding: 14px 18px; border-radius: 12px;">
                                <option value="">-- Select Project Manager --</option>
                                <?php foreach ($usersList as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Invoice Configuration -->
                        <div class="col-12">
                            <div class="card border-0" style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-radius: 16px; padding: 24px;">
                                <h6 class="fw-bold text-body mb-4"><i class="fas fa-file-invoice-dollar me-2"></i>Invoice Configuration (Optional)</h6>

                                <div class="row g-3">
                                    <div class="col-12 col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="auto_create_invoice" id="auto-create-invoice" checked style="width: 48px; height: 24px;">
                                            <label class="form-check-label fw-semibold text-body" for="auto-create-invoice">Auto-Create Invoice</label>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label class="form-label fw-semibold text-body">Payment Plan</label>
                                        <select name="payment_plan_type" class="form-select form-control-theme" style="padding: 10px 14px; border-radius: 10px;">
                                            <option value="full">Full Payment</option>
                                            <option value="partial">50% Deposit</option>
                                            <option value="milestone">Milestone</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label class="form-label fw-semibold text-body">Due Date</label>
                                        <input type="date" name="invoice_due_date" id="invoice-due-date" class="form-control form-control-theme" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" style="padding: 10px 14px; border-radius: 10px;">
                                    </div>
                                    <div class="col-12 col-md-12">
                                        <label class="form-label fw-semibold text-body">Invoice Notes</label>
                                        <textarea name="invoice_notes" class="form-control form-control-theme" rows="2" placeholder="Add any additional notes to the invoice..." style="padding: 10px 14px; border-radius: 10px;"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Team Members -->
                        <div class="col-12">
                            <label class="form-label fw-semibold text-body"><i class="fas fa-users me-2 text-primary"></i>Assign Team Members</label>
                            <div class="border rounded-3 p-3" style="max-height: 280px; overflow-y: auto; background: rgba(255,255,255,0.8);">
                                <div class="row g-2">
                                    <?php foreach ($usersList as $user): ?>
                                        <div class="col-12 col-md-6">
                                            <div class="form-check form-check-reverse">
                                                <input class="form-check-input" type="checkbox" name="team_members[]" value="<?= $user['id'] ?>" id="team-member-<?= $user['id'] ?>">
                                                <label class="form-check-label w-100 p-2 rounded-2" for="team-member-<?= $user['id'] ?>" style="cursor: pointer;">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span class="fw-semibold text-body"><?= htmlspecialchars($user['name']) ?></span>
                                                            <?php if (!empty($user['profession'])): ?>
                                                                <span class="badge bg-light text-secondary border ms-2" style="font-size:0.7rem;font-weight:600;text-transform:uppercase;"><?= htmlspecialchars($user['profession']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="text-muted small"><?= htmlspecialchars($user['email']) ?></span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-gradient-primary px-6 py-2 rounded-pill fw-bold">
                        <i class="fas fa-check-circle me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enable Payment Modal -->
<div class="modal fade" id="enablePaymentModal" tabindex="-1" aria-labelledby="enablePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl rounded-4">
            <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #ff6600, #e65c00); color: #ffffff; border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold p-4" id="enablePaymentModalLabel"><i class="fas fa-credit-card me-2"></i>Enable Payment & Create Invoice</h5>
                <button type="button" class="btn-close btn-close-white me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="enablePaymentForm" class="p-4">
                <input type="hidden" name="ajax_action" value="enable_payment">
                <input type="hidden" name="project_id" id="ep-project-id" value="">
                <input type="hidden" name="client_id" id="ep-client-id" value="">
                <input type="hidden" name="project_title" id="ep-project-title" value="">

                <div class="modal-body py-4">
                    <div class="row g-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-body"><i class="fas fa-dollar-sign me-2"></i>Invoice Amount</label>
                            <div class="input-group">
                                <select class="input-group-text bg-gradient-accent text-white" name="currency" id="ep-currency" style="border-radius: 12px 0 0 12px; border: none;">
                                    <option value="₦" selected>₦</option>
                                    <option value="$">$</option>
                                    <option value="€">€</option>
                                </select>
                                <input type="number" step="0.01" name="amount" id="ep-amount" class="form-control form-control-theme" required style="border-radius: 0 12px 12px 0;">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-body"><i class="fas fa-calendar-check me-2"></i>Due Date</label>
                            <input type="date" name="due_date" id="ep-due-date" class="form-control form-control-theme" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold text-body"><i class="fas fa-align-left me-2"></i>Invoice Description</label>
                            <textarea name="description" id="ep-description" class="form-control form-control-theme" rows="3" placeholder="Add any details about this invoice..." style="border-radius: 12px;"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-gradient-accent px-6 py-2 rounded-pill fw-bold">
                        <i class="fas fa-check-circle me-2"></i>Enable Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Discussion Modal -->
<div class="modal fade" id="discussionModal" tabindex="-1" aria-labelledby="discussionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-xl rounded-4">
            <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #0A2D5E, #0f172a); color: #ffffff; border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold p-4" id="discussionModalLabel"><i class="fas fa-comments me-2"></i>Discussion: <span id="discussion-project-title" class="fw-normal" style="font-size:0.9rem;">Project</span></h5>
                <button type="button" class="btn-close btn-close-white me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="max-height: 60vh; overflow-y: auto; background: #f8fafc;">
                <div id="discussion-messages" class="p-4 d-flex flex-column gap-3">
                    <div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading discussion...</div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3" style="background: #fff;">
                <div class="input-group">
                    <input type="text" id="discussion-input" class="form-control form-control-theme" placeholder="Type your message..." style="border-radius: 12px 0 0 12px;">
                    <button id="discussion-send-btn" class="btn btn-gradient-primary px-4" style="border-radius: 0 12px 12px 0;">
                        <i class="fas fa-paper-plane"></i> Send
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.discussion-bubble {
    max-width: 80%; padding: 10px 16px; border-radius: 16px; font-size: 0.9rem; line-height: 1.5; position: relative;
}
.discussion-bubble.admin {
    align-self: flex-end; background: linear-gradient(135deg, #0A2D5E, #1a3a6e); color: white; border-bottom-right-radius: 4px;
}
.discussion-bubble.client {
    align-self: flex-start; background: #ffffff; color: #1a1a2e; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px;
}
.discussion-bubble.bot {
    align-self: center; background: #f1f5f9; color: #475569; border: 1px dashed #cbd5e1; font-style: italic; font-size: 0.8rem; max-width: 90%;
}
.discussion-bubble .meta {
    font-size: 0.65rem; opacity: 0.6; margin-top: 4px;
}
</style>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>

<script>
// Discussion modal
let currentDiscussionRequestId = 0;
let discussionPollInterval = null;

document.querySelectorAll('.btn-discussion').forEach(btn => {
    btn.addEventListener('click', () => {
        const rid = parseInt(btn.getAttribute('data-request-id'));
        const cname = btn.getAttribute('data-client-name');
        currentDiscussionRequestId = rid;
        document.getElementById('discussion-project-title').textContent = '#' + rid + ' - ' + cname;
        document.getElementById('discussion-messages').innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading discussion...</div>';
        document.getElementById('discussion-input').value = '';
        loadDiscussion(rid);
        if (discussionPollInterval) clearInterval(discussionPollInterval);
        discussionPollInterval = setInterval(() => loadDiscussion(rid, true), 5000);
    });
});

document.getElementById('discussion-send-btn').addEventListener('click', sendDiscussionMessage);
document.getElementById('discussion-input').addEventListener('keypress', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendDiscussionMessage(); }
});

function sendDiscussionMessage() {
    const input = document.getElementById('discussion-input');
    const msg = input.value.trim();
    if (!msg || !currentDiscussionRequestId) return;
    input.disabled = true;
    document.getElementById('discussion-send-btn').disabled = true;

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('request_id', currentDiscussionRequestId);
    formData.append('message', msg);

    fetch('../request_discussions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(resp => {
        input.value = '';
        input.disabled = false;
        document.getElementById('discussion-send-btn').disabled = false;
        if (resp.success) loadDiscussion(currentDiscussionRequestId);
        else Swal.fire({ title: 'Error', text: resp.error || 'Failed to send message.', icon: 'error' });
    })
    .catch(() => {
        input.disabled = false;
        document.getElementById('discussion-send-btn').disabled = false;
        Swal.fire({ title: 'Connection Error', text: 'Could not send message.', icon: 'error' });
    });
}

function loadDiscussion(rid, silent = false) {
    if (!rid) return;
    fetch('../request_discussions.php?action=fetch&request_id=' + rid)
    .then(r => r.json())
    .then(resp => {
        if (!resp.success) return;
        const container = document.getElementById('discussion-messages');
        if (!silent) container.innerHTML = '';
        let html = '';
        resp.messages.forEach(m => {
            const cls = m.is_from_bot == 1 ? 'bot' : (m.user_id == <?= (int)$userIdCheck ?> ? 'admin' : 'client');
            const name = m.user_name || 'Unknown';
            const avatar = m.user_picture ? '<img src="' + m.user_picture + '" style="width:20px;height:20px;border-radius:50%;margin-right:6px;">' : '<i class="fas fa-user-circle me-1"></i>';
            html += '<div class="discussion-bubble ' + cls + '">';
            html += m.message.replace(/\n/g, '<br>');
            html += '<div class="meta">' + avatar + ' ' + name + ' &middot; ' + m.created_at + '</div>';
            html += '</div>';
        });
        if (!silent) container.innerHTML = html || '<div class="text-center text-muted py-4">No discussion messages yet. Start the conversation above.</div>';
        else if (html) container.innerHTML = html;
        container.scrollTop = container.scrollHeight;
    })
    .catch(() => {});
}

// Cleanup polling on modal close
document.getElementById('discussionModal').addEventListener('hidden.bs.modal', () => {
    if (discussionPollInterval) { clearInterval(discussionPollInterval); discussionPollInterval = null; }
});

// Populate project modal
document.querySelectorAll('.btn-manage-project').forEach(btn => {
    btn.addEventListener('click', () => {
        const project = JSON.parse(btn.getAttribute('data-project'));
        document.getElementById('modal-action').value = project.id && project.budget ? 'update_project' : 'approve_project';
        document.getElementById('modal-request-id').value = project.id;
        document.getElementById('modal-title').value = project.title;
        document.getElementById('modal-description').value = project.description;
        document.getElementById('modal-budget').value = project.budget;
        document.getElementById('modal-end-date').value = project.end_date;
        document.getElementById('modal-progress').value = project.progress;
        document.getElementById('progress-value').textContent = project.progress + '%';
        document.getElementById('modal-manager').value = project.manager_id;

        // Populate URL fields
        document.getElementById('modal-live-url').value = project.live_url || '';
        document.getElementById('modal-download-url').value = project.download_url || '';
        document.getElementById('modal-doc-url').value = project.doc_url || '';

        // Reset team checkboxes
        document.querySelectorAll('[name="team_members[]"]').forEach(cb => {
            cb.checked = false;
        });

        // Check assigned team members
        if (project.team) {
            Object.keys(project.team).forEach(userId => {
                const checkbox = document.getElementById(`team-member-${userId}`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }
    });
});

// Populate enable payment modal
document.querySelectorAll('.btn-enable-payment').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('ep-project-id').value = btn.getAttribute('data-project-id');
        document.getElementById('ep-client-id').value = btn.getAttribute('data-client-id');
        document.getElementById('ep-project-title').value = btn.getAttribute('data-project-title');
        document.getElementById('ep-amount').value = btn.getAttribute('data-budget');
        document.getElementById('ep-description').value = `Payment for ${btn.getAttribute('data-project-title')}`;
    });
});

// Handle enable payment form
document.getElementById('enablePaymentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    try {
        const res = await fetch('client_requests.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Payment Enabled!',
                text: `Invoice ${data.invoice_number} has been created.`,
                timer: 2500
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to enable payment. Please try again.'
        });
    }
});

// Complete project + auto-record commission
async function completeProject(projectId, projectTitle) {
    const result = await Swal.fire({
        title: 'Mark Project as Completed?',
        html: `<p class="text-muted">This will mark <strong>"${projectTitle}"</strong> as completed.</p>
               <p class="text-muted small mb-0">If this project was referred, the referrer's commission will be automatically recorded.</p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check-double me-1"></i> Yes, Complete It!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#16a34a',
        customClass: { popup: 'swal2-border-radius' }
    });

    if (result.isConfirmed) {
        try {
            const formData = new FormData();
            formData.append('ajax_action', 'complete_project');
            formData.append('project_id', projectId);
            const res = await fetch('client_requests.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                let msg = data.message;
                if (data.commission > 0) {
                    msg += `<br><br><strong style="color:#16a34a;">Commission Recorded:</strong> ₦${Number(data.commission).toLocaleString('en-NG', {minimumFractionDigits:2})} (${data.commission_pct}%)`;
                }
                Swal.fire({
                    icon: 'success',
                    title: 'Project Completed!',
                    html: msg,
                    confirmButtonColor: '#0A2D5E',
                    customClass: { popup: 'swal2-border-radius' }
                }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to complete project. Please try again.' });
        }
    }
}

// Disable payment function
async function disablePayment(projectId) {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: 'This will delete any unpaid invoice for this project.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, disable it!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ff6600'
    });

    if (result.isConfirmed) {
        try {
            const formData = new FormData();
            formData.append('ajax_action', 'disable_payment');
            formData.append('project_id', projectId);
            const res = await fetch('client_requests.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Disabled!',
                    text: 'Payment has been disabled.',
                    timer: 2000
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to disable payment. Please try again.'
            });
        }
    }
}

// View invoice function
function viewInvoice(invoiceId) {
    window.open('../generate_invoice.php?id=' + invoiceId, '_blank');
}

// ===================== CANCELLATION REQUEST HANDLERS =====================
document.querySelectorAll('.btn-approve-cancel').forEach(btn => {
    btn.addEventListener('click', async () => {
        const requestId = btn.getAttribute('data-request-id');
        const title = btn.getAttribute('data-project-title');

        const result = await Swal.fire({
            title: 'Approve Cancellation?',
            html: `<div class="text-start">
                <p class="text-muted mb-2">You are about to approve the cancellation request for:</p>
                <div class="p-2 rounded-3 mb-2" style="background: #fef2f2; border: 1px solid #fecaca;">
                    <strong class="text-danger">"${title}"</strong>
                </div>
                <p class="text-danger small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>This will permanently delete the project, all associated files, team allocations, and records. This action cannot be undone.</p>
            </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Yes, Delete Everything',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'swal2-border-radius' },
            reverseButtons: true
        });

        if (result.isConfirmed) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

            try {
                const formData = new FormData();
                formData.append('action', 'approve_cancellation');
                formData.append('request_id', requestId);

                const res = await fetch('client_requests.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });

                Swal.fire({
                    icon: 'success',
                    title: 'Cancellation Approved',
                    html: `<p>The project <strong>"${title}"</strong> has been deleted permanently.</p>
                           <p class="text-muted small mb-0">The client has been notified.</p>`,
                    confirmButtonColor: '#0A2D5E',
                    customClass: { popup: 'swal2-border-radius' }
                }).then(() => location.reload());
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to approve cancellation. Please try again.',
                    customClass: { popup: 'swal2-border-radius' }
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Approve';
            }
        }
    });
});

document.querySelectorAll('.btn-decline-cancel').forEach(btn => {
    btn.addEventListener('click', async () => {
        const requestId = btn.getAttribute('data-request-id');
        const title = btn.getAttribute('data-project-title');

        const result = await Swal.fire({
            title: 'Decline Cancellation?',
            html: `<div class="text-start">
                <p class="text-muted mb-2">You are about to decline the cancellation request for:</p>
                <div class="p-2 rounded-3 mb-2" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                    <strong class="text-success">"${title}"</strong>
                </div>
                <p class="text-muted small mb-0">The project will remain active and the client will be notified that their cancellation request was declined.</p>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-times-circle me-1"></i> Yes, Decline',
            cancelButtonText: 'Go Back',
            customClass: { popup: 'swal2-border-radius' },
            reverseButtons: true
        });

        if (result.isConfirmed) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

            try {
                const formData = new FormData();
                formData.append('action', 'decline_cancellation');
                formData.append('request_id', requestId);

                const res = await fetch('client_requests.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });

                Swal.fire({
                    icon: 'success',
                    title: 'Cancellation Declined',
                    html: `<p>The cancellation request for <strong>"${title}"</strong> has been declined.</p>
                           <p class="text-muted small mb-0">The client has been notified.</p>`,
                    confirmButtonColor: '#0A2D5E',
                    customClass: { popup: 'swal2-border-radius' }
                }).then(() => location.reload());
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to decline cancellation. Please try again.',
                    customClass: { popup: 'swal2-border-radius' }
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times-circle me-1"></i> Decline';
            }
        }
    });
});

// ===================== SUSPENSION REQUEST HANDLERS =====================
document.querySelectorAll('.btn-approve-suspend').forEach(btn => {
    btn.addEventListener('click', async () => {
        const requestId = btn.getAttribute('data-request-id');
        const title = btn.getAttribute('data-project-title');

        const result = await Swal.fire({
            title: 'Approve Suspension?',
            html: `<div class="text-start">
                <p class="text-muted mb-2">You are about to approve the suspension request for:</p>
                <div class="p-2 rounded-3 mb-2" style="background: #fffbeb; border: 1px solid #fde68a;">
                    <strong style="color:#92400e;">"${title}"</strong>
                </div>
                <p class="small mb-0" style="color:#92400e;"><i class="fas fa-info-circle me-1"></i>This will pause the project and set its status to "On Hold". The project can be resumed later by the admin.</p>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d97706',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-pause-circle me-1"></i> Yes, Approve Suspension',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'swal2-border-radius' },
            reverseButtons: true
        });

        if (result.isConfirmed) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

            try {
                const formData = new FormData();
                formData.append('action', 'approve_suspension');
                formData.append('request_id', requestId);

                const res = await fetch('client_requests.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });

                Swal.fire({
                    icon: 'success',
                    title: 'Suspension Approved',
                    html: `<p>The project <strong>"${title}"</strong> has been suspended.</p>
                           <p class="text-muted small mb-0">The client has been notified.</p>`,
                    confirmButtonColor: '#0A2D5E',
                    customClass: { popup: 'swal2-border-radius' }
                }).then(() => location.reload());
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to approve suspension. Please try again.',
                    customClass: { popup: 'swal2-border-radius' }
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Approve';
            }
        }
    });
});

document.querySelectorAll('.btn-decline-suspend').forEach(btn => {
    btn.addEventListener('click', async () => {
        const requestId = btn.getAttribute('data-request-id');
        const title = btn.getAttribute('data-project-title');

        const result = await Swal.fire({
            title: 'Decline Suspension?',
            html: `<div class="text-start">
                <p class="text-muted mb-2">You are about to decline the suspension request for:</p>
                <div class="p-2 rounded-3 mb-2" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                    <strong class="text-success">"${title}"</strong>
                </div>
                <p class="text-muted small mb-0">The project will remain active and the client will be notified that their suspension request was declined.</p>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-times-circle me-1"></i> Yes, Decline',
            cancelButtonText: 'Go Back',
            customClass: { popup: 'swal2-border-radius' },
            reverseButtons: true
        });

        if (result.isConfirmed) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

            try {
                const formData = new FormData();
                formData.append('action', 'decline_suspension');
                formData.append('request_id', requestId);

                const res = await fetch('client_requests.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });

                Swal.fire({
                    icon: 'success',
                    title: 'Suspension Declined',
                    html: `<p>The suspension request for <strong>"${title}"</strong> has been declined.</p>
                           <p class="text-muted small mb-0">The client has been notified.</p>`,
                    confirmButtonColor: '#0A2D5E',
                    customClass: { popup: 'swal2-border-radius' }
                }).then(() => location.reload());
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to decline suspension. Please try again.',
                    customClass: { popup: 'swal2-border-radius' }
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times-circle me-1"></i> Decline';
            }
        }
    });
});
</script>
