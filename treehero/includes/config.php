<?php
// Database configuration
define('DB_SERVER', 'localhost'); // Your database server, usually 'localhost'
define('DB_USERNAME', 'Danny'); // Your database username
define('DB_PASSWORD', 'Danny'); // Your database password
define('DB_NAME', 'treehero'); // The name of your database

// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
