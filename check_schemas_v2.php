<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

echo "--- tbl_user ---\n";
$res = $conn->query("SHOW COLUMNS FROM tbl_user");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n--- tbl_supplier ---\n";
$res = $conn->query("SHOW COLUMNS FROM tbl_supplier");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "tbl_supplier might not exist or error.\n";
}

echo "\n--- tbl_notification ---\n";
$res = $conn->query("SHOW COLUMNS FROM tbl_notification");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>