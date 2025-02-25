<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's profile details
$query = "SELECT full_name, profile_picture FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Handle connection request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accept'])) {
        $connection_id = (int)$_POST['connection_id'];
        $update_query = "UPDATE connections SET status = 'accepted' WHERE id = $connection_id AND connected_user_id = $user_id";
        mysqli_query($conn, $update_query);
    } elseif (isset($_POST['decline'])) {
        $connection_id = (int)$_POST['connection_id'];
        $delete_query = "DELETE FROM connections WHERE id = $connection_id AND connected_user_id = $user_id";
        mysqli_query($conn, $delete_query);
    } elseif (isset($_POST['connect'])) {
        $connect_user_id = (int)$_POST['connect_user_id'];
        $insert_query = "INSERT INTO connections (user_id, connected_user_id, status) VALUES ($user_id, $connect_user_id, 'pending')";
        mysqli_query($conn, $insert_query);
    }
    header("Location: network.php");
    exit;
}

// Fetch connection requests (pending incoming)
$requests_query = "
    SELECT c.id, c.user_id, u.username, u.full_name, u.profile_picture, u.job_title 
    FROM connections c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.connected_user_id = $user_id AND c.status = 'pending'";
$requests_result = mysqli_query($conn, $requests_query);
$requests_count = mysqli_num_rows($requests_result);

// Fetch my connections (accepted)
$connections_query = "
    SELECT u.id, u.username, u.full_name, u.profile_picture, u.job_title 
    FROM connections c 
    JOIN users u ON (c.user_id = u.id AND c.connected_user_id = $user_id) OR (c.connected_user_id = u.id AND c.user_id = $user_id) 
    WHERE c.status = 'accepted'";
$connections_result = mysqli_query($conn, $connections_query);
$connections_count = mysqli_num_rows($connections_result);

// Fetch people you may know (suggestions, excluding existing connections and self)
$suggestions_query = "
    SELECT u.id, u.username, u.full_name, u.profile_picture, u.job_title 
    FROM users u 
    WHERE u.id != $user_id 
    AND u.id NOT IN (
        SELECT user_id FROM connections WHERE connected_user_id = $user_id 
        UNION 
        SELECT connected_user_id FROM connections WHERE user_id = $user_id
    ) 
    LIMIT 6";
$suggestions_result = mysqli_query($conn, $suggestions_query);

// Fetch notifications (for navbar)
$notif_query = "SELECT id, message, created_at FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$notif_result = mysqli_query($conn, $notif_query);
$notif_count = mysqli_num_rows($notif_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network | MeetMate</title>
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
                    <a href="#" class="flex items-center space-x-3 px-4 py-2.5 text-primary bg-blue-50 rounded-lg">
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
                <!-- Connection Requests -->
                <div class="bg-white rounded-lg shadow-sm border mb-6">
                    <div class="p-6 border-b">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-neutral-text">Connection Requests (<?php echo $requests_count; ?>)</h3>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                        <?php if (mysqli_num_rows($requests_result) > 0): ?>
                            <?php while ($request = mysqli_fetch_assoc($requests_result)): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo $request['profile_picture'] ?: 'https://public.readdy.ai/ai/img_res/f29ebe1ae31fc559ea7f136086965fa9.jpg'; ?>" 
                                             class="w-12 h-12 rounded-full object-cover">
                                        <div>
                                            <h4 class="text-base font-medium text-neutral-text"><?php echo htmlspecialchars($request['full_name'] ?: $request['username']); ?></h4>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($request['job_title'] ?: 'No job title'); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <form action="" method="POST">
                                            <input type="hidden" name="connection_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="accept" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">
                                                <i class="ri-check-line mr-2"></i>Accept
                                            </button>
                                        </form>
                                        <form action="" method="POST">
                                            <input type="hidden" name="connection_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="decline" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-button hover:bg-gray-50">
                                                <i class="ri-close-line mr-2"></i>Decline
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-gray-600">No pending connection requests.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Connections -->
                <div class="bg-white rounded-lg shadow-sm border mb-6">
                    <div class="p-6 border-b">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-neutral-text">My Connections (<?php echo $connections_count; ?>)</h3>
                            <a href="#" class="text-primary text-sm">View all</a>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                        <?php if (mysqli_num_rows($connections_result) > 0): ?>
                            <?php while ($connection = mysqli_fetch_assoc($connections_result)): ?>
                                <div class="flex items-center">
                                    <img src="<?php echo $connection['profile_picture'] ?: 'https://public.readdy.ai/ai/img_res/f29ebe1ae31fc559ea7f136086965fa9.jpg'; ?>" 
                                         class="w-12 h-12 rounded-full object-cover">
                                    <div class="ml-4 flex-1">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="text-base font-medium text-neutral-text"><?php echo htmlspecialchars($connection['full_name'] ?: $connection['username']); ?></h4>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($connection['job_title'] ?: 'No job title'); ?></p>
                                            </div>
                                            <a href="messages.php?user=<?php echo $connection['id']; ?>" class="px-4 py-2 border border-primary text-primary rounded-button hover:bg-primary/5">
                                                <i class="ri-message-3-line mr-2"></i>Message
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-gray-600">You don’t have any connections yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- People You May Know -->
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="p-6 border-b">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-neutral-text">People You May Know</h3>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                        <?php if (mysqli_num_rows($suggestions_result) > 0): ?>
                            <?php while ($suggestion = mysqli_fetch_assoc($suggestions_result)): ?>
                                <div class="flex items-center">
                                    <img src="<?php echo $suggestion['profile_picture'] ?: 'https://public.readdy.ai/ai/img_res/f29ebe1ae31fc559ea7f136086965fa9.jpg'; ?>" 
                                         class="w-12 h-12 rounded-full object-cover">
                                    <div class="ml-4 flex-1">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="text-base font-medium text-neutral-text"><?php echo htmlspecialchars($suggestion['full_name'] ?: $suggestion['username']); ?></h4>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($suggestion['job_title'] ?: 'No job title'); ?></p>
                                            </div>
                                            <form action="" method="POST">
                                                <input type="hidden" name="connect_user_id" value="<?php echo $suggestion['id']; ?>">
                                                <button type="submit" name="connect" class="px-4 py-2 border border-primary text-primary rounded-button hover:bg-primary/5">
                                                    <i class="ri-user-add-line mr-2"></i>Connect
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-gray-600">No suggestions available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
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
    </script>
</body>
</html>