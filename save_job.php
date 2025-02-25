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

    $query = "INSERT INTO saved_items (user_id, item_type, item_id) VALUES ('$user_id', 'job', '$job_id') 
              ON DUPLICATE KEY UPDATE saved_at = NOW()";
    if (mysqli_query($conn, $query)) {
        header("Location: jobs.php");
    } else {
        echo "Error saving job: " . mysqli_error($conn);
    }

    mysqli_close($conn);
}
?>