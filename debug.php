<?php
// Enable error display to see what's wrong
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Testing login.php...<br>";

// Test if db_config.php can be loaded
if (file_exists('db_config.php')) {
    echo "✅ db_config.php exists<br>";
    require_once 'db_config.php';
    echo "✅ db_config.php loaded successfully<br>";

    if (isset($conn)) {
        echo "✅ Database connection object exists<br>";

        // Test a simple query
        $result = $conn->query("SELECT COUNT(*) as count FROM tbl_user");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✅ Database query works! Found " . $row['count'] . " users<br>";
        } else {
            echo "❌ Query failed: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Connection object not created<br>";
    }
} else {
    echo "❌ db_config.php not found<br>";
}

echo "<br><h3>If you see this, PHP is working!</h3>";
echo "<a href='login.php'>Try login.php again</a>";
?>