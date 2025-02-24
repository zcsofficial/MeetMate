<?php
require 'config.php';
header('Content-Type: application/json');

$query = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$results = [
    'users' => [],
    'jobs' => []
];

if (strlen($query) > 2) {
    // Search users
    $user_query = "SELECT id, username, full_name, job_title, profile_picture FROM users WHERE username LIKE '%$query%' OR full_name LIKE '%$query%' OR job_title LIKE '%$query%' LIMIT 5";
    $user_result = mysqli_query($conn, $user_query);
    while ($user = mysqli_fetch_assoc($user_result)) {
        $results['users'][] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'job_title' => $user['job_title'],
            'profile_picture' => $user['profile_picture']
        ];
    }

    // Search jobs
    $job_query = "SELECT id, title, company_name, location, job_type, work_mode FROM jobs WHERE title LIKE '%$query%' OR company_name LIKE '%$query%' OR location LIKE '%$query%' LIMIT 5";
    $job_result = mysqli_query($conn, $job_query);
    while ($job = mysqli_fetch_assoc($job_result)) {
        $results['jobs'][] = [
            'id' => $job['id'],
            'title' => $job['title'],
            'company_name' => $job['company_name'],
            'location' => $job['location'],
            'job_type' => $job['job_type'],
            'work_mode' => $job['work_mode']
        ];
    }
}

echo json_encode($results);
mysqli_close($conn);
?>