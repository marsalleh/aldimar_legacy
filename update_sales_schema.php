<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error)
    die("Connect failed: " . $conn->connect_error);

// 1. ALTER TABLE to change `date` column to DATETIME
// Existing dates like '2025-12-27' will naturally become '2025-12-27 00:00:00'
$sql = "ALTER TABLE tbl_salesrecord MODIFY COLUMN date DATETIME NOT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Table tbl_salesrecord modified successfully. Column 'date' is now DATETIME.";
} else {
    echo "Error updating table: " . $conn->error;
}
$conn->close();
?>