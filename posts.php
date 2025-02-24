<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch logged-in user's details
$query = "SELECT username, full_name, profile_picture FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);
$display_name = $user['full_name'] ?: $user['username'];

// Fetch all posts
$posts_query = "SELECT p.*, u.username, u.profile_picture FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC";
$posts_result = mysqli_query($conn, $posts_query);

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
    <title>CollabConnect - Posts</title>
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
        .notification-modal, .profile-modal {
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
        .notification-modal .notification, .profile-modal a {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .notification-modal .notification:last-child, .profile-modal a:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body class="bg-neutral-base min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-2xl font-['Pacifico'] text-primary">CollabConnect</span>
                    <div class="ml-10 relative w-96">
                        <input type="text" id="global-search" placeholder="Search jobs, people, resources..." class="w-full pl-10 pr-4 py-2 border rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <div class="absolute left-3 top-0 h-full w-5 flex items-center justify-center">
                            <i class="ri-search-line text-gray-400"></i>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="w-8 h-8 flex items-center justify-center relative" id="notification-btn">
                        <i class="ri-notification-3-line text-gray-600"></i>
                        <?php if ($notif_count > 0): ?>
                            <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
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
                    </div>
                    <div class="w-8 h-8 flex items-center justify-center">
                        <a href="messages.php" class="text-gray-600"><i class="ri-message-3-line"></i></a>
                    </div>
                    <div class="flex items-center space-x-3 relative">
                        <?php if ($user['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="w-10 h-10 rounded-full object-cover" alt="Profile">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center text-white text-xl"><?php echo strtoupper(substr($display_name, 0, 1)); ?></div>
                        <?php endif; ?>
                        <button id="profileDropdown" class="flex items-center text-gray-700">
                            <span class="text-sm font-medium text-neutral-text"><?php echo htmlspecialchars($display_name); ?></span>
                            <i class="ri-arrow-down-s-line ml-1"></i>
                        </button>
                        <div class="profile-modal" id="profile-modal">
                            <a href="profile.php" class="text-sm text-gray-700 hover:bg-gray-50">Profile</a>
                            <a href="settings.php" class="text-sm text-gray-700 hover:bg-gray-50">Settings</a>
                            <a href="logout.php" class="text-sm text-gray-700 hover:bg-gray-50">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Layout -->
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
                    <a href="posts.php" class="flex items-center space-x-3 px-4 py-2.5 text-primary bg-blue-50 rounded-lg">
                        <i class="ri-file-text-line"></i>
                        <span class="text-sm font-medium">Posts</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-64 p-8">
            <div class="max-w-7xl mx-auto">
                <div class="flex items-center justify-between mb-8">
                    <h1 class="text-2xl font-semibold text-neutral-text">All Posts</h1>
                    <div class="flex space-x-4">
                        <a href="dashboard.php" class="flex items-center px-4 py-2 bg-primary text-white rounded-button">
                            <i class="ri-arrow-left-line mr-2"></i>
                            <span>Back to Dashboard</span>
                        </a>
                    </div>
                </div>

                <!-- Posts Section -->
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-medium text-neutral-text">Posts</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <?php
                        if ($posts_result && mysqli_num_rows($posts_result) > 0) {
                            while ($post = mysqli_fetch_assoc($posts_result)) {
                                echo "<div class='border-b pb-6'>";
                                echo "<div class='flex items-start'>";
                                if ($post['profile_picture']) {
                                    echo "<img src='" . htmlspecialchars($post['profile_picture']) . "' class='w-12 h-12 rounded-full object-cover'>";
                                } else {
                                    echo "<div class='w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center text-white text-lg'>" . strtoupper(substr($post['username'], 0, 1)) . "</div>";
                                }
                                echo "<div class='ml-4 flex-1'>";
                                echo "<div class='flex items-center justify-between'>";
                                echo "<div>";
                                echo "<h4 class='text-base font-medium text-neutral-text'>" . htmlspecialchars($post['username']) . "</h4>";
                                echo "<p class='text-sm text-gray-500'>" . ($post['job_id'] ? "Job Post" : "General Post") . " • " . $post['created_at'] . "</p>";
                                echo "</div>";
                                echo "<button class='text-gray-400 hover:text-gray-500'><i class='ri-more-2-fill'></i></button>";
                                echo "</div>";
                                echo "<p class='mt-2 text-gray-700'>" . htmlspecialchars($post['content']) . "</p>";
                                if ($post['image_url']) {
                                    echo "<img src='" . htmlspecialchars($post['image_url']) . "' class='mt-3 rounded-lg w-full h-48 object-cover'>";
                                }
                                echo "<div class='flex items-center justify-between mt-4'>";
                                echo "<div class='flex items-center space-x-6'>";
                                echo "<button class='like-btn flex items-center text-gray-500 hover:text-primary' data-post-id='" . $post['id'] . "'><i class='ri-thumb-up-line mr-2'></i><span>" . $post['likes'] . "</span></button>";
                                echo "<button class='comment-btn flex items-center text-gray-500 hover:text-primary' data-post-id='" . $post['id'] . "'><i class='ri-chat-1-line mr-2'></i><span>" . $post['comments'] . "</span></button>";
                                echo "<button class='share-btn flex items-center text-gray-500 hover:text-primary' data-post-id='" . $post['id'] . "'><i class='ri-share-line mr-2'></i><span>Share</span></button>";
                                echo "</div>";
                                echo "<button class='save-btn text-gray-500 hover:text-primary' data-post-id='" . $post['id'] . "'><i class='ri-bookmark-line'></i></button>";
                                echo "</div>";
                                echo "</div>";
                                echo "</div>";
                                echo "</div>";
                            }
                        } else {
                            echo "<p class='text-gray-700'>No posts available yet.</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </main>
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

        // Profile Dropdown
        const profileBtn = document.getElementById('profileDropdown');
        const profileModal = document.getElementById('profile-modal');
        profileBtn.addEventListener('click', () => {
            profileModal.style.display = profileModal.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
            if (!profileBtn.contains(e.target) && !profileModal.contains(e.target)) {
                profileModal.style.display = 'none';
            }
        });

        // Search Functionality
        const globalSearch = document.getElementById('global-search');
        globalSearch.addEventListener('input', function() {
            const query = this.value;
            if (query.length > 2) {
                fetch('search.php?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        console.log('Search results:', data);
                        // Implement UI to display results (e.g., dropdown)
                    });
            }
        });

        // Post Interactions
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                fetch('interact.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'post_id=' + postId + '&type=like'
                }).then(() => {
                    let count = parseInt(this.querySelector('span').textContent);
                    this.classList.toggle('text-primary');
                    this.querySelector('span').textContent = this.classList.contains('text-primary') ? count + 1 : count - 1;
                });
            });
        });

        document.querySelectorAll('.comment-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                alert('Comment functionality for post ' + postId + ' coming soon!');
            });
        });

        document.querySelectorAll('.share-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                fetch('interact.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'post_id=' + postId + '&type=share'
                }).then(() => {
                    alert('Post ' + postId + ' shared!');
                });
            });
        });

        document.querySelectorAll('.save-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                fetch('save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'item_type=post&item_id=' + postId
                }).then(() => {
                    this.classList.toggle('text-primary');
                });
            });
        });
    });
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>