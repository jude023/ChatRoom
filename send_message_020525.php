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
$username = $_SESSION['username'];
$roomId = $_POST['room_id'];
$messageText = isset($_POST['message']) ? trim($_POST['message']) : '';
$fileData = isset($_POST['file_data']) ? json_decode($_POST['file_data'], true) : null;

// Check if either message or file is provided
if (empty($messageText) && !$fileData) {
    echo json_encode(['success' => false, 'error' => 'Message or file is required']);
    exit;
}

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
    file_put_contents($chatFile, json_encode(['messages' => []]));
}

$chatData = json_decode(file_get_contents($chatFile), true);
if (!$chatData) {
    $chatData = ['messages' => []];
}

// Add new message
$messageId = uniqid('msg_');
$newMessage = [
    'id' => $messageId,
    'room_id' => $roomId,
    'user_id' => $userId,
    'username' => $username,
    'text' => htmlspecialchars($messageText),
    'time' => date('Y-m-d H:i:s'),
    'has_file' => $fileData ? true : false,
    'read_by' => [$userId], // Sender has read their own message
    'reactions' => [] // Initialize empty reactions
];

// Add file data if present
if ($fileData) {
    $newMessage['file'] = [
        'name' => htmlspecialchars($fileData['name']),
        'path' => $fileData['path'],
        'size' => $fileData['size'],
        'type' => $fileData['type']
    ];
}

$chatData['messages'][] = $newMessage;

// Save updated chat data
if (!file_put_contents($chatFile, json_encode($chatData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to save message']);
    exit;
}

// Create notifications for all room members except the sender
$notificationsFile = 'notifications.json';
if (!file_exists($notificationsFile)) {
    file_put_contents($notificationsFile, json_encode(['notifications' => []]));
}

$notificationsData = json_decode(file_get_contents($notificationsFile), true);
if (!$notificationsData) {
    $notificationsData = ['notifications' => []];
}

$messageType = $fileData ? 'file' : 'message';
foreach ($room['members'] as $member) {
    if ($member['id'] !== $userId) {
        $notification = [
            'id' => uniqid('notif_'),
            'user_id' => $member['id'],
            'room_id' => $roomId,
            'room_name' => $room['name'],
            'message' => "New $messageType from $username in {$room['name']}",
            'created_at' => date('Y-m-d H:i:s'),
            'read' => false
        ];
        
        $notificationsData['notifications'][] = $notification;
    }
}

file_put_contents($notificationsFile, json_encode($notificationsData, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);
?>