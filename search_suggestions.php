<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['q']) || empty($_GET['q'])) {
    echo json_encode([]);
    exit;
}

$search_term = mysqli_real_escape_string($conn, $_GET['q']);
$results = [];

$query = "
    SELECT 'User' as type, id, username as title, full_name as subtitle, profile_picture as image 
    FROM users 
    WHERE username LIKE '%$search_term%' OR full_name LIKE '%$search_term%'
    UNION
    SELECT 'Job' as type, id, title, company_name as subtitle, company_logo as image 
    FROM jobs 
    WHERE title LIKE '%$search_term%' OR company_name LIKE '%$search_term%'
    LIMIT 10
";
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $results[] = [
            'type' => $row['type'],
            'title' => htmlspecialchars($row['title']),
            'subtitle' => htmlspecialchars($row['subtitle']),
            'image' => $row['image']
        ];
    }
}

echo json_encode($results);
mysqli_close($conn);
?>