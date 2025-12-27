<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Tbl_salesrecord Columns ===\n";
$result = $conn->query("SHOW COLUMNS FROM Tbl_salesrecord");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - Null: " . $row['Null'] . "\n";
}

echo "\n=== Tbl_salesrecord Create Statement ===\n";
$createRes = $conn->query("SHOW CREATE TABLE Tbl_salesrecord");
$row = $createRes->fetch_assoc();
echo $row['Create Table'] . "\n";

$conn->close();
?>