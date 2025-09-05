<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in']);
    exit;
}

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Check if room ID is provided
if (!isset($_POST['room_id'])) {
    echo json_encode(['success' => false, 'error' => 'Room ID is required']);
    exit;
}

$userId = $_SESSION['user_id'];
$roomId = $_POST['room_id'];
$messageIds = isset($_POST['message_ids']) ? json_decode($_POST['message_ids'], true) : [];

if (empty($messageIds)) {
    echo json_encode(['success' => true, 'message' => 'No messages to mark as read']);
    exit;
}

// Load chat data
$chatFile = 'chat_data.json';
if (!file_exists($chatFile)) {
    echo json_encode(['success' => false, 'error' => 'Chat data not found']);
    exit;
}

$chatData = json_decode(file_get_contents($chatFile), true);
if (!$chatData) {
    echo json_encode(['success' => false, 'error' => 'Invalid chat data']);
    exit;
}

$updated = false;

// Update read status for each message
foreach ($chatData['messages'] as &$message) {
    if ($message['room_id'] === $roomId && in_array($message['id'], $messageIds)) {
        // Initialize read_by array if it doesn't exist
        if (!isset($message['read_by'])) {
            $message['read_by'] = [];
        }
        
        // Add user to read_by if not already there
        if (!in_array($userId, $message['read_by'])) {
            $message['read_by'][] = $userId;
            $updated = true;
        }
    }
}

// Save updated chat data if changes were made
if ($updated) {
    if (!file_put_contents($chatFile, json_encode($chatData, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => false, 'error' => 'Failed to update read status']);
        exit;
    }
}

echo json_encode(['success' => true]);
?>