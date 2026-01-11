<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
  echo "<script>alert('Access denied. Admins only!'); window.location.href='index.php';</script>";
  exit;
}

// Database Connection
require_once 'db_config.php';


$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle Update Request
if (isset($_POST['update'])) {
  $newRole = $_POST['new_role'];
  $userID = $_POST['userID'];

  // Update role
  $sql = "UPDATE tbl_user SET role=? WHERE userID=?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("si", $newRole, $userID);
  $stmt->execute();
  $stmt->close();

  // Auto-insert into tbl_supplier if new role is Supplier
  if ($newRole === 'Supplier') {
    // Check if already exists in tbl_supplier
    $check = $conn->prepare("SELECT * FROM tbl_supplier WHERE email = (SELECT email FROM tbl_user WHERE userID = ?)");
    $check->bind_param("i", $userID);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
      // Get user details
      $get = $conn->prepare("SELECT name, email, phone FROM tbl_user WHERE userID = ?");
      $get->bind_param("i", $userID);
      $get->execute();
      $user = $get->get_result()->fetch_assoc();
      $get->close();

      // Insert into tbl_supplier
      $insert = $conn->prepare("INSERT INTO tbl_supplier (name, email, phone) VALUES (?, ?, ?)");
      $insert->bind_param("sss", $user['name'], $user['email'], $user['phone']);
      $insert->execute();
      $insert->close();
    }
    $check->close();
  }

  // Refresh to show updates
  header("Location: manage_users.php?updated=1");
  exit;
}

// Handle Delete Request
if (isset($_GET['delete'])) {
  if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo "<script>alert('Access denied. Admin only!'); window.location.href='index.php';</script>";
    exit;
  }
  $deleteID = intval($_GET['delete']);
  $conn->query("DELETE FROM tbl_user WHERE userID = $deleteID");
  header("Location: manage_users.php?deleted=1");
  exit();
}

// Fetch Users with Search
if ($search !== '') {
  $stmt = $conn->prepare("SELECT * FROM tbl_user WHERE username LIKE ? OR role LIKE ?");
  $searchTerm = "%$search%";
  $stmt->bind_param("ss", $searchTerm, $searchTerm);
  $stmt->execute();
  $result = $stmt->get_result();
} else {
  $result = $conn->query("SELECT * FROM tbl_user");
}

// Fetch Data for Sidebar (Profile & Inventory/Suppliers for Restock)
$userID = $_SESSION['userID'];
$userRes = $conn->query("SELECT * FROM tbl_user WHERE userID = $userID");
$profileData = $userRes->fetch_assoc();



// Handle Profile Update (Sidebar)
if (isset($_POST['update_profile'])) {
  $newUsername = trim($_POST['username']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $password = $_POST['password'];

  if (!empty($password)) {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE tbl_user SET username=?, email=?, phone=?, password=? WHERE userID=?");
    $stmt->bind_param("ssssi", $newUsername, $email, $phone, $hashed, $userID);
  } else {
    $stmt = $conn->prepare("UPDATE tbl_user SET username=?, email=?, phone=? WHERE userID=?");
    $stmt->bind_param("sssi", $newUsername, $email, $phone, $userID);
  }

  if ($stmt->execute()) {
    $_SESSION['username'] = $newUsername;
    $_SESSION['username'] = $newUsername;
    header("Location: manage_users.php?profile_updated=1");
    exit();
  } else {
    header("Location: manage_users.php?error=update_failed");
  }
}

// Count Unread Notifications
$notifRes = $conn->query("SELECT COUNT(*) as count FROM tbl_notification WHERE recipientRole = 'Admin' AND is_read = 0");
$notifCount = $notifRes->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users - Aldimar Legacy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="css/admin_modals.css">
  <link rel="stylesheet" href="css/admin_sidebar.css">
  <style>
    /* --- Global Styles & Layout (Matches admin_dashboard.php) --- */
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

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    th,
    td {
      padding: 15px;
      text-align: left;
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

    .pending {
      background-color: #fff8e1;
    }

    .pending td {
      color: #856404;
    }

    select {
      padding: 8px 12px;
      border: 1px solid #ccc;
      border-radius: 6px;
      background: white;
      cursor: pointer;
      font-size: 14px;
    }

    button {
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 13px;
      transition: all 0.2s;
    }

    button[name="update"] {
      background-color: #5e4b8b;
      color: white;
      margin-left: 10px;
    }

    button[name="update"]:hover {
      background-color: #4a3c74;
    }

    .delete-btn {
      background-color: #ff6b6b;
      color: white;
    }

    .delete-btn:hover {
      background-color: #fa5252;
    }

    /* Standard Modals */
    /* Premium Modal Style (Popup) */

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
  </style>
</head>

<body>

  <div class="container">
    <div class="sidebar" id="sidebar">
      <h3>Admin Panel</h3>
      <ul>
        <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="manage_users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="manage_inventory.php"><i class="fas fa-box"></i> Inventory</a></li>
        <li><a href="manage_supplier.php"><i class="fas fa-truck"></i> Suppliers</a></li>
        <li><a href="record_sales.php"><i class="fas fa-shopping-cart"></i> Sales</a></li>
        <li><a href="report.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
      </ul>
      <div class="bottom-logout">
        <a href="javascript:void(0)" onclick="openLogoutModal()"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>

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
            <h2>Manage Users</h2>
            <div class="search-box">
              <form method="GET">
                <input type="text" name="search" placeholder="Search by username or role..."
                  value="<?= htmlspecialchars($search) ?>">
                <i class="fas fa-search"></i>
              </form>
            </div>
          </div>

          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Current Role</th>
                <th>Change Role</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()) { ?>
                <tr class="<?= $row['role'] === 'Unknown' ? 'pending' : '' ?>">
                  <td><?= htmlspecialchars($row['userID']) ?></td>
                  <td><?= htmlspecialchars($row['username']) ?></td>
                  <td>
                    <?php if ($row['role'] === 'Unknown'): ?>
                      <span
                        style="background:orange; color:white; padding:2px 6px; border-radius:4px; font-size:12px;">Pending</span>
                    <?php else: ?>
                      <?= htmlspecialchars($row['role']) ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" action="manage_users.php" style="display:flex; align-items:center;">
                      <select name="new_role" required>
                        <option value="Admin" <?= $row['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Employee" <?= $row['role'] === 'Employee' ? 'selected' : '' ?>>Employee</option>
                        <option value="Supplier" <?= $row['role'] === 'Supplier' ? 'selected' : '' ?>>Supplier</option>
                        <option value="Unknown" <?= $row['role'] === 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                      </select>
                      <input type="hidden" name="userID" value="<?= $row['userID'] ?>">
                      <button type="submit" name="update">Update</button>
                    </form>
                  </td>
                  <td>
                    <button class="delete-btn" onclick="openDeleteModal(<?= $row['userID'] ?>)"><i
                        class="fas fa-trash"></i> Delete</button>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </main>
    </div>
  </div>

  <!-- Standard Delete Modal -->
  <div id="deleteModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-exclamation-triangle warning-icon"></i>
      <h3>Confirm Deletion</h3>
      <p>Are you sure you want to delete this user? This action cannot be undone and will permanently remove their data
        from the portal.</p>
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
      <p>Are you sure you want to end your session? You will need to login again to access the admin panel.</p>
      <div class="modal-popup-footer">
        <button class="btn-popup-cancel"
          onclick="document.getElementById('logoutModal').style.display='none'">Cancel</button>
        <button class="btn-popup-confirm" onclick="window.location.href='logout.php'">Yes, Logout</button>
      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div id="toast" class="toast"></div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    let deleteUserId = null;

    function toggleUserSidebar() {
      document.getElementById('userSidebar').classList.toggle('active');
      document.getElementById('overlay').classList.toggle('active');
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

    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('active');
      document.getElementById('mainContent').classList.toggle('shifted');
    }

    function openLogoutModal() {
      document.getElementById('logoutModal').style.display = 'flex';
    }

    function openDeleteModal(id) {
      deleteUserId = id;
      document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeModal() {
      deleteUserId = null;
      document.getElementById('deleteModal').style.display = 'none';
    }

    function confirmDelete() {
      if (deleteUserId) window.location.href = `manage_users.php?delete=${deleteUserId}`;
    }

    // Toast Logic
    window.onload = function () {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('profile_updated')) {
        showToast("Profile updated successfully!");
        window.history.replaceState({}, document.title, window.location.pathname);
      }
      if (urlParams.has('updated')) showMessage('User role updated successfully!');
      if (urlParams.has('deleted')) showMessage('User deleted successfully!');

      // Clean existing messages URL params
      if (urlParams.has('updated') || urlParams.has('deleted')) {
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