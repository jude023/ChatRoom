<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Check if room ID is provided
if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$roomId = $_GET['id'];

// Load rooms data
$roomsFile = 'rooms.json';
if (!file_exists($roomsFile)) {
    header('Location: dashboard.php');
    exit;
}

$roomsData = json_decode(file_get_contents($roomsFile), true);
if (!$roomsData) {
    header('Location: dashboard.php');
    exit;
}

// Find the room
$room = null;
foreach ($roomsData['rooms'] as $r) {
    if ($r['id'] === $roomId) {
        $room = $r;
        break;
    }
}

if (!$room) {
    header('Location: dashboard.php');
    exit;
}

// Check if user is a member of the room
$isMember = false;
$isAdmin = false;
foreach ($room['members'] as $member) {
    if ($member['id'] === $userId) {
        $isMember = true;
        $isAdmin = ($member['role'] === 'admin');
        break;
    }
}

if (!$isMember) {
    // If room has password, show password prompt
    if ($room['has_password']) {
        $needPassword = true;
    } else {
        // Join public room automatically
        $usersFile = 'users.json';
        $userData = json_decode(file_get_contents($usersFile), true);
        
        // Add room to user's rooms
        foreach ($userData['users'] as &$user) {
            if ($user['id'] === $userId) {
                if (!in_array($roomId, $user['rooms'])) {
                    $user['rooms'][] = $roomId;
                }
                break;
            }
        }
        
        file_put_contents($usersFile, json_encode($userData, JSON_PRETTY_PRINT));
        
        // Add user to room members
        foreach ($roomsData['rooms'] as &$r) {
            if ($r['id'] === $roomId) {
                $r['members'][] = [
                    'id' => $userId,
                    'username' => $username,
                    'role' => 'member'
                ];
                break;
            }
        }
        
        file_put_contents($roomsFile, json_encode($roomsData, JSON_PRETTY_PRINT));
        
        // Redirect to refresh
        header('Location: room.php?id=' . $roomId);
        exit;
    }
} else {
    $needPassword = false;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($room['name']); ?> - Chat Room</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            display: flex;
            height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            overflow-y: auto;
        }
        .room-info {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #34495e;
        }
        .room-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .room-description {
            font-size: 14px;
            color: #bdc3c7;
            margin-bottom: 10px;
        }
        .members-list {
            margin-top: 20px;
        }
        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #34495e;
        }
        .member-name {
            display: flex;
            align-items: center;
        }
        .admin-badge {
            background-color: #e74c3c;
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 3px;
            margin-left: 5px;
        }
        .kick-btn {
            background-color: transparent;
            color: #e74c3c;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            padding: 15px 20px;
            background-color: #fff;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .back-btn {
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .invite-btn {
            padding: 8px 15px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .messages-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
        }
        .message-info {
            font-size: 12px;
            margin-bottom: 5px;
        }
        .message-actions {
            position: absolute;
            right: 5px;
            top: 5px;
            display: none;
        }
        .message:hover .message-actions {
            display: block;
        }
        .delete-msg-btn {
            background-color: transparent;
            color: #e74c3c;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }
        .my-message {
            align-items: flex-end;
        }
        .my-message .message-bubble {
            background-color: #DCF8C6;
        }
        .other-message {
            align-items: flex-start;
        }
        .other-message .message-bubble {
            background-color: #fff;
            border: 1px solid #ddd;
        }
        .input-area {
            padding: 15px;
            background-color: #fff;
            border-top: 1px solid #ddd;
            display: flex;
        }
        .message-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
        }
        .send-btn {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .password-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .password-form {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 300px;
        }
        .invite-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
        }
        .invite-form {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 300px;
        }
    </style>
</head>
<body>
    <?php if ($needPassword): ?>
    <div class="password-modal">
        <div class="password-form">
            <h2>Enter Room Password</h2>
            <form id="password-form">
                <div class="form-group">
                    <label for="room-password">Password:</label>
                    <input type="password" id="room-password" name="password" required>
                </div>
                <div id="password-error" style="color: red; margin-top: 10px;"></div>
                <button type="submit" class="send-btn" style="width: 100%; margin-top: 10px;">Join Room</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="invite-modal" id="invite-modal">
        <div class="invite-form">
            <h2>Invite User</h2>
            <form id="invite-form">
                <div class="form-group">
                    <label for="invite-username">Username:</label>
                    <input type="text" id="invite-username" name="username" required>
                </div>
                <div id="invite-error" style="color: red; margin-top: 10px;"></div>
                <div id="invite-success" style="color: green; margin-top: 10px;"></div>
                <button type="submit" class="send-btn" style="width: 100%; margin-top: 10px;">Send Invitation</button>
                <button type="button" class="back-btn" style="width: 100%; margin-top: 10px;" onclick="closeInviteModal()">Close</button>
            </form>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <div class="room-info">
                <div class="room-name"><?php echo htmlspecialchars($room['name']); ?></div>
                <div class="room-description"><?php echo htmlspecialchars($room['description']); ?></div>
                <div>Created by: <?php echo htmlspecialchars($room['creator_name']); ?></div>
                <div>Created: <?php echo htmlspecialchars($room['created_at']); ?></div>
            </div>
            
            <div class="members-list">
                <h3>Members (<?php echo count($room['members']); ?>)</h3>
                <?php foreach ($room['members'] as $member): ?>
                    <div class="member-item">
                        <div class="member-name">
                            <?php echo htmlspecialchars($member['username']); ?>
                            <?php if ($member['role'] === 'admin'): ?>
                                <span class="admin-badge">Admin</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($isAdmin && $member['id'] !== $userId): ?>
                            <button class="kick-btn" onclick="kickMember('<?php echo $member['id']; ?>')">Kick</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="chat-area">
            <div class="chat-header">
                <button class="back-btn" onclick="window.location.href='dashboard.php'">Back to Dashboard</button>
                <?php if ($isAdmin): ?>
                    <button class="invite-btn" onclick="showInviteModal()">Invite User</button>
                    <?php endif; ?>
            </div>
            
            <div class="messages-container" id="messages-container">
                <!-- Messages will be loaded here -->
            </div>
            
            <div class="input-area">
                <input type="text" class="message-input" id="message-input" placeholder="Type your message...">
                <button class="send-btn" id="send-btn">Send</button>
            </div>
        </div>
    </div>

    <script>
        // Load messages
        function loadMessages() {
            fetch('get_messages.php?room_id=<?php echo $roomId; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const messagesContainer = document.getElementById('messages-container');
                        let messagesHTML = '';
                        
                        data.messages.forEach(message => {
                            const isMyMessage = message.user_id === '<?php echo $userId; ?>';
                            const messageClass = isMyMessage ? 'message my-message' : 'message other-message';
                            
                            messagesHTML += `
                                <div class="${messageClass}" data-id="${message.id}">
                                    <div class="message-info">${message.username} - ${message.time}</div>
                                    <div class="message-bubble">
                                        ${message.text}
                                        ${isMyMessage || <?php echo $isAdmin ? 'true' : 'false'; ?> ? 
                                            `<div class="message-actions">
                                                <button class="delete-msg-btn" onclick="deleteMessage('${message.id}')">Delete</button>
                                            </div>` : ''
                                        }
                                    </div>
                                </div>
                            `;
                        });
                        
                        messagesContainer.innerHTML = messagesHTML;
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                })
                .catch(error => console.error('Error loading messages:', error));
        }

        // Send message
        function sendMessage() {
            const messageInput = document.getElementById('message-input');
            const messageText = messageInput.value.trim();
            
            if (!messageText) {
                return;
            }
            
            const formData = new FormData();
            formData.append('room_id', '<?php echo $roomId; ?>');
            formData.append('message', messageText);
            
            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    loadMessages();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => console.error('Error sending message:', error));
        }

        // Delete message
        function deleteMessage(messageId) {
            if (confirm('Are you sure you want to delete this message?')) {
                fetch('delete_message.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        'message_id': messageId,
                        'room_id': '<?php echo $roomId; ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadMessages();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => console.error('Error deleting message:', error));
            }
        }

        // Kick member
        function kickMember(memberId) {
            if (confirm('Are you sure you want to kick this member?')) {
                fetch('kick_user.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        'user_id': memberId,
                        'room_id': '<?php echo $roomId; ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Member kicked successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => console.error('Error kicking member:', error));
            }
        }

        // Show invite modal
        function showInviteModal() {
            document.getElementById('invite-modal').style.display = 'flex';
        }

        // Close invite modal
        function closeInviteModal() {
            document.getElementById('invite-modal').style.display = 'none';
            document.getElementById('invite-username').value = '';
            document.getElementById('invite-error').textContent = '';
            document.getElementById('invite-success').textContent = '';
        }

        // Event listeners
        document.getElementById('send-btn').addEventListener('click', sendMessage);
        document.getElementById('message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        <?php if ($needPassword): ?>
        // Password form submission
        document.getElementById('password-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('room-password').value;
            
            fetch('join_room.php', {
                method: 'POST',
                body: new URLSearchParams({
                    'room_id': '<?php echo $roomId; ?>',
                    'password': password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    document.getElementById('password-error').textContent = data.error;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('password-error').textContent = 'An error occurred. Please try again.';
            });
        });
        <?php endif; ?>

        // Invite form submission
        document.getElementById('invite-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('invite-username').value;
            
            fetch('invite_user.php', {
                method: 'POST',
                body: new URLSearchParams({
                    'room_id': '<?php echo $roomId; ?>',
                    'username': username
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('invite-success').textContent = 'Invitation sent successfully';
                    document.getElementById('invite-error').textContent = '';
                    document.getElementById('invite-username').value = '';
                } else {
                    document.getElementById('invite-error').textContent = data.error;
                    document.getElementById('invite-success').textContent = '';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('invite-error').textContent = 'An error occurred. Please try again.';
                document.getElementById('invite-success').textContent = '';
            });
        });

        // Load messages initially and set up auto-refresh
        loadMessages();
        const messageInterval = setInterval(loadMessages, 3000); // Refresh every 3 seconds
    </script>
</body>
</html>