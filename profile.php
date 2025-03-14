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
$query = "SELECT username, full_name, email, profile_picture, job_title, location, phone, linkedin_url, about_me, role FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);
$display_name = $user['full_name'] ?: $user['username'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $job_title = !empty($_POST['job_title']) ? mysqli_real_escape_string($conn, $_POST['job_title']) : NULL;
    $location = !empty($_POST['location']) ? mysqli_real_escape_string($conn, $_POST['location']) : NULL;
    $phone = !empty($_POST['phone']) ? mysqli_real_escape_string($conn, $_POST['phone']) : NULL;
    $linkedin_url = !empty($_POST['linkedin_url']) ? mysqli_real_escape_string($conn, $_POST['linkedin_url']) : NULL;
    $about_me = !empty($_POST['about_me']) ? mysqli_real_escape_string($conn, $_POST['about_me']) : NULL;

    $profile_picture = $user['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $image_name = uniqid() . '-' . basename($_FILES['profile_picture']['name']);
        $image_path = $upload_dir . $image_name;
        $image_type = exif_imagetype($_FILES['profile_picture']['tmp_name']);
        if (in_array($image_type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF]) && move_uploaded_file($_FILES['profile_picture']['tmp_name'], $image_path)) {
            $profile_picture = $image_path;
        }
    }

    $update_query = "UPDATE users SET full_name = '$full_name', job_title = " . ($job_title ? "'$job_title'" : "NULL") . ", location = " . ($location ? "'$location'" : "NULL") . ", phone = " . ($phone ? "'$phone'" : "NULL") . ", linkedin_url = " . ($linkedin_url ? "'$linkedin_url'" : "NULL") . ", about_me = " . ($about_me ? "'$about_me'" : "NULL") . ", profile_picture = " . ($profile_picture ? "'$profile_picture'" : "NULL") . ", last_updated = NOW() WHERE id = $user_id";
    mysqli_query($conn, $update_query);
    header("Location: profile.php");
    exit;
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
    <title>Profile | MeetMate</title>
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
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
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
                            <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Settings</a>
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
                    <h1 class="text-2xl font-semibold text-neutral-text">Profile</h1>
                    <a href="dashboard.php" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Back to Dashboard</a>
                </header>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-start space-x-6">
                        <div class="flex-shrink-0">
                            <?php if ($user['profile_picture']): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="w-24 h-24 rounded-full object-cover" alt="Profile Picture">
                            <?php else: ?>
                                <div class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center text-4xl text-gray-600"><?php echo strtoupper(substr($display_name, 0, 1)); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1">
                            <h2 class="text-xl font-semibold text-neutral-text"><?php echo htmlspecialchars($display_name); ?></h2>
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($user['job_title'] ?: 'No job title'); ?> • <?php echo htmlspecialchars($user['location'] ?: 'No location'); ?></p>
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($user['email']); ?></p>
                            <?php if ($user['linkedin_url']): ?>
                                <a href="<?php echo htmlspecialchars($user['linkedin_url']); ?>" class="text-sm text-primary hover:underline mt-1 block">LinkedIn Profile</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-neutral-text">About Me</h3>
                        <p class="text-sm text-gray-600 mt-2"><?php echo htmlspecialchars($user['about_me'] ?: 'No about me provided.'); ?></p>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-neutral-text">Edit Profile</h3>
                        <form method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                            <div>
                                <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                            </div>
                            <div>
                                <label for="profile_picture" class="block text-sm font-medium text-gray-700">Profile Picture</label>
                                <input type="file" name="profile_picture" id="profile_picture" class="w-full p-2 border rounded-lg" accept="image/jpeg,image/png,image/gif">
                            </div>
                            <div>
                                <label for="job_title" class="block text-sm font-medium text-gray-700">Job Title</label>
                                <input type="text" name="job_title" id="job_title" value="<?php echo htmlspecialchars($user['job_title'] ?: ''); ?>" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                                <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($user['location'] ?: ''); ?>" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?: ''); ?>" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label for="linkedin_url" class="block text-sm font-medium text-gray-700">LinkedIn URL</label>
                                <input type="url" name="linkedin_url" id="linkedin_url" value="<?php echo htmlspecialchars($user['linkedin_url'] ?: ''); ?>" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label for="about_me" class="block text-sm font-medium text-gray-700">About Me</label>
                                <textarea name="about_me" id="about_me" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" rows="4"><?php echo htmlspecialchars($user['about_me'] ?: ''); ?></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" name="update_profile" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Save Changes</button>
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