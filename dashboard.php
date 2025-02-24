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

// Handle Create Post
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_post'])) {
    $content = mysqli_real_escape_string($conn, $_POST['post_content']);
    $job_id = !empty($_POST['job_id']) && is_numeric($_POST['job_id']) ? (int)$_POST['job_id'] : NULL;
    $image_url = NULL;

    // Handle image upload
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $image_name = uniqid() . '-' . basename($_FILES['post_image']['name']);
        $image_path = $upload_dir . $image_name;

        // Validate image
        $image_type = exif_imagetype($_FILES['post_image']['tmp_name']);
        if (in_array($image_type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF]) && move_uploaded_file($_FILES['post_image']['tmp_name'], $image_path)) {
            $image_url = $image_path;
        } else {
            echo "Invalid image file. Only JPEG, PNG, and GIF are allowed.";
            exit;
        }
    }

    $insert_query = "INSERT INTO posts (user_id, content, image_url, job_id, created_at) VALUES ($user_id, '$content', " . ($image_url ? "'$image_url'" : "NULL") . ", " . ($job_id ? $job_id : "NULL") . ", NOW())";
    mysqli_query($conn, $insert_query);
    header("Location: dashboard.php");
    exit;
}

// Fetch posts
$posts_query = "SELECT p.*, u.username, u.profile_picture FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 10";
$posts_result = mysqli_query($conn, $posts_query);

// Fetch notifications
$notif_query = "SELECT id, message, created_at FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$notif_result = mysqli_query($conn, $notif_query);
$notif_count = mysqli_num_rows($notif_result);

// Fetch recommended jobs
$jobs_query = "SELECT * FROM jobs WHERE posted_by != $user_id ORDER BY posted_at DESC LIMIT 3";
$jobs_result = mysqli_query($conn, $jobs_query);

// Fetch network suggestions
$network_query = "SELECT u.id, u.username, u.job_title FROM users u WHERE u.id != $user_id AND u.id NOT IN (SELECT connected_user_id FROM connections WHERE user_id = $user_id AND status = 'accepted') LIMIT 3";
$network_result = mysqli_query($conn, $network_query);

// Fetch user's posted jobs for job_id dropdown
$user_jobs_query = "SELECT id, title FROM jobs WHERE posted_by = $user_id";
$user_jobs_result = mysqli_query($conn, $user_jobs_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CollabConnect Dashboard</title>
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
        .post-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }
        .post-modal-content {
            background-color: white;
            padding: 20px;
            width: 500px;
            border-radius: 8px;
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
                    <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-2.5 text-primary bg-blue-50 rounded-lg">
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
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-64 p-8">
            <div class="max-w-7xl mx-auto">
                <div class="flex items-center justify-between mb-8">
                    <h1 class="text-2xl font-semibold text-neutral-text">Welcome back, <?php echo htmlspecialchars($display_name); ?>!</h1>
                    <div class="flex space-x-4">
                        <button id="create-post-btn" class="flex items-center px-4 py-2 bg-primary text-white rounded-button">
                            <i class="ri-add-line mr-2"></i>
                            <span>Create Post</span>
                        </button>
                    </div>
                </div>

                <!-- Posts Section -->
                <div class="bg-white rounded-lg shadow-sm border mb-8">
                    <div class="p-6 border-b">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-neutral-text">Recent Posts</h3>
                            <a href="posts.php" class="text-primary text-sm">View all</a>
                        </div>
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

                <!-- Recommended Jobs -->
                <div class="bg-white rounded-lg shadow-sm border mb-8">
                    <div class="p-6 border-b">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-neutral-text">Recommended Jobs</h3>
                            <a href="jobs.php" class="text-primary text-sm">View all</a>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                        <?php
                        if ($jobs_result && mysqli_num_rows($jobs_result) > 0) {
                            while ($job = mysqli_fetch_assoc($jobs_result)) {
                                echo "<div class='flex items-start'>";
                                echo "<img src='" . ($job['company_logo'] ?: 'https://via.placeholder.com/48') . "' class='w-12 h-12 rounded-lg object-cover'>";
                                echo "<div class='ml-4 flex-1'>";
                                echo "<div class='flex items-center justify-between'>";
                                echo "<h4 class='text-base font-medium text-neutral-text'>" . htmlspecialchars($job['title']) . "</h4>";
                                echo "<span class='text-sm text-green-500'>" . rand(80, 95) . "% Match</span>";
                                echo "</div>";
                                echo "<p class='text-sm text-gray-600 mt-1'>" . htmlspecialchars($job['company_name']) . " • " . htmlspecialchars($job['location']) . "</p>";
                                echo "<div class='flex items-center mt-2 space-x-2'>";
                                echo "<span class='px-2.5 py-1 bg-blue-50 text-blue-700 text-xs rounded-full'>" . htmlspecialchars($job['job_type']) . "</span>";
                                echo "<span class='px-2.5 py-1 bg-blue-50 text-blue-700 text-xs rounded-full'>" . htmlspecialchars($job['work_mode']) . "</span>";
                                echo "</div>";
                                echo "</div>";
                                echo "</div>";
                            }
                        } else {
                            echo "<p class='text-gray-700'>No jobs available yet.</p>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Network Suggestions -->
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="p-6 border-b">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-neutral-text">Network Suggestions</h3>
                            <a href="network.php" class="text-primary text-sm">View all</a>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                        <?php
                        if ($network_result && mysqli_num_rows($network_result) > 0) {
                            while ($suggestion = mysqli_fetch_assoc($network_result)) {
                                echo "<div class='flex items-center'>";
                                echo "<div class='w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center text-white text-lg'>" . strtoupper(substr($suggestion['username'], 0, 1)) . "</div>";
                                echo "<div class='ml-4 flex-1'>";
                                echo "<div class='flex items-center justify-between'>";
                                echo "<div>";
                                echo "<h4 class='text-base font-medium text-neutral-text'>" . htmlspecialchars($suggestion['username']) . "</h4>";
                                echo "<p class='text-sm text-gray-600'>" . htmlspecialchars($suggestion['job_title'] ?: 'No job title') . "</p>";
                                echo "</div>";
                                echo "<button class='connect-btn px-4 py-2 border border-primary text-primary rounded-button hover:bg-primary/5' data-user-id='" . $suggestion['id'] . "'>";
                                echo "<i class='ri-user-add-line mr-2'></i>Connect";
                                echo "</button>";
                                echo "</div>";
                                echo "</div>";
                                echo "</div>";
                            }
                        } else {
                            echo "<p class='text-gray-700'>No suggestions available.</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Post Modal -->
    <div class="post-modal" id="post-modal">
        <div class="post-modal-content">
            <h2 class="text-lg font-medium text-neutral-text mb-4">Create a Post</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="post_content" class="block text-sm font-medium text-gray-700">Content</label>
                    <textarea name="post_content" id="post_content" class="w-full p-2 border rounded-lg" rows="4" placeholder="What’s on your mind?" required></textarea>
                </div>
                <div class="mb-4">
                    <label for="post_image" class="block text-sm font-medium text-gray-700">Upload Image (optional)</label>
                    <input type="file" name="post_image" id="post_image" class="w-full p-2 border rounded-lg" accept="image/jpeg,image/png,image/gif">
                </div>
                <div class="mb-4">
                    <label for="job_id" class="block text-sm font-medium text-gray-700">Link to Job (optional)</label>
                    <select name="job_id" id="job_id" class="w-full p-2 border rounded-lg">
                        <option value="">None</option>
                        <?php
                        if ($user_jobs_result && mysqli_num_rows($user_jobs_result) > 0) {
                            while ($job = mysqli_fetch_assoc($user_jobs_result)) {
                                echo "<option value='" . $job['id'] . "'>" . htmlspecialchars($job['title']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="flex justify-end mt-4">
                    <button type="button" id="close-post-modal" class="px-4 py-2 text-gray-700 mr-2">Cancel</button>
                    <button type="submit" name="create_post" class="px-4 py-2 bg-primary text-white rounded-button">Post</button>
                </div>
            </form>
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

        // Create Post Modal
        const createPostBtn = document.getElementById('create-post-btn');
        const postModal = document.getElementById('post-modal');
        const closePostModal = document.getElementById('close-post-modal');
        createPostBtn.addEventListener('click', () => {
            postModal.style.display = 'flex';
        });
        closePostModal.addEventListener('click', () => {
            postModal.style.display = 'none';
        });
        window.addEventListener('click', (e) => {
            if (e.target === postModal) {
                postModal.style.display = 'none';
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

        // Connect Button
        document.querySelectorAll('.connect-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.dataset.userId;
                fetch('connect.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'connected_user_id=' + userId
                }).then(() => {
                    this.textContent = 'Pending';
                    this.disabled = true;
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