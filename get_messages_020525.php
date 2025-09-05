<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in']);
    exit;
}

// Check if room ID is provided
if (!isset($_GET['room_id'])) {
    echo json_encode(['success' => false, 'error' => 'Room ID is required']);
    exit;
}

$userId = $_SESSION['user_id'];
$roomId = $_GET['room_id'];

// Load rooms data to check if user is a member
$roomsFile = 'rooms.json';
if (!file_exists($roomsFile)) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

$roomsData = json_decode(file_get_contents($roomsFile), true);
if (!$roomsData) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

// Find the room
$room = null;
foreach ($roomsData['rooms'] as $r) {
    if ($r['id'] === $roomId) {
        $room = $r;
        break;
    }
}

if (!$room) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

// Check if user is a member of the room
$isMember = false;
foreach ($room['members'] as $member) {
    if ($member['id'] === $userId) {
        $isMember = true;
        break;
    }
}

if (!$isMember) {
    echo json_encode(['success' => false, 'error' => 'You are not a member of this room']);
    exit;
}

// Load chat data
$chatFile = 'chat_data.json';
if (!file_exists($chatFile)) {
    echo json_encode(['success' => true, 'messages' => []]);
    exit;
}

$chatData = json_decode(file_get_contents($chatFile), true);
if (!$chatData) {
    echo json_encode(['success' => true, 'messages' => []]);
    exit;
}

// Filter messages for this room
$roomMessages = [];
foreach ($chatData['messages'] as $message) {
    if ($message['room_id'] === $roomId) {
        $roomMessages[] = $message;
    }
}

echo json_encode(['success' => true, 'messages' => $roomMessages]);
?>