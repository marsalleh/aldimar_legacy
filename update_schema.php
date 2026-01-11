<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "aldimar_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add is_read column if it doesn't exist
$sql = "ALTER TABLE tbl_notification ADD COLUMN is_read TINYINT(1) DEFAULT 0";
if ($conn->query($sql) === TRUE) {
    echo "Column is_read added successfully";
} else {
    echo "Error adding column: " . $conn->error;
}

$conn->close();
?>