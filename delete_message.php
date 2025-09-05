<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in']);
    exit;
}

// Check if message ID is provided
if (!isset($_POST['message_id'])) {
    echo json_encode(['success' => false, 'error' => 'Message ID is required']);
    exit;
}

$userId = $_SESSION['user_id'];
$messageId = $_POST['message_id'];

// Load chat data
$chatFile = 'chat_data.json';
if (!file_exists($chatFile)) {
    echo json_encode(['success' => false, 'error' => 'Chat data not found']);
    exit;
}

$chatData = json_decode(file_get_contents($chatFile), true);
if (!$chatData || !isset($chatData['messages'])) {
    echo json_encode(['success' => false, 'error' => 'Failed to parse chat data']);
    exit;
}

// Find the message
$messageIndex = -1;
$message = null;
foreach ($chatData['messages'] as $index => $msg) {
    if ($msg['id'] === $messageId) {
        $messageIndex = $index;
        $message = $msg;
        break;
    }
}

if ($messageIndex === -1) {
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

// Check if user is the message sender
if ($message['user_id'] !== $userId) {
    echo json_encode(['success' => false, 'error' => 'You can only delete your own messages']);
    exit;
}

// NEW: If this message has replies, update them
if (isset($message['replies']) && !empty($message['replies'])) {
    // For each reply, set its parent_id to null (making it a top-level message)
    foreach ($chatData['messages'] as &$msg) {
        if (in_array($msg['id'], $message['replies'])) {
            $msg['parent_id'] = null;
        }
    }
}

// NEW: If this message is a reply, remove it from parent's replies array
if (isset($message['parent_id']) && $message['parent_id']) {
    foreach ($chatData['messages'] as &$msg) {
        if ($msg['id'] === $message['parent_id'] && isset($msg['replies'])) {
            $replyIndex = array_search($messageId, $msg['replies']);
            if ($replyIndex !== false) {
                array_splice($msg['replies'], $replyIndex, 1);
            }
        }
    }
}

// Delete the message
array_splice($chatData['messages'], $messageIndex, 1);

// Save updated chat data
if (!file_put_contents($chatFile, json_encode($chatData, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => false, 'error' => 'Failed to delete message']);
    exit;
}

echo json_encode(['success' => true]);
?>