<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT full_name, profile_picture FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Fetch posts
$posts_query = "SELECT p.*, u.username, u.profile_picture FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 5";
$posts_result = mysqli_query($conn, $posts_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeetMate Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
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
        .suggestions { position: absolute; top: 100%; left: 0; width: 100%; background: white; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 999; max-height: 300px; overflow-y: auto; }
        .suggestion-item { padding: 8px 16px; cursor: pointer; }
        .suggestion-item:hover { background: #f3f4f6; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background-color: white; padding: 20px; width: 90%; max-width: 500px; border-radius: 8px; }
        .close-btn { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; }
        .chart-container { width: 100%; height: 240px; }
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
                    <span class="text-2xl font-['Pacifico'] text-primary">MeetMate</span>
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
                        <i class="ri-notification-3-line text-gray-600"></i>
                        <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
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
                    <a href="#" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
                        <i class="ri-book-read-line"></i>
                        <span class="text-sm font-medium">Resources</span>
                    </a>
                </div>
                <div class="mt-8">
                    <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Saved Items</h3>
                    <div class="mt-4 space-y-2">
                        <a href="saved_job.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
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
                <div class="flex items-center justify-between mb-8">
                    <h1 class="text-2xl font-semibold text-neutral-text">Welcome back, <?php echo $user['full_name']; ?>!</h1>
                    <div class="flex space-x-4">
                        <button id="create-post-btn" class="flex items-center px-4 py-2 bg-primary text-white rounded-button">
                            <i class="ri-add-line mr-2"></i>
                            <span>Create Post</span>
                        </button>
                        <button class="flex items-center px-4 py-2 border border-gray-300 rounded-button text-gray-700 hover:bg-gray-50">
                            <i class="ri-calendar-line mr-2"></i>
                            <span>Schedule Meeting</span>
                        </button>
                    </div>
                </div>

                
                <!-- Posts -->
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="p-6 border-b">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-neutral-text">Recent Posts</h3>
                            <a href="posts.php" class="text-primary text-sm">View all</a>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                        <?php if (mysqli_num_rows($posts_result) > 0): ?>
                            <?php while ($post = mysqli_fetch_assoc($posts_result)): ?>
                                <div class="border-b pb-6">
                                    <div class="flex items-start">
                                        <img src="<?php echo $post['profile_picture'] ?: 'https://public.readdy.ai/ai/img_res/aad2492bc2831d0dfd7402f705386162.jpg'; ?>" 
                                             class="w-12 h-12 rounded-full object-cover">
                                        <div class="ml-4 flex-1">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <h4 class="text-base font-medium text-neutral-text"><?php echo htmlspecialchars($post['username']); ?></h4>
                                                    <p class="text-sm text-gray-500"><?php echo date('M d, Y • h:i A', strtotime($post['created_at'])); ?></p>
                                                </div>
                                                <button class="text-gray-400 hover:text-gray-500">
                                                    <i class="ri-more-2-fill"></i>
                                                </button>
                                            </div>
                                            <p class="mt-2 text-gray-700"><?php echo htmlspecialchars($post['content']); ?></p>
                                            <?php if ($post['image_url']): ?>
                                                <img src="<?php echo $post['image_url']; ?>" alt="Post image" class="mt-3 rounded-lg w-full h-48 object-cover">
                                            <?php endif; ?>
                                            <?php if ($post['job_id']): ?>
                                                <div class="mt-3 p-4 bg-gray-50 rounded-lg">
                                                    <h5 class="font-medium text-neutral-text">Linked Job ID: <?php echo $post['job_id']; ?></h5>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex items-center justify-between mt-4">
                                                <div class="flex items-center space-x-6">
                                                    <button class="flex items-center text-gray-500 hover:text-primary">
                                                        <i class="ri-thumb-up-line mr-2"></i>
                                                        <span><?php echo $post['likes']; ?></span>
                                                    </button>
                                                    <button class="flex items-center text-gray-500 hover:text-primary">
                                                        <i class="ri-chat-1-line mr-2"></i>
                                                        <span><?php echo $post['comments']; ?></span>
                                                    </button>
                                                    <button class="flex items-center text-gray-500 hover:text-primary">
                                                        <i class="ri-share-line mr-2"></i>
                                                        <span>Share</span>
                                                    </button>
                                                </div>
                                                <button class="text-gray-500 hover:text-primary">
                                                    <i class="ri-bookmark-line"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-gray-600">No posts yet. Be the first to share something!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Post Modal -->
    <div id="post-modal" class="modal">
        <div class="modal-content relative">
            <span class="close-btn" id="close-post">×</span>
            <h2 class="text-xl font-bold mb-4 text-neutral-text">Create a Post</h2>
            <form action="create_post.php" method="POST" enctype="multipart/form-data">
                <textarea name="content" placeholder="What’s on your mind?" 
                          class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 mb-4" 
                          rows="4" required></textarea>
                <input type="file" name="image" accept="image/*" 
                       class="w-full p-3 border rounded-lg mb-4">
                <input type="number" name="job_id" placeholder="Link to Job ID (optional)" 
                       class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 mb-4">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['token']; ?>">
                <button type="submit" class="w-full py-2 bg-primary text-white rounded-button hover:bg-primary/90">
                    Post
                </button>
            </form>
        </div>
    </div>

    <script>
        // Profile dropdown
        const profileBtn = document.getElementById('profileDropdown');
        const profileDropdown = document.getElementById('profile-dropdown');
        profileBtn.addEventListener('click', () => {
            profileDropdown.classList.toggle('hidden');
        });

        // Post modal
        const createPostBtn = document.getElementById('create-post-btn');
        const postModal = document.getElementById('post-modal');
        const closePost = document.getElementById('close-post');
        createPostBtn.addEventListener('click', () => {
            postModal.style.display = 'flex';
        });
        closePost.addEventListener('click', () => {
            postModal.style.display = 'none';
        });
        window.addEventListener('click', (e) => {
            if (e.target === postModal) {
                postModal.style.display = 'none';
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