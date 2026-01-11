<?php
// Enable error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>PHP Configuration Test</h2>";
echo "PHP Version: " . phpversion() . "<br><br>";

echo "<h3>Testing Database Connection...</h3>";

// Test database connection
$host = "localhost";
$username = "u715342185_aldimar_user";
$password = "M4rs030128";  // Your password
$database = "u715342185_aldimar_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo "<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>✅ Database connection successful!</p>";

    // Test if tables exist
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "<h3>Tables in database:</h3><ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
    }

    $conn->close();
}

echo "<br><h3>✅ PHP is working correctly!</h3>";
?>