<?php
// Enable error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Testing supplier_dashboard.php components...<br><br>";

session_start();
echo "✅ Session started<br>";

// Simulate logged in supplier
$_SESSION['role'] = 'Supplier';
$_SESSION['userID'] = 1; // Change this to actual supplier userID
$_SESSION['username'] = 'Test Supplier';
echo "✅ Session variables set<br>";

require_once 'db_config.php';
echo "✅ Database connected<br>";

// Test query
$result = $conn->query("SELECT * FROM tbl_user LIMIT 1");
if ($result) {
    echo "✅ Can query tbl_user<br>";
} else {
    echo "❌ Error querying tbl_user: " . $conn->error . "<br>";
}

// Test tbl_supplier
$result = $conn->query("SELECT * FROM tbl_supplier LIMIT 1");
if ($result) {
    echo "✅ Can query tbl_supplier<br>";
} else {
    echo "❌ Error querying tbl_supplier: " . $conn->error . "<br>";
}

// Test tbl_restock_request
$result = $conn->query("SELECT * FROM tbl_restock_request LIMIT 1");
if ($result) {
    echo "✅ Can query tbl_restock_request<br>";
} else {
    echo "❌ Error querying tbl_restock_request: " . $conn->error . "<br>";
}

// Test tbl_notification
$result = $conn->query("SELECT * FROM tbl_notification LIMIT 1");
if ($result) {
    echo "✅ Can query tbl_notification<br>";
} else {
    echo "❌ Error querying tbl_notification: " . $conn->error . "<br>";
}

echo "<br><h3>If all tests pass, the issue might be with specific queries in supplier_dashboard.php</h3>";
echo "<p><a href='supplier_dashboard.php'>Try supplier_dashboard.php</a></p>";

$conn->close();
?>