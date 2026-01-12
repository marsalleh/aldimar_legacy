<?php
$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

// Set MySQL timezone to Malaysia time (GMT+8)
$conn->query("SET time_zone = '+08:00'");
?>