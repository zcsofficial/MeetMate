<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $job_id = (int)$_POST['job_id'];

    $query = "INSERT INTO job_applications (user_id, job_id, status) VALUES ('$user_id', '$job_id', 'applied') 
              ON DUPLICATE KEY UPDATE updated_at = NOW()";
    if (mysqli_query($conn, $query)) {
        header("Location: jobs.php");
    } else {
        echo "Error applying to job: " . mysqli_error($conn);
    }

    mysqli_close($conn);
}
?>