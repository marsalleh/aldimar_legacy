<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$result = $conn->query("DESCRIBE tbl_inventory");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\n--- tbl_user ---\n";
$result = $conn->query("DESCRIBE tbl_user");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
$conn->close();
?>