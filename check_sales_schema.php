<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== tbl_salesrecord Columns ===\n";
$result = $conn->query("SHOW COLUMNS FROM tbl_salesrecord");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - Null: " . $row['Null'] . "\n";
}

echo "\n=== tbl_salesrecord Create Statement ===\n";
$createRes = $conn->query("SHOW CREATE TABLE tbl_salesrecord");
$row = $createRes->fetch_assoc();
echo $row['Create Table'] . "\n";

$conn->close();
?>