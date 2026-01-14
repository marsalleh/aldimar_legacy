<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

echo "--- tbl_inventory ---\n";
$result = $conn->query("DESCRIBE tbl_inventory");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "tbl_inventory does not exist.\n";
}

$conn->close();
?>