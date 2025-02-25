<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

$query = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$response = ['jobs' => [], 'users' => [], 'companies' => []];

if (strlen($query) > 2) {
    // Search jobs
    $jobs_query = "SELECT id, title, company_name FROM jobs WHERE title LIKE ? OR company_name LIKE ? LIMIT 5";
    $stmt = mysqli_prepare($conn, $jobs_query);
    $search_term = "%$query%";
    mysqli_stmt_bind_param($stmt, "ss", $search_term, $search_term);
    mysqli_stmt_execute($stmt);
    $jobs_result = mysqli_stmt_get_result($stmt);
    while ($job = mysqli_fetch_assoc($jobs_result)) {
        $response['jobs'][] = ['id' => $job['id'], 'title' => $job['title'], 'company_name' => $job['company_name']];
    }

    // Search users
    $users_query = "SELECT id, username FROM users WHERE username LIKE ? LIMIT 5";
    $stmt = mysqli_prepare($conn, $users_query);
    mysqli_stmt_bind_param($stmt, "s", $search_term);
    mysqli_stmt_execute($stmt);
    $users_result = mysqli_stmt_get_result($stmt);
    while ($user = mysqli_fetch_assoc($users_result)) {
        $response['users'][] = ['id' => $user['id'], 'username' => $user['username']];
    }

    // Search companies (unique company names from jobs)
    $companies_query = "SELECT DISTINCT company_name FROM jobs WHERE company_name LIKE ? LIMIT 5";
    $stmt = mysqli_prepare($conn, $companies_query);
    mysqli_stmt_bind_param($stmt, "s", $search_term);
    mysqli_stmt_execute($stmt);
    $companies_result = mysqli_stmt_get_result($stmt);
    while ($company = mysqli_fetch_assoc($companies_result)) {
        $response['companies'][] = $company['company_name'];
    }
}

echo json_encode($response);
mysqli_close($conn);
?>