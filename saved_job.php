<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT full_name, profile_picture, role FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Fetch saved jobs
$saved_jobs_query = "
    SELECT j.*, u.username 
    FROM saved_items si 
    JOIN jobs j ON si.item_id = j.id 
    JOIN users u ON j.posted_by = u.id 
    WHERE si.user_id = $user_id AND si.item_type = 'job' 
    ORDER BY si.saved_at DESC";
$saved_jobs_result = mysqli_query($conn, $saved_jobs_query);
$saved_job_count = $saved_jobs_result ? mysqli_num_rows($saved_jobs_result) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Jobs | MeetMate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                    <a href="#" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
                        <i class="ri-user-2-line"></i>
                        <span class="text-sm font-medium">Network</span>
                    </a>
                    <a href="#" class="flex items-center space-x-3 px-4 py-2.5 text-gray-700 hover:bg-gray-50 rounded-lg">
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
                        <a href="#" class="flex items-center space-x-3 px-4 py-2.5 text-primary bg-blue-50 rounded-lg">
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
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-semibold text-neutral-text">Saved Jobs</h1>
                        <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-sm"><?php echo $saved_job_count; ?> saved</span>
                    </div>
                </header>

                <!-- Saved Jobs List -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <?php if ($saved_jobs_result && mysqli_num_rows($saved_jobs_result) > 0): ?>
                        <?php while ($job = mysqli_fetch_assoc($saved_jobs_result)): ?>
                            <?php
                            $applied_query = "SELECT status FROM job_applications WHERE user_id = $user_id AND job_id = {$job['id']}";
                            $applied_result = mysqli_query($conn, $applied_query);
                            $application = mysqli_fetch_assoc($applied_result);
                            $is_applied = $application !== null;
                            ?>
                            <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-4">
                                    <img src="<?php echo $job['company_logo'] ?: 'https://public.readdy.ai/ai/img_res/26e8668d63c2bd57130f166edf3fd0d2.jpg'; ?>" 
                                         class="w-12 h-12 rounded-lg" alt="Company logo">
                                    <form action="remove_saved_job.php" method="POST">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="w-8 h-8 flex items-center justify-center text-primary hover:text-red-500">
                                            <i class="ri-bookmark-fill"></i>
                                        </button>
                                    </form>
                                </div>
                                <h3 class="text-lg font-semibold text-neutral-text mb-1"><?php echo htmlspecialchars($job['title']); ?></h3>
                                <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                <div class="flex items-center gap-4 text-sm text-gray-500 mb-6">
                                    <div class="flex items-center gap-1">
                                        <i class="ri-map-pin-line"></i> <?php echo htmlspecialchars($job['location']); ?>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <i class="ri-time-line"></i> <?php echo htmlspecialchars($job['job_type']); ?>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Posted <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                                    <div class="flex items-center gap-2">
                                        <?php if ($is_applied): ?>
                                            <span class="text-sm font-medium text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full"><?php echo ucfirst($application['status']); ?></span>
                                            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 !rounded-button whitespace-nowrap">View Status</button>
                                        <?php else: ?>
                                            <form action="apply_job.php" method="POST">
                                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 !rounded-button whitespace-nowrap">Apply Now</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-gray-600 col-span-3">You haven’t saved any jobs yet.</p>
                    <?php endif; ?>
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