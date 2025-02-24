<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's profile details
$query = "SELECT username, full_name, profile_picture FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);
$display_name = $user['full_name'] ?: $user['username'];

// Handle connection request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['connect'])) {
    $connected_user_id = (int)$_POST['connected_user_id'];
    $check_query = "SELECT * FROM connections WHERE user_id = $user_id AND connected_user_id = $connected_user_id";
    $check_result = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_result) == 0) {
        $insert_query = "INSERT INTO connections (user_id, connected_user_id, status, created_at) VALUES ($user_id, $connected_user_id, 'pending', NOW())";
        mysqli_query($conn, $insert_query);
        $notif_query = "INSERT INTO notifications (user_id, type, related_id, message, created_at) VALUES ($connected_user_id, 'connection_request', $user_id, '$display_name has sent you a connection request', NOW())";
        mysqli_query($conn, $notif_query);
    }
    header("Location: network.php");
    exit;
}

// Handle accept/reject connection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['accept']) || isset($_POST['reject']))) {
    $connection_id = (int)$_POST['connection_id'];
    $status = isset($_POST['accept']) ? 'accepted' : 'rejected';
    $update_query = "UPDATE connections SET status = '$status' WHERE id = $connection_id AND connected_user_id = $user_id";
    mysqli_query($conn, $update_query);
    if ($status == 'accepted') {
        $notif_query = "INSERT INTO notifications (user_id, type, related_id, message, created_at) VALUES ((SELECT user_id FROM connections WHERE id = $connection_id), 'connection_request', $user_id, '$display_name has accepted your connection request', NOW())";
        mysqli_query($conn, $notif_query);
    }
    header("Location: network.php");
    exit;
}

// Fetch accepted connections
$accepted_query = "SELECT c.id, u.id AS connected_user_id, u.username, u.full_name, u.profile_picture, u.job_title 
                  FROM connections c 
                  JOIN users u ON c.connected_user_id = u.id 
                  WHERE c.user_id = $user_id AND c.status = 'accepted'";
$accepted_result = mysqli_query($conn, $accepted_query);

// Fetch pending incoming requests
$pending_query = "SELECT c.id, u.id AS user_id, u.username, u.full_name, u.profile_picture, u.job_title 
                  FROM connections c 
                  JOIN users u ON c.user_id = u.id 
                  WHERE c.connected_user_id = $user_id AND c.status = 'pending'";
$pending_result = mysqli_query($conn, $pending_query);

// Fetch connection suggestions (users not yet connected)
$suggestions_query = "SELECT u.id, u.username, u.full_name, u.profile_picture, u.job_title 
                      FROM users u 
                      WHERE u.id != $user_id 
                      AND u.id NOT IN (SELECT connected_user_id FROM connections WHERE user_id = $user_id AND status IN ('pending', 'accepted'))
                      AND u.id NOT IN (SELECT user_id FROM connections WHERE connected_user_id = $user_id AND status IN ('pending', 'accepted'))
                      LIMIT 5";
$suggestions_result = mysqli_query($conn, $suggestions_query);

// Fetch notifications
$notif_query = "SELECT id, message, created_at FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$notif_result = mysqli_query($conn, $notif_query);
$notif_count = mysqli_num_rows($notif_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network | CollabConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :where([class^="ri-"])::before { content: "\f3c2"; }
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .notification-modal {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            width: 300px;
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-modal .notification {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .notification-modal .notification:last-child {
            border-bottom: none;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#6366F1'
                    },
                    borderRadius: {
                        'none': '0px',
                        'sm': '4px',
                        DEFAULT: '8px',
                        'md': '12px',
                        'lg': '16px',
                        'xl': '20px',
                        '2xl': '24px',
                        '3xl': '32px',
                        'full': '9999px',
                        'button': '8px'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <header class="py-6 flex items-center justify-between">
            <div class="flex items-center">
                <h1 class="text-2xl font-semibold text-gray-900">Network</h1>
            </div>
            <div class="flex items-center space-x-4">
                <button class="relative w-10 h-10 flex items-center justify-center" id="notification-btn">
                    <i class="ri-notification-3-line text-gray-600 text-xl"></i>
                    <?php if ($notif_count > 0): ?>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    <?php endif; ?>
                    <div class="notification-modal" id="notification-modal">
                        <?php
                        if ($notif_count > 0) {
                            while ($notif = mysqli_fetch_assoc($notif_result)) {
                                echo "<div class='notification'>";
                                echo "<p class='text-sm text-gray-700'>" . htmlspecialchars($notif['message']) . "</p>";
                                echo "<small class='text-gray-500'>" . $notif['created_at'] . "</small>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div class='notification'><p class='text-sm text-gray-700'>No new notifications</p></div>";
                        }
                        ?>
                    </div>
                </button>
                <a href="profile.php" class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                    <?php if ($user['profile_picture']): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="w-10 h-10 rounded-full object-cover" alt="Profile">
                    <?php else: ?>
                        <div class="text-gray-600 text-xl"><?php echo strtoupper(substr($display_name, 0, 1)); ?></div>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <div class="mt-6">
            <div class="relative">
                <input type="text" id="global-search" placeholder="Search for people..." class="w-full h-12 pl-12 pr-4 text-sm rounded-lg border-none bg-white shadow-sm focus:ring-2 focus:ring-primary focus:outline-none">
                <div class="absolute left-4 top-0 h-full flex items-center">
                    <i class="ri-search-line text-gray-400 text-lg"></i>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6">
            <!-- Accepted Connections -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">My Connections (<?php echo mysqli_num_rows($accepted_result); ?>)</h2>
                <div class="space-y-4">
                    <?php
                    if (mysqli_num_rows($accepted_result) > 0) {
                        while ($connection = mysqli_fetch_assoc($accepted_result)) {
                            echo "<div class='flex items-center justify-between'>";
                            echo "<div class='flex items-center space-x-4'>";
                            if ($connection['profile_picture']) {
                                echo "<img src='" . htmlspecialchars($connection['profile_picture']) . "' class='w-12 h-12 rounded-full object-cover'>";
                            } else {
                                echo "<div class='w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-xl text-gray-600'>" . strtoupper(substr($connection['full_name'] ?: $connection['username'], 0, 1)) . "</div>";
                            }
                            echo "<div>";
                            echo "<h3 class='font-medium text-gray-900'>" . htmlspecialchars($connection['full_name'] ?: $connection['username']) . "</h3>";
                            echo "<p class='text-sm text-gray-500'>" . htmlspecialchars($connection['job_title'] ?: 'No job title') . "</p>";
                            echo "</div>";
                            echo "</div>";
                            echo "<a href='profile.php?id=" . $connection['connected_user_id'] . "' class='px-4 py-2 text-sm text-primary hover:bg-primary/5 rounded-button'>View Profile</a>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p class='text-gray-600'>You have no accepted connections yet.</p>";
                    }
                    ?>
                </div>
            </div>

            <!-- Pending Connection Requests -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Pending Requests (<?php echo mysqli_num_rows($pending_result); ?>)</h2>
                <div class="space-y-4">
                    <?php
                    if (mysqli_num_rows($pending_result) > 0) {
                        while ($request = mysqli_fetch_assoc($pending_result)) {
                            echo "<div class='flex items-center justify-between'>";
                            echo "<div class='flex items-center space-x-4'>";
                            if ($request['profile_picture']) {
                                echo "<img src='" . htmlspecialchars($request['profile_picture']) . "' class='w-12 h-12 rounded-full object-cover'>";
                            } else {
                                echo "<div class='w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-xl text-gray-600'>" . strtoupper(substr($request['full_name'] ?: $request['username'], 0, 1)) . "</div>";
                            }
                            echo "<div>";
                            echo "<h3 class='font-medium text-gray-900'>" . htmlspecialchars($request['full_name'] ?: $request['username']) . "</h3>";
                            echo "<p class='text-sm text-gray-500'>" . htmlspecialchars($request['job_title'] ?: 'No job title') . "</p>";
                            echo "</div>";
                            echo "</div>";
                            echo "<div class='flex space-x-2'>";
                            echo "<form method='POST'>";
                            echo "<input type='hidden' name='connection_id' value='" . $request['id'] . "'>";
                            echo "<button type='submit' name='accept' class='px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90'>Accept</button>";
                            echo "</form>";
                            echo "<form method='POST'>";
                            echo "<input type='hidden' name='connection_id' value='" . $request['id'] . "'>";
                            echo "<button type='submit' name='reject' class='px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-button'>Reject</button>";
                            echo "</form>";
                            echo "</div>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p class='text-gray-600'>No pending connection requests.</p>";
                    }
                    ?>
                </div>
            </div>

            <!-- Connection Suggestions -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">People You May Know</h2>
                <div class="space-y-4">
                    <?php
                    if (mysqli_num_rows($suggestions_result) > 0) {
                        while ($suggestion = mysqli_fetch_assoc($suggestions_result)) {
                            echo "<div class='flex items-center justify-between'>";
                            echo "<div class='flex items-center space-x-4'>";
                            if ($suggestion['profile_picture']) {
                                echo "<img src='" . htmlspecialchars($suggestion['profile_picture']) . "' class='w-12 h-12 rounded-full object-cover'>";
                            } else {
                                echo "<div class='w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-xl text-gray-600'>" . strtoupper(substr($suggestion['full_name'] ?: $suggestion['username'], 0, 1)) . "</div>";
                            }
                            echo "<div>";
                            echo "<h3 class='font-medium text-gray-900'>" . htmlspecialchars($suggestion['full_name'] ?: $suggestion['username']) . "</h3>";
                            echo "<p class='text-sm text-gray-500'>" . htmlspecialchars($suggestion['job_title'] ?: 'No job title') . "</p>";
                            echo "</div>";
                            echo "</div>";
                            echo "<form method='POST'>";
                            echo "<input type='hidden' name='connected_user_id' value='" . $suggestion['id'] . "'>";
                            echo "<button type='submit' name='connect' class='px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90'>Connect</button>";
                            echo "</form>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p class='text-gray-600'>No connection suggestions available.</p>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-center">
            <a href="dashboard.php" class="px-4 py-2 text-sm text-primary hover:bg-primary/5 rounded-button">Back to Dashboard</a>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Notification Modal
        const notifBtn = document.getElementById('notification-btn');
        const notifModal = document.getElementById('notification-modal');
        notifBtn.addEventListener('click', () => {
            notifModal.style.display = notifModal.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
            if (!notifBtn.contains(e.target) && !notifModal.contains(e.target)) {
                notifModal.style.display = 'none';
            }
        });

        // Search Functionality
        const globalSearch = document.getElementById('global-search');
        globalSearch.addEventListener('input', function() {
            const query = this.value;
            if (query.length > 2) {
                fetch('search.php?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        console.log('Search results:', data);
                        // Implement UI to display results (e.g., dropdown)
                    });
            }
        });
    });
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>