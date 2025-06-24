<?php

class User {
    private $conn;

    // Constructor to initialize the database connection
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    // Register a new user
    public function register($username, $email, $password) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $initial_xp = 0;
        $initial_streak_days = 0;
        $last_activity_date = null;
        $profile_picture_url = null; // New users start with no profile picture

        $stmt = $this->conn->prepare("INSERT INTO users (username, email, password_hash, xp, current_streak_days, last_activity_date, profile_picture_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return ['success' => false, 'message' => 'Database error preparing registration.'];
        }
        
        // sssiiss: string, string, string, int, int, string (date), string (url)
        $stmt->bind_param("sssiiss", $username, $email, $password_hash, $initial_xp, $initial_streak_days, $last_activity_date, $profile_picture_url); 

        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Registration successful!'];
        } else {
            $error_message = $stmt->error;
            $stmt->close();
            if (strpos($error_message, 'Duplicate entry') !== false) {
                if (strpos($error_message, 'username') !== false) {
                    return ['success' => false, 'message' => 'Username already exists.'];
                } elseif (strpos($error_message, 'email') !== false) {
                    return ['success' => false, 'message' => 'Email already registered.'];
                }
            }
            return ['success' => false, 'message' => 'Error registering user: ' . $error_message];
        }
    }

    // Login a user
    public function login($username_or_email, $password) {
        // Fetch XP, streak days, last activity date, AND profile picture URL for session
        $stmt = $this->conn->prepare("SELECT user_id, username, password_hash, xp, current_streak_days, last_activity_date, profile_picture_url FROM users WHERE username = ? OR email = ?");
        if (!$stmt) {
            error_log("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return ['success' => false, 'message' => 'Database error preparing login.'];
        }

        $stmt->bind_param("ss", $username_or_email, $username_or_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close(); // Close statement after fetching result
            if (password_verify($password, $user['password_hash'])) {
                return [
                    'success' => true, 
                    'user_id' => $user['user_id'], 
                    'username' => $user['username'], 
                    'xp' => $user['xp'],
                    'current_streak_days' => $user['current_streak_days'],
                    'last_activity_date' => $user['last_activity_date'],
                    'profile_picture_url' => $user['profile_picture_url'] // Added profile_picture_url
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid username/email or password.'];
            }
        } else {
            $stmt->close(); // Close statement even if user not found
            return ['success' => false, 'message' => 'Invalid username/email or password.'];
        }
    }

    // Function to update a user's XP
    public function updateXp($user_id, $xp_amount) {
        $stmt = $this->conn->prepare("UPDATE users SET xp = xp + ? WHERE user_id = ?");
        if (!$stmt) {
            error_log("Prepare failed (updateXp): (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("ii", $xp_amount, $user_id); 
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Error updating user XP: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    // Function to update a user's streak and last activity date
    public function updateStreak($user_id, $new_streak_days, $new_last_activity_date) {
        $stmt = $this->conn->prepare("UPDATE users SET current_streak_days = ?, last_activity_date = ? WHERE user_id = ?");
        if (!$stmt) {
            error_log("Prepare failed (updateStreak): (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("isi", $new_streak_days, $new_last_activity_date, $user_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Error updating user streak: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    // New: Function to update a user's profile picture URL
    public function updateProfilePicture($user_id, $picture_url) {
        error_log("Attempting to update profile picture for user_id: " . $user_id . " with URL: " . $picture_url);
        
        $stmt = $this->conn->prepare("UPDATE users SET profile_picture_url = ? WHERE user_id = ?");
        if (!$stmt) {
            error_log("Prepare failed (updateProfilePicture): (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("si", $picture_url, $user_id); // 's' for string (URL), 'i' for int (user_id)
        $result = $stmt->execute();
        
        if ($result) {
            error_log("Successfully updated profile picture URL in database");
            // Update session variable
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                $_SESSION['profile_picture_url'] = $picture_url;
                error_log("Updated session profile picture URL");
            }
            $stmt->close();
            return true;
        } else {
            error_log("Error updating profile picture URL: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}
?>
