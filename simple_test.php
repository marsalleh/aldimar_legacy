<?php
// Minimal login test - no sessions, just basic PHP
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>Simple Test</title></head><body>";
echo "<h2>Testing login.php components...</h2>";

// Test 1: Can we include db_config?
echo "<p>Test 1: Loading db_config.php...</p>";
try {
    require_once 'db_config.php';
    echo "<p style='color:green'>✅ db_config.php loaded</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    die();
}

// Test 2: Can we start a session?
echo "<p>Test 2: Starting session...</p>";
try {
    session_start();
    echo "<p style='color:green'>✅ Session started</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test 3: Can we query the database?
echo "<p>Test 3: Querying database...</p>";
try {
    $sql = "SELECT * FROM tbl_user LIMIT 1";
    $result = $conn->query($sql);
    if ($result) {
        echo "<p style='color:green'>✅ Database query successful</p>";
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo "<p>Found user: " . htmlspecialchars($user['username']) . "</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Query failed: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>All tests passed! The issue might be in login.php itself.</h3>";
echo "<p><a href='login.php'>Try login.php</a></p>";
echo "</body></html>";
?>