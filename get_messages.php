<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in']);
    exit;
}

// Check if room ID is provided
if (!isset($_GET['room_id'])) {
    echo json_encode(['success' => false, 'error' => 'Room ID is required']);
    exit;
}

$userId = $_SESSION['user_id'];
$roomId = $_GET['room_id'];

// Load rooms data to check if user is a member
$roomsFile = 'rooms.json';
if (!file_exists($roomsFile)) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

$roomsData = json_decode(file_get_contents($roomsFile), true);
if (!$roomsData) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

// Find the room
$room = null;
// Check if 'rooms' key exists in the data
if (isset($roomsData['rooms'])) {
    foreach ($roomsData['rooms'] as $r) {
        if ($r['id'] === $roomId) {
            $room = $r;
            break;
        }
    }
} else {
    // Assume roomsData is directly the array of rooms
    foreach ($roomsData as $r) {
        if ($r['id'] === $roomId) {
            $room = $r;
            break;
        }
    }
}

if (!$room) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

// Check if user is a member of the room
$isMember = false;
foreach ($room['members'] as $member) {
    if ($member['id'] === $userId) {
        $isMember = true;
        break;
    }
}

if (!$isMember) {
    echo json_encode(['success' => false, 'error' => 'You are not a member of this room']);
    exit;
}

// Load chat data
$chatFile = 'chat_data.json';
$messages = [];

if (file_exists($chatFile)) {
    $chatData = json_decode(file_get_contents($chatFile), true);
    if ($chatData && isset($chatData['messages'])) {
        // Filter messages for this room
        foreach ($chatData['messages'] as $message) {
            if ($message['room_id'] === $roomId) {
                // Mark message as read by current user if not already
                if (!isset($message['read_by']) || !in_array($userId, $message['read_by'])) {
                    // Find the message in the original data and update it
                    foreach ($chatData['messages'] as &$origMessage) {
                        if ($origMessage['id'] === $message['id']) {
                            if (!isset($origMessage['read_by'])) {
                                $origMessage['read_by'] = [];
                            }
                            if (!in_array($userId, $origMessage['read_by'])) {
                                $origMessage['read_by'][] = $userId;
                            }
                            break;
                        }
                    }
                }
                
                $messages[] = $message;
            }
        }
        
        // Save updated read status
        file_put_contents($chatFile, json_encode($chatData, JSON_PRETTY_PRINT));
    }
}

// NEW: Organize messages into threads
$threadedMessages = [];
$repliesMap = [];

// First, create a map of all messages by ID for easy lookup
$messagesById = [];
foreach ($messages as $message) {
    $messagesById[$message['id']] = $message;
}

// Then, collect only top-level messages (those without a parent)
foreach ($messages as $message) {
    if (empty($message['parent_id'])) {
        // Add the message to threaded messages
        $threadedMessage = $message;
        
        // Add reply_messages array with actual message objects
        $threadedMessage['reply_messages'] = [];
        if (isset($message['replies']) && is_array($message['replies'])) {
            foreach ($message['replies'] as $replyId) {
                if (isset($messagesById[$replyId])) {
                    $threadedMessage['reply_messages'][] = $messagesById[$replyId];
                }
            }
        }
        
        $threadedMessages[] = $threadedMessage;
    }
}

// Sort messages by time
usort($threadedMessages, function($a, $b) {
    return strtotime($a['time']) - strtotime($b['time']);
});

// Return messages
echo json_encode(['success' => true, 'messages' => $threadedMessages]);
?>