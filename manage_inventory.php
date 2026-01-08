<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Employee')) {
  echo "<script>alert('Access denied. Admins and Employees only!'); window.location.href='index.php';</script>";
  exit;
}

require_once 'db_config.php';


$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$dashboardLink = ($_SESSION['role'] === 'Admin') ? 'admin_dashboard.php' : 'employee_dashboard.php';

// Add product
if (isset($_POST['add_product'])) {
  $name = $_POST['itemName'];
  $category = $_POST['category'];
  $price = $_POST['price'];
  $sellingPrice = $_POST['sellingPrice'];
  $quantity = $_POST['stockQuantity'];
  $threshold = $_POST['threshold'];
  $status = ($quantity <= $threshold) ? 'Low Stock' : 'Available';

  $stmt = $conn->prepare("INSERT INTO Tbl_inventory (itemName, category, price, sellingPrice, stockQuantity, threshold, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("ssddiss", $name, $category, $price, $sellingPrice, $quantity, $threshold, $status);

  if ($stmt->execute()) {
    if ($status === 'Low Stock') {
      $conn->query("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES ('Low stock alert for $name', 'Admin', NOW())");
      $conn->query("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES ('Low stock alert for $name', 'Employee', NOW())");
    }
    header("Location: manage_inventory.php?added=1");
    exit();
  }
}
// Edit product
if (isset($_POST['save_edit'])) {
  $id = $_POST['editID'];
  $name = $_POST['itemName'];
  $category = $_POST['category'];
  $price = $_POST['price'];
  $sellingPrice = $_POST['sellingPrice'];
  $quantity = $_POST['stockQuantity'];
  $threshold = $_POST['threshold'];
  $status = ($quantity <= $threshold) ? 'Low Stock' : 'Available';

  // Fetch old data to check for restock
  $oldQQuery = $conn->query("SELECT stockQuantity FROM Tbl_inventory WHERE itemID=$id");
  $oldQ = $oldQQuery->fetch_assoc()['stockQuantity'];

  $stmt = $conn->prepare("UPDATE Tbl_inventory SET itemName=?, category=?, price=?, sellingPrice=?, stockQuantity=?, threshold=?, status=? WHERE itemID=?");
  $stmt->bind_param("ssddissi", $name, $category, $price, $sellingPrice, $quantity, $threshold, $status, $id);
  $stmt->execute();

  // Notifications
  if ($status === 'Low Stock') {
    // Notifications
    $conn->query("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES ('Low stock alert for $name (Qty: $quantity)', 'Admin', NOW())");
    $conn->query("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES ('Low stock alert for $name (Qty: $quantity)', 'Employee', NOW())");
  }
  if ($quantity > $oldQ) {
    $conn->query("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES ('Restock: $name quantity increased to $quantity', 'Admin', NOW())");
    $conn->query("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES ('Restock: $name quantity increased to $quantity', 'Employee', NOW())");
  }

  header("Location: manage_inventory.php?updated=1");
  exit();
}

// Delete product
if (isset($_GET['delete'])) {
  $deleteID = intval($_GET['delete']);
  try {
    $conn->query("DELETE FROM Tbl_inventory WHERE itemID = $deleteID");
    header("Location: manage_inventory.php?deleted=1");
    exit();
  } catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1451) { // Foreign key constraint fails
      header("Location: manage_inventory.php?error=constraint");
      exit();
    } else {
      header("Location: manage_inventory.php?error=unknown");
      exit();
    }
  }
}

// Request Restock (Admin Only)
if (isset($_POST['request_restock'])) {
  $itemID = $_POST['restockID'];
  $quantity = $_POST['restockQuantity'];

  $res = $conn->query("SELECT itemName FROM Tbl_inventory WHERE itemID=$itemID");
  if ($res->num_rows > 0) {
    $itemName = $res->fetch_assoc()['itemName'];
    // Insert Logic (With Supplier Name)
    $supplierID = $_POST['supplierID'];
    $sName = "Unknown";
    $sRes = $conn->query("SELECT name FROM Tbl_supplier WHERE supplierID=$supplierID");
    if ($sRes->num_rows > 0) {
      $sName = $sRes->fetch_assoc()['name'];
    }

    $stmt = $conn->prepare("INSERT INTO Tbl_restock_request (itemID, itemName, quantity, status, supplierName, requestDate) VALUES (?, ?, ?, 'Pending', ?, NOW())");
    $stmt->bind_param("isss", $itemID, $itemName, $quantity, $sName);
    $stmt->execute();

    // Notify Supplier (Ideally we'd notify the specific supplier user, but for now generic 'Supplier' role + name in message)
    $msg = "New Restock Request for $sName: $itemName (Qty: $quantity)";
    $stmtNotif = $conn->prepare("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES (?, 'Supplier', NOW())");
    $stmtNotif->bind_param("s", $msg);
    $stmtNotif->execute();

    header("Location: manage_inventory.php?restock_requested=1");
    exit();
  }
}

// Fetch inventory
if ($search !== '') {
  $stmt = $conn->prepare("SELECT * FROM Tbl_inventory WHERE itemName LIKE ? OR category LIKE ?");
  $searchTerm = "%$search%";
  $stmt->bind_param("ss", $searchTerm, $searchTerm);
  $stmt->execute();
  $result = $stmt->get_result();
} else {
  $result = $conn->query("SELECT * FROM Tbl_inventory");
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
    header("Location: manage_inventory.php?profile_updated=1");
    exit();
  } else {
    header("Location: manage_inventory.php?error=update_failed");
  }
}

// Count Unread Notifications
// Check role for Notification Badge
$role = $_SESSION['role']; // 'Admin' or 'Employee'
$notifRes = $conn->query("SELECT COUNT(*) as count FROM Tbl_notification WHERE recipientRole = '$role' AND is_read = 0");
$notifCount = $notifRes->fetch_assoc()['count'];

// Fetch All Suppliers for Dropdown
$supList = $conn->query("SELECT supplierID, name FROM Tbl_supplier");
$suppliers = [];
while ($s = $supList->fetch_assoc()) {
  $suppliers[] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Inventory - Aldimar Legacy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="css/admin_modals.css">
  <link rel="stylesheet" href="css/admin_sidebar.css">
  <style>
    /* --- Global Styles (Sidebar/Header from Admin Dashboard) --- */
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

    .status-low {
      color: #e74c3c;
      font-weight: bold;
    }

    .status-ok {
      color: #27ae60;
      font-weight: bold;
    }

    .actions {
      display: flex;
      justify-content: center;
      gap: 10px;
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
      width: 450px;
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

    .modal-content input {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ddd;
      border-radius: 6px;
      outline: none;
    }

    .modal-content input:focus {
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

    /* Delete Modal Specifics */
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

    .confirm-yes:hover {
      background-color: #c0392b;
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

    .confirm-no:hover {
      background-color: #ddd;
    }

    .delete-actions {
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    /* Success Toast Message (Pill Shape) */
    #successToast {
      visibility: hidden;
      min-width: 300px;
      background-color: #4CAF50;
      /* Green background */
      color: #fff;
      text-align: center;
      border-radius: 50px;
      /* Pill shape */
      padding: 16px;
      position: fixed;
      z-index: 3000;
      left: 50%;
      top: 90px;
      /* Slightly below header */
      transform: translateX(-50%);
      font-size: 16px;
      font-weight: 500;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      opacity: 0;
      transition: opacity 0.5s, top 0.5s;
    }

    #successToast.show {
      visibility: visible;
      opacity: 1;
      top: 100px;
      /* Slide down effect */
    }
  </style>
</head>

<body>

  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
      <h3><?php echo ($_SESSION['role'] === 'Admin') ? 'Admin Panel' : 'Employee Panel'; ?></h3>
      <ul>
        <?php if ($_SESSION['role'] === 'Admin'): ?>
          <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
          <li><a href="manage_users.php"><i class="fas fa-users"></i> Users</a></li>
          <li><a href="manage_inventory.php" class="active"><i class="fas fa-box"></i> Inventory</a></li>
          <li><a href="manage_supplier.php"><i class="fas fa-truck"></i> Suppliers</a></li>
          <li><a href="record_sales.php"><i class="fas fa-shopping-cart"></i> Sales</a></li>
          <li><a href="report.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <?php else: ?>
          <li><a href="employee_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
          <li><a href="manage_inventory.php" class="active"><i class="fas fa-box"></i> Inventory</a></li>
          <li><a href="record_sales.php"><i class="fas fa-shopping-cart"></i> Sales</a></li>
        <?php endif; ?>
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
          <a href="<?php echo ($_SESSION['role'] === 'Admin') ? 'admin_dashboard.php' : 'employee_dashboard.php'; ?>"
            style="text-decoration:none; color:white;">
            <h1>ALDIMAR LEGACY</h1>
          </a>
        </div>
      </header>

      <div id="successToast">Success Message</div>

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

      <main>
        <div class="content-card">
          <div class="page-header">
            <h2>Manage Inventory</h2>
            <div class="search-box">
              <form method="GET">
                <input type="text" name="search" placeholder="Search by Name or Category..."
                  value="<?= htmlspecialchars($search) ?>">
                <i class="fas fa-search"></i>
              </form>
            </div>
            <button class="add-btn" onclick="document.getElementById('addModal').style.display='flex'">
              <i class="fas fa-plus"></i> Add Product
            </button>
          </div>

          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price (RM)</th>
                <th>Selling (RM)</th>
                <th>Qty</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()) {
                $statusClass = $row['stockQuantity'] <= $row['threshold'] ? 'status-low' : 'status-ok';
                ?>
                <tr>
                  <td><?= $row['itemID'] ?></td>
                  <td><?= htmlspecialchars($row['itemName']) ?></td>
                  <td><?= htmlspecialchars($row['category']) ?></td>
                  <td><?= number_format($row['price'], 2) ?></td>
                  <td><?= number_format($row['sellingPrice'], 2) ?></td>
                  <td><?= $row['stockQuantity'] ?></td>
                  <td class="<?= $statusClass ?>"><?= $row['status'] ?></td>
                  <td class="actions">
                    <button class="edit-btn"
                      onclick="openEditModal(<?= $row['itemID'] ?>, '<?= htmlspecialchars($row['itemName'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['category'], ENT_QUOTES) ?>', <?= $row['price'] ?>, <?= $row['sellingPrice'] ?>, <?= $row['stockQuantity'] ?>, <?= $row['threshold'] ?>)"><i
                        class="fas fa-edit"></i> Edit</button>
                    <?php if ($_SESSION['role'] === 'Admin'): ?>
                      <button class="delete-btn" style="background-color: #2ecc71;"
                        onclick="openRestockModal(<?= $row['itemID'] ?>, '<?= htmlspecialchars($row['itemName'], ENT_QUOTES) ?>')"><i
                          class="fas fa-truck"></i> Restock</button>
                    <?php endif; ?>
                    <button class="delete-btn" onclick="openDeleteModal(<?= $row['itemID'] ?>)"><i
                        class="fas fa-trash"></i>
                      Delete</button>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </main>
    </div>
  </div>

  <!-- Add Modal -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <h3>Add New Product</h3>
      <form method="POST">
        <label>Item Name</label><input type="text" name="itemName" required>
        <label>Category</label><input type="text" name="category" required>
        <div style="display:flex; gap:10px;">
          <div style="flex:1;"><label>Cost Price</label><input type="number" step="0.01" name="price" required></div>
          <div style="flex:1;"><label>Selling Price</label><input type="number" step="0.01" name="sellingPrice"
              required></div>
        </div>
        <div style="display:flex; gap:10px;">
          <div style="flex:1;"><label>Quantity</label><input type="number" name="stockQuantity" required></div>
          <div style="flex:1;"><label>Threshold</label><input type="number" name="threshold" required></div>
        </div>
        <div class="modal-buttons">
          <button type="button" class="btn-cancel"
            onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
          <button type="submit" name="add_product" class="btn-save">Add Product</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <h3>Edit Product</h3>
      <form method="POST">
        <input type="hidden" name="editID" id="editID">
        <label>Item Name</label><input type="text" name="itemName" id="editName" required>
        <label>Category</label><input type="text" name="category" id="editCategory" required>
        <div style="display:flex; gap:10px;">
          <div style="flex:1;"><label>Cost Price</label><input type="number" step="0.01" name="price" id="editPrice"
              required></div>
          <div style="flex:1;"><label>Selling Price</label><input type="number" step="0.01" name="sellingPrice"
              id="editSellingPrice" required></div>
        </div>
        <div style="display:flex; gap:10px;">
          <div style="flex:1;"><label>Quantity</label><input type="number" name="stockQuantity" id="editQuantity"
              required></div>
          <div style="flex:1;"><label>Threshold</label><input type="number" name="threshold" id="editThreshold"
              required></div>
        </div>
        <div class="modal-buttons">
          <button type="button" class="btn-cancel"
            onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
          <button type="submit" name="save_edit" class="btn-save">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Restock Request Modal -->
  <div id="restockModal" class="modal">
    <div class="modal-content">
      <h3>Request Restock from Supplier</h3>
      <p>Requesting restock for: <strong id="restockItemName"></strong></p>
      <form method="POST">
        <input type="hidden" name="restockID" id="restockID">

        <label>Select Supplier</label>
        <select name="supplierID" required
          style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:4px;">
          <option value="" disabled selected>-- Choose Supplier --</option>
          <?php foreach ($suppliers as $sup): ?>
            <option value="<?= $sup['supplierID'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <label>Quantity Needed</label>
        <input type="number" name="restockQuantity" required min="1" value="10">
        <div class="modal-buttons">
          <button type="button" class="btn-cancel"
            onclick="document.getElementById('restockModal').style.display='none'">Cancel</button>
          <button type="submit" name="request_restock" class="btn-save" style="background-color: #2ecc71;">Send
            Request</button>
        </div>
      </form>
    </div>
  </div>


  <!-- Standard Delete Modal -->
  <div id="deleteModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-exclamation-triangle warning-icon"></i>
      <h3>Confirm Deletion</h3>
      <p>Are you sure you want to delete this product? This action cannot be undone and will permanently remove it
        from the inventory.</p>
      <div class="modal-popup-footer">
        <button class="btn-popup-cancel"
          onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
        <button class="btn-popup-confirm" onclick="deleteConfirmed()">Yes, Delete</button>
      </div>
    </div>
  </div>

  <!-- Logout Modal -->
  <div id="logoutModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-sign-out-alt warning-icon"></i>
      <h3>Confirm Logout</h3>
      <p>Are you sure you want to end your session? You will need to login again to access the admin panel.</p>
      <div class="modal-popup-footer">
        <button class="btn-popup-cancel"
          onclick="document.getElementById('logoutModal').style.display='none'">Cancel</button>
        <button class="btn-popup-confirm" onclick="window.location.href='logout.php'">Yes, Logout</button>
      </div>
    </div>
  </div>

  <script src="js/inventory_chart.js"></script>
  <script>
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

    let deleteID = null;
    function openRestockModal(id, name) {
      document.getElementById('restockID').value = id;
      document.getElementById('restockItemName').innerText = name;
      document.getElementById('restockModal').style.display = 'flex';
    }

    function openDeleteModal(id) {
      deleteID = id;
      document.getElementById('deleteModal').style.display = 'flex';
    }

    function deleteConfirmed() {
      if (deleteID) window.location.href = `manage_inventory.php?delete=${deleteID}`;
    }

    // URL Params Check to trigger Toast
    const urlParams = new URLSearchParams(window.location.search);
    document.addEventListener("DOMContentLoaded", () => {
      if (urlParams.has('updated')) showSuccess('Product edited successfully!');
      if (urlParams.has('added')) showSuccess('Product added successfully!');
      if (urlParams.has('deleted')) showSuccess('Product deleted successfully!');
      if (urlParams.has('error')) {
        let err = urlParams.get('error');
        if (err === 'constraint') showSuccess('Cannot delete: Item has sales records!', true);
        else showSuccess('An unknown error occurred.', true);
      }
    });

    // Show Toast (Green for Success, Red for Error)
    function showSuccess(msg, isError = false) {
      const x = document.getElementById("successToast");
      x.textContent = msg;
      if (isError) {
        x.style.backgroundColor = "#e74c3c"; // Red for error
      } else {
        x.style.backgroundColor = "#4CAF50"; // Green for success
      }
      x.className = "show";
      setTimeout(function () {
        x.className = x.className.replace("show", "");
      }, 4000); // Hide after 4 seconds

      // Clean URL
      window.history.replaceState({}, document.title, window.location.pathname);
    }

    function openEditModal(id, name, category, price, sellingPrice, quantity, threshold) {
      document.getElementById('editID').value = id;
      document.getElementById('editName').value = name;
      document.getElementById('editCategory').value = category;
      document.getElementById('editPrice').value = price;
      document.getElementById('editSellingPrice').value = sellingPrice;
      document.getElementById('editQuantity').value = quantity;
      document.getElementById('editThreshold').value = threshold;
      document.getElementById('editModal').style.display = 'flex';
    }
  </script>
</body>

</html>
<?php $conn->close(); ?>