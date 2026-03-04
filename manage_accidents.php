<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accidents - Road Safety Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php
session_start();
if (!isset($_SESSION['admin_logged_in']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    header('Location: login.php');
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "accident_prediction_db";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle accident addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $lat = floatval($_POST['latitude']);
    $lng = floatval($_POST['longitude']);
    $severity = $_POST['severity'];
    $weather = $_POST['weather'];
    $road = $_POST['road'];
    $desc = $_POST['description'];
    $datetime = $_POST['datetime'];

    $stmt = $conn->prepare("INSERT INTO accident_records (latitude, longitude, accident_severity, weather_condition, road_condition, description, date_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ddsssss", $lat, $lng, $severity, $weather, $road, $desc, $datetime);

    if ($stmt->execute()) {
        $success_msg = "Accident record added successfully!";
    }
    else {
        $error_msg = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Handle accident deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM accident_records WHERE id = $id");
    header('Location: manage_accidents.php');
    exit;
}

// Search Logic
$search_id = isset($_GET['search_id']) ? intval($_GET['search_id']) : null;
$where_clause = "";
if ($search_id) {
    $where_clause = " WHERE id = $search_id";
}

// Fetch accidents
$query = "SELECT * FROM accident_records" . $where_clause . " ORDER BY date_time DESC";
$result = $conn->query($query);
?>

    <div class="admin-container">
        <aside class="sidebar">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                <span>Road Safety Admin</span>
            </div>
            <nav>
                <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="manage_zones.php"><i class="fas fa-map-marked-alt"></i> Risk Zones</a>
                <a href="manage_accidents.php" class="active"><i class="fas fa-car-crash"></i> Accidents</a>
                <a href="manage_training_data.php"><i class="fas fa-brain"></i> Training Data</a>
                <a href="upload_csv.php"><i class="fas fa-upload"></i> Bulk Upload</a>
                <a href="manage_users.php"><i class="fas fa-users"></i> Users</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <h1><i class="fas fa-car-crash"></i> Manage Accident Records</h1>
                <div class="header-actions">
                    <div class="search-box">
                        <form method="GET" action="" style="display: flex; gap: 5px;">
                            <input type="number" name="search_id" placeholder="Search by ID..." value="<?php echo $search_id; ?>" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 140px;">
                            <button type="submit" class="btn btn-primary" style="padding: 8px 15px;">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if ($search_id): ?>
                                <a href="manage_accidents.php" class="btn btn-secondary" style="padding: 8px 15px; text-decoration: none;">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php
endif; ?>
                        </form>
                    </div>
                    <button onclick="showUploadForm()" class="btn btn-info">
                        <i class="fas fa-upload"></i> Upload CSV
                    </button>
                    <button onclick="showAddForm()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Accident
                    </button>
                    <button onclick="syncAndTrain()" class="btn btn-success">
                        <i class="fas fa-sync-alt"></i> Sync & Train ML Model
                    </button>
                </div>
            </header>

            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php
endif; ?>
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-error"><?php echo $error_msg; ?></div>
            <?php
endif; ?>

            <div id="add-form" class="form-card" style="display: none;">
                <h2>Add New Accident Record</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Latitude *</label>
                            <input type="number" name="latitude" step="0.000001" required>
                        </div>
                        <div class="form-group">
                            <label>Longitude *</label>
                            <input type="number" name="longitude" step="0.000001" required>
                        </div>
                        <div class="form-group">
                            <label>Severity *</label>
                            <select name="severity" required>
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High" selected>High</option>
                                <option value="Fatal">Fatal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date & Time *</label>
                            <input type="datetime-local" name="datetime" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Weather Condition *</label>
                            <select name="weather" required>
                                <option value="Clear">Clear</option>
                                <option value="Rainy">Rainy</option>
                                <option value="Cloudy">Cloudy</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Road Condition *</label>
                            <select name="road" required>
                                <option value="Dry">Dry</option>
                                <option value="Wet">Wet</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Describe the accident details..."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Accident
                        </button>
                        <button type="button" onclick="hideAddForm()" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>

            <div id="upload-form" class="form-card" style="display: none;">
                <h2><i class="fas fa-file-csv"></i> Upload Accident CSV</h2>
                <div class="info-box" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0 0 8px 0; color: #1976d2; font-weight: 600;"><i class="fas fa-info-circle"></i> Required CSV Format:</p>
                    <code style="background: #fff; padding: 4px 8px; border-radius: 4px; font-size: 13px;">latitude,longitude,weather_condition,road_condition,traffic_volume,risk_level</code>
                    <p style="margin: 10px 0 5px 0; font-size: 13px;"><strong>Example:</strong> <code style="background: #fff; padding: 2px 6px; border-radius: 4px; font-size: 12px;">6.927079,79.861244,Rainy,Wet,High,High</code></p>
                    <ul style="margin: 8px 0 0 20px; font-size: 13px; color: #555;">
                        <li><strong>weather:</strong> Clear, Rainy, Cloudy, Foggy, Stormy</li>
                        <li><strong>road:</strong> Dry, Wet</li>
                        <li><strong>traffic:</strong> Low, Medium, High</li>
                        <li><strong>risk:</strong> Low, Medium, High</li>
                    </ul>
                </div>
                <form id="csv-upload-form">
                    <div class="form-group">
                        <label for="csv-file"><i class="fas fa-cloud-upload-alt"></i> Select CSV File</label>
                        <input type="file" id="csv-file" name="csv_file" accept=".csv" required style="padding: 10px;">
                    </div>
                    <div id="upload-progress" style="display: none; margin: 15px 0;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                        <p id="upload-status" style="text-align: center; color: #666; margin-top: 10px;">Uploading...</p>
                    </div>
                    <div class="form-actions" style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload & Import
                        </button>
                        <button type="button" onclick="hideUploadForm()" class="btn btn-secondary" style="background: #95a5a6; color: white;">Cancel</button>
                    </div>
                </form>
            </div>

            <div class="table-card">
                <h2>Accident Records (<?php echo $result->num_rows; ?>)</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Location</th>
                                <th>Severity</th>
                                <th>Weather</th>
                                <th>Road</th>
                                <th>Date & Time</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>
                                        <td>
                                            <small><?php echo number_format($row['latitude'], 6); ?>, <?php echo number_format($row['longitude'], 6); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($row['accident_severity']); ?>">
                                                <?php echo $row['accident_severity']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $row['weather_condition'] ?? 'N/A'; ?></td>
                                        <td><?php echo $row['road_condition'] ?? 'N/A'; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($row['date_time'])); ?></td>
                                        <td><?php echo substr($row['description'] ?? 'No description', 0, 50); ?></td>
                                        <td>
                                            <a href="?delete=<?php echo $row['id']; ?>" 
                                               onclick="return confirm('Delete this accident record?')" 
                                               class="btn-icon btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php
    endwhile; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No accident records found</td>
                                </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="training-status" class="alert" style="display: none;"></div>
        </main>
    </div>

    <script>
        function showAddForm() {
            document.getElementById('add-form').style.display = 'block';
            document.getElementById('upload-form').style.display = 'none';
            document.getElementById('add-form').scrollIntoView({ behavior: 'smooth' });
        }

        function hideAddForm() {
            document.getElementById('add-form').style.display = 'none';
        }

        function showUploadForm() {
            document.getElementById('upload-form').style.display = 'block';
            document.getElementById('add-form').style.display = 'none';
            document.getElementById('upload-form').scrollIntoView({ behavior: 'smooth' });
        }

        function hideUploadForm() {
            document.getElementById('upload-form').style.display = 'none';
        }

        // Handle CSV upload
        document.getElementById('csv-upload-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('csv-file');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a CSV file');
                return;
            }
            
            const formData = new FormData();
            formData.append('csv_file', file);
            
            const progressDiv = document.getElementById('upload-progress');
            const statusP = document.getElementById('upload-status');
            const progressFill = document.getElementById('progress-fill');
            
            progressDiv.style.display = 'block';
            statusP.textContent = 'Uploading and processing CSV...';
            progressFill.style.width = '50%';
            
            try {
                const response = await fetch('../api/upload_accident_csv.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                progressFill.style.width = '100%';
                
                if (result.success) {
                    statusP.innerHTML = `
                        <strong style="color: #4caf50;">✓ Upload Successful!</strong><br>
                        Total rows: ${result.total_rows}<br>
                        Added: ${result.added}<br>
                        Skipped: ${result.skipped}
                    `;
                    
                    if (result.errors && result.errors.length > 0) {
                        statusP.innerHTML += '<br><br><strong>Errors:</strong><br>' + result.errors.join('<br>');
                    }
                    
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    statusP.innerHTML = `<strong style="color: #f44336;">✗ Error:</strong> ${result.error}`;
                    if (result.found_headers) {
                        statusP.innerHTML += `<br><br>Found headers: ${result.found_headers.join(', ')}`;
                    }
                }
            } catch (error) {
                progressFill.style.width = '100%';
                progressFill.style.background = '#f44336';
                statusP.innerHTML = `<strong style="color: #f44336;">✗ Error:</strong> ${error.message}`;
            }
        });

        async function syncAndTrain() {
            const statusEl = document.getElementById('training-status');
            const btn = event.target.closest('.btn') || event.target;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Training...';
            
            statusEl.className = 'alert alert-info';
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <strong>Step 1/3:</strong> Exporting accidents to training CSV...';
            statusEl.style.display = 'block';
            statusEl.scrollIntoView({ behavior: 'smooth' });

            try {
                const response = await fetch('../api/trigger_training.php');
                const result = await response.json();

                if (result.success) {
                    let stepsHtml = '';
                    result.steps.forEach(step => {
                        const icon = step.status === 'success' ? '✓' : step.status === 'warning' ? '⚠' : '✗';
                        const color = step.status === 'success' ? '#4caf50' : step.status === 'warning' ? '#ff9800' : '#f44336';
                        stepsHtml += `<div style="margin: 5px 0;"><span style="color: ${color}; font-weight: bold;">${icon} Step ${step.step}:</span> ${step.name} — ${step.message}</div>`;
                    });

                    statusEl.className = 'alert alert-success';
                    statusEl.innerHTML = `
                        <strong><i class="fas fa-check-circle"></i> Auto-Training Complete!</strong>
                        <div style="margin: 15px 0;">${stepsHtml}</div>
                        <div style="background: rgba(0,0,0,0.05); padding: 12px; border-radius: 8px; margin-top: 10px;">
                            <strong>Summary:</strong><br>
                            Dataset: ${result.summary.dataset_size} examples<br>
                            Test Accuracy: ${result.summary.accuracy}<br>
                            Cross-Validation: ${result.summary.cv_accuracy}<br>
                            Model Size: ${result.summary.model_size}<br>
                            Trained At: ${result.summary.trained_at}
                        </div>
                        <p style="margin-top: 10px;"><strong>✓ Refresh the map to see updated predictions!</strong></p>
                    `;
                } else {
                    throw new Error(result.error || 'Training failed');
                }

            } catch (error) {
                statusEl.className = 'alert alert-error';
                statusEl.innerHTML = `<strong><i class="fas fa-exclamation-circle"></i> Training Failed</strong><br>${error.message}`;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync & Train ML Model';
            }
        }
    </script>

    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-low { background: #4caf50; color: white; }
        .badge-medium { background: #ff9800; color: white; }
        .badge-high { background: #f44336; color: white; }
        .badge-fatal { background: #9c27b0; color: white; }
        .header-actions { display: flex; gap: 10px; }
        .btn-success { background: #4caf50; }
        .btn-success:hover { background: #45a049; }
        .btn-info { background: #2196f3; }
        .btn-info:hover { background: #0b7dda; }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #45a049);
            transition: width 0.3s ease;
            width: 0%;
        }
        #upload-status {
            margin-top: 15px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            line-height: 1.6;
        }
    </style>

    <?php $conn->close(); ?>
</body>
</html>
