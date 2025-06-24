<?php
session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get activity ID from URL
$activity_id = filter_var($_GET['activity_id'], FILTER_VALIDATE_INT);
if (!$activity_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch activity details
$stmt = $conn->prepare("SELECT a.*, u.username, 
    (SELECT COUNT(*) FROM activity_likes WHERE activity_id = a.activity_id) as like_count,
    (SELECT COUNT(*) FROM activity_comments WHERE activity_id = a.activity_id) as comment_count,
    (SELECT COUNT(*) > 0 FROM activity_likes WHERE activity_id = a.activity_id AND user_id = ?) as user_has_liked
    FROM activities a 
    JOIN users u ON a.user_id = u.user_id 
    WHERE a.activity_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $activity_id);
$stmt->execute();
$result = $stmt->get_result();
$activity = $result->fetch_assoc();

if (!$activity) {
    header('Location: dashboard.php');
    exit();
}

// Fetch comments for this activity
$stmt_comments = $conn->prepare("SELECT c.*, u.username 
    FROM activity_comments c 
    JOIN users u ON c.user_id = u.user_id 
    WHERE c.activity_id = ? 
    ORDER BY c.created_at DESC");
$stmt_comments->bind_param("i", $activity_id);
$stmt_comments->execute();
$comments = $stmt_comments->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Details - TreeHero</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold">Activity by <?php echo htmlspecialchars($activity['username']); ?></h2>
                <span class="text-gray-500"><?php echo date('M d, Y', strtotime($activity['activity_date'])); ?></span>
            </div>
            <div class="mb-4">
                <p>Trees Planted: <?php echo htmlspecialchars($activity['trees_planted']); ?></p>
                <p>Species: <?php echo htmlspecialchars($activity['tree_species']); ?></p>
                <?php if ($activity['description']): ?>
                    <p class="mt-2"><?php echo htmlspecialchars($activity['description']); ?></p>
                <?php endif; ?>
            </div>
            <div class="flex items-center space-x-4">
                <button class="like-button flex items-center space-x-1" data-activity-id="<?php echo $activity['activity_id']; ?>">
                    <i data-lucide="heart" class="<?php echo $activity['user_has_liked'] ? 'text-red-500' : 'text-gray-500'; ?>"></i>
                    <span class="like-count"><?php echo $activity['like_count']; ?></span>
                </button>
                <div class="flex items-center space-x-1">
                    <i data-lucide="message-circle" class="text-gray-500"></i>
                    <span class="comment-count"><?php echo $activity['comment_count']; ?></span>
                </div>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Comments</h3>
            
            <!-- Add Comment Form -->
            <form class="add-comment-form mb-6" data-activity-id="<?php echo $activity_id; ?>">
                <div class="flex space-x-2">
                    <input type="text" name="comment_text" class="flex-1 border rounded-lg px-4 py-2" placeholder="Add a comment..." required>
                    <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">Post</button>
                </div>
            </form>

            <!-- Comments List -->
            <div class="comments-container space-y-4">
                <?php while ($comment = $comments->fetch_assoc()): ?>
                    <div class="comment border-b pb-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="font-semibold"><?php echo htmlspecialchars($comment['username']); ?></span>
                                <p class="mt-1"><?php echo htmlspecialchars($comment['comment_text']); ?></p>
                            </div>
                            <span class="text-sm text-gray-500"><?php echo date('M d, H:i', strtotime($comment['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Handle likes
        document.querySelectorAll('.like-button').forEach(button => {
            button.addEventListener('click', async () => {
                const activityId = button.dataset.activityId;
                const heartIcon = button.querySelector('[data-lucide="heart"]');
                const likeCount = button.querySelector('.like-count');
                const action = heartIcon.classList.contains('text-red-500') ? 'unlike' : 'like';

                try {
                    const response = await fetch('api_interactions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: action,
                            activity_id: activityId
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        heartIcon.classList.toggle('text-red-500');
                        heartIcon.classList.toggle('text-gray-500');
                        likeCount.textContent = data.new_like_count;
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            });
        });

        // Handle comment submission
        document.querySelectorAll('.add-comment-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const activityId = form.dataset.activityId;
                const commentInput = form.querySelector('input[name="comment_text"]');
                const commentText = commentInput.value.trim();

                if (!commentText) return;

                try {
                    const response = await fetch('api_interactions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'add_comment',
                            activity_id: activityId,
                            comment_text: commentText
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        // Create new comment element
                        const commentHtml = `
                            <div class="comment border-b pb-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="font-semibold">${data.username}</span>
                                        <p class="mt-1">${data.comment_text}</p>
                                    </div>
                                    <span class="text-sm text-gray-500">${data.created_at}</span>
                                </div>
                            </div>
                        `;
                        const commentsContainer = document.querySelector('.comments-container');
                        commentsContainer.insertAdjacentHTML('afterbegin', commentHtml);
                        
                        // Update comment count
                        const commentCount = document.querySelector('.comment-count');
                        commentCount.textContent = data.new_comment_count;
                        
                        // Clear input
                        commentInput.value = '';
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            });
        });
    </script>
</body>
</html>