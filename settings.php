<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's profile details
$query = "SELECT full_name, profile_picture, email, email_notifications, is_public FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Handle settings updates
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Password Update
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Fetch current password hash
        $password_query = "SELECT password FROM users WHERE id = $user_id";
        $password_result = mysqli_query($conn, $password_query);
        $current_hash = mysqli_fetch_assoc($password_result)['password'];

        if (password_verify($current_password, $current_hash)) {
            if ($new_password === $confirm_password) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = '$new_hash' WHERE id = $user_id";
                if (mysqli_query($conn, $update_query)) {
                    $success_message = "Password updated successfully.";
                } else {
                    $error_message = "Error updating password: " . mysqli_error($conn);
                }
            } else {
                $error_message = "New password and confirmation do not match.";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    }

    // Email Preferences and Privacy
    if (isset($_POST['update_settings'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $is_public = isset($_POST['is_public']) ? 1 : 0;

        $update_query = "UPDATE users SET email_notifications = $email_notifications, is_public = $is_public WHERE id = $user_id";
        if (mysqli_query($conn, $update_query)) {
            $success_message = "Settings updated successfully.";
            // Refresh user data
            $result = mysqli_query($conn, $query);
            $user = mysqli_fetch_assoc($result);
        } else {
            $error_message = "Error updating settings: " . mysqli_error($conn);
        }
    }
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
    <title>Settings | MeetMate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0066FF',
                        secondary: '#4F46E5',
                        neutral: {
                            base: '#F9FAFB',
                            text: '#1F2A44'
                        }
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
    <style>
        .suggestions { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            width: 100%; 
            background: white; 
            border: 1px solid #e5e7eb; 
            border-radius: 0 0 8px 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            z-index: 999; 
            max-height: 300px; 
            overflow-y: auto; 
        }
        .suggestion-item { padding: 8px 16px; cursor: pointer; }
        .suggestion-item:hover { background: #f3f4f6; }
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
    </style>
</head>
<body class="bg-neutral-base min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white border-b fixed top-0 w-full z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-2xl font-['Pacifico'] text-primary">MeetMate</a>
                    <div class="ml-10 relative w-96">
                        <input type="text" id="search-input" placeholder="Search jobs, people, resources..." 
                               class="w-full pl-10 pr-4 py-2 border rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <div class="absolute left-3 top-0 h-full w-5 flex items-center justify-center">
                            <i class="ri-search-line text-gray-400"></i>
                        </div>
                        <div id="suggestions" class="suggestions hidden"></div>
                    </div>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="w-8 h-8 flex items-center justify-center relative">
                        <button id="notification-btn" class="w-full h-full flex items-center justify-center">
                            <i class="ri-notification-3-line text-gray-600"></i>
                            <?php if ($notif_count > 0): ?>
                                <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
                            <?php endif; ?>
                        </button>
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
                    </div>
                    <div class="w-8 h-8 flex items-center justify-center">
                        <i class="ri-message-3-line text-gray-600"></i>
                    </div>
                    <div class="flex items-center space-x-3 relative">
                        <img src="<?php echo $user['profile_picture'] ?: 'https://public.readdy.ai/ai/img_res/8fc4275b74f60207e4de29585f8c51ec.jpg'; ?>" 
                             class="w-10 h-10 rounded-full object-cover">
                        <button id="profileDropdown" class="flex items-center text-gray-700">
                            <span class="text-sm font-medium text-neutral-text"><?php echo $user['full_name']; ?></span>
                            <i class="ri-arrow-down-s-line ml-1"></i>
                        </button>
                        <div id="profile-dropdown" class="hidden absolute right-0 top-12 w-48 bg-white rounded-lg shadow-lg py-2">
                            <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="settings.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Settings</a>
                            <a href="logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white h-screen border-r fixed left-0 top-16">
            <div class="p-4">
                <div class="space-y-2">
                    <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
                        <i class="ri-dashboard-line"></i>
                        <span class="text-sm font-medium">Dashboard</span>
                    </a>
                    <a href="jobs.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
                        <i class="ri-briefcase-line"></i>
                        <span class="text-sm font-medium">Jobs</span>
                    </a>
                    <a href="network.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
                        <i class="ri-user-2-line"></i>
                        <span class="text-sm font-medium">Network</span>
                    </a>
                    <a href="messages.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
                        <i class="ri-message-2-line"></i>
                        <span class="text-sm font-medium">Messages</span>
                    </a>
                    <a href="#" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
                        <i class="ri-book-read-line"></i>
                        <span class="text-sm font-medium">Resources</span>
                    </a>
                </div>
                <div class="mt-8">
                    <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Saved Items</h3>
                    <div class="mt-4 space-y-2">
                        <a href="saved_jobs.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
                            <i class="ri-bookmark-line"></i>
                            <span class="text-sm font-medium">Saved Jobs</span>
                        </a>
                        <a href="#" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
                            <i class="ri-file-list-line"></i>
                            <span class="text-sm font-medium">My Applications</span>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-64 p-8 pt-24">
            <div class="max-w-7xl mx-auto">
                <header class="py-6 flex items-center justify-between">
                    <h1 class="text-2xl font-semibold text-neutral-text">Settings</h1>
                    <a href="dashboard.php" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Back to Dashboard</a>
                </header>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <!-- Messages -->
                    <?php if ($success_message): ?>
                        <div class="mb-4 p-4 bg-green-50 text-green-700 rounded-lg"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="mb-4 p-4 bg-red-50 text-red-700 rounded-lg"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <!-- Password Update -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-neutral-text">Change Password</h3>
                        <form method="POST" class="mt-4 space-y-4">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                                <input type="password" name="current_password" id="current_password" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                            </div>
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                                <input type="password" name="new_password" id="new_password" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" name="update_password" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Update Password</button>
                            </div>
                        </form>
                    </div>

                    <!-- Email Preferences and Privacy -->
                    <div>
                        <h3 class="text-lg font-medium text-neutral-text">Preferences & Privacy</h3>
                        <form method="POST" class="mt-4 space-y-4">
                            <div class="flex items-center">
                                <input type="checkbox" name="email_notifications" id="email_notifications" 
                                       class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded" 
                                       <?php echo $user['email_notifications'] ? 'checked' : ''; ?>>
                                <label for="email_notifications" class="ml-2 text-sm text-gray-700">Receive email notifications</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="is_public" id="is_public" 
                                       class="h-4 w-4 text-primary focus:ring-primary/20 border-gray-300 rounded" 
                                       <?php echo $user['is_public'] ? 'checked' : ''; ?>>
                                <label for="is_public" class="ml-2 text-sm text-gray-700">Make my profile public</label>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" name="update_settings" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Save Settings</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Profile dropdown
        const profileBtn = document.getElementById('profileDropdown');
        const profileDropdown = document.getElementById('profile-dropdown');
        profileBtn.addEventListener('click', () => {
            profileDropdown.classList.toggle('hidden');
        });

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

        // Real-time search suggestions
        const searchInput = document.getElementById('search-input');
        const suggestionsDiv = document.getElementById('suggestions');
        searchInput.addEventListener('input', async () => {
            const query = searchInput.value.trim();
            if (query.length < 1) {
                suggestionsDiv.classList.add('hidden');
                return;
            }
            try {
                const response = await fetch(`search_suggestions.php?q=${encodeURIComponent(query)}`);
                const results = await response.json();
                if (results.length > 0) {
                    suggestionsDiv.innerHTML = results.map(item => `
                        <div class="suggestion-item flex items-center gap-3">
                            <img src="${item.image || 'https://via.placeholder.com/40'}" alt="${item.title}" class="w-8 h-8 rounded-full">
                            <div>
                                <p class="font-medium text-neutral-text">${item.title}</p>
                                <p class="text-sm text-gray-600">${item.subtitle} (${item.type})</p>
                            </div>
                        </div>
                    `).join('');
                    suggestionsDiv.classList.remove('hidden');
                } else {
                    suggestionsDiv.innerHTML = '<div class="suggestion-item text-gray-600">No results found</div>';
                    suggestionsDiv.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Search error:', error);
                suggestionsDiv.innerHTML = '<div class="suggestion-item text-red-600">Error fetching suggestions</div>';
                suggestionsDiv.classList.remove('hidden');
            }
        });
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                suggestionsDiv.classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>