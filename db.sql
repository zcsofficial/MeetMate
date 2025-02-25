CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Hashed password
    full_name VARCHAR(100) NOT NULL, -- e.g., "Emily Thompson"
    profile_picture VARCHAR(255), -- URL to image
    job_title VARCHAR(100), -- e.g., "Senior Product Designer"
    location VARCHAR(100), -- e.g., "San Francisco, CA"
    phone VARCHAR(20), -- e.g., "+1 (415) 555-0123"
    linkedin_url VARCHAR(255), -- e.g., "linkedin.com/in/emily-thompson"
    about_me TEXT, -- e.g., "Passionate product designer..."
    profile_completion INT DEFAULT 0, -- Percentage (e.g., 85)
    is_public BOOLEAN DEFAULT TRUE, -- About Me visibility toggle
    role ENUM('job_seeker', 'recruiter', 'entrepreneur', 'admin', 'moderator') DEFAULT 'job_seeker',
    online_status BOOLEAN DEFAULT FALSE, -- For Messages page
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE professional_experience (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_title VARCHAR(100) NOT NULL, -- e.g., "Senior Product Designer"
    company_name VARCHAR(100) NOT NULL, -- e.g., "TechVision Inc."
    company_logo VARCHAR(255), -- URL to logo
    start_date DATE NOT NULL, -- e.g., "2022-01-01"
    end_date DATE, -- NULL if current job
    location VARCHAR(100), -- e.g., "San Francisco, CA"
    description TEXT, -- Bullet points stored as text (e.g., "Led redesign...")
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill_name VARCHAR(50) NOT NULL, -- e.g., "UI Design"
    category ENUM('design', 'tools', 'soft_skills', 'other') NOT NULL, -- e.g., "design"
    expertise_level ENUM('beginner', 'intermediate', 'advanced', 'expert') NOT NULL, -- e.g., "expert"
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_skill (user_id, skill_name) -- Prevents duplicates
);
CREATE TABLE job_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    desired_titles TEXT, -- Comma-separated, e.g., "Senior Product Designer, UX Lead"
    preferred_industries TEXT, -- Comma-separated, e.g., "Technology, Healthcare"
    salary_min INT, -- e.g., 120000 (USD)
    salary_max INT, -- e.g., 150000 (USD)
    work_type ENUM('remote', 'hybrid', 'on-site') NOT NULL, -- e.g., "remote"
    open_to_opportunities BOOLEAN DEFAULT TRUE, -- Toggle from UI
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id) -- One set of preferences per user
);
CREATE TABLE connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    connected_user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (connected_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_connection (user_id, connected_user_id)
);
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    posted_by INT NOT NULL, -- Recruiter/Admin user
    title VARCHAR(100) NOT NULL, -- e.g., "Senior Product Designer"
    company_name VARCHAR(100), -- e.g., "TechCorp Inc."
    company_logo VARCHAR(255), -- URL to logo
    location VARCHAR(100), -- e.g., "San Francisco, CA"
    description TEXT NOT NULL,
    skills_required TEXT, -- e.g., "UI/UX, Figma"
    job_type ENUM('full-time', 'part-time', 'freelance') NOT NULL,
    work_mode ENUM('remote', 'hybrid', 'on-site') NOT NULL,
    salary_range VARCHAR(50), -- e.g., "$120k-$160k"
    match_percentage INT, -- e.g., 95 (calculated dynamically)
    posted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT NOT NULL, -- e.g., "Just finished a workshop..."
    image_url VARCHAR(255), -- Optional image (e.g., workshop photo)
    job_id INT, -- Nullable, links to jobs table if it’s a job post
    likes INT DEFAULT 0, -- e.g., 124
    comments INT DEFAULT 0, -- e.g., 28
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL
);
CREATE TABLE post_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    interaction_type ENUM('like', 'share') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_interaction (post_id, user_id, interaction_type)
);

CREATE TABLE job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    status ENUM('applied', 'in_review', 'interview', 'offer', 'rejected') DEFAULT 'applied',
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (user_id, job_id)
);
CREATE TABLE profile_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- Whose profile was viewed
    viewer_id INT, -- Nullable if anonymous
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL, -- e.g., "Future of UX Design"
    description TEXT,
    event_type ENUM('webinar', 'workshop', 'networking', 'conference') NOT NULL,
    event_date DATETIME NOT NULL, -- e.g., "2025-03-15 14:00:00"
    event_link VARCHAR(255), -- e.g., Zoom URL
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE event_attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (event_id, user_id)
);
CREATE TABLE saved_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_type ENUM('job', 'post') NOT NULL, -- Expanded to include posts
    item_id INT NOT NULL,
    saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('connection_request', 'message', 'job_match', 'event_reminder', 'post_interaction') NOT NULL,
    related_id INT, -- e.g., message ID, connection ID
    message VARCHAR(255) NOT NULL, -- e.g., "Sarah Anderson sent you a message"
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE users ADD COLUMN email_notifications BOOLEAN DEFAULT TRUE;