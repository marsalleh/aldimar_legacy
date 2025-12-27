<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "ALTER TABLE Tbl_inventory DROP COLUMN tags";

if ($conn->query($sql) === TRUE) {
    echo "Column 'tags' dropped successfully from Tbl_inventory";
} else {
    echo "Error dropping column: " . $conn->error;
}

$conn->close();
?>