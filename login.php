<?php
header('Content-Type: application/json');
session_start();

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Check if username and password are provided
if (!isset($_POST['username']) || !isset($_POST['password'])) {
    echo json_encode(['success' => false, 'error' => 'Username and password are required']);
    exit;
}

$username = trim($_POST['username']);
$password = $_POST['password'];

// Validate input
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Username and password are required']);
    exit;
}

// Load users data
$usersFile = 'users.json';
// echo file_exists($usersFile);
if (!file_exists($usersFile)) {
    
    echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
    exit;
}

$userData = json_decode(file_get_contents($usersFile), true);
// echo $userData;
if (!$userData) {
    
    echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
    exit;
}

// Find user by username
$user = null;
foreach ($userData['users'] as $u) {
    if ($u['username'] === $username) {
        $user = $u;
        break;
    }
}

// Check if user exists and password is correct
if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
    exit;
}

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];

echo json_encode(['success' => true]);
?>