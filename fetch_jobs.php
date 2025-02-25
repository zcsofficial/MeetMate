<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$type = isset($_GET['type']) ? $_GET['type'] : 'all';

if ($type === 'saved') {
    $query = "SELECT j.*, u.username, IF(ja.id IS NOT NULL, 1, 0) as has_applied, 1 as is_saved 
              FROM jobs j 
              JOIN users u ON j.posted_by = u.id 
              JOIN saved_items si ON si.item_id = j.id AND si.item_type = 'job' 
              LEFT JOIN job_applications ja ON ja.job_id = j.id AND ja.user_id = ? 
              WHERE si.user_id = ? 
              ORDER BY j.posted_at DESC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
} else {
    $query = "SELECT j.*, u.username, IF(ja.id IS NOT NULL, 1, 0) as has_applied, IF(si.id IS NOT NULL, 1, 0) as is_saved 
              FROM jobs j 
              JOIN users u ON j.posted_by = u.id 
              LEFT JOIN job_applications ja ON ja.job_id = j.id AND ja.user_id = ? 
              LEFT JOIN saved_items si ON si.item_id = j.id AND si.item_type = 'job' AND si.user_id = ? 
              ORDER BY j.posted_at DESC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$jobs = [];
while ($job = mysqli_fetch_assoc($result)) {
    $jobs[] = $job;
}

echo json_encode($jobs);
mysqli_close($conn);
?>