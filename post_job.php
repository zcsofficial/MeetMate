<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT role FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

if (!in_array($user['role'], ['admin', 'recruiter'])) {
    header("Location: jobs.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['token']) {
        die("Invalid CSRF token");
    }

    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $company_name = mysqli_real_escape_string($conn, trim($_POST['company_name']));
    $location = mysqli_real_escape_string($conn, trim($_POST['location']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $skills_required = mysqli_real_escape_string($conn, trim($_POST['skills_required']));
    $job_type = mysqli_real_escape_string($conn, trim($_POST['job_type']));
    $work_mode = mysqli_real_escape_string($conn, trim($_POST['work_mode']));
    $salary_range = mysqli_real_escape_string($conn, trim($_POST['salary_range']));
    $company_logo = NULL;

    // Handle image upload
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Create uploads folder if it doesn’t exist
        }

        $file_name = uniqid() . '-' . basename($_FILES['company_logo']['name']);
        $target_file = $upload_dir . $file_name;

        // Validate file type and size (max 5MB, images only)
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['company_logo']['tmp_name']);
        if (in_array($file_type, $allowed_types) && $_FILES['company_logo']['size'] <= 5 * 1024 * 1024) {
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $target_file)) {
                $company_logo = $target_file;
            } else {
                die("Error uploading company logo.");
            }
        } else {
            die("Invalid file type or size (max 5MB, JPG/PNG/GIF only).");
        }
    }

    $query = "INSERT INTO jobs (posted_by, title, company_name, company_logo, location, description, skills_required, job_type, work_mode, salary_range, posted_at) 
              VALUES ('$user_id', '$title', '$company_name', " . ($company_logo ? "'$company_logo'" : "NULL") . ", '$location', '$description', '$skills_required', '$job_type', '$work_mode', '$salary_range', NOW())";
    if (mysqli_query($conn, $query)) {
        header("Location: jobs.php");
    } else {
        echo "Error posting job: " . mysqli_error($conn);
    }

    mysqli_close($conn);
}
?>