<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch logged-in user's details including role
$query = "SELECT username, full_name, profile_picture, role FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);
$display_name = $user['full_name'] ?: $user['username'];
$user_role = $user['role'];

// Handle job application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_job'])) {
    $job_id = (int)$_POST['job_id'];
    $check_query = "SELECT * FROM job_applications WHERE user_id = $user_id AND job_id = $job_id";
    $check_result = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_result) == 0) {
        $apply_query = "INSERT INTO job_applications (user_id, job_id, status, applied_at) VALUES ($user_id, $job_id, 'applied', NOW())";
        mysqli_query($conn, $apply_query);
        $notif_query = "INSERT INTO notifications (user_id, type, related_id, message, created_at) VALUES ((SELECT posted_by FROM jobs WHERE id = $job_id), 'job_match', $job_id, '$display_name has applied to your job posting', NOW())";
        mysqli_query($conn, $notif_query);
    }
    header("Location: jobs.php");
    exit;
}

// Handle job posting (recruiter/admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_job']) && in_array($user_role, ['recruiter', 'admin'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $skills_required = mysqli_real_escape_string($conn, $_POST['skills_required']);
    $job_type = mysqli_real_escape_string($conn, $_POST['job_type']);
    $work_mode = mysqli_real_escape_string($conn, $_POST['work_mode']);
    $salary_range = !empty($_POST['salary_range']) ? mysqli_real_escape_string($conn, $_POST['salary_range']) : NULL;
    
    $company_logo = NULL;
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $logo_name = uniqid() . '-' . basename($_FILES['company_logo']['name']);
        $logo_path = $upload_dir . $logo_name;
        $image_type = exif_imagetype($_FILES['company_logo']['tmp_name']);
        if (in_array($image_type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF]) && move_uploaded_file($_FILES['company_logo']['tmp_name'], $logo_path)) {
            $company_logo = $logo_path;
        }
    }

    $insert_query = "INSERT INTO jobs (posted_by, title, company_name, company_logo, location, description, skills_required, job_type, work_mode, salary_range, posted_at) VALUES ($user_id, '$title', '$company_name', " . ($company_logo ? "'$company_logo'" : "NULL") . ", '$location', '$description', '$skills_required', '$job_type', '$work_mode', " . ($salary_range ? "'$salary_range'" : "NULL") . ", NOW())";
    mysqli_query($conn, $insert_query);
    header("Location: jobs.php");
    exit;
}

// Fetch all jobs
$jobs_query = "SELECT j.*, u.username FROM jobs j JOIN users u ON j.posted_by = u.id ORDER BY j.posted_at DESC";
$jobs_result = mysqli_query($conn, $jobs_query);
$job_count = mysqli_num_rows($jobs_result);

// Fetch notifications
$notif_query = "SELECT id, message, created_at FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$notif_result = mysqli_query($conn, $notif_query);
$notif_count = mysqli_num_rows($notif_result);

// Fetch saved jobs count
$saved_jobs_query = "SELECT COUNT(*) as saved_count FROM saved_items WHERE user_id = $user_id AND item_type = 'job'";
$saved_jobs_result = mysqli_query($conn, $saved_jobs_query);
$saved_jobs = mysqli_fetch_assoc($saved_jobs_result)['saved_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobs | CollabConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :where([class^="ri-"])::before { content: "\f3c2"; }
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .job-modal {
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
        .job-modal-content {
            background-color: white;
            padding: 20px;
            width: 500px;
            border-radius: 8px;
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
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#6366F1'
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
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <header class="py-6 flex items-center justify-between">
            <div class="flex items-center">
                <h1 class="text-2xl font-semibold text-gray-900">Jobs</h1>
                <span class="ml-3 text-sm text-gray-500"><?php echo $job_count; ?> jobs available</span>
            </div>
            <div class="flex items-center space-x-4">
                <button class="relative w-10 h-10 flex items-center justify-center" id="notification-btn">
                    <i class="ri-notification-3-line text-gray-600 text-xl"></i>
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
                <a href="profile.php" class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                    <?php if ($user['profile_picture']): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="w-10 h-10 rounded-full object-cover" alt="Profile">
                    <?php else: ?>
                        <div class="text-gray-600 text-xl"><?php echo strtoupper(substr($display_name, 0, 1)); ?></div>
                    <?php endif; ?>
                </a>
                <?php if (in_array($user_role, ['recruiter', 'admin'])): ?>
                    <button id="post-job-btn" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90 whitespace-nowrap">
                        Post Job
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <div class="mt-6">
            <div class="relative">
                <input type="text" id="global-search" placeholder="Search for jobs, companies, or keywords" class="w-full h-12 pl-12 pr-4 text-sm rounded-lg border-none bg-white shadow-sm focus:ring-2 focus:ring-primary focus:outline-none">
                <div class="absolute left-4 top-0 h-full flex items-center">
                    <i class="ri-search-line text-gray-400 text-lg"></i>
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center space-x-4 overflow-x-auto pb-4">
            <div class="relative">
                <button class="px-4 py-2 text-sm bg-white rounded-button shadow-sm hover:bg-gray-50 flex items-center space-x-2 whitespace-nowrap">
                    <span>Location</span>
                    <i class="ri-arrow-down-s-line"></i>
                </button>
            </div>
            <div class="relative">
                <button class="px-4 py-2 text-sm bg-white rounded-button shadow-sm hover:bg-gray-50 flex items-center space-x-2 whitespace-nowrap">
                    <span>Job Type</span>
                    <i class="ri-arrow-down-s-line"></i>
                </button>
            </div>
            <div class="relative">
                <button class="px-4 py-2 text-sm bg-white rounded-button shadow-sm hover:bg-gray-50 flex items-center space-x-2 whitespace-nowrap">
                    <span>Industry</span>
                    <i class="ri-arrow-down-s-line"></i>
                </button>
            </div>
            <div class="relative">
                <button class="px-4 py-2 text-sm bg-white rounded-button shadow-sm hover:bg-gray-50 flex items-center space-x-2 whitespace-nowrap">
                    <span>Experience Level</span>
                    <i class="ri-arrow-down-s-line"></i>
                </button>
            </div>
            <button class="px-4 py-2 text-sm text-primary hover:bg-primary/5 rounded-button whitespace-nowrap">Clear All</button>
        </div>

        <div class="mt-6 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <button class="text-gray-900 font-medium">All Jobs</button>
                <button class="text-gray-500">Saved Jobs (<?php echo $saved_jobs; ?>)</button>
            </div>
            <div class="flex items-center space-x-2">
                <button class="w-8 h-8 flex items-center justify-center rounded hover:bg-gray-100">
                    <i class="ri-layout-grid-line text-gray-600"></i>
                </button>
                <button class="w-8 h-8 flex items-center justify-center rounded bg-gray-100">
                    <i class="ri-list-unordered text-gray-900"></i>
                </button>
            </div>
        </div>

        <div class="mt-6 grid gap-6">
            <?php
            if ($jobs_result && mysqli_num_rows($jobs_result) > 0) {
                while ($job = mysqli_fetch_assoc($jobs_result)) {
                    $applied_query = "SELECT * FROM job_applications WHERE user_id = $user_id AND job_id = " . $job['id'];
                    $applied_result = mysqli_query($conn, $applied_query);
                    $has_applied = mysqli_num_rows($applied_result) > 0;

                    echo "<div class='bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow'>";
                    echo "<div class='flex items-start justify-between'>";
                    echo "<div class='flex items-start space-x-4'>";
                    echo "<div class='w-12 h-12 rounded bg-gray-100 flex items-center justify-center'>";
                    echo "<img src='" . ($job['company_logo'] ?: 'https://via.placeholder.com/48') . "' class='w-12 h-12 rounded object-cover'>";
                    echo "</div>";
                    echo "<div>";
                    echo "<h3 class='font-medium text-gray-900'>" . htmlspecialchars($job['title']) . "</h3>";
                    echo "<p class='text-sm text-gray-500 mt-1'>" . htmlspecialchars($job['company_name']) . " • " . htmlspecialchars($job['location']) . "</p>";
                    echo "<div class='flex items-center space-x-4 mt-3'>";
                    echo "<span class='text-xs px-2.5 py-0.5 bg-blue-50 text-blue-600 rounded-full'>" . htmlspecialchars($job['job_type']) . "</span>";
                    echo "<span class='text-xs px-2.5 py-0.5 " . ($job['work_mode'] == 'remote' ? "bg-green-50 text-green-600" : "bg-purple-50 text-purple-600") . " rounded-full'>" . htmlspecialchars($job['work_mode']) . "</span>";
                    echo "<span class='text-xs text-gray-500'>Posted " . humanTiming(strtotime($job['posted_at'])) . " ago</span>";
                    echo "</div>";
                    echo "</div>";
                    echo "</div>";
                    echo "<div class='flex items-center space-x-2'>";
                    echo "<button class='save-btn w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100' data-job-id='" . $job['id'] . "'>";
                    echo "<i class='ri-bookmark-line text-gray-400 hover:text-gray-600'></i>";
                    echo "</button>";
                    echo "</div>";
                    echo "</div>";
                    echo "<div class='mt-4'>";
                    echo "<p class='text-sm text-gray-600 line-clamp-2'>" . htmlspecialchars($job['description']) . "</p>";
                    echo "</div>";
                    echo "<div class='mt-4 flex items-center justify-between'>";
                    echo "<div class='flex items-center space-x-4'>";
                    if ($job['salary_range']) {
                        echo "<span class='text-sm font-medium text-gray-900'>" . htmlspecialchars($job['salary_range']) . "</span>";
                    }
                    echo "<span class='text-sm text-gray-500'>" . (rand(3, 7)) . "+ years experience</span>";
                    echo "</div>";
                    echo "<form method='POST'>";
                    echo "<input type='hidden' name='job_id' value='" . $job['id'] . "'>";
                    echo "<button type='submit' name='apply_job' class='px-4 py-2 " . ($has_applied ? "bg-gray-300" : "bg-primary") . " text-white rounded-button hover:bg-primary/90 whitespace-nowrap' " . ($has_applied ? "disabled" : "") . ">" . ($has_applied ? "Applied" : "Apply Now") . "</button>";
                    echo "</form>";
                    echo "</div>";
                    echo "</div>";
                }
            } else {
                echo "<p class='text-gray-700'>No jobs available yet.</p>";
            }
            ?>
        </div>

        <div class="mt-8 flex justify-center">
            <button class="px-4 py-2 text-sm text-primary hover:bg-primary/5 rounded-button">Load More Jobs</button>
        </div>
    </div>

    <!-- Job Posting Modal (Recruiter/Admin Only) -->
    <?php if (in_array($user_role, ['recruiter', 'admin'])): ?>
    <div class="job-modal" id="job-modal">
        <div class="job-modal-content">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Post a Job</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700">Job Title</label>
                    <input type="text" name="title" id="title" class="w-full p-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name</label>
                    <input type="text" name="company_name" id="company_name" class="w-full p-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="company_logo" class="block text-sm font-medium text-gray-700">Company Logo (optional)</label>
                    <input type="file" name="company_logo" id="company_logo" class="w-full p-2 border rounded-lg" accept="image/jpeg,image/png,image/gif">
                </div>
                <div class="mb-4">
                    <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                    <input type="text" name="location" id="location" class="w-full p-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="description" class="w-full p-2 border rounded-lg" rows="4" required></textarea>
                </div>
                <div class="mb-4">
                    <label for="skills_required" class="block text-sm font-medium text-gray-700">Skills Required (comma-separated)</label>
                    <input type="text" name="skills_required" id="skills_required" class="w-full p-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="job_type" class="block text-sm font-medium text-gray-700">Job Type</label>
                    <select name="job_type" id="job_type" class="w-full p-2 border rounded-lg" required>
                        <option value="full-time">Full-time</option>
                        <option value="part-time">Part-time</option>
                        <option value="freelance">Freelance</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="work_mode" class="block text-sm font-medium text-gray-700">Work Mode</label>
                    <select name="work_mode" id="work_mode" class="w-full p-2 border rounded-lg" required>
                        <option value="remote">Remote</option>
                        <option value="hybrid">Hybrid</option>
                        <option value="on-site">On-site</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="salary_range" class="block text-sm font-medium text-gray-700">Salary Range (optional)</label>
                    <input type="text" name="salary_range" id="salary_range" class="w-full p-2 border rounded-lg" placeholder="e.g., $120k-$160k">
                </div>
                <div class="flex justify-end mt-4">
                    <button type="button" id="close-job-modal" class="px-4 py-2 text-gray-700 mr-2">Cancel</button>
                    <button type="submit" name="post_job" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Post Job</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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

        // Job Posting Modal
        <?php if (in_array($user_role, ['recruiter', 'admin'])): ?>
        const postJobBtn = document.getElementById('post-job-btn');
        const jobModal = document.getElementById('job-modal');
        const closeJobModal = document.getElementById('close-job-modal');
        postJobBtn.addEventListener('click', () => {
            jobModal.style.display = 'flex';
        });
        closeJobModal.addEventListener('click', () => {
            jobModal.style.display = 'none';
        });
        window.addEventListener('click', (e) => {
            if (e.target === jobModal) {
                jobModal.style.display = 'none';
            }
        });
        <?php endif; ?>

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

        // Bookmark Buttons
        const bookmarkButtons = document.querySelectorAll('.save-btn');
        bookmarkButtons.forEach(button => {
            button.addEventListener('click', function() {
                const jobId = this.dataset.jobId;
                fetch('save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'item_type=job&item_id=' + jobId
                }).then(() => {
                    const icon = this.querySelector('i');
                    if (icon.classList.contains('ri-bookmark-line')) {
                        icon.classList.replace('ri-bookmark-line', 'ri-bookmark-fill');
                        icon.classList.add('text-primary');
                    } else {
                        icon.classList.replace('ri-bookmark-fill', 'ri-bookmark-line');
                        icon.classList.remove('text-primary');
                    }
                });
            });
        });

        // Tabs
        const tabs = document.querySelectorAll('button');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                if (this.textContent.includes('All Jobs') || this.textContent.includes('Saved Jobs')) {
                    tabs.forEach(t => t.classList.remove('text-gray-900', 'font-medium'));
                    tabs.forEach(t => t.classList.add('text-gray-500'));
                    this.classList.remove('text-gray-500');
                    this.classList.add('text-gray-900', 'font-medium');
                }
            });
        });

        // Layout Buttons
        const layoutButtons = document.querySelectorAll('[class*="ri-layout"], [class*="ri-list"]');
        layoutButtons.forEach(button => {
            button.addEventListener('click', function() {
                layoutButtons.forEach(b => {
                    b.parentElement.classList.remove('bg-gray-100');
                    b.classList.remove('text-gray-900');
                    b.classList.add('text-gray-600');
                });
                this.parentElement.classList.add('bg-gray-100');
                this.classList.remove('text-gray-600');
                this.classList.add('text-gray-900');
            });
        });
    });

    // Human-readable time difference
    function humanTiming(time) {
        const now = new Date();
        const then = new Date(time * 1000);
        const seconds = Math.floor((now - then) / 1000);
        const intervals = [
            { label: 'year', seconds: 31536000 },
            { label: 'month', seconds: 2592000 },
            { label: 'week', seconds: 604800 },
            { label: 'day', seconds: 86400 },
            { label: 'hour', seconds: 3600 },
            { label: 'minute', seconds: 60 },
            { label: 'second', seconds: 1 }
        ];
        for (const interval of intervals) {
            const count = Math.floor(seconds / interval.seconds);
            if (count >= 1) {
                return count + ' ' + interval.label + (count > 1 ? 's' : '');
            }
        }
        return 'just now';
    }
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>