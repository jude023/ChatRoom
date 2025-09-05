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

// Load rooms data
$roomsFile = 'rooms.json';
$roomsData = json_decode(file_get_contents($roomsFile), true);

// Find the room and check if user is the creator
$roomIndex = -1;
$roomMembers = [];
$roomName = '';
foreach ($roomsData['rooms'] as $index => $room) {
    if ($room['id'] === $roomId) {
        $roomIndex = $index;
        $roomMembers = $room['members'];
        $roomName = $room['name'];
        if ($room['creator_id'] !== $userId) {
            echo json_encode(['success' => false, 'error' => 'Only the room creator can delete the room']);
            exit;
        }
        break;
    }
}

if ($roomIndex === -1) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

// Remove room from rooms data
array_splice($roomsData['rooms'], $roomIndex, 1);

// Save updated rooms data
if (!file_put_contents($roomsFile, json_encode($roomsData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to delete room']);
    exit;
}

// Load users data
$usersFile = 'users.json';
$userData = json_decode(file_get_contents($usersFile), true);

// Remove room from all users' rooms list
foreach ($userData['users'] as &$user) {
    $roomIdIndex = array_search($roomId, $user['rooms']);
    if ($roomIdIndex !== false) {
        array_splice($user['rooms'], $roomIdIndex, 1);
    }
}

// Save updated users data
if (!file_put_contents($usersFile, json_encode($userData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to update user data']);
    exit;
}

// Delete all messages for this room
$chatFile = 'chat_data.json';
$chatData = json_decode(file_get_contents($chatFile), true);

$newMessages = [];
foreach ($chatData['messages'] as $message) {
    if ($message['room_id'] !== $roomId) {
        $newMessages[] = $message;
    }
}

$chatData['messages'] = $newMessages;
file_put_contents($chatFile, json_encode($chatData, JSON_PRETTY_PRINT));

// Create notifications for all members
$notificationsFile = 'notifications.json';
$notificationsData = json_decode(file_get_contents($notificationsFile), true);

foreach ($roomMembers as $member) {
    if ($member['id'] !== $userId) { // Don't notify the creator
        $notification = [
            'id' => uniqid('notif_'),
            'user_id' => $member['id'],
            'room_id' => null,
            'room_name' => $roomName,
            'message' => "The room '$roomName' has been deleted by the creator",
            'created_at' => date('Y-m-d H:i:s'),
            'read' => false
        ];
        
        $notificationsData['notifications'][] = $notification;
    }
}

file_put_contents($notificationsFile, json_encode($notificationsData, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);
?>