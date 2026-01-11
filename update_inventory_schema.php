<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "ALTER TABLE tbl_inventory ADD COLUMN tags VARCHAR(255) DEFAULT '' AFTER category";

if ($conn->query($sql) === TRUE) {
    echo "Column 'tags' added successfully to tbl_inventory";
} else {
    // If it already exists, just ignore the error
    if ($conn->errno == 1060) {
        echo "Column 'tags' already exists.";
    } else {
        echo "Error updating table: " . $conn->error;
    }
}

$conn->close();
?>