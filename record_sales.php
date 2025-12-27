<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Employee'])) {
  echo "<script>alert('Access denied.'); window.location.href='login.php';</script>";
  exit;
}
date_default_timezone_set('Asia/Kuala_Lumpur');

$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error)
  die("Connection failed: " . $conn->connect_error);

// Handle Add Sale
if (isset($_POST['add_sales'])) {
  $itemID = $_POST['itemID'];
  $date = $_POST['date']; // Y-m-d
  $quantity = $_POST['quantity'];
  $price = $_POST['price']; // User defined sold price

  // Create full timestamp using selected date and current time
  // If date matches today, use current time. If historic date, just append current time or 12:00? 
  // User asked for "Current date and time", implying live recording.
  // We'll append the current H:i:s to the selected Y-m-d to make it unique and sortable.
  $timestamp = $date . ' ' . date('H:i:s');

  $totalPrice = $price * $quantity;

  $conn->begin_transaction();
  try {
    $stmt = $conn->prepare("INSERT INTO Tbl_salesRecord (itemID, date, price, quantity, totalPrice) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdid", $itemID, $timestamp, $price, $quantity, $totalPrice);
    $stmt->execute();

    // Deduct Stock
    $update = $conn->prepare("UPDATE Tbl_inventory SET stockQuantity = stockQuantity - ? WHERE itemID = ?");
    $update->bind_param("ii", $quantity, $itemID);
    $update->execute();

    // Check Threshold
    // Check Threshold & Notify if needed
    // First, check if status changed to Low Stock
    $checkStatus = $conn->query("SELECT stockQuantity, threshold, itemName FROM Tbl_inventory WHERE itemID = $itemID");
    $itemData = $checkStatus->fetch_assoc();

    if ($itemData['stockQuantity'] <= $itemData['threshold']) {
      $conn->query("UPDATE Tbl_inventory SET status = 'Low Stock' WHERE itemID = $itemID");

      // Insert Notification for Admin AND Employee
      $msg = "Low stock alert for " . $itemData['itemName'];
      $conn->query("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES ('$msg', 'Admin', NOW())");
      $conn->query("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES ('$msg', 'Employee', NOW())");
    } else {
      $conn->query("UPDATE Tbl_inventory SET status = 'Available' WHERE itemID = $itemID");
    }

    $conn->commit();
    header("Location: record_sales.php?filter_type=daily&date=$date&added=1");
    exit;
  } catch (Exception $e) {
    $conn->rollback();
    echo "<script>alert('Error while recording sale: " . addslashes($e->getMessage()) . "'); window.location.href='record_sales.php';</script>";
    exit;
  }
}

// Edit Sale
// Edit Sale with Inventory Adjustment
if (isset($_POST['edit_sales'])) {
  $saleID = $_POST['saleID'];
  $newItemID = $_POST['itemID'];
  $newQuantity = $_POST['quantity'];
  $newPrice = $_POST['price'];
  $newTotalPrice = $newPrice * $newQuantity;

  $conn->begin_transaction();
  try {
    // 1. Fetch Original Record
    $origQuery = $conn->query("SELECT * FROM Tbl_salesRecord WHERE saleID = $saleID");
    if ($origQuery->num_rows === 0)
      throw new Exception("Sale record not found.");
    $orig = $origQuery->fetch_assoc();
    $oldItemID = $orig['itemID'];
    $oldQuantity = $orig['quantity'];

    // 2. Revert Old Inventory (Add back old quantity)
    $conn->query("UPDATE Tbl_inventory SET stockQuantity = stockQuantity + $oldQuantity WHERE itemID = $oldItemID");
    // Check status for old item
    $conn->query("UPDATE Tbl_inventory SET status = CASE WHEN stockQuantity <= threshold THEN 'Low Stock' ELSE 'Available' END WHERE itemID = $oldItemID");

    // 3. Deduct New Inventory (Subtract new quantity)
    $conn->query("UPDATE Tbl_inventory SET stockQuantity = stockQuantity - $newQuantity WHERE itemID = $newItemID");
    // Check status for new item
    $conn->query("UPDATE Tbl_inventory SET status = CASE WHEN stockQuantity <= threshold THEN 'Low Stock' ELSE 'Available' END WHERE itemID = $newItemID");

    // 4. Update Sale Record
    $stmt = $conn->prepare("UPDATE Tbl_salesRecord SET itemID=?, price=?, quantity=?, totalPrice=? WHERE saleID=?");
    $stmt->bind_param("idddi", $newItemID, $newPrice, $newQuantity, $newTotalPrice, $saleID);
    $stmt->execute();

    $conn->commit();
    header("Location: record_sales.php?updated=1");
    exit;

  } catch (Exception $e) {
    $conn->rollback();
    echo "<script>alert('Error updating sale: " . addslashes($e->getMessage()) . "'); window.location.href='record_sales.php';</script>";
    exit;
  }
}

// Delete Sale
if (isset($_GET['delete'])) {
  $saleID = intval($_GET['delete']);

  $conn->begin_transaction();
  try {
    // 1. Fetch Sale Details
    $stmt = $conn->prepare("SELECT itemID, quantity FROM Tbl_salesRecord WHERE saleID = ?");
    $stmt->bind_param("i", $saleID);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
      $sale = $res->fetch_assoc();
      $itemID = $sale['itemID'];
      $quantity = $sale['quantity'];

      // 2. Restore Inventory
      $updateStmt = $conn->prepare("UPDATE Tbl_inventory SET stockQuantity = stockQuantity + ? WHERE itemID = ?");
      $updateStmt->bind_param("ii", $quantity, $itemID);
      $updateStmt->execute();

      // 3. Update Status
      $conn->query("UPDATE Tbl_inventory SET status = CASE WHEN stockQuantity <= threshold THEN 'Low Stock' ELSE 'Available' END WHERE itemID = $itemID");

      // 4. Delete Record
      $delStmt = $conn->prepare("DELETE FROM Tbl_salesRecord WHERE saleID = ?");
      $delStmt->bind_param("i", $saleID);
      $delStmt->execute();
    }

    $conn->commit();
    header("Location: record_sales.php?deleted=1");
    exit;

  } catch (Exception $e) {
    $conn->rollback();
    header("Location: record_sales.php?error=delete_failed");
    exit;
  }
}

// Filter Logic
$filterType = $_GET['filter_type'] ?? 'daily'; // daily, monthly, all
$dateFilter = $_GET['date'] ?? date('Y-m-d');
$monthFilter = $_GET['month'] ?? date('Y-m'); // Y-m
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build Query
$conditions = [];
if ($filterType === 'daily') {
  $conditions[] = "DATE(s.date) = '$dateFilter'";
} elseif ($filterType === 'monthly') {
  $conditions[] = "s.date LIKE '$monthFilter%'"; // LIKE works for '2025-12%' on DATETIME
}
// 'all' adds no conditions

// 2. Filter Logic
if (!empty($search)) {
  $conditions[] = "(i.itemName LIKE '%$search%' OR s.price LIKE '%$search%' OR s.totalPrice LIKE '%$search%')";
}

$query = "SELECT s.*, i.itemName FROM Tbl_salesRecord s LEFT JOIN Tbl_inventory i ON s.itemID = i.itemID";

if (!empty($conditions)) {
  $query .= " WHERE " . implode(' AND ', $conditions);
}
// Sort by full datetime
$query .= " ORDER BY s.date DESC, s.saleID DESC";

$sales = $conn->query($query);
$inventory = $conn->query("SELECT itemID, itemName, sellingPrice FROM Tbl_inventory");

// Fetch Data for Sidebar (Profile & Inventory/Suppliers for Restock)
$userID = $_SESSION['userID'];
$userRes = $conn->query("SELECT * FROM Tbl_user WHERE userID = $userID");
$profileData = $userRes->fetch_assoc();



// Handle Profile Update (Sidebar)
if (isset($_POST['update_profile'])) {
  $newUsername = trim($_POST['username']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $password = $_POST['password'];

  if (!empty($password)) {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE Tbl_user SET username=?, email=?, phone=?, password=? WHERE userID=?");
    $stmt->bind_param("ssssi", $newUsername, $email, $phone, $hashed, $userID);
  } else {
    $stmt = $conn->prepare("UPDATE Tbl_user SET username=?, email=?, phone=? WHERE userID=?");
    $stmt->bind_param("sssi", $newUsername, $email, $phone, $userID);
  }

  if ($stmt->execute()) {
    $_SESSION['username'] = $newUsername;
    $_SESSION['username'] = $newUsername;
    header("Location: record_sales.php?profile_updated=1");
    exit();
  } else {
    header("Location: record_sales.php?error=update_failed");
  }
}

// Count Unread Notifications
// Check role for Notification Badge
$role = $_SESSION['role'];
$notifRes = $conn->query("SELECT COUNT(*) as count FROM Tbl_notification WHERE recipientRole = '$role' AND is_read = 0");
$notifCount = $notifRes->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Record Sales - Aldimar Legacy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="css/admin_modals.css">
  <link rel="stylesheet" href="css/admin_sidebar.css">
  <style>
    /* --- Global Styles --- */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background-color: #f4f4f9;
      color: #333;
    }

    .container {
      display: flex;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* Sidebar */
    .sidebar {
      width: 250px;
      background-color: #5e4b8b;
      color: white;
      height: 100vh;
      position: fixed;
      top: 0;
      left: 0;
      padding: 20px;
      transition: transform 0.3s ease;
      z-index: 1000;
      transform: translateX(-100%);
    }

    .sidebar.active {
      transform: translateX(0);
    }

    .sidebar h3 {
      text-align: center;
      margin-bottom: 30px;
      font-size: 22px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
      padding-bottom: 10px;
    }

    .sidebar ul {
      list-style: none;
    }

    .sidebar ul li {
      margin: 15px 0;
    }

    .sidebar ul li a {
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      padding: 12px 15px;
      display: flex;
      align-items: center;
      border-radius: 8px;
      transition: all 0.3s ease;
      font-size: 16px;
    }

    .sidebar ul li a i {
      margin-right: 15px;
      width: 20px;
      text-align: center;
    }

    .sidebar ul li a:hover,
    .sidebar ul li a.active {
      background-color: #7a5dca;
      color: white;
      transform: translateX(5px);
    }

    .bottom-logout {
      position: absolute;
      bottom: 20px;
      width: 80%;
    }

    .bottom-logout a {
      color: white;
      text-decoration: none;
      display: flex;
      align-items: center;
      padding: 10px;
      border-radius: 8px;
      transition: background 0.3s;
    }

    .bottom-logout a:hover {
      background-color: #ff6b6b;
    }

    .bottom-logout i {
      margin-right: 10px;
    }

    /* Main Content */
    .main-content {
      margin-left: 0;
      flex: 1;
      padding: 0;
      transition: margin-left 0.3s ease;
      width: 100%;
    }

    .main-content.shifted {
      margin-left: 250px;
    }

    /* Header */
    header {
      background-color: #5e4b8b;
      padding: 15px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      height: 70px;
      color: white;
    }

    .hamburger {
      font-size: 24px;
      cursor: pointer;
      color: white;
      margin-right: 20px;
    }

    .header-title {
      flex: 1;
      text-align: center;
    }

    .header-title h1 {
      color: white;
      font-size: 24px;
      margin: 0;
      font-weight: 700;
      letter-spacing: 1px;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .user-info i {
      font-size: 20px;
      color: white;
      cursor: pointer;
      transition: color 0.3s;
    }

    .user-info i:hover {
      color: #d1c4e9;
    }

    /* --- Page Specific Styles --- */
    main {
      padding: 30px;
    }

    .content-card {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      flex-wrap: wrap;
      gap: 15px;
    }

    .page-header h2 {
      color: #5e4b8b;
      font-size: 22px;
      margin: 0;
    }

    /* Segmented Filter Bar */
    .filter-wrapper {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      background: #f8f9fa;
      padding: 5px;
      border-radius: 25px;
      border: 1px solid #ddd;
    }

    .filter-segment {
      display: flex;
      background: #eee;
      border-radius: 20px;
      padding: 2px;
    }

    .filter-segment label {
      padding: 6px 15px;
      cursor: pointer;
      border-radius: 18px;
      font-size: 13px;
      font-weight: 600;
      color: #666;
      transition: 0.3s;
    }

    .filter-segment input[type="radio"] {
      display: none;
    }

    .filter-segment input[type="radio"]:checked+label {
      background-color: #5e4b8b;
      color: white;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    .input-group {
      display: flex;
      align-items: center;
      gap: 5px;
      margin-left: 5px;
    }

    .input-group input {
      border: 1px solid #ddd;
      padding: 6px 10px;
      border-radius: 15px;
      outline: none;
      background: white;
      font-family: inherit;
      font-size: 13px;
    }

    .filter-search input {
      border: none;
      background: transparent;
      outline: none;
      width: 150px;
      padding: 6px;
      font-size: 13px;
      border-left: 1px solid #ddd;
      margin-left: 10px;
    }

    .action-group {
      display: flex;
      gap: 10px;
    }

    .btn-primary {
      background-color: #5e4b8b;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 20px;
      cursor: pointer;
      font-weight: bold;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: 0.3s;
    }

    .btn-primary:hover {
      background-color: #4a3c74;
    }

    .btn-secondary {
      background-color: #fff;
      color: #5e4b8b;
      border: 1px solid #5e4b8b;
      padding: 10px 20px;
      border-radius: 20px;
      cursor: pointer;
      font-weight: bold;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: 0.3s;
    }

    .btn-secondary:hover {
      background-color: #f4f4f9;
    }

    /* Table */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    th,
    td {
      padding: 15px;
      text-align: center;
      border-bottom: 1px solid #eee;
    }

    th {
      background-color: #f8f9fa;
      color: #5e4b8b;
      font-weight: 700;
      border-bottom: 2px solid #e9ecef;
      text-transform: uppercase;
      font-size: 13px;
    }

    tbody tr:hover {
      background-color: #fcfcfc;
    }

    .edit-btn,
    .delete-btn {
      border: none;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      color: white;
      transition: 0.2s;
      margin-left: 5px;
    }

    .edit-btn {
      background-color: #3498db;
    }

    .edit-btn:hover {
      background-color: #2980b9;
    }

    .delete-btn {
      background-color: #ff6b6b;
    }

    .delete-btn:hover {
      background-color: #fa5252;
    }

    /* Modals */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      justify-content: center;
      align-items: center;
      z-index: 2000;
    }

    .modal-content {
      background: white;
      padding: 25px;
      border-radius: 12px;
      width: 400px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      text-align: left;
      position: relative;
    }

    .modal-content h3 {
      color: #5e4b8b;
      margin-bottom: 20px;
      text-align: center;
    }

    .modal-content label {
      font-weight: 600;
      font-size: 14px;
      display: block;
      margin-top: 10px;
      color: #555;
    }

    .modal-content input,
    .modal-content select {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ddd;
      border-radius: 6px;
      outline: none;
    }

    .modal-content input:focus,
    .modal-content select:focus {
      border-color: #5e4b8b;
    }

    .modal-buttons {
      margin-top: 25px;
      text-align: right;
    }

    .modal-buttons button {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      margin-left: 10px;
    }

    .btn-cancel {
      background-color: #eee;
      color: #333;
    }

    .btn-save {
      background-color: #5e4b8b;
      color: white;
    }

    .btn-save:hover {
      background-color: #4a3c74;
    }

    .delete-header {
      color: #e74c3c !important;
      margin-bottom: 15px;
      font-weight: bold;
      font-size: 20px;
      text-align: center;
    }

    .delete-text {
      color: #555;
      margin-bottom: 25px;
      font-size: 15px;
      line-height: 1.5;
      text-align: center;
    }

    .confirm-yes {
      background-color: #e74c3c;
      color: white;
      padding: 10px 20px;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      font-weight: bold;
    }

    .confirm-no {
      background-color: #eee;
      color: #333;
      padding: 10px 20px;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      font-weight: 600;
    }

    .delete-actions {
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    #success {
      display: none;
      position: fixed;
      top: 80px;
      left: 50%;
      transform: translateX(-50%);
      background: #4CAF50;
      color: white;
      padding: 10px 20px;
      border-radius: 20px;
      z-index: 2001;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }


    /* User Sidebar Styles */
    .user-sidebar {
      position: fixed;
      top: 0;
      right: -350px;
      /* Hidden by default */
      width: 350px;
      height: 100%;
      background-color: #fff;
      box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
      transition: right 0.3s ease;
      z-index: 1500;
      overflow-y: auto;
      padding: 20px;
    }

    .user-sidebar.active {
      right: 0;
    }

    .user-sidebar h2 {
      color: #5e4b8b;
      font-size: 24px;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 10px;
      border-bottom: 1px solid #eee;
      padding-bottom: 15px;
    }

    .user-sidebar h2 i {
      color: #5e4b8b;
    }

    .sidebar-section {
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }

    .sidebar-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }

    .sidebar-section h3 {
      color: #5e4b8b;
      font-size: 18px;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .sidebar-form label {
      display: block;
      font-weight: 600;
      margin-bottom: 5px;
      color: #555;
      font-size: 14px;
    }

    .sidebar-form input[type="text"],
    .sidebar-form input[type="email"],
    .sidebar-form input[type="tel"],
    .sidebar-form input[type="password"],
    .sidebar-form select,
    .sidebar-form textarea {
      width: 100%;
      padding: 10px 12px;
      margin-bottom: 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      box-sizing: border-box;
    }

    .sidebar-form input:focus,
    .sidebar-form select:focus,
    .sidebar-form textarea:focus {
      border-color: #5e4b8b;
      outline: none;
      box-shadow: 0 0 0 2px rgba(94, 75, 139, 0.2);
    }

    .sidebar-form small {
      display: block;
      color: #888;
      font-size: 12px;
      margin-top: -10px;
      margin-bottom: 10px;
    }

    .btn-sidebar-save {
      background-color: #5e4b8b;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: bold;
      font-size: 15px;
      width: 100%;
      transition: background-color 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-sidebar-save:hover {
      background-color: #4a3c74;
    }

    .overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      z-index: 1000;
      transition: opacity 0.3s ease;
    }

    .overlay.active {
      display: block;
    }

    @media print {

      .sidebar,
      header,
      .page-header,
      #success,
      .action-group,
      .action-btns,
      .user-sidebar,
      .overlay {
        display: none !important;
      }

      .main-content {
        margin-left: 0 !important;
        width: 100% !important;
      }

      .container {
        display: block;
      }

      .content-card {
        box-shadow: none;
        border: none;
        padding: 0;
      }

      table {
        width: 100%;
        border: 1px solid #ccc;
      }

      th,
      td {
        border: 1px solid #ccc;
        padding: 10px;
      }

      th {
        background-color: #eee !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
      }
    }
  </style>
</head>

<body>

  <div class="container">
    <div class="sidebar" id="sidebar">
      <h3><?php echo ($_SESSION['role'] === 'Admin') ? 'Admin Panel' : 'Employee Panel'; ?></h3>
      <ul>
        <?php if ($_SESSION['role'] === 'Admin'): ?>
          <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
          <li><a href="manage_users.php"><i class="fas fa-users"></i> Users</a></li>
          <li><a href="manage_inventory.php"><i class="fas fa-box"></i> Inventory</a></li>
          <li><a href="manage_supplier.php"><i class="fas fa-truck"></i> Suppliers</a></li>
          <li><a href="record_sales.php" class="active"><i class="fas fa-shopping-cart"></i> Sales</a></li>
          <li><a href="report.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <?php else: ?>
          <li><a href="employee_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
          <li><a href="manage_inventory.php"><i class="fas fa-box"></i> Inventory</a></li>
          <li><a href="record_sales.php" class="active"><i class="fas fa-shopping-cart"></i> Sales</a></li>
        <?php endif; ?>
      </ul>
      <div class="bottom-logout">
        <a href="javascript:void(0)" onclick="openLogoutModal()"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>

    <div class="main-content" id="mainContent">
      <header>
        <div class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></div>
        <div class="header-title">
          <a href="<?php echo ($_SESSION['role'] === 'Admin') ? 'admin_dashboard.php' : 'employee_dashboard.php'; ?>"
            style="text-decoration:none; color:white;">
            <h1>ALDIMAR LEGACY</h1>
          </a>
        </div>
      </header>

      <div id="success"></div>

      <!-- User Sidebar -->
      <div class="overlay" id="overlay" onclick="toggleUserSidebar()"></div>
      <div class="user-sidebar" id="userSidebar">
        <h2><i class="fas fa-user-circle"></i> My Account</h2>

        <!-- Profile Section -->
        <div class="sidebar-section">
          <h3><i class="fas fa-edit"></i> Edit Profile</h3>
          <form method="POST" class="sidebar-form">
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($profileData['username'] ?? '') ?>" required>

            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($profileData['email'] ?? '') ?>" required>

            <label>Phone Number</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($profileData['phone'] ?? '') ?>"
              placeholder="e.g. 012-3456789">

            <label>New Password <small>(Leave blank to keep)</small></label>
            <div class="password-container">
              <input type="password" name="password" id="sidebarPassword" placeholder="******">
              <i class="fas fa-eye" id="togglePassword" onclick="toggleSidebarPassword()"></i>
            </div>

            <button type="submit" name="update_profile" class="btn-sidebar-save">Update Profile</button>
          </form>
        </div>

      </div>

      <main>
        <div class="content-card">
          <div class="page-header">
            <h2>Sales Records</h2>

            <form method="GET" id="filterForm" class="filter-wrapper">
              <!-- Filter Type Segmented Control -->
              <div class="filter-segment">
                <input type="radio" name="filter_type" id="type_daily" value="daily" <?= $filterType === 'daily' ? 'checked' : '' ?> onchange="this.form.submit()">
                <label for="type_daily">Daily</label>

                <input type="radio" name="filter_type" id="type_monthly" value="monthly" <?= $filterType === 'monthly' ? 'checked' : '' ?> onchange="this.form.submit()">
                <label for="type_monthly">Monthly</label>

                <input type="radio" name="filter_type" id="type_all" value="all" <?= $filterType === 'all' ? 'checked' : '' ?> onchange="this.form.submit()">
                <label for="type_all">Show All</label>
              </div>

              <!-- Dynamic Inputs -->
              <div class="input-group">
                <?php if ($filterType === 'daily'): ?>
                  <input type="date" name="date" value="<?= $dateFilter ?>" onchange="this.form.submit()"
                    title="Select Date">
                <?php elseif ($filterType === 'monthly'): ?>
                  <input type="month" name="month" value="<?= $monthFilter ?>" onchange="this.form.submit()"
                    title="Select Month">
                <?php endif; ?>
              </div>

              <!-- Search -->
              <div class="filter-search">
                <input type="text" name="search" placeholder="Search item..." value="<?= htmlspecialchars($search) ?>">
                <i class="fas fa-search" onclick="document.getElementById('filterForm').submit()"
                  style="cursor:pointer; color:#888;"></i>
              </div>
            </form>

            <div class="action-group">
              <button class="btn-primary" onclick="document.getElementById('salesModal').style.display='flex'">
                <i class="fas fa-plus"></i> Add Sales
              </button>
              <button class="btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print
              </button>
            </div>
          </div>

          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Sold Price (RM)</th>
                <th>Total Price (RM)</th>
                <th class="action-btns">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($sales->num_rows > 0): ?>
                <?php while ($row = $sales->fetch_assoc()) { ?>
                  <tr>
                    <td><?= $row['date'] ?></td>
                    <td>
                      <?= !empty($row['itemName']) ? htmlspecialchars($row['itemName']) : '<span style="color:red; font-style:italic;">Deleted Item</span>' ?>
                    </td>
                    <td><?= $row['quantity'] ?></td>
                    <td><?= number_format($row['price'], 2) ?></td>
                    <td><?= number_format($row['totalPrice'], 2) ?></td>
                    <td class="action-btns">
                      <button class="edit-btn"
                        onclick="openEditModal(<?= $row['saleID'] ?>, <?= $row['itemID'] ?>, <?= $row['quantity'] ?>, <?= $row['price'] ?>)">Edit</button>
                      <button class="delete-btn" onclick="openDeleteModal(<?= $row['saleID'] ?>)">Delete</button>
                    </td>
                  </tr>
                <?php } ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="padding:20px; color:#777;">No sales records found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </main>
    </div>
  </div>

  <!-- Add Sales Modal -->
  <div class="modal" id="salesModal">
    <div class="modal-content">
      <h3>Record New Sale</h3>
      <form method="POST">
        <label>Select Date</label>
        <input type="date" name="date" required value="<?= date('Y-m-d') ?>">
        <label>Select Item</label>
        <select name="itemID" required onchange="updatePrice(this)">
          <option value="" disabled selected>-- Choose Product --</option>
          <?php $inventory->data_seek(0);
          while ($item = $inventory->fetch_assoc()) { ?>
            <option value="<?= $item['itemID'] ?>" data-price="<?= $item['sellingPrice'] ?>">
              <?= htmlspecialchars($item['itemName']) ?>
            </option>
          <?php } ?>
        </select>
        <label>Sold Price (RM)</label>
        <input type="number" name="price" id="addPrice" step="0.01" min="0" required placeholder="0.00">

        <label>Quantity</label>
        <input type="number" name="quantity" min="1" required>
        <div class="modal-buttons">
          <button type="button" class="btn-cancel"
            onclick="document.getElementById('salesModal').style.display='none'">Cancel</button>
          <button type="submit" name="add_sales" class="btn-save">Submit</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal" id="editModal">
    <div class="modal-content">
      <h3>Edit Sales Record</h3>
      <form method="POST">
        <input type="hidden" name="saleID" id="editSaleID">
        <label>Item</label>
        <select name="itemID" id="editItem" required>
          <?php $inventory->data_seek(0);
          while ($item = $inventory->fetch_assoc()) { ?>
            <option value="<?= $item['itemID'] ?>"><?= htmlspecialchars($item['itemName']) ?></option>
          <?php } ?>
        </select>
        <label>Sold Price (RM)</label>
        <input type="number" name="price" id="editPrice" step="0.01" min="0" required>

        <label>Quantity</label>
        <input type="number" name="quantity" id="editQuantity" min="1" required>
        <div class="modal-buttons">
          <button type="button" class="btn-cancel"
            onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
          <button type="submit" name="edit_sales" class="btn-save">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Standard Delete Modal -->
  <div id="deleteModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-exclamation-triangle warning-icon"></i>
      <h3>Confirm Deletion</h3>
      <p>Are you sure you want to delete this sales record? This action cannot be undone and will permanently remove the
        transaction data.</p>
      <div class="modal-popup-footer">
        <button class="btn-popup-cancel"
          onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
        <button class="btn-popup-confirm" onclick="confirmDelete()">Yes, Delete</button>
      </div>
    </div>
  </div>

  <!-- Logout Modal -->
  <div id="logoutModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-sign-out-alt warning-icon"></i>
      <h3>Confirm Logout</h3>
      <p>Are you sure you want to end your current session? You will need to login again to access the admin panel.</p>
      <div class="modal-popup-footer">
        <button class="btn-popup-cancel"
          onclick="document.getElementById('logoutModal').style.display='none'">Cancel</button>
        <button class="btn-popup-confirm" onclick="window.location.href='logout.php'">Yes, Logout</button>
      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div id="toast" class="toast"></div>

  <script src="js/sales_chart.js"></script>
  <script>
    let saleIdToDelete = null;

    function toggleUserSidebar() {
      document.getElementById('userSidebar').classList.toggle('active');
      document.getElementById('overlay').classList.toggle('active');
    }

    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('active');
      document.getElementById('mainContent').classList.toggle('shifted');
    }

    function openLogoutModal() {
      document.getElementById('logoutModal').style.display = 'flex';
    }

    function toggleSidebarPassword() {
      const passwordInput = document.getElementById('sidebarPassword');
      const icon = document.getElementById('togglePassword');
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }

    function updatePrice(select) {
      const price = select.options[select.selectedIndex].getAttribute('data-price');
      document.getElementById('addPrice').value = price || '';
    }

    function openEditModal(id, itemID, quantity, price) {
      document.getElementById('editSaleID').value = id;
      document.getElementById('editItem').value = itemID;
      document.getElementById('editQuantity').value = quantity;
      document.getElementById('editPrice').value = price;
      document.getElementById('editModal').style.display = 'flex';
    }

    function openDeleteModal(id) {
      saleIdToDelete = id;
      document.getElementById('deleteModal').style.display = 'flex';
    }

    function confirmDelete() {
      if (saleIdToDelete) {
        window.location.href = `record_sales.php?delete=${saleIdToDelete}`;
      }
    }

    // Toast Logic
    window.onload = function () {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('profile_updated')) {
        showToast("Profile updated successfully!");
        window.history.replaceState({}, document.title, window.location.pathname);
      }
      if (urlParams.has('added')) showMessage('Sale recorded successfully!');
      if (urlParams.has('updated')) showMessage('Sale updated successfully!');
      if (urlParams.has('deleted')) showMessage('Sale deleted successfully!');

      if (urlParams.has('added') || urlParams.has('updated') || urlParams.has('deleted')) {
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    }

    function showToast(message) {
      var x = document.getElementById("toast");
      x.innerHTML = '<i class="fas fa-check-circle" style="color: #2ecc71;"></i> ' + message;
      x.className = "toast show";
      setTimeout(function () { x.className = x.className.replace("show", ""); }, 3000);
    }

    function showMessage(msg) {
      const el = document.getElementById('success');
      if (el) {
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 2000);
      }
    }
  </script>
</body>

</html>
<?php $conn->close(); ?>