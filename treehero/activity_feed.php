<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'includes/config.php'; // Contains $conn
require_once 'includes/user.php';   // For User class (if needed for profile pics etc.)

$current_user_id = $_SESSION['user_id']; // Needed for checking if current user has liked
$activity_feed = [];
$feed_error = '';

// --- Fetch All Recent Activities for the Global Feed ---
// Fetch activities from all users, along with their associated user details
$stmt_feed_activities = $conn->prepare("
    SELECT 
        a.activity_id, 
        a.activity_date, 
        a.trees_planted, 
        a.tree_species, 
        a.description, 
        a.created_at,
        u.user_id,
        u.username,
        u.profile_picture_url,
        (SELECT COUNT(*) FROM activity_comments ac WHERE ac.activity_id = a.activity_id) AS comment_count,
        (SELECT COUNT(*) FROM activity_likes al WHERE al.activity_id = a.activity_id) AS like_count,
        (SELECT COUNT(*) FROM activity_likes al2 WHERE al2.activity_id = a.activity_id AND al2.user_id = ?) AS user_has_liked
    FROM 
        activities a
    JOIN 
        users u ON a.user_id = u.user_id
    ORDER BY 
        a.created_at DESC -- Order by creation timestamp for true recent feed
    LIMIT 20 -- Limit the number of activities shown in the feed
");

if ($stmt_feed_activities) {
    $stmt_feed_activities->bind_param("i", $current_user_id); // Bind the current user ID for user_has_liked subquery
    $stmt_feed_activities->execute();
    $result_feed_activities = $stmt_feed_activities->get_result();
    while ($row = $result_feed_activities->fetch_assoc()) {
        $activity_feed[] = $row;
    }
    $stmt_feed_activities->close();
} else {
    $feed_error = "Error fetching activity feed: " . $conn->error;
    error_log($feed_error);
}

// --- Handle Like/Unlike Activity from this page ---
// This logic is duplicated from dashboard.php and activity.php to allow likes directly on the feed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_id_for_interaction = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);

    if ($activity_id_for_interaction) {
        if (isset($_POST['like_activity'])) {
            $stmt_like = $conn->prepare("INSERT IGNORE INTO activity_likes (activity_id, user_id) VALUES (?, ?)");
            if ($stmt_like) {
                $stmt_like->bind_param("ii", $activity_id_for_interaction, $current_user_id);
                $stmt_like->execute();
                // No need to check affected_rows, just redirect to refresh the page
                $stmt_like->close();
            } else {
                error_log("Error preparing like statement on feed: " . $conn->error);
            }
        } elseif (isset($_POST['unlike_activity'])) {
            $stmt_unlike = $conn->prepare("DELETE FROM activity_likes WHERE activity_id = ? AND user_id = ?");
            if ($stmt_unlike) {
                $stmt_unlike->bind_param("ii", $activity_id_for_interaction, $current_user_id);
                $stmt_unlike->execute();
                $stmt_unlike->close();
            } else {
                error_log("Error preparing unlike statement on feed: " . $conn->error);
            }
        }
        // Redirect to prevent form re-submission and refresh feed
        header('Location: activity_feed.php');
        exit();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Feed - Tree Hero Challenge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <style>
        .profile-thumb {
            width: 32px;
            height: 32px;
            border-radius: 9999px; /* Tailwind's rounded-full */
            object-fit: cover;
            border: 1px solid #e5e7eb; /* Tailwind gray-200 */
        }
        .activity-card {
            background-color: white;
            border-radius: 0.75rem; /* rounded-xl */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-lg */
            padding: 1.5rem; /* p-6 */
            margin-bottom: 1.5rem; /* mb-6 */
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
                    <a href="activity_feed.php" class="nav-btn text-green-600 font-bold">Activity Feed</a>
                    <a href="logout.php" class="nav-btn text-red-500 hover:text-red-600 font-medium">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Global Activity Feed</h2>

            <?php if (!empty($feed_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($feed_error); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($activity_feed)): ?>
                <p class="text-gray-600 text-center text-lg">No activities to show yet. Be the first to plant!</p>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($activity_feed as $activity): ?>
                        <div class="activity-card">
                            <div class="flex items-center mb-3">
                                <?php if ($activity['profile_picture_url']): ?>
                                    <img src="<?php echo htmlspecialchars($activity['profile_picture_url']); ?>" alt="Profile" class="profile-thumb mr-3">
                                <?php else: ?>
                                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center mr-3 text-gray-500">
                                        <i data-lucide="user" class="w-4 h-4"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="font-semibold text-gray-800">
                                    <a href="profile.php?user_id=<?php echo htmlspecialchars($activity['user_id']); ?>" class="hover:underline">
                                        <?php echo htmlspecialchars($activity['username']); ?>
                                    </a>
                                </span>
                                <span class="text-sm text-gray-500 ml-auto"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></span>
                            </div>
                            
                            <p class="text-lg font-semibold text-green-700 mb-2">
                                Planted <?php echo htmlspecialchars($activity['trees_planted']); ?> tree<?php echo $activity['trees_planted'] != 1 ? 's' : ''; ?> 
                                (<?php echo htmlspecialchars($activity['tree_species']); ?>) on <?php echo date('M d, Y', strtotime($activity['activity_date'])); ?>
                            </p>
                            <?php if (!empty($activity['description'])): ?>
                                <p class="text-gray-700 italic mb-3">"<?php echo htmlspecialchars($activity['description']); ?>"</p>
                            <?php endif; ?>

                            <!-- Interaction Bar: Likes and Comments -->
                            <div class="flex items-center space-x-4 text-gray-600 text-sm mt-3 pt-3 border-t border-gray-100">
                                <form action="activity_feed.php" method="POST" class="flex items-center">
                                    <input type="hidden" name="activity_id" value="<?php echo htmlspecialchars($activity['activity_id']); ?>">
                                    <?php if ((bool)$activity['user_has_liked']): ?>
                                        <button type="submit" name="unlike_activity" class="flex items-center space-x-1 text-blue-600">
                                            <i data-lucide="heart" class="w-4 h-4 fill-current"></i>
                                            <span><?php echo htmlspecialchars($activity['like_count']); ?> Like<?php echo $activity['like_count'] != 1 ? 's' : ''; ?></span>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="like_activity" class="flex items-center space-x-1 hover:text-blue-500">
                                            <i data-lucide="heart" class="w-4 h-4"></i>
                                            <span><?php echo htmlspecialchars($activity['like_count']); ?> Like<?php echo $activity['like_count'] != 1 ? 's' : ''; ?></span>
                                        </button>
                                    <?php endif; ?>
                                </form>
                                <a href="activity.php?activity_id=<?php echo htmlspecialchars($activity['activity_id']); ?>" class="flex items-center space-x-1 hover:text-green-500">
                                    <i data-lucide="message-circle" class="w-4 h-4"></i>
                                    <span><?php echo htmlspecialchars($activity['comment_count']); ?> Comment<?php echo $activity['comment_count'] != 1 ? 's' : ''; ?></span>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
