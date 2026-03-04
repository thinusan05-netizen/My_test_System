<?php
/**
 * Intelligent Road Safety System
 * Manage Risk Zones Page
 * Add, view, edit, and delete danger zones
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

// CSV Import Logic
if (isset($_POST['import']) && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");

        $count = 0;
        $line = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $line++;

            // Skip header row if it exists
            if ($line == 1 && !is_numeric($data[1])) {
                continue;
            }

            // Validate data
            if (count($data) >= 3) {
                $name = $conn->real_escape_string(trim($data[0]));
                $lat = floatval($data[1]);
                $lng = floatval($data[2]);
                $risk = isset($data[3]) ? $conn->real_escape_string(trim($data[3])) : 'High';
                $radius = isset($data[4]) ? intval($data[4]) : 300;
                $desc = isset($data[5]) ? $conn->real_escape_string(trim($data[5])) : '';

                // Validate coordinates
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    $sql = "INSERT INTO risk_zones (name, lat, lng, risk_level, radius, description) 
                            VALUES ('$name', $lat, $lng, '$risk', $radius, '$desc')";

                    if ($conn->query($sql)) {
                        $count++;
                    }
                }
            }
        }
        fclose($handle);

        if ($count > 0) {
            $message = "$count zone(s) imported successfully!";
        }
        else {
            $error = "No valid zones found in CSV file.";
        }
    }
    else {
        $error = "Error uploading file. Please try again.";
    }
}

// Delete Zone Logic
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM risk_zones WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Zone deleted successfully!";
    }
    else {
        $error = "Error deleting zone: " . $conn->error;
    }
}

// Fetch all zones
$sql = "SELECT * FROM risk_zones ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Zones - Road Safety System</title>
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
        <a href="manage_zones.php" class="active">
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
        <a href="manage_users.php">
            <i class="fas fa-users"></i> Drivers
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1><i class="fas fa-map-marked-alt"></i> Manage Risk Zones</h1>

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

        <!-- CSV Import Card -->
        <div class="card" style="border-left: 4px solid #27ae60;">
            <h3><i class="fas fa-file-csv"></i> Import Danger Zones (CSV)</h3>
            <p style="color: #7f8c8d; margin-bottom: 15px;">
                Upload a CSV file containing danger zone data. Format: Name, Latitude, Longitude, Risk Level, Radius, Description
            </p>
            
            <form method="POST" enctype="multipart/form-data" class="flex align-center gap-10">
                <input type="file" name="csv_file" accept=".csv" required style="flex: 1;">
                <button type="submit" name="import" class="btn btn-success">
                    <i class="fas fa-upload"></i> Upload & Import
                </button>
            </form>
            
            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                <small style="color: #7f8c8d;">
                    <strong>Example CSV format:</strong><br>
                    Colombo Fort, 6.9344, 79.8428, High, 300, Busy intersection<br>
                    Maradana, 6.9271, 79.8612, Medium, 250, Railway crossing
                </small>
            </div>
        </div>

        <!-- Add Zone via Map Card -->
        <div class="card" style="border-left: 4px solid #3498db;">
            <div class="flex justify-between align-center">
                <div>
                    <h3><i class="fas fa-map"></i> Add Zone via Interactive Map</h3>
                    <p style="color: #7f8c8d; margin-top: 5px;">
                        Use the map interface to visually select and add danger zones
                    </p>
                </div>
                <a href="../../frontend/admin.html" target="_blank" class="btn btn-info">
                    <i class="fas fa-external-link-alt"></i> Open Map
                </a>
            </div>
        </div>

        <!-- Existing Zones Card -->
        <div class="card">
            <div class="flex justify-between align-center mb-20">
                <h3><i class="fas fa-list"></i> Existing Danger Zones</h3>
                <span style="color: #7f8c8d;">
                    Total: <strong><?php echo $result->num_rows; ?></strong> zones
                </span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Zone Name</th>
                        <th>Risk Level</th>
                        <th>Coordinates</th>
                        <th>Radius (m)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                    <?php if (!empty($row['description'])): ?>
                                        <br><small style="color: #7f8c8d;">
                                            <?php echo htmlspecialchars($row['description']); ?>
                                        </small>
                                    <?php
        endif; ?>
                                </td>
                                <td>
                                    <?php
        $risk_color = '';
        switch ($row['risk_level']) {
            case 'High':
                $risk_color = '#e74c3c';
                break;
            case 'Medium':
                $risk_color = '#f39c12';
                break;
            case 'Low':
                $risk_color = '#27ae60';
                break;
        }
?>
                                    <span style="color: <?php echo $risk_color; ?>; font-weight: 600;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php echo $row['risk_level']; ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php echo number_format($row['lat'], 6); ?>,
                                        <?php echo number_format($row['lng'], 6); ?>
                                    </small>
                                </td>
                                <td><?php echo $row['radius']; ?>m</td>
                                <td>
                                    <a href="?delete=<?php echo $row['id']; ?>" 
                                       onclick="return confirm('Delete this zone?');"
                                       style="color: #e74c3c; text-decoration: none; font-weight: 600;">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php
    endwhile; ?>
                    <?php
else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #95a5a6;">
                                <i class="fas fa-map-marked-alt" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                No danger zones added yet. Import CSV or add via map.
                            </td>
                        </tr>
                    <?php
endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
