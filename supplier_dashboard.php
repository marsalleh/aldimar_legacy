<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Supplier') {
  echo "<script>alert('Access denied. Suppliers only!'); window.location.href='login.php';</script>";
  exit;
}

$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Handle Status Update
if (isset($_POST['update_status'])) {
  $requestID = $_POST['requestID'];
  $newStatus = $_POST['status'];
  $itemName = $_POST['itemName'];

  $stmt = $conn->prepare("UPDATE Tbl_restock_request SET status=?, updatedDate=NOW() WHERE requestID=?");
  $stmt->bind_param("si", $newStatus, $requestID);

  if ($stmt->execute()) {
    // Notify Admin
    $msg = "Supplier Update: Item '$itemName' status changed to '$newStatus'";
    $stmtNotif = $conn->prepare("INSERT INTO Tbl_notification (message, recipientRole, dateSent) VALUES (?, 'Admin', NOW())");
    $stmtNotif->bind_param("s", $msg);
    $stmtNotif->execute();

    // Send to Employee as well if they track stock? Maybe. Safe to just send to Admin for now or both.
    // User requirement: "supplier can only receive notification and sent the status" 
    // This part is OUTGOING notification.

    // Redirect with success flag
    header("Location: supplier_dashboard.php?status_updated=1");
    exit();
  }
}

// Handle Delete Notification
if (isset($_GET['delete_notif'])) {
  $notifID = intval($_GET['delete_notif']);
  $conn->query("DELETE FROM Tbl_notification WHERE notifID = $notifID");
  header("Location: supplier_dashboard.php");
  exit();
}

// Handle Delete Restock Request
if (isset($_GET['delete_request'])) {
  $requestID = intval($_GET['delete_request']);
  $conn->query("DELETE FROM Tbl_restock_request WHERE requestID = $requestID");
  header("Location: supplier_dashboard.php?deleted=1");
  exit();
}

// Handle Profile Update
if (isset($_POST['update_profile'])) {
  $userID = $_SESSION['userID'];
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
    header("Location: supplier_dashboard.php?profile_updated=1");
    exit();
  } else {
    header("Location: supplier_dashboard.php?error=update_failed");
  }
}

// Fetch Restock Requests for this Supplier
$userID = $_SESSION['userID'];
// 1. Get Email from Tbl_user
$stmtUser = $conn->prepare("SELECT email FROM Tbl_user WHERE userID = ?");
$stmtUser->bind_param("i", $userID);
$stmtUser->execute();
$uRes = $stmtUser->get_result();
$uEmail = ($uRes->num_rows > 0) ? $uRes->fetch_assoc()['email'] : '';

// 2. Get Supplier Name from tbl_supplier
$stmtSup = $conn->prepare("SELECT name FROM tbl_supplier WHERE email = ?");
$stmtSup->bind_param("s", $uEmail);
$stmtSup->execute();
$sRes = $stmtSup->get_result();
$supplierName = ($sRes->num_rows > 0) ? $sRes->fetch_assoc()['name'] : '';

// 3. Filter Requests based on Supplier Name
if ($supplierName) {
  $stmtReq = $conn->prepare("SELECT * FROM Tbl_restock_request WHERE supplierName = ? ORDER BY requestDate DESC");
  $stmtReq->bind_param("s", $supplierName);
  $stmtReq->execute();
  $reqResult = $stmtReq->get_result();
} else {
  // No matching supplier found for this user
  $reqResult = $conn->query("SELECT * FROM Tbl_restock_request WHERE 1=0");
}

// Fetch Notifications for Supplier (Filtered by Name in Message)
if ($supplierName) {
  $stmtNotif = $conn->prepare("SELECT * FROM Tbl_notification WHERE recipientRole = 'Supplier' AND message LIKE CONCAT('%', ?, '%') ORDER BY dateSent DESC LIMIT 5");
  $stmtNotif->bind_param("s", $supplierName);
  $stmtNotif->execute();
  $notifRes = $stmtNotif->get_result();

  // Count Unread Notifications (For Badge)
  $stmtUnread = $conn->prepare("SELECT COUNT(*) as count FROM Tbl_notification WHERE recipientRole = 'Supplier' AND is_read = 0 AND message LIKE CONCAT('%', ?, '%')");
  $stmtUnread->bind_param("s", $supplierName);
  $stmtUnread->execute();
  $notifCount = $stmtUnread->get_result()->fetch_assoc()['count'];
} else {
  $notifRes = $conn->query("SELECT * FROM Tbl_notification WHERE 1=0");
  $notifCount = 0;
}

// Fetch Sidebar Data (Profile)
$profileData = $conn->query("SELECT * FROM Tbl_user WHERE userID = " . $_SESSION['userID'])->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Supplier Dashboard - Aldimar Legacy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- Font Awesome 6 -->
  <!-- Reusing Admin Styles for Consistency -->
  <link rel="stylesheet" href="css/admin_modals.css">
  <link rel="stylesheet" href="css/admin_sidebar.css">
  <style>
    /* Inline overrides or specific styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', sans-serif;
    }

    body {
      background-color: #f4f4f9;
      color: #333;
    }

    .container {
      display: flex;
      min-height: 100vh;
    }

    /* Reuse Sidebar Style logic from other pages (referencing css files but structure needed) */
    .sidebar {
      width: 250px;
      background-color: #5e4b8b;
      color: white;
      position: fixed;
      height: 100vh;
      left: 0;
      top: 0;
      transition: transform 0.3s;
      z-index: 1000;
      transform: translateX(-100%);
      padding: 20px;
    }

    .sidebar.active {
      transform: translateX(0);
    }

    .sidebar h3 {
      text-align: center;
      margin-bottom: 30px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
      padding-bottom: 10px;
    }

    .sidebar ul li a {
      display: block;
      padding: 12px;
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      border-radius: 8px;
      margin-bottom: 10px;
      transition: 0.3s;
    }

    .sidebar ul li a:hover,
    .sidebar ul li a.active {
      background: #7a5dca;
      color: white;
      transform: translateX(5px);
    }

    .main-content {
      margin-left: 0;
      transition: margin-left 0.3s;
      width: 100%;
    }

    .main-content.shifted {
      margin-left: 250px;
    }

    header {
      background: #5e4b8b;
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: white;
      height: 70px;
    }

    .header-title h1 {
      margin: 0;
      font-size: 24px;
      font-weight: 700;
    }

    .status-badge {
      padding: 5px 10px;
      border-radius: 15px;
      font-size: 12px;
      font-weight: bold;
      color: white;
    }

    .status-pending {
      background: #f39c12;
    }

    .status-accepted {
      background: #3498db;
    }

    .status-shipped {
      background: #9b59b6;
    }

    .status-delivered {
      background: #2ecc71;
    }

    .status-cancelled {
      background: #e74c3c;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
      margin-top: 20px;
    }

    th,
    td {
      padding: 15px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }

    th {
      background: #f8f9fa;
      color: #5e4b8b;
      font-weight: 600;
    }

    .btn-update {
      background: #2ecc71;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 6px;
      cursor: pointer;
    }
  </style>
</head>

<body>

  <div class="container">
    <div class="sidebar" id="sidebar">
      <h3>Supplier Panel</h3>
      <ul>
        <li><a href="supplier_dashboard.php" class="active"><i class="fas fa-tasks"></i> Restock Requests</a></li>
        <!-- Removed "Notifications" link if everything is on dashboard, but let's keep it clean -->
      </ul>
      <div style="position: absolute; bottom: 20px; width: 80%;">
        <a href="javascript:void(0)" onclick="openLogoutModal()"
          style="display:flex; align-items:center; color:white; text-decoration:none; padding:10px;"><i
            class="fas fa-sign-out-alt" style="margin-right:10px;"></i> Logout</a>
      </div>
    </div>

    <div class="main-content" id="mainContent">
      <header>
        <div class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"
            style="font-size: 24px; cursor: pointer;"></i></div>
        <div class="header-title">
          <h1>ALDIMAR LEGACY</h1>
        </div>
        <div class="user-info" style="display:flex; gap:20px; align-items:center;">
          <!-- Notification Bell Removed -->

          <div style="display:flex; align-items:center; gap:10px; color:white; cursor:pointer;"
            onclick="toggleUserSidebar()">
            <span style="font-weight:600;"><?= htmlspecialchars($_SESSION['username']) ?></span>
            <i class="fas fa-user-circle" style="font-size:24px;"></i>
          </div>
        </div>
      </header>

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

      <main style="padding: 30px;">
        <h2><i class="fas fa-boxes"></i> Restock Requests from Admin</h2>

        <?php if ($reqResult->num_rows > 0): ?>
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Item Name</th>
                <th>Quantity Needed</th>
                <th>Date Requested</th>
                <th>Current Status</th>
                <th>Update Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $reqResult->fetch_assoc()): ?>
                <tr>
                  <td>#<?= $row['requestID'] ?></td>
                  <td><strong><?= htmlspecialchars($row['itemName']) ?></strong></td>
                  <td><?= $row['quantity'] ?> Units</td>
                  <td><?= date('d M Y', strtotime($row['requestDate'])) ?></td>
                  <td>
                    <span class="status-badge status-<?= strtolower($row['status']) ?>">
                      <?= $row['status'] ?>
                    </span>
                  </td>
                  <td>
                    <form method="POST" style="display:flex; gap:10px;">
                      <input type="hidden" name="requestID" value="<?= $row['requestID'] ?>">
                      <input type="hidden" name="itemName" value="<?= htmlspecialchars($row['itemName']) ?>">
                      <select name="status" style="padding:5px; border-radius:4px; border:1px solid #ddd;">
                        <option value="Pending" <?= $row['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Accepted" <?= $row['status'] == 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                        <option value="Shipped" <?= $row['status'] == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="Delivered" <?= $row['status'] == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="Cancelled" <?= $row['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                      </select>
                      <button type="submit" name="update_status" class="btn-update"><i class="fas fa-check"></i></button>
                    </form>
                  </td>
                  <td>
                    <button class="delete-btn" onclick="openDeleteRequestModal(<?= $row['requestID'] ?>)"
                      style="background: #e74c3c; color: white; padding: 6px 10px; border-radius: 4px; border:none; cursor:pointer;">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div style="text-align:center; padding:50px; color:#888;">
            <i class="fas fa-check-circle" style="font-size:40px; margin-bottom:15px; color:#ddd;"></i>
            <p>No active restock requests.</p>
          </div>
        <?php endif; ?>

        <!-- Notification Section (Read-Only List) -->
        <h3 style="margin-top:50px; border-bottom:1px solid #ddd; padding-bottom:10px;">Your Notifications</h3>
        <div
          style="background:white; border-radius:8px; padding:20px; margin-top:20px; box-shadow:0 4px 10px rgba(0,0,0,0.05);">
          <?php if ($notifRes->num_rows > 0): ?>
            <?php while ($notif = $notifRes->fetch_assoc()): ?>
              <div
                style="border-bottom:1px solid #eee; padding:10px 0; display:flex; justify-content:space-between; align-items:center;">
                <div>
                  <span><?= htmlspecialchars($notif['message']) ?></span>
                </div>
                <button type="button" style="background:none; border:none; color:#e74c3c; cursor:pointer;" title="Delete"
                  onclick="openDeleteNotifModal(<?= $notif['notifID'] ?>)"><i class="fas fa-trash-alt"></i></button>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <p style="color:#888;">No recent notifications.</p>
          <?php endif; ?>
        </div>

      </main>
    </div>
  </div>

  <!-- Logout Modal -->
  <div id="logoutModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-sign-out-alt warning-icon"></i>
      <h3>Confirm Logout</h3>
      <p>Are you sure you want to end your session?</p>
      <div class="modal-popup-footer">
        <button class="btn-popup-cancel"
          onclick="document.getElementById('logoutModal').style.display='none'">Cancel</button>
        <button class="btn-popup-confirm" onclick="window.location.href='logout.php'">Yes, Logout</button>
      </div>
    </div>
  </div>

  <!-- Delete Notification Modal -->
  <div id="deleteNotifModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-exclamation-circle warning-icon" style="color:#e74c3c;"></i>
      <h3>Delete Notification</h3>
      <p>Are you sure you want to remove this notification?</p>
      <div class="modal-popup-footer">
        <button class="btn-popup-cancel"
          onclick="document.getElementById('deleteNotifModal').style.display='none'">Cancel</button>
        <button class="btn-popup-confirm" id="confirmDeleteBtn">Yes, Delete</button>
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

  <!-- Toast Notification -->
  <div id="toast" class="toast"><i class="fas fa-check-circle" style="color: #2ecc71;"></i> Status updated successfully!
  </div>

  <script>
    // Check URL for success flag
    window.onload = function () {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('status_updated')) {
        showToast("Status updated successfully!");
        window.history.replaceState({}, document.title, window.location.pathname);
      }
      if (urlParams.has('profile_updated')) {
        showToast("Profile updated successfully!");
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    }

    function showToast(message) {
      var x = document.getElementById("toast");
      x.innerHTML = '<i class="fas fa-check-circle" style="color: #2ecc71;"></i> ' + message;
      x.className = "toast show";
      setTimeout(function () { x.className = x.className.replace("show", ""); }, 3000);
    }

    function showToast(message) {
      var x = document.getElementById("toast");
      x.className = "toast show";
      setTimeout(function () { x.className = x.className.replace("show", ""); }, 3000);
    }

    let notifToDelete = null;
    let deleteRequestID = null;

    function openDeleteNotifModal(id) {
      notifToDelete = id;
      document.getElementById('deleteNotifModal').style.display = 'flex';
      document.getElementById('confirmDeleteBtn').onclick = function () {
        window.location.href = "?delete_notif=" + notifToDelete;
      };
    }

    function openDeleteRequestModal(id) {
      deleteRequestID = id;
      document.getElementById('deleteRequestModal').style.display = 'flex';
    }

    function confirmDeleteRequest() {
      if (deleteRequestID) {
        window.location.href = `supplier_dashboard.php?delete_request=${deleteRequestID}`;
      }
    }

    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('active');
      document.getElementById('mainContent').classList.toggle('shifted');
    }

    function toggleUserSidebar() {
      document.getElementById('userSidebar').classList.toggle('active');
      document.getElementById('overlay').classList.toggle('active');
    }

    function openLogoutModal() {
      document.getElementById('logoutModal').style.display = 'flex';
    }
  </script>
</body>

</html>