<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is NOT logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Function to check and award achievements (MODIFIED TO PASS BY REFERENCE)
function checkAndAwardAchievements($conn, $user_id, &$userProgress, $badges, &$user_earned_badge_ids) {
    $awarded = false;
    $newly_earned_badges_names = [];
    
    foreach ($badges as $badge) {
        // Skip if already earned
        if (in_array($badge['achievement_id'], $user_earned_badge_ids)) {
            continue;
        }

        $requirement_met = false;
        // CRITICAL: Ensure 'requirement_type' and 'requirement_value' are used here
        switch ($badge['requirement_type']) { // THIS MUST BE 'requirement_type'
            case 'total_trees':
                $requirement_met = $userProgress['treesPlanted'] >= $badge['requirement_value'];
                break;
            case 'distinct_species':
                $requirement_met = $userProgress['species'] >= $badge['requirement_value'];
                break;
            case 'streak_days':
                $requirement_met = $userProgress['streakDays'] >= $badge['requirement_value'];
                break;
        }

        if ($requirement_met) {
            try {
                $stmt = $conn->prepare("INSERT INTO user_achievements (user_id, achievement_id, earned_at) VALUES (?, ?, NOW())");
                $stmt->bind_param("ii", $user_id, $badge['achievement_id']);
                if ($stmt->execute()) {
                    $user_earned_badge_ids[] = $badge['achievement_id'];
                    $newly_earned_badges_names[] = $badge['name'];
                    $awarded = true;

                    $xp_stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE user_id = ?");
                    $xp_stmt->bind_param("ii", $badge['xp_reward'], $user_id);
                    $xp_stmt->execute();
                    $xp_stmt->close();

                    $userProgress['totalXP'] += $badge['xp_reward'];
                    $userProgress['badgesEarned']++;
                }
                $stmt->close();
            } catch (Exception $e) {
                if ($conn->errno == 1062) {
                    error_log("Attempted to re-award already earned badge " . $badge['name'] . " to user " . $user_id . " (User already has it).");
                } else {
                    error_log("Error awarding achievement: " . $e->getMessage());
                }
            }
        }
    }
    return [
        'awarded' => $awarded,
        'newly_earned_badges_names' => $newly_earned_badges_names
    ];
}


// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $upload_dir = __DIR__ . '/uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file = $_FILES['profile_picture'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        header('Location: dashboard.php?error=' . urlencode('Invalid file type. Please upload a JPG, PNG or GIF image.'));
        exit();
    }

    if ($file['size'] > $max_size) {
        header('Location: dashboard.php?error=' . urlencode('File is too large. Maximum size is 5MB.'));
        exit();
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        header('Location: dashboard.php?error=' . urlencode('Error uploading file. Please try again.'));
        exit();
    }

    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $profile_url = 'uploads/' . $new_filename;

        // Assuming User::updateProfilePicture exists in includes/user.php
        require_once 'includes/config.php';
        require_once 'includes/user.php';
        $user_obj = new User($conn);

        if ($user_obj->updateProfilePicture($_SESSION['user_id'], $profile_url)) {
            $_SESSION['profile_picture_url'] = $profile_url; // Update session variable for immediate display
            header('Location: dashboard.php?success=' . urlencode('Profile picture updated successfully!'));
            exit();
        } else {
            unlink($upload_path); // Delete the uploaded file if database update fails
            header('Location: dashboard.php?error=' . urlencode('Error updating profile picture in database.'));
            exit();
        }
    }

    header('Location: dashboard.php?error=' . urlencode('Error saving file. Please try again.'));
    exit();
}


require_once 'includes/config.php'; // Contains $conn
require_once 'includes/user.php';   // Essential for User::updateXp(), User::updateStreak(), and User::updateProfilePicture()

$username = $_SESSION['username'] ?? 'Guest';
$user_id = $_SESSION['user_id'];

$activity_error = '';
$activity_success_message = '';
$recent_activities = [];

// Initialize UserProgress, fetching current XP and streak data from DB
$userProgress = [
    'username' => htmlspecialchars($username),
    'treesPlanted' => 0,
    'species' => 0,
    'speciesPlanted' => [],
    'totalXP' => 0,
    'level' => 1,
    'currentXP' => 0,
    'requiredXP' => 100,
    'badgesEarned' => 0,
    'streakDays' => 0,
    'co2Offset' => 0,
    'lastActivityDate' => null,
    'profilePictureUrl' => null // Initialize profile picture URL
];

// Add profile_picture_url column if it doesn't exist (this block should ideally be in a migration script, not here)
try {
    if ($conn && !$conn->connect_error) {
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture_url'");
        if ($check_column && $check_column->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN profile_picture_url VARCHAR(255) DEFAULT NULL");
            error_log("Added profile_picture_url column to users table");
        }
    } else {
        error_log("Database connection not available for column check");
    }
} catch (Exception $e) {
    error_log("Error checking/adding profile_picture_url column: " . $e->getMessage());
}


// --- Fetch User's XP, Streak Data, and total activity stats from Database ---
// Fetch XP, streak days, and last activity date directly from the users table
$stmt_user_data = $conn->prepare("SELECT xp, current_streak_days, last_activity_date, profile_picture_url FROM users WHERE user_id = ?");
if ($stmt_user_data) {
    $stmt_user_data->bind_param("i", $user_id);
    $stmt_user_data->execute();
    $result_user_data = $stmt_user_data->get_result();
    if ($row_user_data = $result_user_data->fetch_assoc()) {
        $userProgress['totalXP'] = (int)$row_user_data['xp'];
        $userProgress['streakDays'] = (int)$row_user_data['current_streak_days'];
        $userProgress['lastActivityDate'] = $row_user_data['last_activity_date'];
        $userProgress['profilePictureUrl'] = $row_user_data['profile_picture_url']; // Fetch profile picture URL
    }
    $stmt_user_data->close();
} else {
    error_log("Error fetching initial user data (XP/Streak/ProfilePic): " . $conn->error);
}

// Fetch activity stats (trees planted, distinct species)
$stmt_stats = $conn->prepare("SELECT SUM(trees_planted) as total_trees, GROUP_CONCAT(DISTINCT tree_species) as distinct_species FROM activities WHERE user_id = ?");
if ($stmt_stats) {
    $stmt_stats->bind_param("i", $user_id);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    $stats = $result_stats->fetch_assoc();
    $stmt_stats->close();

    $userProgress['treesPlanted'] = (int)$stats['total_trees'];
    $userProgress['co2Offset'] = $userProgress['treesPlanted'] * 10;

    if (!empty($stats['distinct_species'])) {
        $userProgress['speciesPlanted'] = array_map('trim', explode(',', $stats['distinct_species']));
        $userProgress['species'] = count($userProgress['speciesPlanted']);
    }

    // Level calculation based on persistent totalXP
    $userProgress['level'] = floor($userProgress['totalXP'] / 500) + 1;
    $userProgress['requiredXP'] = ($userProgress['level']) * 500;
    $userProgress['currentXP'] = $userProgress['totalXP'] % 500;

} else {
    error_log("Error fetching user activity stats: " . $conn->error);
}


// --- Fetch ALL Achievement Definitions from Database ---
$badges = [];
$stmt_achievements_def = $conn->query("SELECT achievement_id, name, description, requirement_type, requirement_value, xp_reward, icon_name, color_class FROM achievements ORDER BY xp_reward ASC");
if ($stmt_achievements_def) {
    while ($row = $stmt_achievements_def->fetch_assoc()) {
        $badges[] = $row;
    }
} else {
    error_log("Error fetching achievement definitions: " . $conn->error);
}

// --- Fetch User's EARNED Achievements from Database (for checking which are earned) ---
$user_earned_badge_ids = [];
$stmt_user_badges = $conn->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = ?");
if ($stmt_user_badges) {
    $stmt_user_badges->bind_param("i", $user_id);
    $stmt_user_badges->execute();
    $result_user_badges = $stmt_user_badges->get_result();
    while ($row = $result_user_badges->fetch_assoc()) {
        $user_earned_badge_ids[] = $row['achievement_id'];
    }
    $stmt_user_badges->close();
} else {
    error_log("Error fetching user earned badges: " . $conn->error);
}
$userProgress['badgesEarned'] = count($user_earned_badge_ids); // Update the count for initial display

// Call the achievement check function initially (on page load)
checkAndAwardAchievements($conn, $user_id, $userProgress, $badges, $user_earned_badge_ids);


// --- Handle Activity Form Submission (main POST logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_activity_submit'])) {
    $activity_date = $_POST['activity_date'] ?? '';
    $trees_planted = filter_var($_POST['trees_planted'] ?? 1, FILTER_VALIDATE_INT);
    $tree_species_selected = trim($_POST['tree_species'] ?? '');
    $other_species = trim($_POST['other_species'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $final_tree_species = $tree_species_selected;
    if ($tree_species_selected === 'Other' && !empty($other_species)) {
        $final_tree_species = $other_species;
    } elseif ($tree_species_selected === 'Other' && empty($other_species)) {
        $activity_error = 'Please specify the tree species for "Other".';
    }

    if (empty($activity_date)) {
        $activity_error = 'Please select an activity date.';
    } elseif ($trees_planted === false || $trees_planted <= 0) {
        $activity_error = 'Number of trees must be a positive whole number.';
    } elseif (empty($final_tree_species) && empty($activity_error)) {
        $activity_error = 'Please select or specify the tree species.';
    }

    if (empty($activity_error)) {
        $stmt = $conn->prepare("INSERT INTO activities (user_id, activity_date, trees_planted, tree_species, description) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isiss", $user_id, $activity_date, $trees_planted, $final_tree_species, $description);
            if ($stmt->execute()) {
                $activity_success_message = 'Activity logged successfully!';
                $user_obj = new User($conn);

                // --- Calculate and Update Streak ---
                // Fetch the latest streak data from DB for accurate calculation
                $stmt_fetch_current_streak = $conn->prepare("SELECT current_streak_days, last_activity_date FROM users WHERE user_id = ?");
                $stmt_fetch_current_streak->bind_param("i", $user_id);
                $stmt_fetch_current_streak->execute();
                $result_current_streak = $stmt_fetch_current_streak->get_result();
                $current_user_streak_data = $result_current_streak->fetch_assoc();
                $stmt_fetch_current_streak->close();

                $current_streak_days = (int)$current_user_streak_data['current_streak_days'];
                $last_activity_db_date = $current_user_streak_data['last_activity_date'];

                $logged_activity_dt = new DateTime($activity_date);
                $today_dt = new DateTime(); // Current server date

                $new_streak_days = $current_streak_days;
                $update_last_activity_date = $last_activity_db_date; // Default to no change

                if ($last_activity_db_date === null) {
                    // First activity ever for this user
                    $new_streak_days = 1;
                    $update_last_activity_date = $logged_activity_dt->format('Y-m-d');
                } else {
                    $last_activity_dt_obj = new DateTime($last_activity_db_date);

                    // Normalize dates to ensure only day difference matters, not time
                    $logged_activity_date_only = new DateTime($logged_activity_dt->format('Y-m-d'));
                    $last_activity_date_only = new DateTime($last_activity_dt_obj->format('Y-m-d'));
                    $today_date_only = new DateTime($today_dt->format('Y-m-d'));

                    $diff_logged_to_last = $logged_activity_date_only->diff($last_activity_date_only)->days;

                    // Case 1: Logging activity for the same day as last logged activity
                    if ($logged_activity_date_only == $last_activity_date_only) {
                        // Streak remains the same, last_activity_date also remains the same
                        // No change to $new_streak_days and $update_last_activity_date (already initialized to current values)
                    }
                    // Case 2: Logging activity for the day IMMEDIATELY after last logged activity
                    else if ($diff_logged_to_last == 1 && $logged_activity_date_only > $last_activity_date_only) {
                        $new_streak_days = $current_streak_days + 1;
                        $update_last_activity_date = $logged_activity_date_only->format('Y-m-d');
                    }
                    // Case 3: Logging activity more than one day after last logged activity OR logging a past date that is not consecutive
                    else {
                        // Check if today's date continues the streak or breaks it
                        $diff_today_to_last_activity = $today_date_only->diff($last_activity_date_only)->days;

                        if ($today_date_only == $last_activity_date_only) {
                            // User has already logged today. Streak is maintained.
                            $new_streak_days = $current_streak_days;
                            $update_last_activity_date = $last_activity_date_only->format('Y-m-d');
                        } elseif ($diff_today_to_last_activity == 1 && $today_date_only > $last_activity_date_only) {
                            // User did not log yesterday, but logs today, making it consecutive from last activity
                            // This means they've completed the current day's activity
                            $new_streak_days = $current_streak_days + 1;
                            $update_last_activity_date = $today_date_only->format('Y-m-d');
                        } else {
                            // Streak is broken (gap of 2+ days or logging an old date that doesn't continue the streak)
                            $new_streak_days = 1;
                            // Only update last_activity_date if the currently logged activity is more recent than the recorded last activity
                            if ($logged_activity_date_only > $last_activity_date_only) {
                                $update_last_activity_date = $logged_activity_date_only->format('Y-m-d');
                            } else {
                                $update_last_activity_date = $last_activity_date_only->format('Y-m-d'); // Keep old date if current log is older
                            }
                        }
                    }
                }

                // Update the database with the new streak and last activity date
                $user_obj->updateStreak($user_id, $new_streak_days, $update_last_activity_date);
                $userProgress['streakDays'] = $new_streak_days; // Update in-memory for immediate display
                $userProgress['lastActivityDate'] = $update_last_activity_date; // Update in-memory


                // --- Award XP for the activity itself ---
                $xp_for_activity = $trees_planted * 10; // 10 XP per tree planted
                $user_obj->updateXp($user_id, $xp_for_activity);
                $userProgress['totalXP'] += $xp_for_activity; // Update in-memory progress

                // --- Re-fetch current user activity stats for ACCURATE badge checking ---
                $stmt_stats_recheck = $conn->prepare("SELECT SUM(trees_planted) as total_trees, GROUP_CONCAT(DISTINCT tree_species) as distinct_species FROM activities WHERE user_id = ?");
                if ($stmt_stats_recheck) {
                    $stmt_stats_recheck->bind_param("i", $user_id);
                    $stmt_stats_recheck->execute();
                    $result_stats_recheck = $stmt_stats_recheck->get_result();
                    $stats_recheck = $result_stats_recheck->fetch_assoc();
                    $stmt_stats_recheck->close();
                    $userProgress['treesPlanted'] = (int)$stats_recheck['total_trees'];
                    if (!empty($stats_recheck['distinct_species'])) {
                        $userProgress['speciesPlanted'] = array_map('trim', explode(',', $stats_recheck['distinct_species']));
                        $userProgress['species'] = count($userProgress['speciesPlanted']);
                    }
                }

                // --- Check for and award NEW Achievements (using the updated userProgress and earned_badge_ids) ---
                $achievement_result = checkAndAwardAchievements($conn, $user_id, $userProgress, $badges, $user_earned_badge_ids);

                if (!empty($achievement_result['newly_earned_badges_names'])) {
                    $activity_success_message .= " You also earned: " . implode(', ', $achievement_result['newly_earned_badges_names']) . "!";
                }

                header('Location: dashboard.php?success=' . urlencode($activity_success_message));
                exit();
            } else {
                $activity_error = 'Error logging activity: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $activity_error = 'Database error: ' . $conn->error;
        }
    }
}

// Check for success/error message from redirect (after logging activity or profile picture upload)
if (isset($_GET['success'])) {
    $activity_success_message = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $activity_error = htmlspecialchars($_GET['error']);
}


// --- Fetch Recent Activities for the logged-in user (for display), including like/comment counts and user's like status ---
$stmt_activities = $conn->prepare("
    SELECT
        a.activity_id,
        a.activity_date,
        a.trees_planted,
        a.tree_species,
        a.description,
        a.created_at,
        (SELECT COUNT(*) FROM activity_likes al WHERE al.activity_id = a.activity_id) AS like_count,
        (SELECT COUNT(*) FROM activity_comments ac WHERE ac.activity_id = a.activity_id) AS comment_count,
        (SELECT COUNT(*) FROM activity_likes al WHERE al.activity_id = a.activity_id AND al.user_id = ?) AS user_has_liked
    FROM
        activities a
    WHERE
        a.user_id = ?
    ORDER BY
        a.activity_date DESC, a.created_at DESC
    LIMIT 5
");
if ($stmt_activities) {
    $stmt_activities->bind_param("ii", $user_id, $user_id); // Bind user_id twice for the subqueries
    $stmt_activities->execute();
    $result_activities = $stmt_activities->get_result();
    while ($row = $result_activities->fetch_assoc()) {
        // Fetch comments for each activity
        $stmt_comments = $conn->prepare("
            SELECT ac.comment_text, ac.created_at, u.username
            FROM activity_comments ac
            JOIN users u ON ac.user_id = u.user_id
            WHERE ac.activity_id = ?
            ORDER BY ac.created_at ASC
        ");
        $comments = [];
        if ($stmt_comments) {
            $stmt_comments->bind_param("i", $row['activity_id']);
            $stmt_comments->execute();
            $result_comments = $stmt_comments->get_result();
            while ($comment_row = $result_comments->fetch_assoc()) {
                $comments[] = $comment_row;
            }
            $stmt_comments->close();
        }
        $row['comments'] = $comments;
        $recent_activities[] = $row;
    }
    $stmt_activities->close();
} else {
    error_log("Error fetching recent activities: " . $conn->error);
}

// --- Re-fetch user's earned badge IDs after potential new awards for correct display --- (This block is now less critical due to pass-by-reference)
// However, keeping it ensures the $user_earned_badge_ids is fully consistent with DB before rendering.
$user_earned_badge_ids_refreshed = []; // Use a new variable for clarity
$stmt_user_badges_refresh = $conn->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = ?");
if ($stmt_user_badges_refresh) {
    $stmt_user_badges_refresh->bind_param("i", $user_id);
    $stmt_user_badges_refresh->execute();
    $result_user_badges_refresh = $stmt_user_badges_refresh->get_result();
    while ($row_refresh = $result_user_badges_refresh->fetch_assoc()) {
        $user_earned_badge_ids_refreshed[] = $row_refresh['achievement_id'];
    }
    $stmt_user_badges_refresh->close();
}
$user_earned_badge_ids = $user_earned_badge_ids_refreshed; // Assign the refreshed list back to the main variable
$userProgress['badgesEarned'] = count($user_earned_badge_ids); // Update the count

// Recalculate level progress values based on latest totalXP
$userProgress['level'] = floor($userProgress['totalXP'] / 500) + 1;
$userProgress['requiredXP'] = ($userProgress['level']) * 500;
$userProgress['currentXP'] = $userProgress['totalXP'] % 500;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tree Hero Challenge - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Custom styles for date input icon */
        input[type="date"]::-webkit-calendar-picker-indicator {
            background: transparent;
            bottom: 0;
            color: transparent;
            cursor: pointer;
            height: auto;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            width: auto;
        }
        /* Styles for the new design provided */
        .badge-glow { box-shadow: 0 0 20px rgba(34, 197, 94, 0.4); }
        .level-progress { background: linear-gradient(90deg, #10b981 var(--progress), #e5e7eb var(--progress)); }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .pulse-green { animation: pulse-green 2s infinite; }
        @keyframes pulse-green {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .slide-in { animation: slideIn 0.5s ease-out; }
        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .badge-card {
            min-width: 180px; /* Ensure a minimum width for badges */
            border: 2px solid #ccc;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            background: #f9f9f9;
            transition: all 0.3s ease;
        }
        .badge-card.locked {
            filter: grayscale(80%);
            opacity: 0.6;
        }
        .badge-card.earned {
            border-color: #4caf50;
            background: #e6ffe6;
            box-shadow: 0 0 15px #4caf50;
        }
        /* New style for small profile thumbs in leaderboard */
        .profile-thumb-small {
            width: 32px;
            height: 32px;
            border-radius: 9999px; /* Tailwind's rounded-full */
            object-fit: cover;
            border: 1px solid #e5e7eb; /* Tailwind gray-200 */
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-100 min-h-screen font-sans text-gray-800">
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-emerald-500 rounded-lg flex items-center justify-center">
                        <i data-lucide="tree-pine" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Tree Hero Challenge</h1>
                        <p class="text-sm text-gray-600">GeoTrees Gamification Platform</p>
                    </div>
                </div>

                <div class="flex items-center space-x-6">
                    <a href="#dashboard" class="nav-btn text-gray-700 hover:text-green-600 font-medium">Dashboard</a>
                    <a href="activity_feed.php" class="nav-btn text-gray-700 hover:text-green-600 font-medium">Activity Feed</a> <!-- NEW LINK -->
                    <a href="#plant" class="nav-btn text-gray-700 hover:text-green-600 font-medium">Plant Tree</a>
                    <a href="#badges" class="nav-btn text-gray-700 hover:text-green-600 font-medium">Badges</a>
                    <a href="#leaderboard" class="nav-btn text-gray-700 hover:text-green-600 font-medium">Leaderboard</a>
                    <a href="logout.php" class="nav-btn text-red-500 hover:text-red-600 font-medium">Logout</a>

                    <div class="flex items-center space-x-2 bg-green-100 px-3 py-2 rounded-full">
                        <div id="connection-status" class="w-3 h-3 bg-green-500 rounded-full pulse-green"></div>
                        <span class="text-sm font-medium text-green-800">Online</span>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div id="dashboard" class="section mb-12">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-green-600 to-emerald-600 p-6 text-white">
                            <div class="flex items-center space-x-4">
                                <div class="flex flex-col items-center">
                                    <?php if ($userProgress['profilePictureUrl']): ?>
                                        <img src="<?php echo htmlspecialchars($userProgress['profilePictureUrl']); ?>" alt="Profile Picture" class="w-20 h-20 rounded-full object-cover border-2 border-white shadow-md">
                                    <?php else: ?>
                                        <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center border-2 border-white shadow-md">
                                            <i data-lucide="user" class="w-10 h-10 text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <form action="dashboard.php" method="POST" enctype="multipart/form-data" class="mt-2">
                                        <label class="block text-white text-sm cursor-pointer hover:underline">
                                            <input type="file" name="profile_picture" class="hidden" onchange="this.form.submit()">
                                            <span class="text-xs">Change Picture</span>
                                        </label>
                                    </form>
                                    <?php if (isset($_GET['error'])): ?>
                                        <div class="text-red-200 text-xs mt-1"><?php echo htmlspecialchars($_GET['error']); ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($_GET['success'])): ?>
                                        <div class="text-green-200 text-xs mt-1"><?php echo htmlspecialchars($_GET['success']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($userProgress['username']); ?></h2>
                                    <p class="text-green-100">Level <?php echo $userProgress['level']; ?> - Tree Guardian</p>
                                    <div class="flex items-center space-x-4 mt-2">
                                        <span class="text-sm">🌳 <?php echo $userProgress['treesPlanted']; ?> Trees Planted</span>
                                        <span class="text-sm">🏆 <?php echo $userProgress['badgesEarned']; ?> Badges Earned</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6">
                                <div class="flex justify-between text-sm mb-2">
                                    <span>Progress to Level <?php echo ($userProgress['level'] + 1); ?></span>
                                    <span><?php echo $userProgress['currentXP']; ?> / <?php echo $userProgress['requiredXP']; ?> XP</span>
                                </div>
                                <div class="w-full bg-white/20 rounded-full h-3">
                                    <div class="bg-white h-3 rounded-full" style="width: <?php echo ($userProgress['currentXP'] / $userProgress['requiredXP'] * 100); ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="text-center p-4 bg-green-50 rounded-lg">
                                    <div class="text-2xl font-bold text-green-600"><?php echo $userProgress['treesPlanted']; ?></div>
                                    <div class="text-sm text-gray-600">Trees Planted</div>
                                </div>
                                <div class="text-center p-4 bg-blue-50 rounded-lg">
                                    <div class="text-2xl font-bold text-blue-600"><?php echo $userProgress['species']; ?></div>
                                    <div class="text-sm text-gray-600">Species</div>
                                </div>
                                <div class="text-center p-4 bg-purple-50 rounded-lg">
                                    <div class="text-2xl font-bold text-purple-600"><?php echo $userProgress['streakDays']; ?></div>
                                    <div class="text-sm text-gray-600">Days Streak</div>
                                </div>
                                <div class="text-center p-4 bg-orange-50 rounded-lg">
                                    <div class="text-2xl font-bold text-orange-600"><?php echo $userProgress['co2Offset']; ?>kg</div>
                                    <div class="text-sm text-gray-600">CO₂ Offset</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="plant" class="bg-white rounded-xl shadow-lg p-6 flex flex-col">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 text-center">Log New Tree Planting Activity</h3>
                    <?php if (!empty($activity_success_message)): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($activity_success_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($activity_error)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($activity_error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4 flex-grow flex flex-col">
                        <div>
                            <label for="activity_date" class="block text-sm font-medium text-gray-700 mb-1">Date of Activity</label>
                            <input type="date" id="activity_date" name="activity_date" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 relative">
                        </div>
                        <div>
                            <label for="trees_planted" class="block text-sm font-medium text-gray-700 mb-1">Number of Trees Planted</label>
                            <input type="number" id="trees_planted" name="trees_planted" min="1" value="1" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label for="tree_species" class="block text-sm font-medium text-gray-700 mb-1">Tree Species</label>
                            <select id="tree_species" name="tree_species" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                <option value="">Select a species</option>
                                <option value="Tualang">Tualang</option>
                                <option value="Meranti">Meranti</option>
                                <option value="Chengal">Chengal</option>
                                <option value="Rubber">Rubber</option>
                                <option value="Keruing">Keruing</option>
                                <option value="Balau">Balau</option>
                                <option value="Other">Other (Please specify below)</option>
                            </select>
                        </div>

                        <div id="otherSpeciesField" class="hidden">
                            <label for="other_species" class="block text-sm font-medium text-gray-700 mb-1">Specify Other Species</label>
                            <input type="text" id="other_species" name="other_species"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                                   placeholder="e.g., Mahogony, Acacia">
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                            <textarea id="description" name="description" rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
                        </div>
                        <button type="submit" name="log_activity_submit"
                                class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-2 px-4 rounded-md font-semibold mt-auto
                                       hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Log Activity
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div id="badges" class="section mt-12 mb-12">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-2xl font-bold text-gray-900 mb-6 text-center">Your Achievements</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 justify-items-center">
                    <?php foreach ($badges as $badge): ?>
                    <div class="badge-card <?php echo in_array($badge['achievement_id'], $user_earned_badge_ids) ? 'earned' : 'locked'; ?>">
                        <div class="w-12 h-12 bg-<?php echo htmlspecialchars($badge['color_class']); ?>-500 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="<?php echo htmlspecialchars($badge['icon_name']); ?>" class="w-6 h-6 text-white"></i>
                        </div>
                        <div class="badge-name font-bold text-lg mb-2"><?php echo htmlspecialchars($badge['name']); ?></div>
                        <div class="badge-desc text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($badge['description']); ?></div>
                        <div class="badge-status text-sm font-medium <?php echo in_array($badge['achievement_id'], $user_earned_badge_ids) ? 'text-green-600' : 'text-gray-500'; ?>">
                            <?php echo in_array($badge['achievement_id'], $user_earned_badge_ids) ? 'Earned!' : 'Locked'; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="p-6 bg-blue-50 rounded-xl shadow-lg mt-12 mb-12">
    <h2 class="text-2xl font-semibold text-gray-900 mb-4 text-center">Your Recent Activities</h2>
    <?php if (empty($recent_activities)): ?>
        <p class="text-gray-600 text-center">No activities logged yet. Start planting!</p>
    <?php else: ?>
        <ul class="space-y-3">
            <?php foreach ($recent_activities as $activity): ?>
                <li class="bg-white p-3 rounded-md shadow-sm border border-gray-200">
                    <div class="flex justify-between items-center mb-1">
                        <span class="font-medium text-gray-800">
                            <?php echo htmlspecialchars($activity['trees_planted']); ?> tree<?php echo $activity['trees_planted'] > 1 ? 's' : ''; ?> planted
                        </span>
                        <span class="text-sm text-gray-500 flex flex-wrap items-center">
                            on <?php echo date('M d, Y', strtotime($activity['activity_date'])); ?>
                            <?php if (!empty($activity['tree_species'])): ?>
                                <span class="ml-2 px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                    <?php echo htmlspecialchars($activity['tree_species']); ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if (!empty($activity['description'])): ?>
                        <p class="text-sm text-gray-600 italic mb-2">"<?php echo htmlspecialchars($activity['description']); ?>"</p>
                    <?php endif; ?>

                    <div class="flex items-center space-x-4 text-gray-500 text-sm mt-2 pt-2 border-t border-gray-100">
                        <button class="flex items-center space-x-1 like-button <?php echo $activity['user_has_liked'] ? 'text-blue-600' : 'text-gray-500'; ?>" data-activity-id="<?php echo $activity['activity_id']; ?>">
                            <i data-lucide="thumbs-up" class="w-4 h-4 fill-current"></i>
                            <span class="like-count"><?php echo htmlspecialchars($activity['like_count']); ?></span>
                            <span>Likes</span>
                        </button>
                        <button class="flex items-center space-x-1 comment-toggle-button" data-activity-id="<?php echo $activity['activity_id']; ?>">
                            <i data-lucide="message-circle" class="w-4 h-4"></i>
                            <span class="comment-count"><?php echo htmlspecialchars($activity['comment_count']); ?></span>
                            <span>Comments</span>
                        </button>
                    </div>

                    <div id="comments-section-<?php echo $activity['activity_id']; ?>" class="comments-section mt-3 hidden bg-gray-50 p-3 rounded-md">
                        <h4 class="font-semibold text-gray-800 mb-2">Comments:</h4>
                        <div class="comment-list space-y-2">
                            <?php if (empty($activity['comments'])): ?>
                                <p class="text-gray-600 text-sm">No comments yet. Be the first!</p>
                            <?php else: ?>
                                <?php foreach ($activity['comments'] as $comment): ?>
                                    <div class="flex text-sm text-gray-700">
                                        <span class="font-semibold mr-1"><?php echo htmlspecialchars($comment['username']); ?>:</span>
                                        <span><?php echo htmlspecialchars($comment['comment_text']); ?></span>
                                        <span class="ml-auto text-gray-500 text-xs"><?php echo date('M d, H:i', strtotime($comment['created_at'])); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <form class="add-comment-form mt-3" data-activity-id="<?php echo $activity['activity_id']; ?>">
                            <textarea name="comment_text" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-sm focus:ring-green-500 focus:border-green-500" placeholder="Add a comment..."></textarea>
                            <button type="submit" class="mt-2 bg-green-500 text-white px-3 py-1 rounded-md text-sm hover:bg-green-600">Post Comment</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

        <div id="leaderboard" class="section mt-12 mb-12">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Leaderboard</h2>
                <?php
                // Fetch leaderboard data
                $leaderboard_data = [];
                // Check if $conn is still open before preparing statement for leaderboard
                if ($conn->ping()) {
                    $stmt_leaderboard = $conn->prepare("
                        SELECT
                            u.user_id,
                            u.username,
                            u.xp,
                            u.current_streak_days,
                            COALESCE(SUM(a.trees_planted), 0) AS total_trees_planted
                        FROM
                            users u
                        LEFT JOIN
                            activities a ON u.user_id = a.user_id
                        GROUP BY
                            u.user_id, u.username, u.xp, u.current_streak_days
                        ORDER BY
                            u.xp DESC, total_trees_planted DESC
                        LIMIT 10
                    ");
                    if ($stmt_leaderboard) {
                        $stmt_leaderboard->execute();
                        $result_leaderboard = $stmt_leaderboard->get_result();
                        while ($row = $result_leaderboard->fetch_assoc()) {
                            $leaderboard_data[] = $row;
                        }
                        $stmt_leaderboard->close();
                    } else {
                        error_log("Error preparing leaderboard statement: " . $conn->error);
                    }
                } else {
                    error_log("Database connection was closed before fetching leaderboard data.");
                }

                if (empty($leaderboard_data)): ?>
                    <p class="text-gray-600 text-center">No users on the leaderboard yet. Start planting!</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white rounded-lg overflow-hidden shadow-md">
                            <thead class="bg-green-100 text-green-800">
                                <tr>
                                    <th class="py-3 px-4 text-left text-sm font-semibold uppercase">Rank</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold uppercase">User</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold uppercase">XP</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold uppercase">Trees Planted</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold uppercase">Streak</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($leaderboard_data as $entry): ?>
                                <tr class="<?php echo ($rank % 2 == 0) ? 'bg-gray-50' : 'bg-white'; ?> border-b border-gray-200">
                                    <td class="py-3 px-4 text-sm text-gray-900"><?php echo $rank++; ?></td>
                                    <td class="py-3 px-4 text-sm font-medium text-green-700">
                                        <a href="profile.php?user_id=<?php echo htmlspecialchars($entry['user_id']); ?>" class="hover:underline">
                                            <?php echo htmlspecialchars($entry['username']); ?>
                                        </a>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($entry['xp']); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($entry['total_trees_planted']); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($entry['current_streak_days']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-10 text-center">
            <a href="logout.php" class="inline-block bg-red-500 text-white px-8 py-3 rounded-lg shadow-md hover:bg-red-600 transition duration-300 font-semibold text-lg">
                Logout
            </a>
        </div>
    </div>
    <?php
    // Close database connection if it exists and is open - KEEP ONLY THIS ONE
    if (isset($conn) && !$conn->connect_error) {
        $conn->close();
    }
    ob_end_flush();
    ?>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Smooth scroll for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // JavaScript to show/hide 'Other Species' input field
        const treeSpeciesSelect = document.getElementById('tree_species');
        const otherSpeciesField = document.getElementById('otherSpeciesField');
        const otherSpeciesInput = document.getElementById('other_species');

        function toggleOtherSpeciesField() {
            if (treeSpeciesSelect.value === 'Other') {
                otherSpeciesField.classList.remove('hidden');
                otherSpeciesInput.setAttribute('required', 'required'); // Make it required when visible
            } else {
                otherSpeciesField.classList.add('hidden');
                otherSpeciesInput.removeAttribute('required'); // Remove required when hidden
                otherSpeciesInput.value = ''; // Clear its value when hidden
            }
        }

        // Add event listener for when the select value changes
        treeSpeciesSelect.addEventListener('change', toggleOtherSpeciesField);

        // Call it once on page load to handle initial state (e.g., if form was submitted with error)
        window.addEventListener('load', toggleOtherSpeciesField);

        // Function to handle like/unlike
        document.querySelectorAll('.like-button').forEach(button => {
            button.addEventListener('click', function() {
                const activityId = this.dataset.activityId;
                const likeCountSpan = this.querySelector('.like-count');
                const isLiked = this.classList.contains('text-blue-600'); // Check if it's currently liked

                fetch('/treehero/api_interactions.php', { // This file handles the API logic
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: isLiked ? 'unlike' : 'like',
                        activity_id: activityId
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        likeCountSpan.textContent = data.new_like_count;
                        if (isLiked) {
                            button.classList.remove('text-blue-600');
                            button.classList.add('text-gray-500');
                        } else {
                            button.classList.remove('text-gray-500');
                            button.classList.add('text-blue-600');
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });

        // Function to toggle comments section visibility
        document.querySelectorAll('.comment-toggle-button').forEach(button => {
            button.addEventListener('click', function() {
                const activityId = this.dataset.activityId;
                const commentsSection = document.getElementById(`comments-section-${activityId}`);
                commentsSection.classList.toggle('hidden');
            });
        });

        // Function to handle adding a new comment
        document.querySelectorAll('.add-comment-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const activityId = this.dataset.activityId;
                const commentTextarea = this.querySelector('textarea[name="comment_text"]');
                const commentText = commentTextarea.value.trim();
                const commentList = this.closest('.comments-section').querySelector('.comment-list');
                const commentCountSpan = document.querySelector(`.comment-toggle-button[data-activity-id="${activityId}"] .comment-count`);

                if (!commentText) {
                    alert('Comment cannot be empty.');
                    return;
                }

                fetch('api_interactions.php', { // This file handles the API logic
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'add_comment',
                        activity_id: activityId,
                        comment_text: commentText
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Add the new comment to the list
                        const newCommentHtml = `
                            <div class="flex text-sm text-gray-700">
                                <span class="font-semibold mr-1">${data.username}:</span>
                                <span>${data.comment_text}</span>
                                <span class="ml-auto text-gray-500 text-xs">${data.created_at}</span>
                            </div>
                        `;
                        // If there was a "No comments yet" message, remove it
                        const noCommentsMessage = commentList.querySelector('p.text-gray-600');
                        if (noCommentsMessage) {
                            noCommentsMessage.remove();
                        }
                        commentList.insertAdjacentHTML('beforeend', newCommentHtml);
                        commentTextarea.value = ''; // Clear the textarea
                        commentCountSpan.textContent = data.new_comment_count; // Update comment count

                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });
    </script>
</body>
</html>