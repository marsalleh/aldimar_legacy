<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Employee') {
  echo "<script>alert('Access denied. Employees only!'); window.location.href='login.php';</script>";
  exit;
}

$conn = new mysqli("localhost", "root", "", "aldimar_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Handle Delete Notification
if (isset($_GET['delete_id'])) {
  $id = intval($_GET['delete_id']);
  $conn->query("DELETE FROM Tbl_notification WHERE notifID = $id");
  header("Location: employee_notification.php");
  exit();
}

// Handle Clear All
if (isset($_GET['clear_all'])) {
  $conn->query("DELETE FROM Tbl_notification WHERE recipientRole = 'Employee'");
  header("Location: employee_notification.php");
  exit();
}

// 1. Count Unread (Before marking as read, so badge shows count)
$unreadRes = $conn->query("SELECT COUNT(*) as count FROM Tbl_notification WHERE recipientRole = 'Employee' AND is_read = 0");
$notifCount = $unreadRes->fetch_assoc()['count'];

// 2. Fetch Notifications (Before marking as read, so we can style new ones distinctively)
$result = $conn->query("SELECT * FROM Tbl_notification WHERE recipientRole = 'Employee' ORDER BY dateSent DESC");

// 3. Mark all as read (For next time)
$conn->query("UPDATE Tbl_notification SET is_read = 1 WHERE recipientRole = 'Employee' AND is_read = 0");


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
    header("Location: employee_notification.php?profile_updated=1");
    exit();
  } else {
    header("Location: employee_notification.php?error=update_failed");
  }
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
  <title>Notifications - Aldimar Legacy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="css/admin_modals.css">
  <link rel="stylesheet" href="css/admin_sidebar.css">
  <style>
    /* Reuse global styles from css files */
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

    /* Sidebar Styles are imported from admin_sidebar.css but we need to ensure structure matches */
    /* ...actually admin_sidebar.css mainly handles the RIGHT sidebar. The LEFT sidebar is usually inline or common. */
    /* Let's include the LEFT sidebar styles here to be safe and consistent with dashboard */

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
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
      height: 70px;
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

    .notif-wrapper {
      position: relative;
    }

    .badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: #e74c3c;
      color: white;
      border-radius: 50%;
      padding: 2px 5px;
      font-size: 10px;
      font-weight: bold;
    }

    main {
      padding: 30px;
      max-width: 900px;
      margin: 0 auto;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }

    .page-header h2 {
      color: #5e4b8b;
      font-size: 24px;
    }

    .btn-clear {
      background: #e74c3c;
      color: white;
      border: none;
      padding: 8px 15px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      text-decoration: none;
      transition: 0.3s;
    }

    .btn-clear:hover {
      background: #c0392b;
    }

    .notification-list {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
      overflow: hidden;
    }

    .notification-item {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      padding: 20px;
      border-bottom: 1px solid #eee;
      transition: background 0.3s;
    }

    .notification-item:last-child {
      border-bottom: none;
    }

    .notification-item:hover {
      background: #f9f9ff;
    }

    .notif-content {
      flex: 1;
    }

    .notif-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 5px;
    }

    .notif-title {
      font-weight: 700;
      color: #5e4b8b;
      font-size: 16px;
      margin-right: 10px;
    }

    .notif-time {
      color: #888;
      font-size: 12px;
    }

    .notif-msg {
      color: #555;
      line-height: 1.5;
      font-size: 15px;
      margin-top: 5px;
    }

    /* Standardized delete button */
    .btn-delete {
      border: none;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      color: white;
      transition: 0.2s;
      background-color: #ff6b6b;
      margin-left: 15px;
      text-decoration: none;
      display: inline-block;
    }

    .btn-delete:hover {
      background-color: #fa5252;
      color: white;
    }

    .empty-state {
      padding: 40px;
      text-align: center;
      color: #999;
    }

    .empty-state i {
      font-size: 40px;
      margin-bottom: 15px;
      color: #ddd;
    }

    /* Icons for Types */
    .icon-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      margin-right: 15px;
      flex-shrink: 0;
      color: white;
    }

    .bg-new-user {
      background: #3498db;
    }

    .bg-low-stock {
      background: #e67e22;
    }

    .bg-restock {
      background: #2ecc71;
    }

    .bg-system {
      background: #95a5a6;
    }
  </style>
</head>

<body>

  <div class="container">
    <div class="sidebar" id="sidebar">
      <h3>Employee Panel</h3>
      <ul>
        <li><a href="employee_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="manage_inventory.php"><i class="fas fa-box"></i> Inventory</a></li>
        <li><a href="record_sales.php"><i class="fas fa-shopping-cart"></i> Sales</a></li>
      </ul>
      <div class="bottom-logout">
        <a href="javascript:void(0)" onclick="openLogoutModal()"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>

    <div class="main-content" id="mainContent">
      <header>
        <div class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></div>
        <div class="header-title">
          <a href="employee_dashboard.php" style="text-decoration:none; color:white;">
            <h1>ALDIMAR LEGACY</h1>
          </a>
        </div>
        <div class="user-info">
          <a href="employee_notification.php" class="notif-wrapper" title="Notifications">
            <i class="fas fa-bell" style="font-size:20px;"></i>
            <?php if ($notifCount > 0): ?>
              <span class="badge"><?= $notifCount ?></span>
            <?php endif; ?>
          </a>
          <div style="display:flex; align-items:center; gap:10px; cursor:pointer;" onclick="toggleUserSidebar()">
            <span style="font-weight:600; color:white;"><?= htmlspecialchars($_SESSION['username']) ?></span>
            <i class="fas fa-user-circle"></i>
          </div>
        </div>
      </header>

      <main>
        <div class="page-header">
          <h2>Notifications</h2>
          <?php if ($result->num_rows > 0): ?>
            <a href="?clear_all=1" class="btn-clear" onclick="return confirm('Clear all notifications?')">Clear All</a>
          <?php endif; ?>
        </div>

        <div class="notification-list">
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <?php
              // Determine Icon/Color based on message content
              $msg = strtolower($row['message']);
              $iconClass = "fas fa-info";
              $bgClass = "bg-system";
              $title = "System Alert";

              if (strpos($msg, 'new user') !== false) {
                // Employees shouldn't really see "New User" but just in case
                $iconClass = "fas fa-user-plus";
                $bgClass = "bg-new-user";
                $title = "New User";
              } elseif (strpos($msg, 'low stock') !== false) {
                $iconClass = "fas fa-exclamation-triangle";
                $bgClass = "bg-low-stock";
                $title = "Low Stock";
              } elseif (strpos($msg, 'restock') !== false) {
                $iconClass = "fas fa-box-open";
                $bgClass = "bg-restock";
                $title = "Restock/Delivery";
              }
              ?>
              <div class="notification-item">
                <div class="icon-badge <?= $bgClass ?>"><i class="<?= $iconClass ?>"></i></div>
                <div class="notif-content">
                  <div class="notif-header">
                    <span class="notif-title"><?= $title ?></span>
                    <span class="notif-time"><?= date('M j, g:i a', strtotime($row['dateSent'])) ?></span>
                  </div>
                  <div class="notif-msg"><?= htmlspecialchars($row['message']) ?></div>
                </div>
                <a href="?delete_id=<?= $row['notifID'] ?>" class="btn-delete" title="Remove"><i
                    class="fas fa-times"></i></a>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="empty-state">
              <i class="far fa-bell-slash"></i>
              <p>No new notifications</p>
            </div>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>

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

  <!-- Logout Modal -->
  <div id="logoutModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-sign-out-alt warning-icon"></i>
      <h3>Confirm Logout</h3>
      <p>Are you sure you want to end your session? You will need to login again to access the panel.</p>
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
    window.onload = function() {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('profile_updated')) {
        showToast("Profile updated successfully!");
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    }

    function showToast(message) {
      var x = document.getElementById("toast");
      x.innerHTML = '<i class="fas fa-check-circle" style="color: #2ecc71;"></i> ' + message;
      x.className = "toast show";
      setTimeout(function(){ x.className = x.className.replace("show", ""); }, 3000);
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