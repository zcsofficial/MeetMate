<?php
// Start session for login state
session_start();
$_SESSION['token'] = bin2hex(random_bytes(32)); // CSRF token for security
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeetMate - Connect, Collaborate, and Share</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :where([class^="ri-"])::before { content: "\f3c2"; }
        body { font-family: 'Inter', sans-serif; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); justify-content: center; align-items: center; z-index: 9999; }
        .modal-content { background-color: white; padding: 24px; width: 100%; max-width: 400px; border-radius: 12px; position: relative; }
        .close-btn { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; color: #555; }
    </style>
</head>
<body class="bg-white">
    <header class="fixed w-full bg-white/80 backdrop-blur-sm z-50 border-b border-gray-100">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
            <a href="#" class="text-2xl font-['Pacifico'] text-primary">MeetMate</a>
            <div class="flex items-center gap-6">
                <button class="text-gray-600 hover:text-primary whitespace-nowrap">Find Jobs</button>
                <button class="text-gray-600 hover:text-primary whitespace-nowrap">Companies</button>
                <button class="text-gray-600 hover:text-primary whitespace-nowrap">Recruiters</button>
                <button id="login-btn" class="px-4 py-2 text-primary border border-primary hover:bg-primary/5 !rounded-button whitespace-nowrap">Sign In</button>
                <button id="register-btn" class="px-4 py-2 bg-primary text-white hover:bg-primary/90 !rounded-button whitespace-nowrap">Join Now</button>
            </div>
        </nav>
    </header>

    <main>
        <section class="pt-24 pb-16 relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('https://public.readdy.ai/ai/img_res/c331a4d1b3095311fd2c61ae475cc6f1.jpg')] bg-cover bg-center opacity-10"></div>
            <div class="container mx-auto px-6 relative">
                <div class="max-w-2xl">
                    <h1 class="text-5xl font-bold mb-6 leading-tight">Connect, Collaborate & Build Your Career</h1>
                    <p class="text-xl text-gray-600 mb-8">Join MeetMate to discover opportunities, connect with industry professionals, and take your career to new heights.</p>
                    <div class="flex gap-4">
                        <button id="get-started-btn" class="px-6 py-3 bg-primary text-white hover:bg-primary/90 !rounded-button text-lg whitespace-nowrap">Get Started</button>
                        <button class="px-6 py-3 border border-gray-300 hover:border-primary hover:text-primary !rounded-button text-lg whitespace-nowrap">Learn More</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Login Modal -->
        <div id="login-modal" class="modal">
            <div class="modal-content">
                <span class="close-btn" id="close-login">×</span>
                <h2 class="text-2xl font-bold mb-6 text-center">Sign In</h2>
                <form action="login.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="email" placeholder="Email" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="password" placeholder="Password" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['token']; ?>">
                    <button type="submit" class="w-full py-2 bg-primary text-white hover:bg-primary/90 !rounded-button">Sign In</button>
                </form>
            </div>
        </div>

        <!-- Register Modal -->
        <div id="register-modal" class="modal">
            <div class="modal-content">
                <span class="close-btn" id="close-register">×</span>
                <h2 class="text-2xl font-bold mb-6 text-center">Join MeetMate</h2>
                <form action="register.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" name="username" placeholder="Username" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="email" placeholder="Email" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="full_name" placeholder="Full Name" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="password" placeholder="Password" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['token']; ?>">
                    <button type="submit" class="w-full py-2 bg-primary text-white hover:bg-primary/90 !rounded-button">Create Account</button>
                </form>
            </div>
        </div>

        <section class="py-16 bg-gray-50">
            <div class="container mx-auto px-6">
                <h2 class="text-3xl font-bold text-center mb-12">Everything You Need to Succeed</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div class="bg-white p-6 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 flex items-center justify-center bg-primary/10 rounded-full mb-4">
                            <i class="ri-briefcase-line text-primary text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Find Jobs</h3>
                        <p class="text-gray-600">Discover thousands of job opportunities from top companies worldwide.</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 flex items-center justify-center bg-primary/10 rounded-full mb-4">
                            <i class="ri-building-line text-primary text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Explore Companies</h3>
                        <p class="text-gray-600">Research and connect with companies that match your career goals.</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 flex items-center justify-center bg-primary/10 rounded-full mb-4">
                            <i class="ri-team-line text-primary text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Meet Recruiters</h3>
                        <p class="text-gray-600">Direct communication with recruiters from your dream companies.</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 flex items-center justify-center bg-primary/10 rounded-full mb-4">
                            <i class="ri-message-3-line text-primary text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Instant Chat</h3>
                        <p class="text-gray-600">Real-time messaging to facilitate quick and efficient communication.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-16">
            <div class="container mx-auto px-6">
                <div class="flex flex-col lg:flex-row items-center gap-12">
                    <div class="flex-1">
                        <h2 class="text-3xl font-bold mb-6">Why Choose MeetMate?</h2>
                        <div class="space-y-6">
                            <div class="flex gap-4">
                                <div class="w-8 h-8 flex items-center justify-center bg-primary/10 rounded-full shrink-0">
                                    <i class="ri-check-line text-primary"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold mb-2">Smart Job Matching</h3>
                                    <p class="text-gray-600">Our AI-powered system matches you with the most relevant opportunities based on your skills and preferences.</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="w-8 h-8 flex items-center justify-center bg-primary/10 rounded-full shrink-0">
                                    <i class="ri-check-line text-primary"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold mb-2">Direct Communication</h3>
                                    <p class="text-gray-600">Connect directly with recruiters and hiring managers, eliminating intermediaries.</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="w-8 h-8 flex items-center justify-center bg-primary/10 rounded-full shrink-0">
                                    <i class="ri-check-line text-primary"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold mb-2">Company Insights</h3>
                                    <p class="text-gray-600">Get detailed information about company culture, benefits, and growth opportunities.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <img src="https://public.readdy.ai/ai/img_res/b7f15cb96a4dbab317bfc55ad6f62118.jpg" alt="Team collaboration" class="rounded-lg shadow-lg">
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div>
                    <a href="#" class="text-2xl font-['Pacifico'] text-white mb-4 block">MeetMate</a>
                    <p class="text-gray-400">Connect, collaborate, and build your career with MeetMate.</p>
                </div>
                <div>
                    <h3 class="font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Find Jobs</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Companies</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Recruiters</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">About Us</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold mb-4">Support</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Help Center</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Terms of Service</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Contact Us</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold mb-4">Newsletter</h3>
                    <p class="text-gray-400 mb-4">Stay updated with the latest opportunities</p>
                    <form class="flex gap-2">
                        <input type="email" placeholder="Your email" class="flex-1 px-4 py-2 bg-gray-800 border border-gray-700 rounded-button focus:outline-none focus:border-primary text-white">
                        <button type="submit" class="px-4 py-2 bg-primary text-white !rounded-button hover:bg-primary/90">Subscribe</button>
                    </form>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-gray-400">© 2025 MeetMate. All rights reserved.</p>
                <div class="flex gap-4">
                    <a href="#" class="text-gray-400 hover:text-white"><i class="ri-twitter-fill text-xl"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white"><i class="ri-linkedin-fill text-xl"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white"><i class="ri-facebook-fill text-xl"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white"><i class="ri-instagram-fill text-xl"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Modal elements
        const loginBtn = document.getElementById('login-btn');
        const registerBtn = document.getElementById('register-btn');
        const getStartedBtn = document.getElementById('get-started-btn');
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
        getStartedBtn.addEventListener('click', (e) => {
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