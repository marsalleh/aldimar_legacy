<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

echo "--- tbl_supplier ---\n";
$result = $conn->query("DESCRIBE tbl_supplier");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "tbl_supplier does not exist.\n";
}

echo "\n--- tbl_restock_request ---\n";
$result = $conn->query("DESCRIBE tbl_restock_request");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "tbl_restock_request does not exist.\n";
}

$conn->close();
?>