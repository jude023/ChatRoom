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

// Check if room name is provided
if (!isset($_POST['room_name']) || empty(trim($_POST['room_name']))) {
    echo json_encode(['success' => false, 'error' => 'Room name is required']);
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$roomName = trim($_POST['room_name']);
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

// Load rooms data
$roomsFile = 'rooms.json';
if (!file_exists($roomsFile)) {
    file_put_contents($roomsFile, json_encode(['rooms' => []]));
}

$roomsData = json_decode(file_get_contents($roomsFile), true);
if (!$roomsData) {
    $roomsData = ['rooms' => []];
}

// Create new room
$roomId = uniqid('room_');
$newRoom = [
    'id' => $roomId,
    'name' => $roomName,
    'description' => $description,
    'has_password' => !empty($password),
    'password' => !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : '',
    'creator_id' => $userId,
    'creator_name' => $username,
    'created_at' => date('Y-m-d H:i:s'),
    'members' => [
        [
            'id' => $userId,
            'username' => $username,
            'role' => 'admin' // Creator is admin
        ]
    ]
];

$roomsData['rooms'][] = $newRoom;

// Save updated rooms data
if (!file_put_contents($roomsFile, json_encode($roomsData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to create room']);
    exit;
}

// Update user's rooms list
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

echo json_encode(['success' => true, 'room_id' => $roomId]);
?>