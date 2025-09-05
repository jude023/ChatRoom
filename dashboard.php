<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Load rooms data
$roomsFile = 'rooms.json';
if (!file_exists($roomsFile)) {
    file_put_contents($roomsFile, json_encode(['rooms' => []]));
}

$roomsData = json_decode(file_get_contents($roomsFile), true);
if (!$roomsData) {
    $roomsData = ['rooms' => []];
}

// Load user data to get joined rooms
$usersFile = 'users.json';
$userData = json_decode(file_get_contents($usersFile), true);

$userRooms = [];
foreach ($userData['users'] as $user) {
    if ($user['id'] === $userId) {
        $userRooms = $user['rooms'];
        break;
    }
}

// Get rooms the user has joined
$joinedRooms = [];
foreach ($roomsData['rooms'] as $room) {
    if (in_array($room['id'], $userRooms)) {
        $joinedRooms[] = $room;
    }
}

// Get notifications
$notificationsFile = 'notifications.json';
if (!file_exists($notificationsFile)) {
    file_put_contents($notificationsFile, json_encode(['notifications' => []]));
}

$notificationsData = json_decode(file_get_contents($notificationsFile), true);
if (!$notificationsData) {
    $notificationsData = ['notifications' => []];
}

$userNotifications = [];
foreach ($notificationsData['notifications'] as $notification) {
    if ($notification['user_id'] === $userId && !$notification['read']) {
        $userNotifications[] = $notification;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chat Room Dashboard</title>
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
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #ddd;
        }
        .notification-bell {
            position: relative;
            cursor: pointer;
        }
        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: red;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
        }
        .notification-dropdown {
            position: absolute;
            top: 30px;
            right: 0;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 300px;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
            z-index: 1000;
        }
        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .rooms-container {
            display: flex;
            margin-top: 20px;
        }
        .rooms-list {
            flex: 1;
            margin-right: 20px;
        }
        .create-room {
            flex: 1;
        }
        .room-card {
            background-color: white;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .room-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .room-info {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .room-actions {
            display: flex;
            justify-content: space-between;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        .btn-secondary {
            background-color: #2196F3;
            color: white;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .card {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card-title {
            font-size: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>
            <div class="user-actions">
                <div class="notification-bell" onclick="toggleNotifications()">
                    🔔
                    <?php if (count($userNotifications) > 0): ?>
                        <span class="notification-count"><?php echo count($userNotifications); ?></span>
                    <?php endif; ?>
                    
                    <div class="notification-dropdown" id="notification-dropdown">
                        <?php if (count($userNotifications) > 0): ?>
                            <?php foreach ($userNotifications as $notification): ?>
                                <div class="notification-item">
                                    <div><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <small><?php echo htmlspecialchars($notification['created_at']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notification-item">No new notifications</div>
                        <?php endif; ?>
                    </div>
                </div>
                <button class="btn btn-secondary" onclick="logout()">Logout</button>
            </div>
        </div>
        
        <div class="rooms-container">
            <div class="rooms-list">
                <h2>Your Chat Rooms</h2>
                
                <?php if (count($joinedRooms) > 0): ?>
                    <?php foreach ($joinedRooms as $room): ?>
                        <div class="room-card">
                            <div class="room-title"><?php echo htmlspecialchars($room['name']); ?></div>
                            <div class="room-info">
                                Created by: <?php echo htmlspecialchars($room['creator_name']); ?><br>
                                Members: <?php echo count($room['members']); ?>
                            </div>
                            <div class="room-actions">
                                <button class="btn btn-primary" onclick="enterRoom('<?php echo $room['id']; ?>')">Enter Room</button>
                                <?php if ($room['creator_id'] === $userId): ?>
                                    <button class="btn btn-danger" onclick="deleteRoom('<?php echo $room['id']; ?>')">Delete Room</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>You haven't joined any chat rooms yet.</p>
                <?php endif; ?>
            </div>
            
            <div class="create-room">
                <div class="card">
                    <div class="card-title">Create New Chat Room</div>
                    <form id="create-room-form">
                        <div class="form-group">
                            <label for="room-name">Room Name:</label>
                            <input type="text" id="room-name" name="room_name" required>
                        </div>
                        <div class="form-group">
                            <label for="room-description">Description:</label>
                            <textarea id="room-description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="room-password">Password (optional):</label>
                            <input type="password" id="room-password" name="password">
                            <small>Leave empty for a public room</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Room</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleNotifications() {
            const dropdown = document.getElementById('notification-dropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            
            // Mark notifications as read
            if (dropdown.style.display === 'block') {
                fetch('mark_notifications_read.php', {
                    method: 'POST'
                });
                
                // Remove notification count
                const notificationCount = document.querySelector('.notification-count');
                if (notificationCount) {
                    notificationCount.style.display = 'none';
                }
            }
        }
        
        function enterRoom(roomId) {
            window.location.href = 'room.php?id=' + roomId;
        }
        
        function deleteRoom(roomId) {
            if (confirm('Are you sure you want to delete this room? This action cannot be undone.')) {
                fetch('delete_room.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        'room_id': roomId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Room deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
        
        function logout() {
            fetch('logout.php')
                .then(() => {
                    window.location.href = 'index.php';
                });
        }
        
        // Create room form submission
        document.getElementById('create-room-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const roomName = document.getElementById('room-name').value;
            const description = document.getElementById('room-description').value;
            const password = document.getElementById('room-password').value;
            
            const formData = new FormData();
            formData.append('room_name', roomName);
            formData.append('description', description);
            formData.append('password', password);
            
            fetch('create_room.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Room created successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
        
        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notification-dropdown');
            const bell = document.querySelector('.notification-bell');
            
            if (dropdown.style.display === 'block' && !bell.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Check for new notifications periodically
        setInterval(function() {
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        const notificationCount = document.querySelector('.notification-count');
                        if (notificationCount) {
                            notificationCount.textContent = data.count;
                            notificationCount.style.display = 'flex';
                        } else {
                            const bell = document.querySelector('.notification-bell');
                            const countElement = document.createElement('span');
                            countElement.className = 'notification-count';
                            countElement.textContent = data.count;
                            bell.appendChild(countElement);
                        }
                    }
                });
        }, 10000); // Check every 10 seconds
    </script>
</body>
</html>