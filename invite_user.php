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

// Check if room ID and username are provided
if (!isset($_POST['room_id']) || !isset($_POST['username']) || empty(trim($_POST['username']))) {
    echo json_encode(['success' => false, 'error' => 'Room ID and username are required']);
    exit;
}

$userId = $_SESSION['user_id'];
$roomId = $_POST['room_id'];
$invitedUsername = trim($_POST['username']);

// Load rooms data
$roomsFile = 'rooms.json';
$roomsData = json_decode(file_get_contents($roomsFile), true);

// Check if room exists and user is admin
$room = null;
$isAdmin = false;
foreach ($roomsData['rooms'] as &$r) {
    if ($r['id'] === $roomId) {
        $room = &$r;
        foreach ($r['members'] as $member) {
            if ($member['id'] === $userId && $member['role'] === 'admin') {
                $isAdmin = true;
                break;
            }
        }
        break;
    }
}

if (!$room) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

if (!$isAdmin) {
    echo json_encode(['success' => false, 'error' => 'You are not allowed to invite users to this room']);
    exit;
}

// Load users data
$usersFile = 'users.json';
$userData = json_decode(file_get_contents($usersFile), true);

// Find the invited user
$invitedUser = null;
foreach ($userData['users'] as &$user) {
    if ($user['username'] === $invitedUsername) {
        $invitedUser = &$user;
        break;
    }
}

if (!$invitedUser) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Check if user is already a member
foreach ($room['members'] as $member) {
    if ($member['id'] === $invitedUser['id']) {
        echo json_encode(['success' => false, 'error' => 'User is already a member of this room']);
        exit;
    }
}

// Add room to user's rooms
if (!in_array($roomId, $invitedUser['rooms'])) {
    $invitedUser['rooms'][] = $roomId;
}

// Add user to room members
$room['members'][] = [
    'id' => $invitedUser['id'],
    'username' => $invitedUser['username'],
    'role' => 'member'
];

// Save updated data
if (!file_put_contents($roomsFile, json_encode($roomsData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to update room data']);
    exit;
}

if (!file_put_contents($usersFile, json_encode($userData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to update user data']);
    exit;
}

// Create notification for invited user
$notificationsFile = 'notifications.json';
if (!file_exists($notificationsFile)) {
    file_put_contents($notificationsFile, json_encode(['notifications' => []]));
}

$notificationsData = json_decode(file_get_contents($notificationsFile), true);
if (!$notificationsData) {
    $notificationsData = ['notifications' => []];
}

$notification = [
    'id' => uniqid('notif_'),
    'user_id' => $invitedUser['id'],
    'room_id' => $roomId,
    'room_name' => $room['name'],
    'message' => "You have been invited to join {$room['name']} by {$_SESSION['username']}",
    'created_at' => date('Y-m-d H:i:s'),
    'read' => false
];

$notificationsData['notifications'][] = $notification;
file_put_contents($notificationsFile, json_encode($notificationsData, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);
?>