<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

// Load notifications data
$notificationsFile = 'notifications.json';
if (!file_exists($notificationsFile)) {
    echo json_encode(['success' => true]);
    exit;
}

$notificationsData = json_decode(file_get_contents($notificationsFile), true);
if (!$notificationsData) {
    echo json_encode(['success' => true]);
    exit;
}

// Mark all notifications for the user as read
$updated = false;
foreach ($notificationsData['notifications'] as &$notification) {
    if ($notification['user_id'] === $userId && !$notification['read']) {
        $notification['read'] = true;
        $updated = true;
    }
}

if ($updated) {
    file_put_contents($notificationsFile, json_encode($notificationsData, JSON_PRETTY_PRINT));
}

echo json_encode(['success' => true]);
?>