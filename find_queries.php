<?php
$lines = file('c:/xampp/htdocs/aldimar_legacy/record_sales.php');
foreach ($lines as $lineNum => $line) {
    if (stripos($line, 'SELECT') !== false && stripos($line, 'FROM') !== false) {
        echo "Line " . ($lineNum + 1) . ": " . trim($line) . "\n";
    }
}
?>