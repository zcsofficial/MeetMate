<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

$user_id = $_SESSION['user_id'];
$item_type = mysqli_real_escape_string($conn, $_POST['item_type']);
$item_id = (int)$_POST['item_id'];

// Validate item_type
if (!in_array($item_type, ['job', 'post'])) {
    http_response_code(400);
    echo "Invalid item type";
    exit;
}

// Check if item is already saved
$check_query = "SELECT * FROM saved_items WHERE user_id = $user_id AND item_type = '$item_type' AND item_id = $item_id";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) == 0) {
    // Save item
    $insert_query = "INSERT INTO saved_items (user_id, item_type, item_id, saved_at) VALUES ($user_id, '$item_type', $item_id, NOW())";
    mysqli_query($conn, $insert_query);
    echo "Saved";
} else {
    // Unsave item
    $delete_query = "DELETE FROM saved_items WHERE user_id = $user_id AND item_type = '$item_type' AND item_id = $item_id";
    mysqli_query($conn, $delete_query);
    echo "Unsaved";
}

mysqli_close($conn);
?>