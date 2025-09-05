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

// Check if room ID and user ID are provided
if (!isset($_POST['room_id']) || !isset($_POST['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Room ID and User ID are required']);
    exit;
}

$adminId = $_SESSION['user_id'];
$roomId = $_POST['room_id'];
$kickedUserId = $_POST['user_id'];

// Cannot kick yourself
if ($adminId === $kickedUserId) {
    echo json_encode(['success' => false, 'error' => 'You cannot kick yourself']);
    exit;
}

// Load rooms data
$roomsFile = 'rooms.json';
$roomsData = json_decode(file_get_contents($roomsFile), true);

// Check if room exists and user is admin
$roomIndex = -1;
$isAdmin = false;
foreach ($roomsData['rooms'] as $index => $room) {
    if ($room['id'] === $roomId) {
        $roomIndex = $index;
        foreach ($room['members'] as $member) {
            if ($member['id'] === $adminId && $member['role'] === 'admin') {
                $isAdmin = true;
                break;
            }
        }
        break;
    }
}

if ($roomIndex === -1) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

if (!$isAdmin) {
    echo json_encode(['success' => false, 'error' => 'You are not allowed to kick users from this room']);
    exit;
}

// Remove user from room members
$memberIndex = -1;
foreach ($roomsData['rooms'][$roomIndex]['members'] as $index => $member) {
    if ($member['id'] === $kickedUserId) {
        $memberIndex = $index;
        break;
    }
}

if ($memberIndex === -1) {
    echo json_encode(['success' => false, 'error' => 'User is not a member of this room']);
    exit;
}

// Check if the kicked user is also an admin
if ($roomsData['rooms'][$roomIndex]['members'][$memberIndex]['role'] === 'admin') {
    echo json_encode(['success' => false, 'error' => 'You cannot kick another admin']);
    exit;
}

// Remove member from room
array_splice($roomsData['rooms'][$roomIndex]['members'], $memberIndex, 1);

// Load users data
$usersFile = 'users.json';
$userData = json_decode(file_get_contents($usersFile), true);

// Remove room from user's rooms
foreach ($userData['users'] as &$user) {
    if ($user['id'] === $kickedUserId) {
        $roomIdIndex = array_search($roomId, $user['rooms']);
        if ($roomIdIndex !== false) {
            array_splice($user['rooms'], $roomIdIndex, 1);
        }
        break;
    }
}

// Save updated data
if (!file_put_contents($roomsFile, json_encode($roomsData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to update room data']);
    exit;
}

if (!file_put_contents($usersFile, json_encode($userData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to update user data']);
    exit;
}

// Create notification for kicked user
$notificationsFile = 'notifications.json';
$notificationsData = json_decode(file_get_contents($notificationsFile), true);

$notification = [
    'id' => uniqid('notif_'),
    'user_id' => $kickedUserId,
    'room_id' => null,
    'room_name' => $roomsData['rooms'][$roomIndex]['name'],
    'message' => "You have been kicked from {$roomsData['rooms'][$roomIndex]['name']} by {$_SESSION['username']}",
    'created_at' => date('Y-m-d H:i:s'),
    'read' => false
];

$notificationsData['notifications'][] = $notification;
file_put_contents($notificationsFile, json_encode($notificationsData, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);
?>