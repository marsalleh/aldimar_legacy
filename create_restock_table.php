<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE TABLE IF NOT EXISTS Tbl_restock_request (
    requestID INT AUTO_INCREMENT PRIMARY KEY,
    itemID INT NULL,
    itemName VARCHAR(100),
    quantity INT,
    status ENUM('Pending', 'Accepted', 'Shipped', 'Delivered', 'Cancelled') DEFAULT 'Pending',
    supplierName VARCHAR(100) NULL,
    requestDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedDate DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (itemID) REFERENCES Tbl_inventory(itemID) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Table Tbl_restock_request created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>