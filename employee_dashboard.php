<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Employee') {
  echo "<script>alert('Access denied. Employees only!'); window.location.href='login.php';</script>";
  exit;
}

// Database Connection
$servername = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "aldimar_db";

$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbName);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// 1. Fetch Metrics
// Total Products
$result = $conn->query("SELECT count(*) as count FROM tbl_inventory");
$totalProducts = $result->fetch_assoc()['count'];

// Low Stock
$result = $conn->query("SELECT count(*) as count FROM tbl_inventory WHERE stockQuantity <= threshold");
$lowStock = $result->fetch_assoc()['count'];

// Out of Stock
$result = $conn->query("SELECT count(*) as count FROM tbl_inventory WHERE stockQuantity = 0");
$outOfStock = $result->fetch_assoc()['count'];

// Total Sales (Today)
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT SUM(totalPrice) as total FROM tbl_salesrecord WHERE DATE(date) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$todaySalesResult = $stmt->get_result()->fetch_assoc();
$todaySales = $todaySalesResult['total'] ? number_format($todaySalesResult['total'], 2) : "0.00";
$stmt->close();

// Count Unread Notifications (Employee)
$notifRes = $conn->query("SELECT COUNT(*) as count FROM Tbl_notification WHERE recipientRole = 'Employee' AND is_read = 0");
$notifCount = $notifRes->fetch_assoc()['count'];


// 2. Fetch Chart Data (Last 7 Days Sales)
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime("-$i days"));
  $chartLabels[] = date('D', strtotime($date)); // Mon, Tue, etc.

  $stmt = $conn->prepare("SELECT SUM(totalPrice) as total FROM tbl_salesrecord WHERE DATE(date) = ?");
  $stmt->bind_param("s", $date);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $chartData[] = $res['total'] ? (float) $res['total'] : 0;
  $stmt->close();
}


// 3. Fetch Recent Activity (Sales + Notifications)
$activities = [];

// Fetch Sales
$sqlSales = "SELECT 'sale' as type, s.date as time, i.itemName as content 
             FROM tbl_salesrecord s 
             JOIN tbl_inventory i ON s.itemID = i.itemID 
             ORDER BY s.saleID DESC LIMIT 5";
$resSales = $conn->query($sqlSales);
while ($row = $resSales->fetch_assoc()) {
  $row['timestamp'] = strtotime($row['time']);
  $activities[] = $row;
}

// Fetch Notifications (System Alerts for Employee)
$sqlNotifs = "SELECT 'alert' as type, dateSent as time, message as content 
              FROM Tbl_notification 
              WHERE recipientRole = 'Employee' 
              ORDER BY dateSent DESC LIMIT 5";
$resNotifs = $conn->query($sqlNotifs);
while ($row = $resNotifs->fetch_assoc()) {
  $row['timestamp'] = strtotime($row['time']);
  $activities[] = $row;
}

// Sort by timestamp DESC
usort($activities, function ($a, $b) {
  return $b['timestamp'] - $a['timestamp'];
});

// Slice top 8
$recentActivities = array_slice($activities, 0, 8);

// 4. Handle Profile Update
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
    header("Location: employee_dashboard.php?profile_updated=1");
    exit();
  } else {
    header("Location: employee_dashboard.php?error=update_failed");
  }
  $stmt->close();
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
  <title>Employee Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="css/admin_modals.css">
  <link rel="stylesheet" href="css/admin_sidebar.css">
  <style>
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
      /* Matching Login Theme */
      color: white;
      height: 100vh;
      position: fixed;
      top: 0;
      left: 0;
      padding: 20px;
      transition: transform 0.3s ease;
      z-index: 1000;
      transform: translateX(-100%);
      /* Hidden by default on mobile/toggle approach */
    }

    /* When active class is added, sidebar slides in */
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
      /* Updated: Starts with 0 margin since sidebar is hidden */
      flex: 1;
      padding: 0;
      transition: margin-left 0.3s ease;
      width: 100%;
    }

    /* When Sidebar is Active, push content */
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

    /* Dashboard Cards */
    .notif-wrapper {
      position: relative;
      cursor: pointer;
    }

    .notif-wrapper i {
      font-size: 20px;
      color: white;
      transition: color 0.3s;
    }

    .notif-wrapper:hover i {
      color: #d1c4e9;
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
      min-width: 15px;
      text-align: center;
    }

    main {
      padding: 30px;
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }

    .card {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
      text-align: center;
      transition: transform 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .card:hover {
      transform: translateY(-5px);
    }

    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 5px;
      background: #5e4b8b;
    }

    .card h4 {
      color: #777;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 10px;
    }

    .card p {
      font-size: 28px;
      color: #333;
      font-weight: bold;
    }

    .card i {
      font-size: 30px;
      color: #f0f0f5;
      position: absolute;
      top: 20px;
      right: 20px;
      z-index: 0;
    }

    /* Charts & Recent */
    .dashboard-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 25px;
    }

    @media (max-width: 900px) {
      .dashboard-grid {
        grid-template-columns: 1fr;
      }
    }

    .section-container {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .section-container h3 {
      margin-bottom: 20px;
      color: #5e4b8b;
      font-size: 18px;
    }

    .recent ul {
      list-style: none;
    }

    .recent ul li {
      padding: 15px 0;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .recent ul li:last-child {
      border-bottom: none;
    }

    .recent-item {
      display: flex;
      flex-direction: column;
    }

    .recent-name {
      font-weight: 600;
      color: #333;
    }

    .recent-date {
      font-size: 12px;
      color: #888;
      margin-top: 4px;
    }

    /* User Sidebar (Right) */
    .user-sidebar {
      position: fixed;
      top: 0;
      right: -400px;
      width: 380px;
      height: 100vh;
      background: white;
      box-shadow: -5px 0 25px rgba(0, 0, 0, 0.15);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      z-index: 2005;
      padding: 25px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
    }

    .user-sidebar.active {
      right: 0;
    }

    .user-sidebar h2 {
      color: #5e4b8b;
      font-size: 22px;
      margin-bottom: 25px;
      border-bottom: 2px solid #5e4b8b;
      padding-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .sidebar-section {
      background: #fdfdfd;
      border: 1px solid #f0f0f0;
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 25px;
    }

    .sidebar-section h3 {
      font-size: 16px;
      color: #5e4b8b;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .sidebar-form label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #666;
      margin-bottom: 5px;
    }

    .sidebar-form input,
    .sidebar-form select,
    .sidebar-form textarea {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      transition: border-color 0.3s;
    }

    .sidebar-form input:focus {
      border-color: #5e4b8b;
      background: #f9f7ff;
    }

    .btn-sidebar-save {
      width: 100%;
      background: #5e4b8b;
      color: white;
      padding: 12px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 700;
      transition: background 0.3s;
    }

    .btn-sidebar-save:hover {
      background: #4a3c74;
    }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      z-index: 2000;
      display: none;
      backdrop-filter: blur(2px);
    }

    .overlay.active {
      display: block;
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="sidebar" id="sidebar">
      <h3>Employee Panel</h3>
      <ul>
        <li><a href="employee_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
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
          <div style="display: flex; align-items: center; gap: 10px; cursor: pointer;" onclick="toggleUserSidebar()">
            <span style="font-weight: 600; color: white;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <i class="fas fa-user-circle"></i>
          </div>
        </div>
      </header>

      <main>
        <div class="cards">
          <div class="card">
            <i class="fas fa-box"></i>
            <h4>Total Products</h4>
            <p><?php echo $totalProducts; ?></p>
          </div>
          <div class="card">
            <i class="fas fa-exclamation-triangle" style="color: #fff0f0;"></i>
            <h4>Low Stock</h4>
            <p style="color: <?php echo $lowStock > 0 ? '#d9534f' : '#333'; ?>"><?php echo $lowStock; ?></p>
          </div>
          <div class="card">
            <i class="fas fa-times-circle" style="color: #fff0f0;"></i>
            <h4>Out of Stock</h4>
            <p style="color: <?php echo $outOfStock > 0 ? '#d9534f' : '#333'; ?>"><?php echo $outOfStock; ?></p>
          </div>
          <div class="card">
            <i class="fas fa-dollar-sign"></i>
            <h4>Sales Today</h4>
            <p>RM <?php echo $todaySales; ?></p>
          </div>
        </div>

        <div class="dashboard-grid">
          <div class="section-container">
            <h3>Sales Overview (Last 7 Days)</h3>
            <canvas id="overviewChart"></canvas>
          </div>

          <div class="section-container recent">
            <h3>Recent Activity</h3>
            <?php if (count($recentActivities) > 0): ?>
              <ul>
                <?php foreach ($recentActivities as $act):
                  $icon = ($act['type'] === 'sale') ? 'fa-shopping-cart' : 'fa-bell';
                  $color = ($act['type'] === 'sale') ? '#2ecc71' : '#e74c3c';
                  $label = ($act['type'] === 'sale') ? 'Sold: ' . htmlspecialchars($act['content']) : htmlspecialchars($act['content']);
                  ?>
                  <li>
                    <div style="margin-right: 15px;">
                      <div
                        style="background-color: <?= $color ?>; color: white; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas <?= $icon ?>" style="font-size: 14px; position: static;"></i>
                      </div>
                    </div>
                    <div class="recent-item">
                      <span class="recent-name"><?= $label ?></span>
                      <span class="recent-date"><?= date('M d, Y g:i A', $act['timestamp']); ?></span>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p style="color: #888; font-style: italic;">No recent activity.</p>
            <?php endif; ?>
          </div>
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
        <div class="password-container">
          <input type="password" name="password" id="sidebarPassword" placeholder="******">
          <i class="fas fa-eye" id="togglePassword" onclick="toggleSidebarPassword()"></i>
        </div>

        <button type="submit" name="update_profile" class="btn-sidebar-save">Update Profile</button>
      </form>
    </div>
  </div>

  <!-- Logout Modal -->
  <div id="logoutModal" class="modal-popup">
    <div class="modal-popup-content">
      <i class="fas fa-sign-out-alt warning-icon"></i>
      <h3>Confirm Logout</h3>
      <p>Are you sure you want to end your current session? You will need to login again to access the employee panel.
      </p>
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
    // Toast Logic
    window.onload = function () {
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
      setTimeout(function () { x.className = x.className.replace("show", ""); }, 3000);
    }

    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('active');
      document.getElementById('mainContent').classList.toggle('shifted');
    }

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

    function openLogoutModal() {
      document.getElementById('logoutModal').style.display = 'flex';
    }

    const ctx = document.getElementById('overviewChart');
    const chartLabels = <?php echo json_encode($chartLabels); ?>;
    const chartData = <?php echo json_encode($chartData); ?>;

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: chartLabels,
        datasets: [{
          label: 'Sales Revenue (RM)',
          data: chartData,
          borderColor: '#6a4fb3',
          backgroundColor: 'rgba(106, 79, 179, 0.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#6a4fb3'
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: true,
            position: 'top'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: '#f0f0f0'
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        }
      }
    });
  </script>
</body>

</html>