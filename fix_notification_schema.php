<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if it's already datetime (optional, but good practice). But here we force modify.
$sql = "ALTER TABLE tbl_notification MODIFY dateSent DATETIME";

if ($conn->query($sql) === TRUE) {
    echo "Table tbl_notification altered successfully. dateSent is now DATETIME.\n";
} else {
    echo "Error altering table: " . $conn->error . "\n";
}

$conn->close();
?>