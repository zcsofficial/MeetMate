<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's profile details
$query = "SELECT username, full_name, profile_picture, online_status FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);
$display_name = $user['full_name'] ?: $user['username'];

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $insert_query = "INSERT INTO messages (sender_id, receiver_id, message, sent_at) VALUES ($user_id, $receiver_id, '$message', NOW())";
    mysqli_query($conn, $insert_query);
    $notif_query = "INSERT INTO notifications (user_id, type, related_id, message, created_at) VALUES ($receiver_id, 'message', $user_id, '$display_name has sent you a message', NOW())";
    mysqli_query($conn, $notif_query);
    header("Location: messages.php?user=$receiver_id");
    exit;
}

// Fetch conversation threads (distinct users messaged)
$threads_query = "SELECT DISTINCT u.id, u.username, u.full_name, u.profile_picture, u.online_status,
                  (SELECT m.message FROM messages m WHERE (m.sender_id = u.id AND m.receiver_id = $user_id) OR (m.sender_id = $user_id AND m.receiver_id = u.id) ORDER BY m.sent_at DESC LIMIT 1) AS last_message,
                  (SELECT m.sent_at FROM messages m WHERE (m.sender_id = u.id AND m.receiver_id = $user_id) OR (m.sender_id = $user_id AND m.receiver_id = u.id) ORDER BY m.sent_at DESC LIMIT 1) AS last_sent,
                  (SELECT COUNT(*) FROM messages m WHERE m.receiver_id = $user_id AND m.sender_id = u.id AND m.is_read = FALSE) AS unread_count
                  FROM users u 
                  JOIN messages m ON (u.id = m.sender_id OR u.id = m.receiver_id)
                  WHERE (m.sender_id = $user_id OR m.receiver_id = $user_id) AND u.id != $user_id
                  ORDER BY last_sent DESC";
$threads_result = mysqli_query($conn, $threads_query);

// Fetch messages for selected user (if any)
$selected_user_id = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$messages_query = "SELECT m.*, u.username AS sender_username, u.profile_picture AS sender_picture 
                   FROM messages m 
                   JOIN users u ON m.sender_id = u.id 
                   WHERE (m.sender_id = $user_id AND m.receiver_id = $selected_user_id) 
                   OR (m.sender_id = $selected_user_id AND m.receiver_id = $user_id) 
                   ORDER BY m.sent_at ASC";
$messages_result = $selected_user_id ? mysqli_query($conn, $messages_query) : null;

// Fetch selected user's details
$selected_user = null;
if ($selected_user_id) {
    $selected_user_query = "SELECT full_name, username, online_status, profile_picture FROM users WHERE id = $selected_user_id";
    $selected_user_result = mysqli_query($conn, $selected_user_query);
    $selected_user = mysqli_fetch_assoc($selected_user_result);
}

// Mark messages as read
if ($selected_user_id) {
    $update_query = "UPDATE messages SET is_read = TRUE WHERE receiver_id = $user_id AND sender_id = $selected_user_id AND is_read = FALSE";
    mysqli_query($conn, $update_query);
}

// Fetch notifications
$notif_query = "SELECT id, message, created_at FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$notif_result = mysqli_query($conn, $notif_query);
$notif_count = mysqli_num_rows($notif_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - CollabConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :where([class^="ri-"])::before { content: "\f3c2"; }
        .message-list::-webkit-scrollbar, .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        .message-list::-webkit-scrollbar-thumb, .chat-messages::-webkit-scrollbar-thumb {
            background-color: #E5E7EB;
            border-radius: 3px;
        }
        .emoji-picker {
            display: none;
            position: absolute;
            bottom: 100%;
            right: 0;
            background: white;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .emoji-picker.active {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 4px;
        }
        .notification-modal {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            width: 300px;
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-modal .notification {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .notification-modal .notification:last-child {
            border-bottom: none;
        }
        .search-suggestions {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }
        .search-suggestions.active {
            display: block;
        }
        .search-suggestion {
            padding: 8px 12px;
            cursor: pointer;
        }
        .search-suggestion:hover {
            background-color: #F3F4F6;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#7C3AED',
                        secondary: '#60A5FA'
                    },
                    borderRadius: {
                        'none': '0px',
                        'sm': '4px',
                        DEFAULT: '8px',
                        'md': '12px',
                        'lg': '16px',
                        'xl': '20px',
                        '2xl': '24px',
                        '3xl': '32px',
                        'full': '9999px',
                        'button': '8px'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 h-screen flex flex-col">
    <div class="flex-1 flex overflow-hidden">
        <!-- Conversation Threads -->
        <div class="w-96 border-r bg-white flex flex-col">
            <div class="p-4 border-b">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-xl font-semibold">Messages</h1>
                    <div class="flex items-center gap-2">
                        <button class="w-8 h-8 flex items-center justify-center text-primary hover:bg-primary/5 rounded-full" id="notification-btn">
                            <i class="ri-notification-3-line text-xl"></i>
                            <?php if ($notif_count > 0): ?>
                                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                            <?php endif; ?>
                            <div class="notification-modal" id="notification-modal">
                                <?php
                                if ($notif_count > 0) {
                                    while ($notif = mysqli_fetch_assoc($notif_result)) {
                                        echo "<div class='notification'>";
                                        echo "<p class='text-sm text-gray-700'>" . htmlspecialchars($notif['message']) . "</p>";
                                        echo "<small class='text-gray-500'>" . $notif['created_at'] . "</small>";
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<div class='notification'><p class='text-sm text-gray-700'>No new notifications</p></div>";
                                }
                                ?>
                            </div>
                        </button>
                        <a href="profile.php" class="w-8 h-8 flex items-center justify-center text-primary hover:bg-primary/5 rounded-full">
                            <?php if ($user['profile_picture']): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="w-8 h-8 rounded-full object-cover" alt="Profile">
                            <?php else: ?>
                                <div class="text-xl text-gray-600"><?php echo strtoupper(substr($display_name, 0, 1)); ?></div>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                <div class="relative">
                    <input type="text" id="global-search" placeholder="Search messages..." class="w-full pl-10 pr-4 py-2 bg-gray-50 rounded-full text-sm">
                    <i class="ri-search-line absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <div class="search-suggestions" id="search-suggestions"></div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button class="px-4 py-1 text-sm bg-primary text-white rounded-full">All</button>
                    <button class="px-4 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded-full">Unread</button>
                    <button class="px-4 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded-full">Companies</button>
                </div>
            </div>
            <div class="message-list flex-1 overflow-y-auto" id="messageList">
                <?php
                if (mysqli_num_rows($threads_result) > 0) {
                    while ($thread = mysqli_fetch_assoc($threads_result)) {
                        $active = $selected_user_id == $thread['id'] ? 'bg-primary/5' : '';
                        echo "<a href='messages.php?user=" . $thread['id'] . "' class='flex items-center gap-3 p-4 hover:bg-gray-50 $active'>";
                        echo "<div class='relative'>";
                        if ($thread['profile_picture']) {
                            echo "<img src='" . htmlspecialchars($thread['profile_picture']) . "' class='w-10 h-10 rounded-full object-cover'>";
                        } else {
                            echo "<div class='w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-xl text-gray-600'>" . strtoupper(substr($thread['full_name'] ?: $thread['username'], 0, 1)) . "</div>";
                        }
                        if ($thread['online_status']) {
                            echo "<div class='absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white'></div>";
                        }
                        echo "</div>";
                        echo "<div class='flex-1 min-w-0'>";
                        echo "<div class='flex items-center justify-between'>";
                        echo "<h3 class='font-medium truncate'>" . htmlspecialchars($thread['full_name'] ?: $thread['username']) . "</h3>";
                        echo "<span class='text-xs text-gray-500'>" . date('h:i A', strtotime($thread['last_sent'])) . "</span>";
                        echo "</div>";
                        echo "<p class='text-sm text-gray-600 truncate'>" . htmlspecialchars($thread['last_message'] ?: 'No messages yet') . "</p>";
                        echo "</div>";
                        if ($thread['unread_count'] > 0) {
                            echo "<div class='w-5 h-5 bg-primary text-white text-xs rounded-full flex items-center justify-center'>" . $thread['unread_count'] . "</div>";
                        }
                        echo "</a>";
                    }
                } else {
                    echo "<p class='p-4 text-gray-600'>No conversations yet.</p>";
                }
                ?>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="flex-1 flex flex-col bg-white">
            <div class="p-4 border-b flex items-center justify-between">
                <?php if ($selected_user): ?>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <?php if ($selected_user['profile_picture']): ?>
                                <img src="<?php echo htmlspecialchars($selected_user['profile_picture']); ?>" class="w-10 h-10 rounded-full object-cover">
                            <?php else: ?>
                                <div class='w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-xl text-gray-600'><?php echo strtoupper(substr($selected_user['full_name'] ?: $selected_user['username'], 0, 1)); ?></div>
                            <?php endif; ?>
                            <?php if ($selected_user['online_status']): ?>
                                <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h2 class="font-medium"><?php echo htmlspecialchars($selected_user['full_name'] ?: $selected_user['username']); ?></h2>
                            <p class="text-sm <?php echo $selected_user['online_status'] ? 'text-green-500' : 'text-gray-500'; ?>"><?php echo $selected_user['online_status'] ? 'Online' : 'Offline'; ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <button class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded-full">
                            <i class="ri-phone-line text-gray-600"></i>
                        </button>
                        <button class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded-full">
                            <i class="ri-vidicon-line text-gray-600"></i>
                        </button>
                        <button class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded-full">
                            <i class="ri-more-2-fill text-gray-600"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600">Select a conversation to start messaging</p>
                <?php endif; ?>
            </div>
            <div class="chat-messages flex-1 overflow-y-auto p-4" id="chatMessages">
                <?php
                if ($selected_user_id && $messages_result && mysqli_num_rows($messages_result) > 0) {
                    while ($message = mysqli_fetch_assoc($messages_result)) {
                        $is_sent = $message['sender_id'] == $user_id;
                        echo "<div class='flex flex-col " . ($is_sent ? 'items-end' : 'items-start') . " mb-4'>";
                        echo "<div class='max-w-[70%]'>";
                        echo "<div class='px-4 py-2 rounded-2xl " . ($is_sent ? 'bg-primary text-white' : 'bg-gray-100') . "'>";
                        echo "<p class='text-sm'>" . htmlspecialchars($message['message']) . "</p>";
                        echo "</div>";
                        echo "<div class='flex items-center gap-1 mt-1 text-xs text-gray-500'>";
                        echo "<span>" . date('h:i A, M d', strtotime($message['sent_at'])) . "</span>";
                        if ($is_sent) {
                            echo "<i class='ri-check-double-line " . ($message['is_read'] ? 'text-primary' : '') . "'></i>";
                        }
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                    }
                } elseif ($selected_user_id) {
                    echo "<p class='text-gray-600 text-center'>No messages yet.</p>";
                }
                ?>
            </div>
            <?php if ($selected_user_id): ?>
            <div class="p-4 border-t">
                <form method="POST" id="messageForm">
                    <input type="hidden" name="receiver_id" value="<?php echo $selected_user_id; ?>">
                    <div class="flex items-center gap-2">
                        <button type="button" class="w-10 h-10 flex items-center justify-center hover:bg-gray-100 rounded-full">
                            <i class="ri-attachment-2 text-gray-600"></i>
                        </button>
                        <div class="relative flex-1">
                            <input type="text" name="message" id="messageInput" placeholder="Type a message..." class="w-full pl-4 pr-24 py-2 bg-gray-50 rounded-full text-sm" required>
                            <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                <button type="button" class="w-8 h-8 flex items-center justify-center hover:bg-gray-200 rounded-full relative" id="emojiButton">
                                    <i class="ri-emotion-line text-gray-600"></i>
                                </button>
                                <button type="submit" name="send_message" class="w-8 h-8 flex items-center justify-center bg-primary text-white rounded-full" id="sendButton">
                                    <i class="ri-send-plane-fill"></i>
                                </button>
                            </div>
                            <div class="emoji-picker" id="emojiPicker">
                                <button class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded">😊</button>
                                <button class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded">👍</button>
                                <button class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded">❤️</button>
                                <button class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded">😂</button>
                                <button class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded">🎉</button>
                                <button class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 rounded">👋</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Notification Modal
        const notifBtn = document.getElementById('notification-btn');
        const notifModal = document.getElementById('notification-modal');
        notifBtn.addEventListener('click', () => {
            notifModal.style.display = notifModal.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
            if (!notifBtn.contains(e.target) && !notifModal.contains(e.target)) {
                notifModal.style.display = 'none';
            }
        });

        // Search Functionality with Suggestions
        const globalSearch = document.getElementById('global-search');
        const searchSuggestions = document.getElementById('search-suggestions');
        globalSearch.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length > 2) {
                fetch('search.php?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        searchSuggestions.innerHTML = '';
                        if (data.users.length > 0) {
                            data.users.forEach(user => {
                                const div = document.createElement('div');
                                div.className = 'search-suggestion flex items-center gap-2 p-2';
                                div.innerHTML = `
                                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-600">${user.full_name ? user.full_name[0].toUpperCase() : user.username[0].toUpperCase()}</div>
                                    <span>${user.full_name || user.username}</span>
                                    <span class="text-sm text-gray-500">${user.job_title || ''}</span>
                                `;
                                div.addEventListener('click', () => {
                                    window.location.href = 'messages.php?user=' + user.id;
                                });
                                searchSuggestions.appendChild(div);
                            });
                            searchSuggestions.classList.add('active');
                        } else {
                            searchSuggestions.innerHTML = '<div class="p-2 text-gray-600">No users found</div>';
                            searchSuggestions.classList.add('active');
                        }
                    });
            } else {
                searchSuggestions.classList.remove('active');
            }
        });
        document.addEventListener('click', (e) => {
            if (!globalSearch.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.classList.remove('active');
            }
        });

        // Emoji Picker
        const emojiButton = document.getElementById('emojiButton');
        const emojiPicker = document.getElementById('emojiPicker');
        emojiButton.addEventListener('click', function() {
            emojiPicker.classList.toggle('active');
        });

        document.querySelectorAll('.emoji-picker button').forEach(btn => {
            btn.addEventListener('click', function() {
                const input = document.getElementById('messageInput');
                input.value += this.textContent;
                emojiPicker.classList.remove('active');
                input.focus();
            });
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#emojiButton') && !e.target.closest('#emojiPicker')) {
                emojiPicker.classList.remove('active');
            }
        });

        // Auto-scroll to bottom of messages
        const messageContainer = document.getElementById('chatMessages');
        if (messageContainer) {
            messageContainer.scrollTop = messageContainer.scrollHeight;
        }
    });
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>