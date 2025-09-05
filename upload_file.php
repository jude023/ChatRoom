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

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error = isset($_FILES['file']) ? $_FILES['file']['error'] : 'No file uploaded';
    echo json_encode(['success' => false, 'error' => 'File upload error: ' . $error]);
    exit;
}

$userId = $_SESSION['user_id'];
$roomId = $_POST['room_id'] ?? '';

if (empty($roomId)) {
    echo json_encode(['success' => false, 'error' => 'Room ID is required']);
    exit;
}

// Check if user is a member of the room
$roomsFile = 'rooms.json';
$roomsData = json_decode(file_get_contents($roomsFile), true);

$isMember = false;
foreach ($roomsData['rooms'] as $room) {
    if ($room['id'] === $roomId) {
        foreach ($room['members'] as $member) {
            if ($member['id'] === $userId) {
                $isMember = true;
                break;
            }
        }
        break;
    }
}

if (!$isMember) {
    echo json_encode(['success' => false, 'error' => 'You are not a member of this room']);
    exit;
}

// Get file information
$file = $_FILES['file'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileTmpName = $file['tmp_name'];
$fileError = $file['error'];
$fileType = $file['type'];

// Get file extension
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Set allowed file types (you can customize this list)
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];

// Check if file extension is allowed
if (!in_array($fileExt, $allowedExtensions)) {
    echo json_encode(['success' => false, 'error' => 'File type not allowed']);
    exit;
}

// Check file size (limit to 10MB)
if ($fileSize > 10000000) {
    echo json_encode(['success' => false, 'error' => 'File is too large (max 10MB)']);
    exit;
}

// Create unique filename to prevent overwriting
$newFileName = uniqid('file_') . '.' . $fileExt;
$uploadPath = 'uploads/' . $newFileName;

// Move uploaded file to destination
if (move_uploaded_file($fileTmpName, $uploadPath)) {
    // Return success with file information
    echo json_encode([
        'success' => true,
        'file' => [
            'name' => $fileName,
            'path' => $uploadPath,
            'size' => $fileSize,
            'type' => $fileType
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
}
?>