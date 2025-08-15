<?php
// maintenance.php
session_start();
require_once '../backend/crud/db_config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../pages/loginn.php");
    exit;
}

$user_role = $_SESSION['membershipType'] ?? 'guest';
if ($user_role !== 'admin') {
    exit("Access denied. Admins only.");
}

$user_id = $_SESSION['UserID'];
$action  = $_POST['action'] ?? '';

function redirect_with($msg, $type = 'success', $anchorTab = '') {
    $loc = "BookMain.php?msg=" . urlencode($msg) . "&type=" . urlencode($type);
    if ($anchorTab) $loc .= $anchorTab; // e.g. "#tab-active"
    header("Location: $loc");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with('Invalid request method.', 'danger');
}

if ($action === 'issue') {
    $copy_id = isset($_POST['copy_id']) ? intval($_POST['copy_id']) : 0;
    $issue   = trim($_POST['issue'] ?? '');

    if ($copy_id <= 0 || $issue === '') {
        redirect_with('Missing copy or issue.', 'danger');
    }

    // Ensure copy exists and is Available
    $stmt = $con->prepare("SELECT Status FROM BookCopy WHERE CopyID = ?");
    $stmt->bind_param("i", $copy_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        redirect_with('Copy not found.', 'danger');
    }
    $row = $res->fetch_assoc();
    if ($row['Status'] !== 'Available') {
        redirect_with('Only Available copies can be sent to maintenance.', 'warning');
    }

    // Insert maintenance log (IsResolved defaults FALSE)
    $stmt2 = $con->prepare("INSERT INTO MaintenanceLog (CopyID, UserID, DateReported, IssueDescription, IsResolved) VALUES (?, ?, CURDATE(), ?, FALSE)");
    $stmt2->bind_param("iis", $copy_id, $user_id, $issue);
    $ok1 = $stmt2->execute();

    // Update copy status
    $stmt3 = $con->prepare("UPDATE BookCopy SET Status = 'Maintenance' WHERE CopyID = ?");
    $stmt3->bind_param("i", $copy_id);
    $ok2 = $stmt3->execute();

    if ($ok1 && $ok2) {
        redirect_with('Copy sent to maintenance.', 'success', '#tab-active');
    } else {
        redirect_with('Failed to issue maintenance.', 'danger');
    }
}

if ($action === 'resolve') {
    $log_id  = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
    $copy_id = isset($_POST['copy_id']) ? intval($_POST['copy_id']) : 0;

    if ($log_id <= 0 || $copy_id <= 0) {
        redirect_with('Missing maintenance record.', 'danger');
    }

    // Mark log resolved
    $stmt1 = $con->prepare("UPDATE MaintenanceLog SET IsResolved = TRUE WHERE LogID = ?");
    $stmt1->bind_param("i", $log_id);
    $ok1 = $stmt1->execute();

    // Set copy back to Available
    $stmt2 = $con->prepare("UPDATE BookCopy SET Status = 'Available' WHERE CopyID = ?");
    $stmt2->bind_param("i", $copy_id);
    $ok2 = $stmt2->execute();

    if ($ok1 && $ok2) {
        redirect_with('Maintenance resolved and copy is now Available.', 'success', '#tab-history');
    } else {
        redirect_with('Failed to resolve maintenance.', 'danger');
    }
}

redirect_with('Unknown action.', 'danger');
