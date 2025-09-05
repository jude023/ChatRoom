<?php
header('Content-Type: application/json');
session_start();

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Check if all required fields are provided
if (!isset($_POST['username']) || !isset($_POST['email']) || !isset($_POST['password'])) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = $_POST['password'];

// Validate input
if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters long']);
    exit;
}

// Get user's IP address
$ip_address = $_SERVER['REMOTE_ADDR'];

// Load existing users
$usersFile = 'users.json';
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode(['users' => []]));
}

$userData = json_decode(file_get_contents($usersFile), true);
if (!$userData) {
    $userData = ['users' => []];
}

// Check if username or email already exists
foreach ($userData['users'] as $user) {
    if ($user['username'] === $username) {
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        exit;
    }
    if ($user['email'] === $email) {
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        exit;
    }
}

// Create new user
$newUser = [
    'id' => uniqid(),
    'username' => $username,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'ip_address' => $ip_address,
    'created_at' => date('Y-m-d H:i:s'),
    'rooms' => []
];

$userData['users'][] = $newUser;

// Save updated user data
if (file_put_contents($usersFile, json_encode($userData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to register user']);
}
?>