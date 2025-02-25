<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$job_id) {
    header("Location: dashboard.php");
    exit;
}

// Fetch job details (assuming this links to a company)
$job_query = "SELECT title, company_name, company_logo FROM jobs WHERE id = $job_id";
$job_result = mysqli_query($conn, $job_query);
$job = mysqli_fetch_assoc($job_result);

if (!$job) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['company_name']); ?> | MeetMate</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <div class="max-w-7xl mx-auto p-4">
        <h1 class="text-2xl font-semibold"><?php echo htmlspecialchars($job['company_name']); ?></h1>
        <p>Job: <?php echo htmlspecialchars($job['title']); ?></p>
        <?php if ($job['company_logo']): ?>
            <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" alt="Company Logo" class="w-24 h-24 object-cover">
        <?php endif; ?>
        <a href="dashboard.php" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded">Back to Dashboard</a>
    </div>
</body>
</html>
