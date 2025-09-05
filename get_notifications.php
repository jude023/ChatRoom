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
    file_put_contents($notificationsFile, json_encode(['notifications' => []]));
}

$notificationsData = json_decode(file_get_contents($notificationsFile), true);
if (!$notificationsData) {
    $notificationsData = ['notifications' => []];
}

// Get unread notifications for the user
$unreadNotifications = [];
foreach ($notificationsData['notifications'] as $notification) {
    if ($notification['user_id'] === $userId && !$notification['read']) {
        $unreadNotifications[] = $notification;
    }
}

echo json_encode([
    'success' => true,
    'count' => count($unreadNotifications),
    'notifications' => $unreadNotifications
]);
?>