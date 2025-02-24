<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password

    // Check if username or email already exists
    $check_query = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo "Username or email already exists.";
    } else {
        // Insert new user
        $query = "INSERT INTO users (username, email, full_name, password) VALUES ('$username', '$email', '$full_name', '$password')";
        if (mysqli_query($conn, $query)) {
            header("Location: index.php?registered=1");
            exit;
        } else {
            echo "Registration failed: " . mysqli_error($conn);
        }
    }
}

mysqli_close($conn);
?>