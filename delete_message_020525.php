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

// Check if message ID and room ID are provided
if (!isset($_POST['message_id']) || !isset($_POST['room_id'])) {
    echo json_encode(['success' => false, 'error' => 'Message ID and Room ID are required']);
    exit;
}

$userId = $_SESSION['user_id'];
$messageId = $_POST['message_id'];
$roomId = $_POST['room_id'];

// Load rooms data to check if user is admin
$roomsFile = 'rooms.json';
$roomsData = json_decode(file_get_contents($roomsFile), true);

// Check if user is admin of the room
$isAdmin = false;
foreach ($roomsData['rooms'] as $room) {
    if ($room['id'] === $roomId) {
        foreach ($room['members'] as $member) {
            if ($member['id'] === $userId && $member['role'] === 'admin') {
                $isAdmin = true;
                break;
            }
        }
        break;
    }
}

// Load chat data
$chatFile = 'chat_data.json';
$chatData = json_decode(file_get_contents($chatFile), true);

// Find the message
$messageIndex = -1;
$messageOwnerId = null;
foreach ($chatData['messages'] as $index => $message) {
    if ($message['id'] === $messageId) {
        $messageIndex = $index;
        $messageOwnerId = $message['user_id'];
        break;
    }
}

if ($messageIndex === -1) {
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

// Check if user is allowed to delete the message (admin or message owner)
if (!$isAdmin && $messageOwnerId !== $userId) {
    echo json_encode(['success' => false, 'error' => 'You are not allowed to delete this message']);
    exit;
}

// Remove the message
array_splice($chatData['messages'], $messageIndex, 1);

// Save updated chat data
if (file_put_contents($chatFile, json_encode($chatData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to delete message']);
}
?>