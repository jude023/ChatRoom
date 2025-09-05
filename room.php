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
            background-color: var(--bg-color, #f5f5f5);
            color: var(--text-color, #333333);
            transition: background-color 0.3s, color 0.3s;
            margin: 0;
            padding: 0;
        }
        .container {
            background-color: var(--bg-color, #f5f5f5);
            transition: background-color 0.3s;
            display: flex;
            height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg, #2c3e50);
            color: var(--sidebar-text, #ffffff);
            transition: background-color 0.3s, color 0.3s;
            padding: 20px;
            overflow-y: auto;
        }
        .room-info {
            margin-bottom: 20px;
            padding-bottom: 20px;
            background-color: var(--sidebar-bg, #2c3e50);
            border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
        }
        .room-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--sidebar-text, #ffffff);
        }
        .room-description {
            font-size: 14px;
            color: #bdc3c7;
            margin-bottom: 10px;
        }
        .members-list {
            margin-top: 20px;
            color: var(--sidebar-text, #ffffff);
        }
        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
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
            background-color: var(--button-danger-bg, #f44336);
            color: var(--button-danger-text, #ffffff);
            transition: background-color 0.3s, color 0.3s;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--chat-bg, #ffffff);
            transition: background-color 0.3s;
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
            background-color: var(--button-secondary-bg, #3498db);
            color: var(--button-secondary-text, #ffffff);
            transition: background-color 0.3s, color 0.3s;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .invite-btn {
            padding: 8px 15px;
            background-color: var(--button-primary-bg, #4CAF50);
            color: var(--button-primary-text, #ffffff);
            transition: background-color 0.3s, color 0.3s;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .messages-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: var(--chat-bg, #ffffff);
            transition: background-color 0.3s;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s, color 0.3s;
        }
        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
            padding-right: 30px; /* Add padding to make room for delete button */
            transition: background-color 0.3s, color 0.3s;
        }
        .message-info {
            font-size: 12px;
            margin-bottom: 5px;
            color: var(--secondary-text, #777777);
        }
        .message-actions {
            position: absolute;
            right: 12px;
            bottom: -2px;
            display: none;
        }
        .message:hover .message-actions {
            display: block;
        }
        .delete-msg-btn {
            background-color: #f8f8f8;
            color: #e74c3c;
            border: 1px solid #ddd;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            padding: 2px 5px;
        }
        .delete-msg-btn:hover {
            background-color: #e74c3c;
            color: white;
        }
        .my-message {
            align-items: flex-end;
        }
        .my-message .message-bubble {
            background-color: var(--my-message-bg, #DCF8C6);
            color: var(--text-color, #333333);
        }
        .other-message {
            align-items: flex-start;
        }
        .other-message .message-bubble {
            background-color: var(--other-message-bg, #ffffff);
            color: var(--text-color, #333333);
            border: 1px solid var(--border-color, #dddddd);
        }
        .input-area {
            padding: 15px;
            background-color: var(--chat-bg, #ffffff);
            border-top: 1px solid var(--border-color, #dddddd);
            transition: background-color 0.3s, border-color 0.3s;
            display: flex;
            position: relative;
        }
        .message-input {
            flex: 1;
            padding: 10px;
            background-color: var(--input-bg, #ffffff);
            color: var(--input-text, #333333);
            border: 1px solid var(--input-border, #dddddd);
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
            border-radius: 4px;
            margin-right: 10px;
        }
        .send-btn {
            padding: 10px 15px;
            background-color: var(--button-primary-bg, #4CAF50);
            color: var(--button-primary-text, #ffffff);
            transition: background-color 0.3s, color 0.3s;
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

        .password-form, .invite-form {
            background-color: var(--modal-content-bg, #ffffff);
            color: var(--text-color, #333333);
            border: 1px solid var(--border-color, #dddddd);
        }

        .form-group label {
            color: var(--text-color, #333333);
        }

        .form-group input {
            background-color: var(--input-bg, #ffffff);
            color: var(--input-text, #333333);
            border: 1px solid var(--input-border, #dddddd);
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
        /* Emoji Picker Styles */
        .emoji-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            margin-right: 10px;
            padding: 5px;
        }

        .emoji-btn, .file-btn {
            background-color: transparent;
            color: var(--secondary-text, #777777);
            transition: color 0.3s;
        }

        .emoji-btn:hover, .file-btn:hover {
            color: var(--text-color, #333333);
        }
        .emoji-picker {
            position: absolute;
            bottom: 70px;
            left: 15px;
            background-color: var(--emoji-picker-bg, #ffffff);
            border: 1px solid var(--border-color, #dddddd);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            width: 300px;
            height: 200px;
            overflow-y: auto;
            padding: 10px;
            display: none;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .emoji-picker-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        .emoji-category {
            display: inline-block;
            margin-right: 10px;
            cursor: pointer;
            font-size: 18px;
        }
        .emoji-category.active {
            border-bottom: 2px solid #4CAF50;
        }
        .emoji-container {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 5px;
        }
        .emoji {
            font-size: 20px;
            padding: 5px;
            text-align: center;
            cursor: pointer;
            border-radius: 3px;
        }
        .emoji:hover {
            background-color: #f1f1f1;
        }

        /* Custom emoji styles */
        .custom-emoji {
            width: 30px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2px;
        }

        .custom-emoji-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .custom-emoji-img2 {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* Message emoji styles (for when emojis are displayed in messages) */
        .message-emoji {
            width: 20px;
            height: 20px;
            vertical-align: middle;
            margin: 0 2px;
        }

        /* File Upload Button Styles */
        .file-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            margin-right: 10px;
            padding: 5px;
        }

        /* File in Message Styles */
        .file-attachment {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            background-color: var(--file-attachment-bg, #f8f8f8);
            border: 1px solid var(--file-attachment-border, #dddddd);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .file-attachment a {
            display: flex;
            align-items: center;
            color: var(--file-attachment-link, #3498db);
            text-decoration: none;
        }

        .file-attachment a:hover {
            text-decoration: underline;
        }

        .file-icon {
            font-size: 24px;
            margin-right: 10px;
        }

        .file-name {
            font-size: 14px;
            word-break: break-all;
        }

        .file-size {
            font-size: 12px;
            color: #777;
            margin-left: 10px;
        }

        .read-receipt {
            font-size: 11px;
            color: #777;
            text-align: right;
            margin-top: 2px;
            margin-right: 5px;
        }

        /* Reaction styles */
        .reaction-btn {
            font-size: 14px;
            background-color: var(--reaction-badge-bg, #f1f1f1);
            color: var(--reaction-badge-text, #555555);
            transition: background-color 0.3s, color 0.3s;
            border: none;
            cursor: pointer;
            padding: 2px 5px;
            margin-top: 5px;
        }

        .reaction-btn.reacted {
            background-color: var(--reaction-badge-reacted-bg, #e3f2fd);
        }

        .reaction-btn:hover {
            color: #4CAF50;
        }

        .reaction-picker {
            position: absolute;
            bottom: 100%;
            left: 0;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 5px 10px;
            display: none;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            Y-index: 100;
        }

        .reaction-emoji {
            font-size: 18px;
            cursor: pointer;
            margin: 0 3px;
        }

        .reaction-emoji:hover {
            transform: scale(1.2);
        }

        .reactions-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 5px;
        }

        .reaction-badge {
            background-color: #f1f1f1;
            border-radius: 10px;
            padding: 2px 5px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
        }

        .reaction-emoji-small {
            font-size: 14px;
            margin-right: 210px;
            cursor: pointer;
        }

        .reaction-count {
            color: #555;
        }

        .read-receipt {
            font-size: 11px;
            color: var(--secondary-text);
            text-align: right;
            margin-top: 2px;
            margin-right: 5px;
        }

        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
            padding-right: 30px;
            padding-bottom: 25px; /* Add space for reaction button */
        }

        .theme-switcher {
            position: relative;
            display: inline-block;
            z-index: 1000;
            margin-left: 10px;
        }

        .theme-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 5px;
            color: var(--text-color, #333333);
            transition: transform 0.2s;
        }
        
        .theme-btn:hover {
            transform: scale(1.1);
        }

        .theme-dropdown {
            position: absolute;
            right: 0;
            top: 100%;
            background-color: var(--modal-content-bg, #ffffff);
            border: 1px solid var(--border-color, #dddddd);
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            display: none;
            min-width: 150px;
            z-index: 1000;
            padding: 5px 0;
        }

        .theme-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        .theme-option {
            padding: 8px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            color: var(--text-color, #333333);
            transition: background-color 0.2s;
        }

        .theme-option:hover {
            background-color: var(--hover-bg, #f5f5f5);
        }

        .theme-color {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            margin-right: 10px;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .theme-color.light {
            background-color: #f5f5f5;
        }

        .theme-color.dark {
            background-color: #121212;
        }

        .theme-color.blue {
            background-color: #1e88e5;
        }

        .theme-color.purple {
            background-color: #7b1fa2;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Scrollbars */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background-color: var(--bg-color, #f5f5f5);
        }

        ::-webkit-scrollbar-thumb {
            background-color: var(--secondary-text, #777777);
            border-radius: 4px;
        }

        /* Transitions for smooth theme changes */
        * {
            transition: background-color 0.3s, color 0.3s, border-color 0.3s, box-shadow 0.3s;
        }

        /* Message Threading Styles */
        .message-thread {
            margin-bottom: 15px;
        }

        .message-replies {
            margin-left: 30px;
            margin-top: 5px;
            border-left: 2px solid var(--border-color, #ddd);
            padding-left: 10px;
        }

        .reply-bubble {
            margin-top: 5px;
            max-width: 90%;
        }

        .reply-btn {
            background: transparent;
            border: none;
            color: var(--text-muted, #888);
            cursor: pointer;
            font-size: 0.8em;
            padding: 2px 5px;
            margin-right: 5px;
        }

        .reply-btn:hover {
            text-decoration: underline;
        }

        .reply-indicator {
            font-size: 0.8em;
            color: var(--text-muted, #888);
            margin-bottom: 3px;
        }

        /* Reply Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background-color: var(--bg-color, white);
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            position: relative;
            margin: 10% auto;
        }

        .close-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 20px;
            cursor: pointer;
        }

        .reply-preview {
            background-color: var(--bg-secondary, #f5f5f5);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 0.9em;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
            gap: 10px;
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
                <!-- <button class="back-btn" onclick="window.location.href='dashboard.php'">Back to Dashboard</button> -->
                <div>
                    <button class="back-btn" onclick="window.location.href='dashboard.php'">Back to Dashboard</button>
                </div>
                <div style="display: flex; align-items: center;">
                    <div class="theme-switcher">
                        <!-- <button class="theme-btn" id="theme-btn">🎨</button> -->
                        <div class="theme-dropdown" id="theme-dropdown">
                            <div class="theme-option" data-theme="light">
                                <div class="theme-color light"></div>
                                Light
                            </div>
                            <div class="theme-option" data-theme="dark">
                                <div class="theme-color dark"></div>
                                Dark
                            </div>
                            <div class="theme-option" data-theme="blue">
                                <div class="theme-color blue"></div>
                                Blue
                            </div>
                            <div class="theme-option" data-theme="purple">
                                <div class="theme-color purple"></div>
                                Purple
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                    <button class="invite-btn" onclick="showInviteModal()">Invite User</button>
                <?php endif; ?>
            </div>
            
            <div class="messages-container" id="messages-container">
                <!-- Messages will be loaded here -->
            </div>
            
            <div class="input-area">
                <!-- Emoji Picker Button -->
                <button type="button" class="emoji-btn" id="emoji-btn">😊</button>
                
                <!-- File Upload Button -->
                <button type="button" class="file-btn" id="file-btn">📎</button>
                <input type="file" id="file-input" style="display: none;">

                <!-- Emoji Picker Container -->
                <div class="emoji-picker" id="emoji-picker">
                    <div class="emoji-picker-header">
                        <div class="emoji-category active" data-category="smileys">😊</div>
                        <div class="emoji-category" data-category="animals">🐶</div>
                        <div class="emoji-category" data-category="food">🍔</div>
                        <div class="emoji-category" data-category="activities">⚽</div>
                        <div class="emoji-category" data-category="travel">🚗</div>
                        <div class="emoji-category" data-category="objects">💡</div>
                        <div class="emoji-category" data-category="symbols">❤️</div>
                        <!-- <div class="emoji-category" data-category="pepe">🖼️</div>  -->
                        <!-- Bagong category para sa custom emojis/GIFs -->
                        <div class="emoji-category" data-category="pepe"> <img src="emoji/Pepe_animated/1037_hehepepe.gif" alt="Custom Emoji" class="custom-emoji-img2"></div>
                        <div class="emoji-category" data-category="random"> <img src="emoji/Random_gif/8070-sus.gif" alt="Custom Emoji" class="custom-emoji-img2"></div>
                        
                    </div>
                    <div class="emoji-container" id="emoji-container">
                        <!-- Emojis will be loaded here -->
                    </div>
                </div>
                
                <input type="text" class="message-input" id="message-input" placeholder="Type your message...">
                <button class="send-btn" id="send-btn">Send</button>
            </div>
        </div>
    </div>

    <script>
        // Common reaction emojis
        const commonReactions = ['👍', '❤️', '😂', '😮', '😢', '👏', '🎉', '🔥'];
        // Emoji data by category
        const emojiData = {
            smileys: [
                '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', 
                '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', 
                '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩',
                '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖',
                '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯',
                '🤑', '🤢', '🤮', '🤧', '🤯', '😎', '😈', '💀', '☠️', '💩',
                '🤡', '🖕', '👹', '👽', '👾', '🤖' , '🔥', '🥶', '🧍‍♂️'
            ],
            animals: [
                '🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯',
                '🦁', '🐮', '🐷', '🐸', '🐵', '🙈', '🙉', '🙊', '🐒', '🐔',
                '🐧', '🐦', '🐤', '🐣', '🐥', '🦆', '🦅', '🦉', '🦇', '🐺',
                '🐗', '🐴', '🦄', '🐝', '🐛', '🦋', '🐌', '🐞', '🐜', '🦟'
            ],
            food: [
                '🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍈',
                '🍒', '🍑', '🥭', '🍍', '🥥', '🥝', '🍅', '🍆', '🥑', '🥦',
                '🥬', '🥒', '🌶️', '🌽', '🥕', '🧄', '🧅', '🥔', '🍠', '🥐',
                '🥯', '🍞', '🥖', '🥨', '🧀', '🥚', '🍳', '🧈', '🥞', '🧇'
            ],
            activities: [
                '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱',
                '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🥅', '⛳', '🪁',
                '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛷', '⛸️', '🥌'
            ],
            travel: [
                '🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐',
                '🚚', '🚛', '🚜', '🦯', '🦽', '🦼', '🛴', '🚲', '🛵', '🏍️',
                '🚨', '🚔', '🚍', '🚘', '🚖', '🚡', '🚠', '🚟', '🚃', '🚋'
            ],
            objects: [
                '⌚', '📱', '📲', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '🖲️', '🕹️',
                '🗜️', '💽', '💾', '💿', '📀', '📼', '📷', '📸', '📹', '🎥',
                '📽️', '🎞️', '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️'
            ],
            symbols: [
                '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔',
                '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️',
                '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐'
            ],
            pepe: [],
            random: [],
        };
        const customEmojiPaths = [
            'emoji/Pepe_animated/1037_hehepepe.gif',
            'emoji/Pepe_animated/1207_peepoSignedSimp.gif',
            'emoji/Pepe_animated/1223_PepeSmoke.gif',
            'emoji/Pepe_animated/1502_pepelaugh.gif',
            'emoji/Pepe_animated/1729_peperun.gif',
            'emoji/Pepe_animated/2359_cringepepepet.gif',
            'emoji/Pepe_animated/3190_pepekiss.gif',
            'emoji/Pepe_animated/3608-fuck-off.gif',
            'emoji/Pepe_animated/4642_peepoclap.gif',
            'emoji/Pepe_animated/4697_pepecreepylurk.gif',
            'emoji/Pepe_animated/4879-pepe-ateveryoneping.gif',
            'emoji/Pepe_animated/5408_pepe_kissing_pepe.gif',
            'emoji/Pepe_animated/5628-pepe-sad.gif',    
            'emoji/Pepe_animated/6582_pepeplant.gif',
            'emoji/Pepe_animated/6619_PepegaCredit.gif',
            'emoji/Pepe_animated/9435_PepeClap.gif',
            'emoji/Pepe_animated/9469_Pepe_CowboyShoot.gif',
            'emoji/Pepe_animated/PepePls.gif',
            'emoji/Pepe_animated/PepePls.gif',
            'emoji/Pepe_animated/PepePopcorn.gif',
            'emoji/Pepe_animated/PepeRain.gif',
            
            'emoji/Pepe_animated/6906-pepe-cry.gif',
            'emoji/Pepe_animated/22489-pepepaper.gif',
            'emoji/Pepe_animated/24561-pepe-vanish.gif',
            'emoji/Pepe_animated/24714-pepe-bellyache.gif',
            'emoji/Pepe_animated/26866-pepeclapxmas.gif',
            'emoji/Pepe_animated/26866-pepemcbed.gif',
            'emoji/Pepe_animated/39175-pepefcku.gif',
            'emoji/Pepe_animated/52925-pepedaddy.gif',
            'emoji/Pepe_animated/61444-pepe-hyperspeed.gif',
            'emoji/Pepe_animated/67908-pepejam.gif',
            'emoji/Pepe_animated/80202-pepe-run.gif',
            'emoji/Pepe_animated/86464-apes-musicblur.gif',
            'emoji/Pepe_animated/93659-pepemoneyrain.gif',
            'emoji/Pepe_animated/95735-pepe-toxic.gif',
            
            // Add more paths as needed
        ];

        const randomEmojiPaths = [
            'emoji/Random_gif/8070-sus.gif',
            'emoji/Random_gif/33393-panic.gif',
            'emoji/Random_gif/37786-yaaa.gif',
            'emoji/Random_gif/54853-shiaclapping.gif',
            'emoji/Random_gif/79526-gojo.gif',
            'emoji/Random_gif/90623-heartattack.gif',
            'emoji/Random_gif/92482-gigachatting.gif',

        ]

        function loadCustomEmojis() {
            // Clear any existing custom emojis
            emojiData.pepe = [];    
            emojiData.random = []; 
            // Add custom emoji paths to the custom category
            customEmojiPaths.forEach(path => {
                // console.log(path)
                emojiData.pepe.push(path);
                // console.log(emojiData)
            });

            randomEmojiPaths.forEach(path2 => {
                // console.log(path)
                emojiData.random.push(path2);
                // console.log(emojiData)
            });
        }
        // Global variables
        let currentParentId = null;

        // Function to add reply buttons to messages
        function addReplyButtons() {
            // Add reply button to each message
            document.querySelectorAll('.message-bubble').forEach(messageBubble => {
                // Check if message already has a reply button
                if (messageBubble.querySelector('.reply-btn')) {
                    return;
                }
                
                // Get message ID from data attribute or parent element
                const messageId = messageBubble.dataset.messageId || 
                                messageBubble.closest('.message').dataset.messageId;
                
                if (!messageId) {
                    return;
                }
                
                // Create reply button
                const replyBtn = document.createElement('button');
                replyBtn.className = 'reply-btn';
                replyBtn.textContent = 'Reply';
                replyBtn.dataset.messageId = messageId;
                
                // Add click event listener
                replyBtn.addEventListener('click', function() {
                    openReplyModal(this.dataset.messageId);
                });
                
                // Find message actions container or create one
                let messageActions = messageBubble.querySelector('.message-actions');
                if (!messageActions) {
                    messageActions = document.createElement('div');
                    messageActions.className = 'message-actions';
                    messageBubble.appendChild(messageActions);
                }
                
                // Add reply button to message actions
                messageActions.appendChild(replyBtn);
            });
        }

        // Function to open reply modal
        function openReplyModal(messageId) {
            // Find the message element
            const messageBubble = document.querySelector(`.message-bubble[data-message-id="${messageId}"]`) || 
                                document.querySelector(`.message[data-message-id="${messageId}"] .message-bubble`);
            
            if (!messageBubble) {
                console.error('Message not found:', messageId);
                return;
            }
            
            // Get message content
            const username = messageBubble.querySelector('.message-username').textContent;
            const messageContent = messageBubble.querySelector('.message-content').textContent;
            
            // Set parent message ID
            currentParentId = messageId;
            
            // Show message preview
            const replyPreview = document.getElementById('replyPreview');
            replyPreview.innerHTML = `<strong>${username}:</strong> ${messageContent}`;
            
            // Show modal
            const replyModal = document.getElementById('replyModal');
            replyModal.style.display = 'block';
            
            // Focus on reply text input
            document.getElementById('replyText').focus();
        }

        // Function to send reply
        // function sendReply() {
        //     const replyText = document.getElementById('replyText').value.trim();
            
        //     if (!replyText || !currentParentId) {
        //         return;
        //     }
            
        //     // Get room ID
        //     const roomId = document.querySelector('input[name="room_id"]').value;
            
        //     // Create form data
        //     const formData = new FormData();
        //     formData.append('room_id', roomId);
        //     formData.append('message', replyText);
        //     formData.append('parent_id', currentParentId);
            
        //     // Send AJAX request
        //     fetch('send_message.php', {
        //         method: 'POST',
        //         body: formData
        //     })
        //     .then(response => response.json())
        //     .then(data => {
        //         if (data.success) {
        //             // Close modal and clear input
        //             closeReplyModal();
                    
        //             // Refresh messages
        //             loadMessages();
        //         } else {
        //             alert('Failed to send reply: ' + (data.error || 'Unknown error'));
        //         }
        //     })
        //     .catch(error => {
        //         console.error('Error:', error);
        //         alert('An error occurred while sending your reply.');
        //     });
        // }

        // Function to close reply modal
        function closeReplyModal() {
            document.getElementById('replyModal').style.display = 'none';
            document.getElementById('replyText').value = '';
            currentParentId = null;
        }

        // Function to display threaded messages
        function displayThreadedMessages(messages) {
            const chatContainer = document.querySelector('.chat-messages') || 
                                document.querySelector('.chat-area');
            
            if (!chatContainer) {
                console.error('Chat container not found');
                return;
            }
            
            // Clear existing messages
            chatContainer.innerHTML = '';
            
            // Display messages
            messages.forEach(message => {
                // Create message thread container
                const messageThread = document.createElement('div');
                messageThread.className = 'message-thread';
                messageThread.id = `thread-${message.id}`;
                
                // Create main message element
                const messageElement = createMessageElement(message);
                messageThread.appendChild(messageElement);
                
                // Add replies if any
                if (message.reply_messages && message.reply_messages.length > 0) {
                    const repliesContainer = document.createElement('div');
                    repliesContainer.className = 'message-replies';
                    
                    message.reply_messages.forEach(reply => {
                        const replyElement = createMessageElement(reply, true);
                        repliesContainer.appendChild(replyElement);
                    });
                    
                    messageThread.appendChild(repliesContainer);
                }
                
                // Add message thread to chat container
                chatContainer.appendChild(messageThread);
            });
            
            // Add reply buttons to messages
            addReplyButtons();
            
            // Scroll to bottom
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // Function to create message element
        function createMessageElement(message, isReply = false) {
            const userId = document.querySelector('input[name="user_id"]').value;
            
            // Create message container
            const messageElement = document.createElement('div');
            messageElement.className = isReply ? 'reply-bubble' : 'message';
            messageElement.dataset.messageId = message.id;
            
            // Create message bubble
            const messageBubble = document.createElement('div');
            messageBubble.className = `message-bubble ${message.user_id === userId ? 'my-message' : 'other-message'}`;
            messageBubble.dataset.messageId = message.id;
            
            // Create message header
            const messageHeader = document.createElement('div');
            messageHeader.className = 'message-header';
            
            const usernameSpan = document.createElement('span');
            usernameSpan.className = 'message-username';
            usernameSpan.textContent = message.username;
            
            const timeSpan = document.createElement('span');
            timeSpan.className = 'message-time';
            timeSpan.textContent = message.time;
            
            messageHeader.appendChild(usernameSpan);
            messageHeader.appendChild(timeSpan);
            
            // Create message content
            const messageContent = document.createElement('div');
            messageContent.className = 'message-content';
            
            // Add reply indicator if this is a reply
            if (isReply && message.parent_id) {
                const replyIndicator = document.createElement('div');
                replyIndicator.className = 'reply-indicator';
                replyIndicator.textContent = 'Reply';
                messageContent.appendChild(replyIndicator);
            }
            
            // Add message text
            const messageText = document.createElement('div');
            messageText.textContent = message.text;
            messageContent.appendChild(messageText);
            
            // Add file if present
            if (message.has_file && message.file) {
                const fileLink = document.createElement('a');
                fileLink.href = message.file.path;
                fileLink.className = 'file-attachment';
                fileLink.textContent = `📎 ${message.file.name}`;
                fileLink.target = '_blank';
                messageContent.appendChild(document.createElement('br'));
                messageContent.appendChild(fileLink);
            }
            
            // Create message actions container
            const messageActions = document.createElement('div');
            messageActions.className = 'message-actions';
            
            // Assemble message bubble
            messageBubble.appendChild(messageHeader);
            messageBubble.appendChild(messageContent);
            messageBubble.appendChild(messageActions);
            
            // Add message bubble to message element
            messageElement.appendChild(messageBubble);
            
            return messageElement;
        }

        // Theme Switcher
        document.addEventListener('DOMContentLoaded', function() {
            // Get saved theme from localStorage or use default
            const savedTheme = localStorage.getItem('chatTheme') || 'light';
            console.log(savedTheme)
            setTheme(savedTheme);
            
          // Theme button click event
            // document.getElementById('theme-btn').addEventListener('click', function() {
            //     const dropdown = document.getElementById('theme-dropdown');
            //     dropdown.classList.toggle('show');
            // });
            
            // Sa theme option click
            document.querySelectorAll('.theme-option').forEach(option => {
                option.addEventListener('click', function() {
                    const theme = this.getAttribute('data-theme');
                    setTheme(theme);
                    document.getElementById('theme-dropdown').classList.remove('show');
                });
            });
            
            // Close theme dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const dropdown = document.getElementById('theme-dropdown');
                const themeBtn = document.getElementById('theme-btn');
                
                if (dropdown.style.display === 'block' && 
                    !dropdown.contains(event.target) && 
                    event.target !== themeBtn) {
                    dropdown.style.display = 'none';
                }
            });
            loadCustomEmojis();
            // Set up event listeners
                document.getElementById('emoji-btn').addEventListener('click', toggleEmojiPicker);
                
                // Set up category click handlers
                document.querySelectorAll('.emoji-category').forEach(category => {
                    category.addEventListener('click', function() {
                        loadEmojis(this.getAttribute('data-category'));
                    });
                });
                
        });

        // Set theme function
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('chatTheme', theme);
        }

        // File upload functionality
        document.getElementById('file-btn').addEventListener('click', function() {
            document.getElementById('file-input').click();
        });

        document.getElementById('file-input').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                uploadFile(file);
            }
        });

        // Toggle reaction picker
        // function toggleReactionPicker(messageId) {
        //     const picker = document.getElementById(`reaction-picker-${messageId}`);
        //     if (picker.style.display === 'flex') {
        //         picker.style.display = 'none';
        //     } else {
        //         // Hide all other reaction pickers first
        //         document.querySelectorAll('.reaction-picker').forEach(el => {
        //             el.style.display = 'none';
        //         });
        //         picker.style.display = 'flex';
        //     }
        // }

        // Modified toggleReactionPicker function
        function toggleReactionPicker(messageId) {
            pausePolling(); // Pause polling while user interacts with reactions
            const picker = document.getElementById(`reaction-picker-${messageId}`);
            if (picker.style.display === 'flex') {
                picker.style.display = 'none';
            } else {
                // Hide all other pickers first
                document.querySelectorAll('.reaction-picker').forEach(el => {
                    el.style.display = 'none';
                });
                picker.style.display = 'flex';
            }
        }

        // Modified reactToMessage function
        // function reactToMessage(messageId, emoji) {
        //     pausePolling(); // Pause polling while sending reaction
        //     console.log(encodeURIComponent(emoji))
        //     // Hide the reaction picker
        //     const picker = document.getElementById(`reaction-picker-${messageId}`);
        //     if (picker) {
        //         picker.style.display = 'none';
        //     }

        //     // Send the reaction to the server
        //     fetch('react_to_message.php', {
        //         method: 'POST',
        //         headers: {
        //             'Content-Type': 'application/x-www-form-urlencoded',
        //         },
        //         body: `message_id=${messageId}&emoji=${encodeURIComponent(emoji)}`
        //     })
        //     .then(response => response.json())
        //     .then(data => {
        //         console.log(data)
        //         if (data.success) {
        //             // Manually update the UI to show the new reaction
        //             loadMessages();
        //         } else {
        //             console.error('Error adding reaction:', data.message);
        //         }
        //     })
        //     .catch(error => {
        //         console.error('Error adding reaction:', error);
        //     });
        // }

        function reactToMessage(messageId, reaction) {
            pausePolling(); // Pause polling while sending reaction
            
            console.log('Sending reaction:', messageId, reaction, encodeURIComponent(reaction));
            
            // Hide the reaction picker
            const picker = document.getElementById(`reaction-picker-${messageId}`);
            if (picker) {
                picker.style.display = 'none';
            }
            
            // Send the reaction to the server
            fetch('react_to_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `message_id=${messageId}&reaction=${encodeURIComponent(reaction)}`
            })
            .then(response => {
                // Check if the response is ok before trying to parse JSON
                if (!response.ok) {
                    throw new Error(`Server responded with status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Server response:', data);
                if (data && data.success) {
                    // Manually update the UI to show the new reaction
                    loadMessages();
                } else {
                    console.error('Error adding reaction:', data ? data.message : 'No data returned');
                }
            })
            .catch(error => {
                console.error('Error adding reaction:', error);
            })
            .finally(() => {
                // Resume polling regardless of success or failure
                resumePolling();
            });
        }

        function resumePolling() {
            // Implement the logic to resume your polling mechanism
            // This might involve restarting a setInterval or similar
            if (window.pollingInterval === null && window.shouldPoll) {
                window.pollingInterval = setInterval(loadMessages, 3000); // Adjust timing as needed
            }
        }

        // Upload file
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('room_id', '<?php echo $roomId; ?>');
            
            // Show loading indicator
            const messageInput = document.getElementById('message-input');
            const originalPlaceholder = messageInput.placeholder;
            messageInput.placeholder = 'Uploading file...';
            
            fetch('upload_file.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                messageInput.placeholder = originalPlaceholder;
                
                if (data.success) {
                    // Send message with file data
                    sendMessageWithFile(data.file);
                } else {
                    alert('Error uploading file: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error uploading file:', error);
                messageInput.placeholder = originalPlaceholder;
                alert('An error occurred while uploading the file. Please try again.');
            });
        }

        // Send message with file
        function sendMessageWithFile(fileData) {
            const messageInput = document.getElementById('message-input');
            const messageText = messageInput.value.trim();
            
            const formData = new FormData();
            formData.append('room_id', '<?php echo $roomId; ?>');
            formData.append('message', messageText);
            formData.append('file_data', JSON.stringify(fileData));
            
            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    document.getElementById('file-input').value = '';
                    loadMessages();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => console.error('Error sending message with file:', error));
        }
        // Get appropriate icon for file type
        function getFileIcon(fileType) {
            if (fileType.includes('image')) {
                return '🖼️';
            } else if (fileType.includes('pdf')) {
                return '📄';
            } else if (fileType.includes('word') || fileType.includes('document')) {
                return '📝';
            } else if (fileType.includes('excel') || fileType.includes('spreadsheet')) {
                return '📊';
            } else if (fileType.includes('zip') || fileType.includes('rar') || fileType.includes('compressed')) {
                return '🗜️';
            } else if (fileType.includes('audio')) {
                return '🎵';
            } else if (fileType.includes('video')) {
                return '🎬';
            } else {
                return '📁';
            }
        }

        // Format file size to human-readable format
        function formatFileSize(bytes) {
            if (bytes < 1024) {
                return bytes + ' B';
            } else if (bytes < 1024 * 1024) {
                return (bytes / 1024).toFixed(1) + ' KB';
            } else if (bytes < 1024 * 1024 * 1024) {
                return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
            } else {
                return (bytes / (1024 * 1024 * 1024)).toFixed(1) + ' GB';
            }
        }

        // Mark messages as read
        function markMessagesAsRead(messageIds) {
            if (messageIds.length === 0) return;
            
            fetch('mark_as_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'room_id': '<?php echo $roomId; ?>',
                    'message_ids': JSON.stringify(messageIds)
                })
            })
            .then(response => response.json())
            .catch(error => console.error('Error marking messages as read:', error));
        }

      // Update loadMessages function to include reactions
        // function loadMessages() {
        //     fetch('get_messages.php?room_id=<?php echo $roomId; ?>')
        //         .then(response => response.json())
        //         .then(data => {
        //             if (data.success) {
        //                 const messagesContainer = document.getElementById('messages-container');
        //                 let messagesHTML = '';
        //                 const unreadMessageIds = [];
                        
        //                 data.messages.forEach(message => {
        //                     const isMyMessage = message.user_id === '<?php echo $userId; ?>';
        //                     const messageClass = isMyMessage ? 'message my-message' : 'message other-message';
        //                     const canDelete = isMyMessage || <?php echo $isAdmin ? 'true' : 'false'; ?>;
                            
        //                     // Check if message is read
        //                     const readBy = message.read_by || [];
        //                     const isRead = readBy.includes('<?php echo $userId; ?>');
                            
        //                     // If message is not from current user and not read yet, add to unread list
        //                     if (!isMyMessage && !isRead) {
        //                         unreadMessageIds.push(message.id);
        //                     }
                            
        //                     // Generate read receipt HTML
        //                     let readReceiptHTML = '';
        //                     if (isMyMessage && readBy.length > 1) { // More than 1 because sender is always in read_by
        //                         const readCount = readBy.length - 1; // Exclude sender
        //                         readReceiptHTML = `<div class="read-receipt">Read by ${readCount}</div>`;
        //                     }
                            
        //                     // File attachment HTML
        //                     let fileHTML = '';
        //                     if (message.has_file && message.file) {
        //                         const fileIcon = getFileIcon(message.file.type);
        //                         const fileSize = formatFileSize(message.file.size);
                                
        //                         fileHTML = `
        //                             <div class="file-attachment">
        //                                 <a href="${message.file.path}" target="_blank" download="${message.file.name}">
        //                                     <span class="file-icon">${fileIcon}</span>
        //                                     <span class="file-name">${message.file.name}</span>
        //                                     <span class="file-size">${fileSize}</span>
        //                                 </a>
        //                             </div>
        //                         `;
        //                     }
                            
        //                     // Generate reactions HTML
        //                     let reactionsHTML = '';
        //                     if (message.reactions && Object.keys(message.reactions).length > 0) {
        //                         reactionsHTML = '<div class="reactions-container">';
        //                         for (const [emoji, users] of Object.entries(message.reactions)) {
        //                             const count = users.length;
        //                             const hasReacted = users.includes('<?php echo $userId; ?>');
        //                             const badgeClass = hasReacted ? 'reaction-badge reacted' : 'reaction-badge';
        //                             reactionsHTML += `
        //                                 <div class="${badgeClass}" onclick="reactToMessage('${message.id}', '${emoji}')">
        //                                     <span class="reaction-emoji-small">${emoji}</span>
        //                                     <span class="reaction-count">${count}</span>
        //                                 </div>
        //                             `;
        //                         }
        //                         reactionsHTML += '</div>';
        //                     }
                            
        //                     // Generate reaction picker HTML
        //                     const reactionPickerHTML = `
        //                         <div class="reaction-picker" id="reaction-picker-${message.id}">
        //                             ${commonReactions.map(emoji => 
        //                                 `<span class="reaction-emoji" onclick="reactToMessage('${message.id}', '${emoji}')">${emoji}</span>`
        //                             ).join('')}
        //                         </div>
        //                     `;
                            
        //                     messagesHTML += `
        //                         <div class="${messageClass}" data-id="${message.id}">
        //                             <div class="message-info">${message.username} - ${message.time}</div>
        //                             <div class="message-bubble">
        //                                 ${message.text}
        //                                 ${fileHTML}
        //                                 ${reactionsHTML}
        //                                 <button class="reaction-btn" onclick="toggleReactionPicker('${message.id}')">😊</button>
        //                                 ${reactionPickerHTML}
        //                                 ${canDelete ? 
        //                                     `<div class="message-actions">
        //                                         <button class="delete-msg-btn" onclick="deleteMessage('${message.id}')">Delete</button>
                                                
        //                                     </div>` : ''
        //                                 }
        //                             </div>
        //                             ${readReceiptHTML}
        //                         </div>
        //                     `;
        //                 });
                        
        //                 messagesCisUserInteractingontainer.innerHTML = messagesHTML;
        //                 messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        
        //                 // Mark unread messages as read
        //                 if (unreadMessageIds.length > 0) {
        //                     markMessagesAsRead(unreadMessageIds);
        //                 }
        //             }
        //         })
        //         .catch(error => console.error('Error loading messages:', error));
        // }

        // Variables to control polling
let pollingInterval = 5000; // Start with 5 seconds
const minPollingInterval = 1000; // Minimum 1 second
const maxPollingInterval = 10000; // Maximum 10 seconds
let lastActivityTime = Date.now();
let lastMessageCount = 0;
let messageTimer;
let isUserInteracting = false; // Flag to track user interaction

        // Function to temporarily pause polling during user interactions
        function pausePolling() {
            isUserInteracting = true;
            clearTimeout(messageTimer);

            // Resume polling after a short delay
            setTimeout(() => {
                isUserInteracting = false;
                messageTimer = setTimeout(loadMessages, pollingInterval);
            }, 3000); // 2-second pause
        }

// Enhanced loadMessages function
            function loadMessages() {
                 // Don't load messages if user is interacting with reactions
            if (isUserInteracting) return;

            // Store current scroll position
            const messagesContainer = document.getElementById('messages-container');
            const scrollPosition = messagesContainer.scrollTop;
            const wasAtBottom = messagesContainer.scrollHeight - messagesContainer.clientHeight <= messagesContainer.scrollTop + 100;
                fetch('get_messages.php?room_id=<?php echo $roomId; ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const messagesContainer = document.getElementById('messages-container');
                            let messagesHTML = '';
                            const unreadMessageIds = [];
                            
                            // Your existing message processing code...
                            data.messages.forEach(message => {
                                const isMyMessage = message.user_id === '<?php echo $userId; ?>';
                                const messageClass = isMyMessage ? 'message my-message' : 'message other-message';
                                const canDelete = isMyMessage || <?php echo $isAdmin ? 'true' : 'false'; ?>;
                                
                                // Check if message is read
                                const readBy = message.read_by || [];
                                const isRead = readBy.includes('<?php echo $userId; ?>');
                                
                                // If message is not from current user and not read yet, add to unread list
                                if (!isMyMessage && !isRead) {
                                    unreadMessageIds.push(message.id);
                                }
                                
                                // // Generate read receipt HTML
                                // let readReceiptHTML = '';
                                // if (isMyMessage && readBy.length > 1) { // More than 1 because sender is always in read_by
                                //     const readCount = readBy.length - 1; // Exclude sender
                                //     readReceiptHTML = `<div class="read-receipt">Read by ${readCount}</div>`;
                                // }
                                // Generate read receipt HTML
                                let readReceiptHTML = '';
                                // console.log(isMyMessage,readBy.length)
                                if (isMyMessage && readBy.length > 1) { // More than 1 because sender is always in read_by
                                    
                                    // Option 1: If you have read_by_usernames in your message data
                                    if (message.read_by_usernames) {
                                        const readUsernames = readBy
                                            .filter(readerId => readerId !== message.user_id)
                                            .map(readerId => message.read_by_usernames[readerId])
                                            .filter(username => username) // Remove any undefined values
                                            .join(', ');
                                            console.log(readUsernames)
                                        if (readUsernames) {
                                            readReceiptHTML = `<div class="read-receipt">Read by: ${readUsernames}</div>`;
                                        }
                                    }
                                    // Option 2: If you have a global mapping
                                    else if (typeof userIdToUsername !== 'undefined') {
                                        const readUsernames = readBy
                                            .filter(readerId => readerId !== message.user_id)
                                            .map(readerId => userIdToUsername[readerId] || readerId)
                                            .join(', ');
                                            
                                        if (readUsernames) {
                                            readReceiptHTML = `<div class="read-receipt">Read by: ${readUsernames}</div>`;
                                        }
                                    }
                                    // Fallback to just showing the count
                                    else {
                                        const readCount = readBy.length - 1; // Exclude sender
                                        readReceiptHTML = `<div class="read-receipt">Read by ${readCount}</div>`;
                                    }
                                }

                                const emojiRegex = /\[EMOJI:(.*?)\]/;
                                const text = message.text;
                                const match = text.match(emojiRegex);
                                // console.log(match);
                                let emojisHTML = ''
                                if (match) {
                                    // console.log("Found emoji:", match[1]);
                                    emojisHTML += `<div class="emoji custom-emoji">
                                    <img src="${match[1]}" alt="Custom Emoji" class="custom-emoji-img">
                                </div>`;
                                }
                                // File attachment HTML
                                let fileHTML = '';
                                if (message.has_file && message.file) {
                                    const fileIcon = getFileIcon(message.file.type);
                                    const fileSize = formatFileSize(message.file.size);
                                    // console.log('message',message)
                                    fileHTML = `
                                        <div class="file-attachment">
                                            <a href="${message.file.path}" target="_blank" download="${message.file.name}">
                                                <span class="file-icon">${fileIcon}</span>
                                                <span class="file-name">${message.file.name}</span>
                                                <span class="file-size">${fileSize}</span>
                                            </a>
                                        </div>
                                    `;
                                }
                                
                                // Generate reactions HTML
                                let reactionsHTML = '';
                                if (message.reactions && Object.keys(message.reactions).length > 0) {
                                    reactionsHTML = '<div class="reactions-container">';
                                    for (const [emoji, users] of Object.entries(message.reactions)) {
                                        const count = users.length;
                                        const hasReacted = users.includes('<?php echo $userId; ?>');
                                        const badgeClass = hasReacted ? 'reaction-badge reacted' : 'reaction-badge';
                                        reactionsHTML += `
                                            <div class="${badgeClass}" onclick="reactToMessage('${message.id}', '${emoji}')">
                                                <span class="reaction-emoji-small">${emoji}</span>
                                                
                                            </div>
                                        `;
                                    }
                                    reactionsHTML += '</div>';
                                }
                                
                                // Generate reaction picker HTML <span class="reaction-count">${count}</span>
                                const reactionPickerHTML = `
                                    <div class="reaction-picker" id="reaction-picker-${message.id}">
                                        ${commonReactions.map(emoji => 
                                            `<span class="reaction-emoji" onclick="reactToMessage('${message.id}', '${emoji}')">${emoji}</span>`).join('')}</div>`;
                                
                                messagesHTML += `
                                    <div class="${messageClass}" data-id="${message.id}">
                                        <div class="message-info">${message.username} - ${message.time}</div>
                                        <div class="message-bubble">
                                        ${(containsOnlyEmojis(message.text)) ?  emojisHTML : message.text}
                                            
                                            ${fileHTML}
                                            ${readReceiptHTML}
                                            ${reactionPickerHTML}
                                            ${reactionsHTML}
                                            ${canDelete ? 
                                                `<div class="message-actions">
                                                    <button class="delete-msg-btn" onclick="deleteMessage('${message.id}')">Delete</button>
                                                </div>` : ''
                                            }
                                        </div>
                                        
                                        
                                            <button class="reaction-btn" onclick="toggleReactionPicker('${message.id}')">😊</button>
                                    </div>
                                `;
                            });
                            
                            // Check if there are new messages
                            const currentMessageCount = data.messages.length;
                            const hasNewMessages = currentMessageCount > lastMessageCount;

                            // Update the UI
                            messagesContainer.innerHTML = messagesHTML;
                             // Scroll handling
                            if (hasNewMessages && wasAtBottom) {
                                // If there are new messages and user was at bottom, scroll to bottom
                                scrollToBottom();
                            } else {
                                // Otherwise restore previous scroll position
                                messagesContainer.scrollTop = scrollPosition;
                            }
                            

                            // Adaptive polling interval
                            if (hasNewMessages) {
                                // New messages detected - decrease polling interval
                                pollingInterval = Math.max(minPollingInterval, pollingInterval / 2);
                                lastActivityTime = Date.now();
                            } else {
                                // No new messages - gradually increase polling interval if inactive
                                const timeSinceActivity = Date.now() - lastActivityTime;
                                if (timeSinceActivity > 30000) { // 30 seconds of inactivity
                                    pollingInterval = Math.min(maxPollingInterval, pollingInterval * 1.5);
                                }
                            }
                            // if (currentMessageCount > lastMessageCount) {
                            //     // New messages detected - decrease polling interval
                            //     pollingInterval = Math.max(minPollingInterval, pollingInterval / 2);
                            //     lastActivityTime = Date.now();
                            // } else {
                            //     // No new messages - gradually increase polling interval if inactive
                            //     const timeSinceActivity = Date.now() - lastActivityTime;
                            //     if (timeSinceActivity > 30000) { // 30 seconds of inactivity
                            //         pollingInterval = Math.min(maxPollingInterval, pollingInterval * 1.5);
                            //         console.log('pollingInterval',pollingInterval)
                            //     }
                            // }
                            
                            lastMessageCount = currentMessageCount;
                            
                            // Update the UI
                            // messagesContainer.innerHTML = messagesHTML;
                            // Mark unread messages as read
                            
                            // Only scroll to bottom if user is already near bottom
                            const isNearBottom = messagesContainer.scrollHeight - messagesContainer.clientHeight <= messagesContainer.scrollTop + 100;
                            if (isNearBottom) {
                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                            }
                            
                            // Mark unread messages as read
                            if (unreadMessageIds.length > 0) {
                                markMessagesAsRead(unreadMessageIds);
                            }
                            
                            // Schedule next poll with adaptive interval
                            clearTimeout(messageTimer);
                            messageTimer = setTimeout(loadMessages, pollingInterval);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading messages:', error);
                        // On error, try again after a delay
                        clearTimeout(messageTimer);
                        messageTimer = setTimeout(loadMessages, 5000);
                    });
            }

            // Function to scroll to bottom of chat
            function scrollToBottom() {
                const messagesContainer = document.getElementById('messages-container');
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }

            // Function to check if text is only an emoji reference
            // function isEmojiReference(text) {
            //     // Check if the text matches the emoji reference pattern
            //     return /^$$EMOJI:.*$$$/.test(text.trim());
            // }

            // Function to check if text contains only standard emojis
            function containsOnlyEmojis(text) {
                const emojiRegex =  /\[EMOJI:(.*?)\]/;
                return emojiRegex.test(text);
            }

            // Function to handle user activity
            function userActivity() {
                lastActivityTime = Date.now();
                // When user is active, poll more frequently
                pollingInterval = minPollingInterval;
                
                // Clear existing timer and start a new one
                clearTimeout(messageTimer);
                messageTimer = setTimeout(loadMessages, pollingInterval);
            }

            // Function to send a message
            function sendMessage(text, fileData = null) {
                // Your existing send message code...
                
                // After sending, immediately poll for new messages
                userActivity();
                
                // Also immediately poll after sending a message
                clearTimeout(messageTimer);
                loadMessages();
            }

            // Start polling when page loads
            document.addEventListener('DOMContentLoaded', function() {
                // Initial load
                loadMessages();
                
                // Set up activity listeners
                document.getElementById('message-input').addEventListener('keydown', userActivity);
                document.getElementById('messages-container').addEventListener('scroll', userActivity);
                document.addEventListener('click', userActivity);
            });

        // Function to load messages with threading
        // function loadMessages() {
        //     const roomId = document.querySelector('input[name="room_id"]').value;
            
        //     fetch(`get_messages.php?room_id=${roomId}`)
        //         .then(response => response.json())
        //         .then(data => {
        //             if (data.success) {
        //                 displayThreadedMessages(data.messages);
        //             } else {
        //                 console.error('Error loading messages:', data.error);
        //             }
        //         })
        //         .catch(error => {
        //             console.error('Error:', error);
        //         });
        // }

        // Close reaction pickers when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.reaction-btn') && !event.target.closest('.reaction-picker')) {
                document.querySelectorAll('.reaction-picker').forEach(el => {
                    el.style.display = 'none';
                });
            }
        });

        // Load messages
        // function loadMessages() {
        //     fetch('get_messages.php?room_id=<?php echo $roomId; ?>')
        //         .then(response => response.json())
        //         .then(data => {
        //             if (data.success) {
        //                 const messagesContainer = document.getElementById('messages-container');
        //                 let messagesHTML = '';
                        
        //                 data.messages.forEach(message => {
        //                     const isMyMessage = message.user_id === '<?php echo $userId; ?>';
        //                     const messageClass = isMyMessage ? 'message my-message' : 'message other-message';
        //                     const canDelete = isMyMessage || <?php echo $isAdmin ? 'true' : 'false'; ?>;
                            
        //                     messagesHTML += `
        //                         <div class="${messageClass}" data-id="${message.id}">
        //                             <div class="message-info">${message.username} - ${message.time}</div>
        //                             <div class="message-bubble">
        //                                 ${message.text}
        //                                 ${canDelete ? 
        //                                     `<div class="message-actions">
        //                                         <button class="delete-msg-btn" onclick="deleteMessage('${message.id}')">Delete</button>
        //                                     </div>` : ''
        //                                 }
        //                             </div>
        //                         </div>
        //                     `;
        //                 });
                        
        //                 messagesContainer.innerHTML = messagesHTML;
        //                 messagesContainer.scrollTop = messagesContainer.scrollHeight;
        //             }
        //         })
        //         .catch(error => console.error('Error loading messages:', error));
        // }

        // Send message
        // function sendMessage() {
        //     const messageInput = document.getElementById('message-input');
        //     const messageText = messageInput.value.trim();
            
        //     if (!messageText) {
        //         return;
        //     }
            
        //     const formData = new FormData();
        //     formData.append('room_id', '<?php echo $roomId; ?>');
        //     formData.append('message', messageText);
            
        //     fetch('send_message.php', {
        //         method: 'POST',
        //         body: formData
        //     })
        //     .then(response => response.json())
        //     .then(data => {
        //         if (data.success) {
        //             messageInput.value = '';
        //             loadMessages();
        //         } else {
        //             alert('Error: ' + data.error);
        //         }
        //     })
        //     .catch(error => console.error('Error sending message:', error));
        // }

        // function sendMessage() {
        //     const messageInput = document.getElementById('message-input');
        //     const messageText = messageInput.value.trim();
            
        //     if (message === '' && !hasAttachment()) return;
            
        //     // Create FormData for the server request
        //     const formData = new FormData();
        //     formData.append('room_id', '<?php echo $roomId; ?>');
        //     formData.append('message', messageText);
            
        //     // Send the message to the server
        //     fetch('send_message.php', {
        //         method: 'POST',
        //         body: formData
        //     })
        //     .then(response => response.json())
        //     .then(data => {
        //         if (data.success) {
        //             // Clear input after sending
        //             messageInput.value = '';
        //             clearAttachment();
                    
        //             lastActivityTime = Date.now();
        //             // Reload messages to show the newly sent message
        //             // This assumes you have a loadMessages() function that updates the UI
        //             loadMessages();

        //              // Force scroll to bottom after sending a message
        //             setTimeout(scrollToBottom, 100);
        //         } else {
        //             alert('Error: ' + data.error);
        //         }
        //     })
        //     .catch(error => {
        //         console.error('Error sending message:', error);
        //         alert('Failed to send message. Please try again.');
        //     });
        // }
        // function sendMessage() {
        //     const messageInput = document.getElementById('message-input');
        //     const message = messageInput.value.trim();

        //     if (message === '' && !hasAttachment()) return;

        //     const formData = new FormData();
        //     formData.append('room_id', '<?php echo $roomId; ?>');
        //     formData.append('message', messageText);

        //     // After sending the message
        //     fetch('send_message.php', {
        //         method: 'POST',
        //         body: formData
        //         // Your form data...
        //     })
        //     .then(response => response.json())
        //     .then(data => {
        //         if (data.success) {
        //             // Clear input
        //             messageInput.value = '';
        //             clearAttachment();

        //             // Reset activity time
        //             lastActivityTime = Date.now();

        //             // Load messages and ensure we scroll to bottom
        //             loadMessages();

        //             // Force scroll to bottom after sending a message
        //             setTimeout(scrollToBottom, 100);
        //         } else {
        //             console.error('Error sending message:', data.message);
        //         }
        //     })
        //     .catch(error => {
        //         console.error('Error sending message:', error);
        //     });
        // }

        // Modified sendMessage function to auto-scroll after sending
        function sendMessage() {
            const messageInput = document.getElementById('message-input');
            const messageText = messageInput.value.trim();

            if (messageText === '' && !hasAttachment()) return;

             // Create FormData for the server request
            const formData = new FormData();
            formData.append('room_id', '<?php echo $roomId; ?>');
            formData.append('message', messageText);

            // After sending the message
            fetch('send_message.php', {
                method: 'POST',
                body: formData
                // Your form data...
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear input
                    messageInput.value = '';
                    // clearAttachment();

                    // Reset activity time
                    lastActivityTime = Date.now();

                    // Load messages and ensure we scroll to bottom
                    loadMessages();

                    // Force scroll to bottom after sending a message
                    setTimeout(scrollToBottom, 100);
                } else {
                    console.error('Error sending message:', data.message);
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
            });
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

        // Toggle emoji picker
        function toggleEmojiPicker() {
            const emojiPicker = document.getElementById('emoji-picker');
            if (emojiPicker.style.display === 'block') {
                emojiPicker.style.display = 'none';
            } else {
                emojiPicker.style.display = 'block';
                loadEmojis('smileys'); // Load default category
            }
        }

        // Load emojis for a category
        function loadEmojis(category) {
            const emojiContainer = document.getElementById('emoji-container');
            let emojisHTML = '';
            
            if (category === 'pepe' || category === 'random') {
                // For custom emojis/GIFs, we need to use img tags
                emojiData[category].forEach(emojiPath => {
                    emojisHTML += `<div class="emoji custom-emoji" onclick="addCustomEmoji('${emojiPath}')">
                                    <img src="${emojiPath}" alt="Custom Emoji" class="custom-emoji-img">
                                </div>`;
                });
                // console.log(emojisHTML)
            } else {
                // For regular emojis
                emojiData[category].forEach(emoji => {
                    emojisHTML += `<div class="emoji" onclick="addEmoji('${emoji}')">${emoji}</div>`;
                });
            }
            
            emojiContainer.innerHTML = emojisHTML;
            
            // Update active category
            document.querySelectorAll('.emoji-category').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelector(`.emoji-category[data-category="${category}"]`).classList.add('active');
            // const emojiContainer = document.getElementById('emoji-container');
            // let emojisHTML = '';
            
            // emojiData[category].forEach(emoji => {
            //     emojisHTML += `<div class="emoji" onclick="addEmoji('${emoji}')">${emoji}</div>`;
            // });
            
            // emojiContainer.innerHTML = emojisHTML;
            
            // // Update active category
            // document.querySelectorAll('.emoji-category').forEach(el => {
            //     el.classList.remove('active');
            // });
            // document.querySelector(`.emoji-category[data-category="${category}"]`).classList.add('active');
        }

        // Add emoji to message input
        function addEmoji(emoji) {
            const messageInput = document.getElementById('message-input');
            messageInput.value += emoji;
            messageInput.focus();
        }
        // Add custom emoji/GIF to input
        function addCustomEmoji(emojiPath) {
            const messageInput = document.getElementById('message-input');
            
            // For chat applications, you might want to add a placeholder or code that will be replaced
            // with the actual image when sent. This depends on how your chat system handles images.
            // Here's a simple example that adds a code like [EMOJI:path]
            messageInput.value += `[EMOJI:${emojiPath}]`;
            messageInput.focus();
            
            // Optionally close the emoji picker after selection
            document.getElementById('emoji-picker').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Instead of creating hidden inputs, let's use the existing ones
            // or find the correct variable names from your PHP code
            
            // Load messages on page load
            loadMessages();
            
             // Scroll to bottom on initial load
            setTimeout(scrollToBottom, 500);
            
            // Track user activity
            document.addEventListener('click', function() {
                lastActivityTime = Date.now();
            });

            document.addEventListener('keydown', function() {
                lastActivityTime = Date.now();
            });
                    
            // Reply modal event listeners
            // document.getElementById('sendReplyBtn').addEventListener('click', sendReply);
            document.getElementById('cancelReplyBtn').addEventListener('click', closeReplyModal);
            document.getElementById('closeReplyModal').addEventListener('click', closeReplyModal);
            
            // Existing message sending functionality
            const sendBtn = document.getElementById('send-btn');
            if (sendBtn) {
                sendBtn.addEventListener('click', function() {
                    sendMessage();
                });
            }
            
            const messageInput = document.getElementById('message-input');
            if (messageInput) {
                messageInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        sendMessage();
                    }
                });
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const replyModal = document.getElementById('replyModal');
                if (event.target === replyModal) {
                    closeReplyModal();
                }
            });
            
            // Add reply buttons to existing messages
            addReplyButtons();
        });
         // Event listeners
    // document.getElementById('send-btn').addEventListener('click', sendMessage);
    //     document.getElementById('message-input').addEventListener('keypress', function(e) {
    //         if (e.key === 'Enter') {
    //             sendMessage();
    //         }
    //     });

        // Emoji picker event listeners
        document.getElementById('emoji-btn').addEventListener('click', toggleEmojiPicker);
        document.querySelectorAll('.emoji-category').forEach(el => {
            el.addEventListener('click', function() {
                loadEmojis(this.getAttribute('data-category'));
            });
        });

        // Close emoji picker when clicking outside
        document.addEventListener('click', function(event) {
            const emojiPicker = document.getElementById('emoji-picker');
            const emojiBtn = document.getElementById('emoji-btn');
            
            if (emojiPicker.style.display === 'block' && 
                !emojiPicker.contains(event.target) && 
                event.target !== emojiBtn) {
                emojiPicker.style.display = 'none';
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
        addReplyButtons();
        // const messageInterval = setInterval(loadMessages, 3000); // Refresh every 3 seconds
        loadMessages();

        function showReplyModal() {
            document.getElementById('replyModal').style.display = 'flex';
        }
    </script>


    <div class="modal" id="replyModal" style="display: none;">
    <div class="modal-content">
        <span class="close-modal" id="closeReplyModal">&times;</span>
        <h3>Reply to Message</h3>
        <div class="reply-preview" id="replyPreview"></div>
        <textarea id="replyText" placeholder="Type your reply here..."></textarea>
        <div class="modal-buttons">
            <button id="sendReplyBtn" class="btn primary-btn">Send Reply</button>
            <button id="cancelReplyBtn" class="btn secondary-btn">Cancel</button>
        </div>
    </div>
</div>
</body>
</html>