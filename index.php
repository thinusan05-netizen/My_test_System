<?php
/**
 * Intelligent Road Safety System
 * Admin Dashboard - Main Index
 * Displays statistics and quick actions
 */

session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Database Configuration
$host = "localhost";
$user = "root";
$pass = "";
$db_name = "accident_prediction_db";

// Connect to Database
$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Statistics
$zones_count = 0;
$users_count = 0;
$accidents_count = 0;

$result = $conn->query("SELECT COUNT(*) as c FROM risk_zones");
if ($result) {
    $zones_count = $result->fetch_assoc()['c'];
}

$result = $conn->query("SELECT COUNT(*) as c FROM users");
if ($result) {
    $users_count = $result->fetch_assoc()['c'];
}

$result = $conn->query("SELECT COUNT(*) as c FROM accident_records");
if ($result) {
    $accidents_count = $result->fetch_assoc()['c'];
}

// Get admin info
$admin_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['username'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Road Safety System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
        <a href="index.php" class="active">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="manage_zones.php">
            <i class="fas fa-map-marked-alt"></i> Manage Zones
        </a>
        <a href="manage_accidents.php">
            <i class="fas fa-car-crash"></i> Accidents
        </a>
        <a href="manage_users.php">
            <i class="fas fa-users"></i> Drivers
        </a>
        <a href="manage_training_data.php">
            <i class="fas fa-brain"></i> Training Data
        </a>
        <a href="upload_csv.php">
            <i class="fas fa-upload"></i> Bulk Upload
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1>Dashboard</h1>
        <p style="color: #7f8c8d; margin-bottom: 30px;">
            Welcome back, <strong><?php echo htmlspecialchars($admin_name); ?></strong>!
        </p>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-exclamation-triangle"></i> Danger Zones</h3>
                <p><?php echo $zones_count; ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-car-crash"></i> Reported Accidents</h3>
                <p><?php echo $accidents_count; ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-user-check"></i> Active Drivers</h3>
                <p><?php echo $users_count; ?></p>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="card">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <ul>
                <li>
                    <i class="fas fa-brain"></i>
                    <a href="manage_training_data.php">Manage & View Training Data</a>
                </li>
                <li>
                    <i class="fas fa-file-csv"></i>
                    <a href="upload_csv.php">Bulk Upload Accident CSV</a>
                </li>
                <li>
                    <i class="fas fa-car-crash"></i>
                    <a href="manage_accidents.php">Manage Accident Records</a>
                </li>
                <li>
                    <i class="fas fa-map-marked-alt"></i>
                    <a href="manage_zones.php">Manage Risk Zones</a>
                </li>
                <li>
                    <i class="fas fa-map-marker-alt"></i>
                    <a href="../../frontend/admin.html" target="_blank">Add Zone via Map</a>
                </li>
                <li>
                    <i class="fas fa-users-cog"></i>
                    <a href="manage_users.php">Manage Drivers</a>
                </li>
                <li>
                    <i class="fas fa-database"></i>
                    <a href="../db/manage_admin_accounts.php">Manage Admin Accounts</a>
                </li>
            </ul>
        </div>

        <!-- System Information Card -->
        <div class="card">
            <h3><i class="fas fa-info-circle"></i> System Information</h3>
            <table>
                <tr>
                    <td><strong>Database:</strong></td>
                    <td><?php echo $db_name; ?></td>
                </tr>
                <tr>
                    <td><strong>Server:</strong></td>
                    <td><?php echo $host; ?></td>
                </tr>
                <tr>
                    <td><strong>Admin User:</strong></td>
                    <td><?php echo htmlspecialchars($_SESSION['username']); ?></td>
                </tr>
                <tr>
                    <td><strong>Last Login:</strong></td>
                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
