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

// Check if required parameters are provided
if (!isset($_POST['message_id']) || !isset($_POST['reaction'])) {
    echo json_encode(['success' => false, 'error' => 'Message ID and reaction are required']);
    exit;
}

$userId = $_SESSION['user_id'];
$messageId = $_POST['message_id'];
$reaction = $_POST['reaction'];

// echo $userId,$messageId, $reaction ;


// Load chat data
$chatFile = 'chat_data.json';
if (!file_exists($chatFile)) {
    echo json_encode(['success' => false, 'error' => 'Chat data not found']);
    exit;
}

$chatData = json_decode(file_get_contents($chatFile), true);
if (!$chatData) {
    echo json_encode(['success' => false, 'error' => 'Invalid chat data']);
    exit;
}

$messageFound = false;
$updated = false;

// Update reactions for the message
foreach ($chatData['messages'] as &$message) {
    if ($message['id'] === $messageId) {
        $messageFound = true;
        
        // Initialize reactions if it doesn't exist
        if (!isset($message['reactions'])) {
            $message['reactions'] = [];
        }
        
        // Check if user already reacted with this emoji
        if (isset($message['reactions'][$reaction]) && in_array($userId, $message['reactions'][$reaction])) {
            // Remove the reaction (toggle off)
            $message['reactions'][$reaction] = array_values(array_filter(
                $message['reactions'][$reaction],
                function($id) use ($userId) { return $id !== $userId; }
            ));
            
            // Remove empty reaction arrays
            if (empty($message['reactions'][$reaction])) {
                unset($message['reactions'][$reaction]);
            }
        } else {
            // Add the reaction
            if (!isset($message['reactions'][$reaction])) {
                $message['reactions'][$reaction] = [];
            }
            $message['reactions'][$reaction][] = $userId;
        }
        
        $updated = true;
        break;
    }
}

if (!$messageFound) {
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

// Save updated chat data if changes were made
if ($updated) {
    if (!file_put_contents($chatFile, json_encode($chatData, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => false, 'error' => 'Failed to update reactions']);
        exit;
    }
}

echo json_encode(['success' => true]);
?>