<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
  echo "<script>alert('Access denied. Admin only!'); window.location.href='login.php';</script>";
  exit;
}

$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle Add Supplier
if (isset($_POST['add_supplier'])) {
  $name = $_POST['name'];
  $email = $_POST['email'];
  $phone = $_POST['phone'];

  $stmt = $conn->prepare("INSERT INTO tbl_supplier (name, email, phone) VALUES (?, ?, ?)");
  $stmt->bind_param("sss", $name, $email, $phone);
  $stmt->execute();
  header("Location: manage_supplier.php?added=1");
  exit();
}

// Handle Edit Supplier
if (isset($_POST['edit_supplier'])) {
  $supplierID = $_POST['supplierID'];
  $name = $_POST['name'];
  $email = $_POST['email'];
  $phone = $_POST['phone'];

  $stmt1 = $conn->prepare("UPDATE tbl_supplier SET name=?, email=?, phone=? WHERE supplierID=?");
  $stmt1->bind_param("sssi", $name, $email, $phone, $supplierID);
  $stmt1->execute();

  // Optionally update user table if linked (as per previous logic)
  $stmt2 = $conn->prepare("UPDATE Tbl_user SET name=?, email=?, phone=? WHERE email=? AND role='Supplier'");
  $stmt2->bind_param("ssss", $name, $email, $phone, $email);
  $stmt2->execute();

  header("Location: manage_supplier.php?updated=1");
  exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
  $deleteID = intval($_GET['delete']);
  $conn->query("DELETE FROM tbl_supplier WHERE supplierID = $deleteID");
  header("Location: manage_supplier.php?deleted=1");
  exit();
}

// Handle Delete Restock Request
if (isset($_GET['delete_request'])) {
  $reqID = intval($_GET['delete_request']);

  // Fetch details to find associated notification
  $stmtFetch = $conn->prepare("SELECT itemName, quantity, supplierName FROM Tbl_restock_request WHERE requestID = ?");
  $stmtFetch->bind_param("i", $reqID);
  $stmtFetch->execute();
  $resFetch = $stmtFetch->get_result();

  if ($resFetch->num_rows > 0) {
    $row = $resFetch->fetch_assoc();
    $iName = $row['itemName'];
    $qty = $row['quantity'];
    $sName = $row['supplierName'];

    // Construct notification message to match
    // Format from manage_inventory.php: "New Restock Request for $sName: $itemName (Qty: $quantity)"
    $notifMsg = "New Restock Request for $sName: $iName (Qty: $qty)";

    // Delete Notification
    $stmtDelNotif = $conn->prepare("DELETE FROM Tbl_notification WHERE message = ? AND recipientRole = 'Supplier'");
    $stmtDelNotif->bind_param("s", $notifMsg);
    $stmtDelNotif->execute();

    // Delete Request
    $conn->query("DELETE FROM Tbl_restock_request WHERE requestID = $reqID");
  }

  header("Location: manage_supplier.php?deleted=1");
  exit();
}

// Fetch inventory
if ($search !== '') {
  $stmt = $conn->prepare("SELECT * FROM tbl_supplier WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?");
  $searchTerm = "%$search%";
  $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
  $stmt->execute();
  $result = $stmt->get_result();
} else {
  $result = $conn->query("SELECT * FROM tbl_supplier");
}

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
    header("Location: manage_supplier.php?profile_updated=1");
    exit();
  } else {
    header("Location: manage_supplier.php?error=update_failed");
  }
}

// Count Unread Notifications
$notifRes = $conn->query("SELECT COUNT(*) as count FROM Tbl_notification WHERE recipientRole = 'Admin' AND is_read = 0");
$notifCount = $notifRes->fetch_assoc()['count'];

// Fetch Inventory for Restock Dropdown
$resItems = $conn->query("SELECT itemID, itemName FROM tbl_inventory ORDER BY itemName ASC");

// Handle Restock Notification
if (isset($_POST['send_request'])) {
  $itemID = $_POST['item_id'];
  $supplierID = $_POST['supplier_id']; // Optional specific supplier
  $customMsg = $_POST['message'];

  // Get Item Name
  $iName = $conn->query("SELECT itemName FROM tbl_inventory WHERE itemID=$itemID")->fetch_assoc()['itemName'];

  // Get Supplier Name (if selected)
  $sName = "General";
  if (!empty($supplierID)) {
    $sName = $conn->query("SELECT name FROM tbl_supplier WHERE supplierID=$supplierID")->fetch_assoc()['name'];
  }

  $finalMsg = "Restock Request: $iName. Target: $sName. Msg: $customMsg";
  $dateSent = date('Y-m-d H:i:s');

  $stmt = $conn->prepare("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES (?, 'Supplier', ?)");
  $stmt->bind_param("ss", $finalMsg, $dateSent);

  if ($stmt->execute()) {
    header("Location: manage_supplier.php?sent=1");
    exit();
  } else {
    header("Location: manage_supplier.php?error=send_failed");
    exit();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Suppliers - Aldimar Legacy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* --- Global Styles & Layout (Matches other admin pages) --- */
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
    }

    .search-box {
      position: relative;
    }

    .search-box input {
      padding: 10px 15px;
      padding-right: 40px;
      border-radius: 20px;
      border: 1px solid #ddd;
      outline: none;
      width: 250px;
    }

    .search-box i {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
    }

    .add-btn {
      background-color: #5e4b8b;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 20px;
      cursor: pointer;
      font-weight: bold;
      transition: 0.3s;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .add-btn:hover {
      background-color: #4a3c74;
    }

    /* Table */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
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
      margin: 0 3px;
    }

    .edit-btn {
      background-color: #3498db;
    }

    .edit-btn:hover {
      background-color: #2980b9;
    }

    /* Standardized Buttons */
    .edit-btn,
    .delete-btn {
      border: none;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      color: white;
      transition: 0.2s;
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

    /* Modals (Standardized) */
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
      position: relative;
    }

    .modal-content h3 {
      color: #5e4b8b;
      margin-bottom: 20px;
      text-align: center;
    }

    .modal-content input {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border: 1px solid #ddd;
      border-radius: 6px;
      outline: none;
    }

    .modal-content input:focus {
      border-color: #5e4b8b;
    }

    .modal-buttons {
      margin-top: 20px;
      text-align: right;
    }

    .btn-cancel {
      background-color: #eee;
      color: #333;
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      margin-right: 10px;
    }

    .btn-save {
      background-color: #5e4b8b;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
    }

    .btn-save:hover {
      background-color: #4a3c74;
    }

    /* Delete Modal Specifics (Red Header) */
    .delete-header {
      color: #e74c3c !important;
      font-size: 20px;
      margin-bottom: 15px;
      font-weight: bold;
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

    .confirm-yes:hover {
      background-color: #c0392b;
    }

    .delete-actions {
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    /* Success Message */
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

    /* Toast Notification */
    .toast {
      visibility: hidden;
      min-width: 250px;
      background-color: #333;
      color: #fff;
      text-align: center;
      border-radius: 50px;
      padding: 16px;
      position: fixed;
      z-index: 2002;
      left: 50%;
      bottom: 30px;
      transform: translateX(-50%);
      font-size: 17px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .toast.show {
      visibility: visible;
      animation: fadein 0.5s, fadeout 0.5s 2.5s;
    }

    @keyframes fadein {
      from {
        bottom: 0;
        opacity: 0;
      }

      to {
        bottom: 30px;
        opacity: 1;
      }
    }

    @keyframes fadeout {
      from {
        bottom: 30px;
        opacity: 1;
      }

      to {
        bottom: 0;
        opacity: 0;
      }
    }
  </style>
  <link rel="stylesheet" href="css/admin_modals.css">
  <link rel="stylesheet" href="css/admin_sidebar.css">
</head>

<body>

  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
      <h3>Admin Panel</h3>
      <ul>
        <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="manage_users.php"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="manage_inventory.php"><i class="fas fa-box"></i> Inventory</a></li>
        <li><a href="manage_supplier.php" class="active"><i class="fas fa-truck"></i> Suppliers</a></li>
        <li><a href="record_sales.php"><i class="fas fa-shopping-cart"></i> Sales</a></li>
        <li><a href="report.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
      </ul>
      <div class="bottom-logout">
        <a href="javascript:void(0)" onclick="openLogoutModal()"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
      <header>
        <div class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></div>
        <div class="header-title">
          <a href="admin_dashboard.php" style="text-decoration:none; color:white;">
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
            <input type="password" name="password" placeholder="******">

            <button type="submit" name="update_profile" class="btn-sidebar-save">Update Profile</button>
          </form>
        </div>

      </div>
      <!-- Restock Request Section -->
      <div class="content-card" style="margin-bottom: 30px; border-left: 5px solid #2ecc71;">
        <div class="page-header" style="margin-bottom: 15px;">
          <h2><i class="fas fa-paper-plane" style="margin-right:10px;"></i>Notify Supplier / Restock Request</h2>
        </div>
        <form method="POST" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
          <div style="flex: 1; min-width: 200px;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Select Item</label>
            <select name="item_id" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
              <option value="" disabled selected>-- Choose Item --</option>
              <?php if ($resItems->num_rows > 0) {
                $resItems->data_seek(0);
                while ($item = $resItems->fetch_assoc()) {
                  echo "<option value='" . $item['itemID'] . "'>" . htmlspecialchars($item['itemName']) . "</option>";
                }
              } ?>
            </select>
          </div>
          <div style="flex: 1; min-width: 200px;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Target Supplier (Optional)</label>
            <select name="supplier_id" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
              <option value="">-- All Suppliers --</option>
              <?php
              // Reuse result but reset pointer? No, need separate query or re-loop array. 
              // Simplest is re-query or store in array first. Result is used below.
              // Ideally store result in array. But for quick fix, assume main result is for table.
              // I will fetch suppliers again or use javascript to populate? 
              // Let's do a quick separate fetch or just re-iterate if possible.
              // Result object can data_seek(0).
              if ($result->num_rows > 0) {
                $result->data_seek(0);
                while ($sup = $result->fetch_assoc()) {
                  echo "<option value='" . $sup['supplierID'] . "'>" . htmlspecialchars($sup['name']) . "</option>";
                }
                $result->data_seek(0); // Reset for table below
              }
              ?>
            </select>
          </div>
          <div style="flex: 2; min-width: 300px;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Message</label>
            <input type="text" name="message" placeholder="e.g. Please deliver 50 units by Friday."
              style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
          </div>
          <button type="submit" name="send_request" class="btn-save" style="height: 42px;"><i
              class="fas fa-paper-plane"></i> Send</button>
        </form>
      </div>

      <div class="content-card">
        <div class="page-header">
          <h2>Manage Suppliers</h2>
          <div class="search-box">
            <form method="GET">
              <input type="text" name="search" placeholder="Search suppliers..."
                value="<?= htmlspecialchars($search) ?>">
              <i class="fas fa-search"></i>
            </form>
          </div>
          <button class="add-btn" onclick="document.getElementById('addModal').style.display='flex'">
            <i class="fas fa-plus"></i> Add Supplier
          </button>
        </div>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()) { ?>
              <tr>
                <td><?= $row['supplierID'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
                <td>
                  <button class="edit-btn"
                    onclick="openEditModal(<?= $row['supplierID'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['phone'], ENT_QUOTES) ?>')"><i
                      class="fas fa-edit"></i> Edit</button>
                  <button class="delete-btn" onclick="openDeleteModal(<?= $row['supplierID'] ?>)"><i
                      class="fas fa-trash"></i> Delete</button>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>

      <!-- Restock Requests Section -->
      <div class="content-card" style="margin-top: 30px;">
        <div class="page-header">
          <h2>Restock Requests</h2>
        </div>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Item</th>
              <th>Qty</th>
              <th>Supplier</th>
              <th>Status</th>
              <th>Date</th>
              <th style="text-align:center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $reqRes = $conn->query("SELECT * FROM Tbl_restock_request ORDER BY requestDate DESC");
            if ($reqRes->num_rows > 0) {
              while ($req = $reqRes->fetch_assoc()) {
                $statusColor = 'gray';
                if ($req['status'] == 'Pending')
                  $statusColor = '#f39c12';
                if ($req['status'] == 'Accepted')
                  $statusColor = '#3498db';
                if ($req['status'] == 'Shipped')
                  $statusColor = '#9b59b6';
                if ($req['status'] == 'Delivered')
                  $statusColor = '#2ecc71';
                if ($req['status'] == 'Cancelled')
                  $statusColor = '#e74c3c';
                ?>
                <tr>
                  <td>#<?= $req['requestID'] ?></td>
                  <td><?= htmlspecialchars($req['itemName']) ?></td>
                  <td><?= $req['quantity'] ?></td>
                  <td><?= htmlspecialchars($req['supplierName']) ?></td>
                  <td><span
                      style="background:<?= $statusColor ?>; color:white; padding:4px 8px; border-radius:12px; font-size:12px;"><?= $req['status'] ?></span>
                  </td>
                  <td><?= date('M d, Y', strtotime($req['requestDate'])) ?></td>
                  <td style="text-align:center;">
                    <button class="delete-btn" onclick="openDeleteRequestModal(<?= $req['requestID'] ?>)">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </td>
                </tr>
                <?php
              }
            } else {
              echo "<tr><td colspan='7' style='text-align:center; padding:20px; color:#888;'>No restock requests found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
      </main>
    </div>
  </div>

  <!-- Add Modal -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <h3>Add Supplier</h3>
      <form method="POST">
        <input type="text" name="name" placeholder="Supplier Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="tel" name="phone" placeholder="Phone Number" required>
        <div class="modal-buttons">
          <button type="button" class="btn-cancel"
            onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
          <button type="submit" name="add_supplier" class="btn-save">Add Supplier</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <h3>Edit Supplier</h3>
      <form method="POST">
        <input type="hidden" name="supplierID" id="editID">
        <input type="text" name="name" id="editName" placeholder="Supplier Name" required>
        <input type="email" name="email" id="editEmail" placeholder="Email" required>
        <input type="tel" name="phone" id="editPhone" placeholder="Phone Number" required>
        <div class="modal-buttons">
          <button type="button" class="btn-cancel"
            onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
          <button type="submit" name="edit_supplier" class="btn-save">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!--  <!-- Standard Delete Modal -->
  <div id="deleteModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-exclamation-triangle warning-icon"></i>
      <h3>Confirm Deletion</h3>
      <p>Are you sure you want to delete this supplier? This action cannot be undone and will permanently remove their
        record.</p>
      <div class="modal-popup-footer">
        <button class="btn-popup-cancel"
          onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
        <button class="btn-popup-confirm" onclick="confirmDelete()">Yes, Delete</button>
      </div>
    </div>
  </div>

  </div>

  <!-- Delete Request Modal -->
  <div id="deleteRequestModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-exclamation-triangle warning-icon"></i>
      <h3>Confirm Deletion</h3>
      <p>Are you sure you want to delete this restock request? This action cannot be undone.</p>
      <div class="modal-popup-footer">
        <button class="btn-popup-cancel"
          onclick="document.getElementById('deleteRequestModal').style.display='none'">Cancel</button>
        <button class="btn-popup-confirm" onclick="confirmDeleteRequest()">Yes, Delete</button>
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

  <script>
    // Toast Logic
    window.onload = function () {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('profile_updated')) {
        showToast("Profile updated successfully!");
        window.history.replaceState({}, document.title, window.location.pathname);
      }
      if (urlParams.has('sent')) {
        showToast("Sent Successfully!");
        window.history.replaceState({}, document.title, window.location.pathname);
      }
      if (urlParams.has('added') || urlParams.has('updated') || urlParams.has('deleted')) {
        showSuccess("Action completed successfully!");
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    }
    function showToast(message) {
      var x = document.getElementById("toast");
      x.innerHTML = '<i class="fas fa-check-circle" style="color: #2ecc71;"></i> ' + message;
      x.className = "toast show";
      setTimeout(function () { x.className = x.className.replace("show", ""); }, 3000);
    }

    function showSuccess(msg) {
      document.getElementById('success').innerText = msg;
      document.getElementById('success').style.display = 'block';
      setTimeout(() => { document.getElementById('success').style.display = 'none'; }, 2000);
    }

    function toggleUserSidebar() {
      document.getElementById('userSidebar').classList.toggle('active');
      document.getElementById('overlay').classList.toggle('active');
    }

    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('active');
      document.getElementById('mainContent').classList.toggle('shifted');
    }

    function openEditModal(id, name, email, phone) {
      document.getElementById('editID').value = id;
      document.getElementById('editName').value = name;
      document.getElementById('editEmail').value = email;
      document.getElementById('editPhone').value = phone;
      document.getElementById('editModal').style.display = 'flex';
    }

    let deleteID = null;
    let deleteRequestID = null;

    function openDeleteModal(id) {
      deleteID = id;
      document.getElementById('deleteModal').style.display = 'flex';
    }

    function confirmDelete() {
      if (deleteID) {
        window.location.href = 'manage_supplier.php?delete=' + deleteID;
      }
    }

    function openDeleteRequestModal(id) {
      deleteRequestID = id;
      document.getElementById('deleteRequestModal').style.display = 'flex';
    }

    function confirmDeleteRequest() {
      if (deleteRequestID) {
        window.location.href = 'manage_supplier.php?delete_request=' + deleteRequestID;
      }
    }

    function openLogoutModal() {
      document.getElementById('logoutModal').style.display = 'flex';
    }
  </script>
</body>

</html>