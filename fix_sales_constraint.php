<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Modify itemID to allow NULL
$sql1 = "ALTER TABLE tbl_salesrecord MODIFY itemID INT(11) NULL";
if ($conn->query($sql1) === TRUE) {
    echo "1. itemID column modified to allow NULL.\n";
} else {
    echo "Error modifying itemID: " . $conn->error . "\n";
}

// 2. Drop existing Foreign Key
// Note: Verification script showed 'tbl_salesrecord_ibfk_1'
$sql2 = "ALTER TABLE tbl_salesrecord DROP FOREIGN KEY tbl_salesrecord_ibfk_1";
if ($conn->query($sql2) === TRUE) {
    echo "2. Old Foreign Key dropped.\n";
} else {
    echo "Error dropping FK (might not match name 'tbl_salesrecord_ibfk_1'): " . $conn->error . "\n";
}

// 3. Add new Foreign Key with ON DELETE SET NULL
$sql3 = "ALTER TABLE tbl_salesrecord ADD CONSTRAINT tbl_salesrecord_ibfk_1 FOREIGN KEY (itemID) REFERENCES tbl_inventory(itemID) ON DELETE SET NULL";
if ($conn->query($sql3) === TRUE) {
    echo "3. New Foreign Key with ON DELETE SET NULL added.\n";
} else {
    echo "Error adding new FK: " . $conn->error . "\n";
}

$conn->close();
?>