<?php
/**
 * Intelligent Road Safety System
 * Manage Users/Drivers Page
 * View and manage registered drivers
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

$message = "";
$error = "";

// Delete User Logic
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Prevent deleting self (just in case)
    if ($id != $_SESSION['user_id']) {
        $sql = "DELETE FROM users WHERE id = $id";
        if ($conn->query($sql) === TRUE) {
            $message = "Driver removed successfully!";
        }
        else {
            $error = "Error deleting user: " . $conn->error;
        }
    }
    else {
        $error = "Cannot delete your own account!";
    }
}

// Fetch all users/drivers
$sql = "SELECT * FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Drivers - Road Safety System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
        <a href="index.php">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="manage_zones.php">
            <i class="fas fa-map-marked-alt"></i> Manage Zones
        </a>
        <a href="manage_accidents.php">
            <i class="fas fa-car-crash"></i> Accidents
        </a>
        <a href="manage_training_data.php">
            <i class="fas fa-brain"></i> Training Data
        </a>
        <a href="upload_csv.php">
            <i class="fas fa-upload"></i> Bulk Upload
        </a>
        <a href="manage_users.php" class="active">
            <i class="fas fa-users"></i> Drivers
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1><i class="fas fa-users"></i> Manage Drivers</h1>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php
endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php
endif; ?>

        <!-- Drivers Table Card -->
        <div class="card">
            <div class="flex justify-between align-center mb-20">
                <h3><i class="fas fa-list"></i> Registered Drivers</h3>
                <span style="color: #7f8c8d;">
                    Total: <strong><?php echo $result->num_rows; ?></strong> drivers
                </span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Joined Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['username']); ?></strong>
                                </td>
                                <td>
                                    <?php
        if (isset($row['email']) && !empty($row['email'])) {
            echo htmlspecialchars($row['email']);
        }
        else {
            echo '<em style="color: #95a5a6;">Not set</em>';
        }
?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <span style="color: #27ae60; font-weight: 600;">
                                        <i class="fas fa-check-circle"></i> Active
                                    </span>
                                </td>
                                <td>
                                    <a href="?delete=<?php echo $row['id']; ?>" 
                                       onclick="return confirm('Are you sure you want to remove this driver?');"
                                       style="color: #e74c3c; text-decoration: none; font-weight: 600;">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </a>
                                </td>
                            </tr>
                        <?php
    endwhile; ?>
                    <?php
else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #95a5a6;">
                                <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                No drivers registered yet.
                            </td>
                        </tr>
                    <?php
endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Statistics Card -->
        <div class="card">
            <h3><i class="fas fa-chart-bar"></i> Driver Statistics</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Drivers</h3>
                    <p><?php echo $result->num_rows; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Active Today</h3>
                    <p>0</p>
                </div>
                <div class="stat-card">
                    <h3>New This Month</h3>
                    <p>
                        <?php
$month_result = $conn->query("SELECT COUNT(*) as c FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE())");
echo $month_result ? $month_result->fetch_assoc()['c'] : 0;
?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
