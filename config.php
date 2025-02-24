<?php
// Database configuration
$host = 'localhost';
$dbname = 'meetmate'; // Updated to match your error log
$username = 'adnan';   // Replace with your MySQL username
$password = 'Adnan@66202';       // Replace with your MySQL password

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>