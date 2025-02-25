<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['token']) {
        die("Invalid CSRF token");
    }

    $user_id = $_SESSION['user_id'];
    $content = mysqli_real_escape_string($conn, trim($_POST['content']));
    $job_id = !empty($_POST['job_id']) ? (int)$_POST['job_id'] : NULL;
    $image_url = NULL;

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Create uploads folder if it doesn’t exist
        }

        $file_name = uniqid() . '-' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;

        // Validate file type and size (e.g., max 5MB, images only)
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['image']['tmp_name']);
        if (in_array($file_type, $allowed_types) && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_url = $target_file;
            } else {
                die("Error uploading image.");
            }
        } else {
            die("Invalid file type or size (max 5MB, JPG/PNG/GIF only).");
        }
    }

    // Insert post into database
    $query = "INSERT INTO posts (user_id, content, image_url, job_id) VALUES ('$user_id', '$content', " . ($image_url ? "'$image_url'" : "NULL") . ", " . ($job_id ? "'$job_id'" : "NULL") . ")";
    if (mysqli_query($conn, $query)) {
        header("Location: dashboard.php");
    } else {
        echo "Error creating post: " . mysqli_error($conn);
    }

    mysqli_close($conn);
}
?>