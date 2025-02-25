<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Fetch job details
$query = "SELECT j.*, u.username FROM jobs j JOIN users u ON j.posted_by = u.id WHERE j.id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $job_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$job = mysqli_fetch_assoc($result);

if (!$job) {
    header("Location: jobs.php");
    exit;
}

// Handle job application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_job'])) {
    $check_query = "SELECT * FROM job_applications WHERE user_id = ? AND job_id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $job_id);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($check_result) == 0) {
        $apply_query = "INSERT INTO job_applications (user_id, job_id, status, applied_at) VALUES (?, ?, 'applied', NOW())";
        $stmt = mysqli_prepare($conn, $apply_query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $job_id);
        mysqli_stmt_execute($stmt);

        $notif_query = "INSERT INTO notifications (user_id, type, related_id, message, created_at) VALUES (?, 'job_match', ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $notif_query);
        $message = "{$_SESSION['username']} has applied to your job posting";
        mysqli_stmt_bind_param($stmt, "iis", $job['posted_by'], $job_id, $message);
        mysqli_stmt_execute($stmt);
    }
    header("Location: show_job.php?job_id=$job_id");
    exit;
}

$applied_query = "SELECT * FROM job_applications WHERE user_id = ? AND job_id = ?";
$stmt = mysqli_prepare($conn, $applied_query);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $job_id);
mysqli_stmt_execute($stmt);
$applied_result = mysqli_stmt_get_result($stmt);
$has_applied = mysqli_num_rows($applied_result) > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> | MeetMate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: '#4F46E5', secondary: '#6366F1' },
                    borderRadius: { 'button': '8px' }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="jobs.php" class="text-primary hover:underline mb-4 inline-block"><i class="ri-arrow-left-line mr-2"></i>Back to Jobs</a>
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-start space-x-4">
                <img src="<?php echo htmlspecialchars($job['company_logo'] ?: 'https://via.placeholder.com/64'); ?>" class="w-16 h-16 rounded object-cover">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars($job['title']); ?></h1>
                    <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($job['company_name']); ?> • <?php echo htmlspecialchars($job['location']); ?></p>
                    <p class="text-sm text-gray-500 mt-1">Posted by <?php echo htmlspecialchars($job['username']); ?> • <?php echo humanTiming(strtotime($job['posted_at'])); ?> ago</p>
                </div>
            </div>
            <div class="mt-6">
                <h2 class="text-lg font-medium text-gray-900">Job Details</h2>
                <p class="text-gray-700 mt-2"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
            </div>
            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Job Type</p>
                    <p class="text-gray-900"><?php echo htmlspecialchars($job['job_type']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Work Mode</p>
                    <p class="text-gray-900"><?php echo htmlspecialchars($job['work_mode']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Skills Required</p>
                    <p class="text-gray-900"><?php echo htmlspecialchars($job['skills_required']); ?></p>
                </div>
                <?php if ($job['salary_range']): ?>
                <div>
                    <p class="text-sm text-gray-500">Salary Range</p>
                    <p class="text-gray-900"><?php echo htmlspecialchars($job['salary_range']); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <div class="mt-6 flex justify-end">
                <form method="POST">
                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                    <button type="submit" name="apply_job" class="px-6 py-2 <?php echo $has_applied ? 'bg-gray-300' : 'bg-primary'; ?> text-white rounded-button hover:bg-primary/90" <?php echo $has_applied ? 'disabled' : ''; ?>>
                        <?php echo $has_applied ? 'Applied' : 'Apply Now'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
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
            if (count >= 1) return count + ' ' + interval.label + (count > 1 ? 's' : '');
        }
        return 'just now';
    }
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>