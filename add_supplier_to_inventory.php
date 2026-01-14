<?php
// Migration Script: Add Supplier Link to Inventory
date_default_timezone_set('Asia/Kuala_Lumpur');
$conn = new mysqli("localhost", "root", "", "aldimar_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Starting migration: Add supplierID to tbl_inventory...\n\n";

// Check if column already exists
$checkColumn = $conn->query("SHOW COLUMNS FROM tbl_inventory LIKE 'supplierID'");
if ($checkColumn->num_rows > 0) {
    echo "✓ Column 'supplierID' already exists. Skipping migration.\n";
    $conn->close();
    exit;
}

// Add supplierID column
echo "1. Adding supplierID column to tbl_inventory...\n";
$addColumn = $conn->query("ALTER TABLE tbl_inventory ADD COLUMN supplierID INT(11) NULL AFTER threshold");

if ($addColumn) {
    echo "   ✓ Column added successfully\n";
} else {
    die("   ✗ Error adding column: " . $conn->error . "\n");
}

// Add foreign key constraint
echo "2. Adding foreign key constraint...\n";
$addFK = $conn->query("ALTER TABLE tbl_inventory 
    ADD CONSTRAINT fk_inventory_supplier 
    FOREIGN KEY (supplierID) REFERENCES tbl_supplier(supplierID) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE");

if ($addFK) {
    echo "   ✓ Foreign key constraint added successfully\n";
} else {
    echo "   ✗ Warning: Could not add foreign key constraint: " . $conn->error . "\n";
    echo "   (This is okay if you want to proceed without the constraint)\n";
}

// Verify the change
echo "\n3. Verifying schema changes...\n";
$verify = $conn->query("DESCRIBE tbl_inventory");
echo "   Current tbl_inventory schema:\n";
while ($row = $verify->fetch_assoc()) {
    echo "   - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n✓ Migration completed successfully!\n";
echo "Note: Existing products will have NULL supplier until manually assigned.\n";

$conn->close();
?>