<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Database connection
require_once 'includes/config.php'; 

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];

if (!isset($data['action']) || !isset($data['activity_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$activity_id = filter_var($data['activity_id'], FILTER_VALIDATE_INT);
if (!$activity_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid activity ID']);
    exit();
}

switch ($data['action']) {
    case 'like':
        // Add like
        $stmt = $conn->prepare("INSERT IGNORE INTO activity_likes (activity_id, user_id) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ii", $activity_id, $user_id);
            if ($stmt->execute()) {
                // Get updated like count
                $like_count = getLikeCount($conn, $activity_id);
                echo json_encode(['success' => true, 'new_like_count' => $like_count]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add like']);
            }
            $stmt->close();
        }
        break;

    case 'unlike':
        // Remove like
        $stmt = $conn->prepare("DELETE FROM activity_likes WHERE activity_id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $activity_id, $user_id);
            if ($stmt->execute()) {
                // Get updated like count
                $like_count = getLikeCount($conn, $activity_id);
                echo json_encode(['success' => true, 'new_like_count' => $like_count]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove like']);
            }
            $stmt->close();
        }
        break;

    case 'add_comment':
        if (!isset($data['comment_text']) || trim($data['comment_text']) === '') {
            echo json_encode(['success' => false, 'message' => 'Comment text is required']);
            exit();
        }

        $comment_text = trim($data['comment_text']);
        
        // Insert comment
        $stmt = $conn->prepare("INSERT INTO activity_comments (activity_id, user_id, comment_text) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iis", $activity_id, $user_id, $comment_text);
            if ($stmt->execute()) {
                // Get user's username
                $stmt_user = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
                $stmt_user->bind_param("i", $user_id);
                $stmt_user->execute();
                $result = $stmt_user->get_result();
                $user = $result->fetch_assoc();
                $stmt_user->close();

                // Get updated comment count
                $stmt_count = $conn->prepare("SELECT COUNT(*) FROM activity_comments WHERE activity_id = ?");
                $stmt_count->bind_param("i", $activity_id);
                $stmt_count->execute();
                $stmt_count->bind_result($new_comment_count);
                $stmt_count->fetch();
                $stmt_count->close();

                echo json_encode([
                    'success' => true,
                    'new_comment_count' => $new_comment_count, // ADDED THIS LINE
                    'username' => htmlspecialchars($user['username']), // Added htmlspecialchars for safety
                    'comment_text' => htmlspecialchars($comment_text), // Added htmlspecialchars for safety
                    'created_at' => date('M d, H:i')
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
            }
            $stmt->close();
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// Helper function to get current like count
function getLikeCount($conn, $activity_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM activity_likes WHERE activity_id = ?");
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'];
}