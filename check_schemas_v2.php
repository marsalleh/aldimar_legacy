<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

echo "--- Tbl_user ---\n";
$res = $conn->query("SHOW COLUMNS FROM Tbl_user");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n--- Tbl_supplier ---\n";
$res = $conn->query("SHOW COLUMNS FROM Tbl_supplier");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Tbl_supplier might not exist or error.\n";
}

echo "\n--- Tbl_notification ---\n";
$res = $conn->query("SHOW COLUMNS FROM Tbl_notification");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>