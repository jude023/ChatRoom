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

// Check if room ID and password are provided
if (!isset($_POST['room_id']) || !isset($_POST['password'])) {
    echo json_encode(['success' => false, 'error' => 'Room ID and password are required']);
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$roomId = $_POST['room_id'];
$password = $_POST['password'];

// Load rooms data
$roomsFile = 'rooms.json';
$roomsData = json_decode(file_get_contents($roomsFile), true);

// Find the room
$room = null;
foreach ($roomsData['rooms'] as &$r) {
    if ($r['id'] === $roomId) {
        $room = &$r;
        break;
    }
}

if (!$room) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

// Check if password is correct
if (!password_verify($password, $room['password'])) {
    echo json_encode(['success' => false, 'error' => 'Incorrect password']);
    exit;
}

// Check if user is already a member
foreach ($room['members'] as $member) {
    if ($member['id'] === $userId) {
        echo json_encode(['success' => true]); // Already a member
        exit;
    }
}

// Add user to room members
$room['members'][] = [
    'id' => $userId,
    'username' => $username,
    'role' => 'member'
];

// Save updated rooms data
if (!file_put_contents($roomsFile, json_encode($roomsData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to join room']);
    exit;
}

// Add room to user's rooms list
$usersFile = 'users.json';
$userData = json_decode(file_get_contents($usersFile), true);

foreach ($userData['users'] as &$user) {
    if ($user['id'] === $userId) {
        if (!in_array($roomId, $user['rooms'])) {
            $user['rooms'][] = $roomId;
        }
        break;
    }
}

if (!file_put_contents($usersFile, json_encode($userData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to update user data']);
    exit;
}

echo json_encode(['success' => true]);
?>