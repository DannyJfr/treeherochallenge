<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'includes/config.php'; // Contains $conn
require_once 'includes/user.php'; // User class, for general utility if needed

// Get the user ID from the URL (e.g., profile.php?user_id=X)
$profile_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

// If no user_id is provided, or it's invalid, redirect to current user's dashboard or error page
if (!$profile_user_id) {
    header('Location: dashboard.php'); // Redirect to current user's dashboard if no ID provided
    exit();
}

// Variables for the profile user's data
$profileUserData = [
    'username' => 'Unknown User',
    'treesPlanted' => 0,
    'species' => 0,
    'totalXP' => 0,
    'level' => 1,
    'currentXP' => 0,
    'requiredXP' => 100,
    'badgesEarned' => 0,
    'streakDays' => 0,
    'co2Offset' => 0
];
$profileRecentActivities = [];
$profileEarnedBadgeIds = [];

// --- Fetch Profile User's Basic Data (Username, XP, Streak) ---
$stmt_profile_user_data = $conn->prepare("SELECT username, xp, current_streak_days FROM users WHERE user_id = ?");
if ($stmt_profile_user_data) {
    $stmt_profile_user_data->bind_param("i", $profile_user_id);
    $stmt_profile_user_data->execute();
    $result_profile_user_data = $stmt_profile_user_data->get_result();
    if ($row_profile_user_data = $result_profile_user_data->fetch_assoc()) {
        $profileUserData['username'] = htmlspecialchars($row_profile_user_data['username']);
        $profileUserData['totalXP'] = (int)$row_profile_user_data['xp'];
        $profileUserData['streakDays'] = (int)$row_profile_user_data['current_streak_days'];
    } else {
        // User ID not found, redirect to an error page or dashboard
        header('Location: dashboard.php?error=' . urlencode('Profile not found.'));
        exit();
    }
    $stmt_profile_user_data->close();
} else {
    error_log("Error fetching profile user data: " . $conn->error);
    // Handle error, e.g., redirect
    header('Location: dashboard.php?error=' . urlencode('Database error fetching profile.'));
    exit();
}

// --- Fetch Profile User's Activity Stats (Trees Planted, Distinct Species) ---
$stmt_profile_stats = $conn->prepare("SELECT SUM(trees_planted) as total_trees, GROUP_CONCAT(DISTINCT tree_species) as distinct_species FROM activities WHERE user_id = ?");
if ($stmt_profile_stats) {
    $stmt_profile_stats->bind_param("i", $profile_user_id);
    $stmt_profile_stats->execute();
    $result_profile_stats = $stmt_profile_stats->get_result();
    $stats = $result_profile_stats->fetch_assoc();
    $stmt_profile_stats->close();

    $profileUserData['treesPlanted'] = (int)$stats['total_trees'];
    $profileUserData['co2Offset'] = $profileUserData['treesPlanted'] * 10; 
    
    if (!empty($stats['distinct_species'])) {
        $profileUserData['species'] = count(array_map('trim', explode(',', $stats['distinct_species'])));
    }
} else {
    error_log("Error fetching profile activity stats: " . $conn->error);
}

// Level calculation based on persistent totalXP for profile user
$profileUserData['level'] = floor($profileUserData['totalXP'] / 500) + 1; 
$profileUserData['requiredXP'] = ($profileUserData['level']) * 500;
$profileUserData['currentXP'] = $profileUserData['totalXP'] % 500;


// --- Fetch ALL Achievement Definitions from Database (for displaying all badges) ---
$badges = []; 
$stmt_all_achievements = $conn->query("SELECT * FROM achievements ORDER BY xp_reward ASC");
if ($stmt_all_achievements) {
    while ($row = $stmt_all_achievements->fetch_assoc()) {
        $badges[] = $row;
    }
} else {
    error_log("Error fetching all achievement definitions: " . $conn->error);
}

// --- Fetch Profile User's EARNED Achievements from Database ---
$stmt_profile_earned_badges = $conn->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = ?");
if ($stmt_profile_earned_badges) {
    $stmt_profile_earned_badges->bind_param("i", $profile_user_id);
    $stmt_profile_earned_badges->execute();
    $result_profile_earned_badges = $stmt_profile_earned_badges->get_result();
    while ($row = $result_profile_earned_badges->fetch_assoc()) {
        $profileEarnedBadgeIds[] = $row['achievement_id'];
    }
    $stmt_profile_earned_badges->close();
} else {
    error_log("Error fetching profile user's earned badges: " . $conn->error);
}
$profileUserData['badgesEarned'] = count($profileEarnedBadgeIds); 


// --- Fetch Profile User's Recent Activities ---
$stmt_profile_activities = $conn->prepare("SELECT activity_date, trees_planted, tree_species, description, created_at FROM activities WHERE user_id = ? ORDER BY activity_date DESC, created_at DESC LIMIT 5");
if ($stmt_profile_activities) {
    $stmt_profile_activities->bind_param("i", $profile_user_id);
    $stmt_profile_activities->execute();
    $result_profile_activities = $stmt_profile_activities->get_result();
    while ($row = $result_profile_activities->fetch_assoc()) {
        $profileRecentActivities[] = $row;
    }
    $stmt_profile_activities->close();
} else {
    error_log("Error fetching profile user's recent activities: " . $conn->error);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $profileUserData['username']; ?>'s Profile - Tree Hero Challenge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <style>
        .badge-card {
            min-width: 180px; 
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
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-100 min-h-screen font-sans text-gray-800">
    <!-- Navigation -->
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
                    <a href="dashboard.php" class="nav-btn text-gray-700 hover:text-green-600 font-medium">Your Dashboard</a>
                    <?php if ($profile_user_id == $_SESSION['user_id']): ?>
                        <a href="#plant" class="nav-btn text-gray-700 hover:text-green-600 font-medium">Log Activity</a>
                    <?php endif; ?>
                    <a href="#achievements" class="nav-btn text-gray-700 hover:text-green-600 font-medium">Achievements</a>
                    <a href="#recent-activities" class="nav-btn text-gray-700 hover:text-green-600 font-medium">Activities</a>
                    <a href="logout.php" class="nav-btn text-red-500 hover:text-red-600 font-medium">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <!-- User Profile Header -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-12">
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 p-6 text-white">
                <div class="flex items-center space-x-4">
                    <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center">
                        <i data-lucide="user" class="w-10 h-10"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold"><?php echo $profileUserData['username']; ?></h2>
                        <p class="text-green-100">Level <?php echo $profileUserData['level']; ?> - Tree Guardian</p>
                        <div class="flex items-center space-x-4 mt-2">
                            <span class="text-sm">🌳 <?php echo $profileUserData['treesPlanted']; ?> Trees Planted</span>
                            <span class="text-sm">🏆 <?php echo $profileUserData['badgesEarned']; ?> Badges Earned</span>
                        </div>
                    </div>
                </div>
                
                <!-- Level Progress Bar (for self-profile only, or simplified for others) -->
                <?php if ($profile_user_id == $_SESSION['user_id']): ?>
                <div class="mt-6">
                    <div class="flex justify-between text-sm mb-2">
                        <span>Progress to Level <?php echo ($profileUserData['level'] + 1); ?></span>
                        <span><?php echo $profileUserData['currentXP']; ?> / <?php echo $profileUserData['requiredXP']; ?> XP</span>
                    </div>
                    <div class="w-full bg-white/20 rounded-full h-3">
                        <div class="bg-white h-3 rounded-full" style="width: <?php echo ($profileUserData['currentXP'] / $profileUserData['requiredXP'] * 100); ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Stats Grid -->
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600"><?php echo $profileUserData['treesPlanted']; ?></div>
                        <div class="text-sm text-gray-600">Trees Planted</div>
                    </div>
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600"><?php echo $profileUserData['species']; ?></div>
                        <div class="text-sm text-gray-600">Species</div>
                    </div>
                    <div class="text-center p-4 bg-purple-50 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600"><?php echo $profileUserData['streakDays']; ?></div>
                        <div class="text-sm text-gray-600">Days Streak</div>
                    </div>
                    <div class="text-center p-4 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600"><?php echo $profileUserData['co2Offset']; ?>kg</div>
                        <div class="text-sm text-gray-600">CO₂ Offset</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Achievements Section -->
        <div id="achievements" class="section mt-12 mb-12">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-2xl font-bold text-gray-900 mb-6 text-center"><?php echo $profileUserData['username']; ?>'s Achievements</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 justify-items-center">
                    <?php foreach ($badges as $badge): ?>
                    <div class="badge-card <?php echo in_array($badge['achievement_id'], $profileEarnedBadgeIds) ? 'earned' : 'locked'; ?>">
                        <div class="w-12 h-12 bg-<?php echo htmlspecialchars($badge['color_class']); ?>-500 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="<?php echo htmlspecialchars($badge['icon_name']); ?>" class="w-6 h-6 text-white"></i>
                        </div>
                        <div class="badge-name font-bold text-lg mb-2"><?php echo htmlspecialchars($badge['name']); ?></div>
                        <div class="badge-desc text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($badge['description']); ?></div>
                        <div class="badge-status text-sm font-medium <?php echo in_array($badge['achievement_id'], $profileEarnedBadgeIds) ? 'text-green-600' : 'text-gray-500'; ?>">
                            <?php echo in_array($badge['achievement_id'], $profileEarnedBadgeIds) ? 'Earned!' : 'Locked'; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activities Section -->
        <div id="recent-activities" class="p-6 bg-blue-50 rounded-xl shadow-lg mt-12 mb-12">
            <h2 class="text-2xl font-semibold text-gray-900 mb-4 text-center"><?php echo $profileUserData['username']; ?>'s Recent Activities</h2>
            <?php if (empty($profileRecentActivities)): ?>
                <p class="text-gray-600 text-center"><?php echo $profileUserData['username']; ?> hasn't logged any activities yet.</p>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($profileRecentActivities as $activity): ?>
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
                                <p class="text-sm text-gray-600 italic">"<?php echo htmlspecialchars($activity['description']); }"</p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Link back to Dashboard -->
        <div class="mt-10 text-center">
            <a href="dashboard.php" class="inline-block bg-green-500 text-white px-8 py-3 rounded-lg shadow-md hover:bg-green-600 transition duration-300 font-semibold text-lg">
                Back to Your Dashboard
            </a>
            <a href="logout.php" class="inline-block bg-red-500 text-white px-8 py-3 rounded-lg shadow-md hover:bg-red-600 transition duration-300 font-semibold text-lg ml-4">
                Logout
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
