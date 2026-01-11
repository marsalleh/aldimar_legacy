<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';

echo "<h2>Checking Table Names</h2>";

$result = $conn->query("SHOW TABLES");
echo "<h3>Tables in database:</h3><ul>";
while ($row = $result->fetch_array()) {
    echo "<li><strong>" . $row[0] . "</strong></li>";
}
echo "</ul>";

echo "<hr><h3>Testing queries with different case:</h3>";

// Test with tbl_user (capital T)
echo "<p>Testing: SELECT * FROM tbl_user LIMIT 1</p>";
$result1 = $conn->query("SELECT * FROM tbl_user LIMIT 1");
if ($result1) {
    echo "<p style='color:green'>✅ tbl_user works!</p>";
} else {
    echo "<p style='color:red'>❌ tbl_user failed: " . $conn->error . "</p>";
}

// Test with tbl_user (lowercase t)
echo "<p>Testing: SELECT * FROM tbl_user LIMIT 1</p>";
$result2 = $conn->query("SELECT * FROM tbl_user LIMIT 1");
if ($result2) {
    echo "<p style='color:green'>✅ tbl_user works!</p>";
} else {
    echo "<p style='color:red'>❌ tbl_user failed: " . $conn->error . "</p>";
}

$conn->close();
?>