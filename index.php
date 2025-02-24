<?php
// Start session if needed later (e.g., for login state)
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CollabConnect - Connect, Work, Grow</title>
    <style>
        /* Basic CSS for layout and styling */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        /* Navbar */
        .navbar {
            background-color: #333;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        .navbar .logo {
            font-size: 24px;
            font-weight: bold;
        }
        .navbar .nav-buttons a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 10px 15px;
            background-color: #007BFF;
            border-radius: 5px;
        }
        .navbar .nav-buttons a:hover {
            background-color: #0056b3;
        }

        /* Hero Section */
        .hero {
            text-align: center;
            padding: 100px 20px;
            background-color: #f4f4f4;
        }
        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .hero p {
            font-size: 20px;
            max-width: 600px;
            margin: 0 auto;
            color: #555;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            width: 400px;
            border-radius: 10px;
            position: relative;
        }
        .modal-content h2 {
            margin-bottom: 20px;
        }
        .modal-content input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .modal-content button {
            width: 100%;
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .modal-content button:hover {
            background-color: #0056b3;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #555;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="logo">CollabConnect</div>
        <div class="nav-buttons">
            <a href="#" id="login-btn">Login</a>
            <a href="#" id="register-btn">Register</a>
        </div>
    </div>

    <!-- Hero Section -->
    <div class="hero">
        <h1>Connect, Work, Grow</h1>
        <p>Join a community where job seekers, recruiters, and entrepreneurs connect, share opportunities, and collaborate seamlessly.</p>
    </div>

    <!-- Login Modal -->
    <div id="login-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="close-login">&times;</span>
            <h2>Login</h2>
            <form action="login.php" method="POST">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="register-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="close-register">&times;</span>
            <h2>Register</h2>
            <form action="register.php" method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Register</button>
            </form>
        </div>
    </div>

    <!-- JavaScript for Modal Functionality -->
    <script>
        // Get elements
        const loginBtn = document.getElementById('login-btn');
        const registerBtn = document.getElementById('register-btn');
        const loginModal = document.getElementById('login-modal');
        const registerModal = document.getElementById('register-modal');
        const closeLogin = document.getElementById('close-login');
        const closeRegister = document.getElementById('close-register');

        // Open modals
        loginBtn.addEventListener('click', (e) => {
            e.preventDefault();
            loginModal.style.display = 'flex';
        });
        registerBtn.addEventListener('click', (e) => {
            e.preventDefault();
            registerModal.style.display = 'flex';
        });

        // Close modals
        closeLogin.addEventListener('click', () => {
            loginModal.style.display = 'none';
        });
        closeRegister.addEventListener('click', () => {
            registerModal.style.display = 'none';
        });

        // Close modals when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === loginModal) {
                loginModal.style.display = 'none';
            }
            if (e.target === registerModal) {
                registerModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>