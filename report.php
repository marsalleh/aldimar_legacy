<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo "<script>alert('Access denied. Admin only!'); window.location.href='index.php';</script>";
    exit;
}

require_once 'db_config.php';


// Filter Logic
$reportType = $_GET['report_type'] ?? 'daily'; // daily, monthly, yearly, specific_date

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
        echo "<script>alert('Profile updated successfully!'); window.location.href='report.php';</script>";
    } else {
        echo "<script>alert('Error updating profile');</script>";
    }
}

// Count Unread Notifications
$notifRes = $conn->query("SELECT COUNT(*) as count FROM tbl_notification WHERE recipientRole = 'Admin' AND is_read = 0");
$notifCount = $notifRes->fetch_assoc()['count'];

// Date Selection handling
$selDate = $_GET['specific_date_val'] ?? date('Y-m-d'); // For Specific Daily Report
$selMonth = $_GET['month_val'] ?? date('m');
$selYear = $_GET['year_val'] ?? date('Y');
$fullMonthDate = "$selYear-$selMonth";

$selYearOnly = $_GET['year_only'] ?? date('Y');

$chartLabels = [];
$chartData = [];
$totalSales = 0.00;
$totalTransactions = 0;

// Condition Builders
$mainCondition = ""; // For Summary & Tables
$trendCondition = ""; // For Trend Chart (might differ for Daily)
$groupBy = "";
$labelFormat = "";

if ($reportType === 'specific_date') {
    // Specific Date: Summary/Tables = specific day. Trend = Last 7 Days context.
    $mainCondition = "WHERE DATE(date) = '$selDate'";
    $trendCondition = "WHERE DATE(date) BETWEEN DATE_SUB('$selDate', INTERVAL 6 DAY) AND '$selDate'";
    $groupBy = "GROUP BY DATE(date)";
    $labelFormat = "jS M"; // 25th Oct
} elseif ($reportType === 'daily') {
    // Monthly Report (Daily Breakdown): All = specific month
    $mainCondition = "WHERE date LIKE '$fullMonthDate%'";
    $trendCondition = $mainCondition;
    $groupBy = "GROUP BY DAY(date)";
    $labelFormat = "jS M";
} elseif ($reportType === 'monthly') {
    // Yearly Report (Monthly Breakdown): All = specific year
    $mainCondition = "WHERE date LIKE '$selYearOnly%'";
    $trendCondition = $mainCondition;
    $groupBy = "GROUP BY MONTH(date)";
    $labelFormat = "F";
} else { // yearly
    // All Time: All = All years
    $mainCondition = "";
    $trendCondition = "";
    $groupBy = "GROUP BY YEAR(date)";
    $labelFormat = "Y";
}

// 1. Fetch Chart Data (Trends)
$trendQuery = "SELECT MIN(DATE(date)) as date, SUM(totalPrice) as total, COUNT(*) as count FROM tbl_salesrecord $trendCondition $groupBy ORDER BY date ASC";
$trendResult = $conn->query($trendQuery);

while ($row = $trendResult->fetch_assoc()) {
    $dateObj = new DateTime($row['date']);
    // Removed faulty setDate() - MySQL grouping handles the aggregation, date formatting handles the label
    $chartLabels[] = $dateObj->format($labelFormat);
    $chartData[] = (float) $row['total'];
}

// 2. Fetch Summary Metrics (Revenue / Transactions) - Uses Main Condition
$summaryQuery = "SELECT SUM(totalPrice) as total, COUNT(*) as count FROM tbl_salesrecord $mainCondition";
$summaryRow = $conn->query($summaryQuery)->fetch_assoc();
$totalSales = $summaryRow['total'] ?? 0.00;
$totalTransactions = $summaryRow['count'] ?? 0;

// 3. Fetch Top 5 Selling Items (in period) - Uses Main Condition
$topQuery = "SELECT i.itemName, SUM(s.quantity) as qty, SUM(s.quantity * s.price) as revenue FROM tbl_salesrecord s JOIN tbl_inventory i ON s.itemID = i.itemID $mainCondition GROUP BY s.itemID ORDER BY revenue DESC LIMIT 5";
$topResult = $conn->query($topQuery);

$topLabels = [];
$topData = [];
$topRevenue = [];
while ($row = $topResult->fetch_assoc()) {
    $topLabels[] = $row['itemName'];
    $topData[] = (int) $row['qty'];
    $topRevenue[] = (float) $row['revenue'];
}

// 4. Fetch Least Selling Items (in period) - Uses Main Condition
$leastThreshold = 0;
if ($reportType === 'specific_date')
    $leastThreshold = 3;      // Daily
elseif ($reportType === 'daily')
    $leastThreshold = 10;          // Monthly View
elseif ($reportType === 'monthly')
    $leastThreshold = 50;        // Yearly View

$havingClause = ($leastThreshold > 0) ? "HAVING qty <= $leastThreshold" : "";

$leastQuery = "SELECT i.itemName, SUM(s.quantity) as qty FROM tbl_salesrecord s JOIN tbl_inventory i ON s.itemID = i.itemID $mainCondition GROUP BY s.itemID $havingClause ORDER BY qty ASC LIMIT 5";
$leastResult = $conn->query($leastQuery);

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
        header("Location: report.php?profile_updated=1");
        exit();
    } else {
        header("Location: report.php?error=update_failed");
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Aldimar Legacy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin_modals.css">
    <link rel="stylesheet" href="css/admin_sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* --- Global Styles --- */

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
            margin-bottom: 30px;
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
            gap: 10px;
            align-items: center;
        }

        .input-group input,
        .input-group select {
            border: 1px solid #ddd;
            padding: 6px 10px;
            border-radius: 15px;
            outline: none;
            background: white;
            font-family: inherit;
            font-size: 13px;
            cursor: pointer;
        }

        .btn-print {
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

        .btn-print:hover {
            background-color: #4a3c74;
        }

        /* Summary Boxes */
        .summary-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-box {
            flex: 1;
            background: linear-gradient(135deg, #7a5dca, #5e4b8b);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(94, 75, 139, 0.3);
        }

        .summary-box h3 {
            font-size: 16px;
            margin-bottom: 5px;
            opacity: 0.9;
        }

        .summary-box p {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }

        /* Charts Area - 1:1 ratio, restricted height */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            height: 320px;
            display: flex;
            flex-direction: column;
        }

        .chart-container h4 {
            text-align: center;
            color: #5e4b8b;
            margin-bottom: 10px;
            flex-shrink: 0;
        }

        .chart-canvas-wrapper {
            flex-grow: 1;
            position: relative;
            width: 100%;
            height: 100%;
        }

        /* Tables */
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            color: #5e4b8b;
            font-weight: 700;
        }

        /* Print Styles */
        @media print {

            .sidebar,
            header,
            .page-header {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .container {
                display: block;
            }

            .content-card,
            .chart-container {
                box-shadow: none;
                border: none;
                padding: 0;
                break-inside: avoid;
            }

            .charts-grid,
            .tables-grid {
                display: block;
            }

            .chart-container,
            .summary-row {
                margin-bottom: 30px;
            }

            .chart-container {
                height: auto;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="sidebar" id="sidebar">
            <h3>Admin Panel</h3>
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="manage_inventory.php"><i class="fas fa-box"></i> Inventory</a></li>
                <li><a href="manage_supplier.php"><i class="fas fa-truck"></i> Suppliers</a></li>
                <li><a href="record_sales.php"><i class="fas fa-shopping-cart"></i> Sales</a></li>
                <li><a href="report.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
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

            <main>

                <!-- Controls -->
                <div class="page-header">
                    <h2>Report</h2>
                    <form method="GET" class="filter-wrapper">
                        <div class="filter-segment">
                            <input type="radio" name="report_type" id="type_date" value="specific_date"
                                <?= $reportType === 'specific_date' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <label for="type_date" title="Specific Date Report">Daily</label>

                            <input type="radio" name="report_type" id="type_daily" value="daily"
                                <?= $reportType === 'daily' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <label for="type_daily" title="Monthly Report (Daily Breakdown)">Monthly</label>

                            <input type="radio" name="report_type" id="type_monthly" value="monthly"
                                <?= $reportType === 'monthly' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <label for="type_monthly" title="Yearly Report (Monthly Breakdown)">Yearly</label>

                            <input type="radio" name="report_type" id="type_yearly" value="yearly"
                                <?= $reportType === 'yearly' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <label for="type_yearly" title="All Time Report">All Time</label>
                        </div>

                        <div class="input-group">
                            <?php if ($reportType === 'specific_date'): ?>
                                <input type="date" name="specific_date_val" value="<?= $selDate ?>"
                                    onchange="this.form.submit()" style="padding:5px 10px;">

                            <?php elseif ($reportType === 'daily'): ?>
                                <!-- Month Picker -->
                                <select name="month_val" onchange="this.form.submit()">
                                    <?php
                                    for ($m = 1; $m <= 12; $m++) {
                                        $mStr = str_pad($m, 2, "0", STR_PAD_LEFT);
                                        $mName = date('F', mktime(0, 0, 0, $m, 10));
                                        $sel = ($selMonth == $mStr) ? 'selected' : '';
                                        echo "<option value='$mStr' $sel>$mName</option>";
                                    }
                                    ?>
                                </select>
                                <!-- Year Picker -->
                                <select name="year_val" onchange="this.form.submit()">
                                    <?php
                                    for ($y = date('Y'); $y >= 2020; $y--) {
                                        $sel = ($selYear == $y) ? 'selected' : '';
                                        echo "<option value='$y' $sel>$y</option>";
                                    }
                                    ?>
                                </select>

                            <?php elseif ($reportType === 'monthly'): ?>
                                <span style="font-weight:600; font-size:14px; margin-right:5px;">Select Year:</span>
                                <select name="year_only" onchange="this.form.submit()">
                                    <?php for ($y = date('Y'); $y >= 2020; $y--) { ?>
                                        <option value="<?= $y ?>" <?= $selYearOnly == $y ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php } ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </form>
                    <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print
                        Report</button>
                </div>

                <!-- Summaries -->
                <div class="summary-row">
                    <div class="summary-box">
                        <h3>Total Revenue</h3>
                        <p>RM <?= number_format($totalSales, 2) ?></p>
                    </div>
                    <div class="summary-box">
                        <h3>Total Transactions</h3>
                        <p><?= $totalTransactions ?></p>
                    </div>
                    <div class="summary-box" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                        <h3>Most Sold Item</h3>
                        <p><?= count($topLabels) > 0 ? htmlspecialchars($topLabels[0]) : '-' ?></p>
                    </div>
                </div>

                <!-- Charts -->
                <div class="charts-grid">
                    <div class="chart-container">
                        <h4><?= $reportType === 'specific_date' ? 'Sales 7-Day Trend' : 'Sales Trend' ?></h4>
                        <div class="chart-canvas-wrapper">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-container">
                        <h4>Top 5 Products</h4>
                        <div class="chart-canvas-wrapper">
                            <canvas id="topProductsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Detailed Tables -->
                <div class="tables-grid">
                    <div class="content-card">
                        <h3
                            style="color:#5e4b8b; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
                            Best Selling Items</h3>
                        <table>
                            <tr>
                                <th>Item Name</th>
                                <th>Units Sold</th>
                                <th>Revenue</th>
                            </tr>
                            <?php
                            for ($i = 0; $i < count($topLabels); $i++) {
                                echo "<tr><td>" . htmlspecialchars($topLabels[$i]) . "</td><td>" . $topData[$i] . "</td><td>RM " . number_format($topRevenue[$i], 2) . "</td></tr>";
                            }
                            if (count($topLabels) == 0)
                                echo "<tr><td colspan='3'>No data</td></tr>";
                            ?>
                        </table>
                    </div>

                    <div class="content-card">
                        <h3
                            style="color:#e74c3c; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
                            Least Selling Items</h3>
                        <table>
                            <tr>
                                <th>Item Name</th>
                                <th>Units Sold</th>
                            </tr>
                            <?php
                            while ($row = $leastResult->fetch_assoc()) {
                                echo "<tr><td>" . htmlspecialchars($row['itemName']) . "</td><td>" . $row['qty'] . "</td></tr>";
                            }
                            if ($leastResult->num_rows == 0)
                                echo "<tr><td colspan='2'>No data</td></tr>";
                            ?>
                        </table>
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
                            <input type="text" name="username"
                                value="<?= htmlspecialchars($profileData['username'] ?? '') ?>" required>

                            <label>Email</label>
                            <input type="email" name="email"
                                value="<?= htmlspecialchars($profileData['email'] ?? '') ?>" required>

                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($profileData['phone'] ?? '') ?>"
                                placeholder="e.g. 012-3456789">

                            <label>New Password <small>(Leave blank to keep)</small></label>
                            <input type="password" name="password" placeholder="******">

                            <button type="submit" name="update_profile" class="btn-sidebar-save">Update Profile</button>
                        </form>
                    </div>

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

    <script>
        // Toast Logic
        window.addEventListener('load', function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('profile_updated')) {
                showToast("Profile updated successfully!");
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        function showToast(message) {
            var x = document.getElementById("toast");
            x.innerHTML = '<i class="fas fa-check-circle" style="color: #2ecc71;"></i> ' + message;
            x.className = "toast show";
            setTimeout(function () { x.className = x.className.replace("show", ""); }, 3000);
        }

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

        // Pass PHP data to JS
        const chartLabels = <?= json_encode($chartLabels) ?>;
        const chartData = <?= json_encode($chartData) ?>;
        const topLabels = <?= json_encode($topLabels) ?>;
        const topData = <?= json_encode($topData) ?>;
        const topRevenue = <?= json_encode($topRevenue) ?>;

        // Trend Chart
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Revenue (RM)',
                    data: chartData,
                    borderColor: '#5e4b8b',
                    backgroundColor: 'rgba(94, 75, 139, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        // Top Products Chart - Showing both Units Sold and Revenue
        new Chart(document.getElementById('topProductsChart'), {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [
                    {
                        label: 'Units Sold',
                        data: topData,
                        backgroundColor: '#3498db',
                        borderWidth: 0,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Revenue (RM)',
                        data: topRevenue,
                        backgroundColor: '#5e4b8b',
                        borderWidth: 0,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Units Sold',
                            color: '#3498db',
                            font: { weight: 'bold' }
                        },
                        ticks: {
                            color: '#3498db'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Revenue (RM)',
                            color: '#5e4b8b',
                            font: { weight: 'bold' }
                        },
                        ticks: {
                            color: '#5e4b8b'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.label === 'Revenue (RM)') {
                                    label += 'RM ' + context.parsed.y.toFixed(2);
                                } else {
                                    label += context.parsed.y + ' units';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>