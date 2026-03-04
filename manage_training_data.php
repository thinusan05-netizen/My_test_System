<?php
/**
 * Intelligent Road Safety System
 * Manage Training Data - Admin Panel
 * Allows viewing, adding, and deleting records from accident_data.csv
 */

session_start();
if (!isset($_SESSION['admin_logged_in']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    header('Location: login.php');
    exit;
}

$csvFile = dirname(__FILE__) . '/../../machine_learning/accident_data.csv';
$deletedFile = dirname(__FILE__) . '/../../machine_learning/deleted_coords.json';

// Helper: load / save the blocklist of manually-deleted lat,lng pairs
function loadDeletedCoords($path)
{
    if (!file_exists($path))
        return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}
function saveDeletedCoords($path, $list)
{
    file_put_contents($path, json_encode(array_values($list), JSON_PRETTY_PRINT));
}

// Helper: basic Sri Lanka land boundary check
// Approximate bounding box + simple polygon test to reject sea points
function isInsideSriLanka($lat, $lng)
{
    // Tight bounding box first
    if ($lat < 5.85 || $lat > 9.90 || $lng < 79.50 || $lng > 81.90)
        return false;
    // A rough polygon of Sri Lanka's coastline (simplified, ~20 points)
    $polygon = [
        [9.83, 80.05], [9.80, 80.27], [9.72, 80.74], [9.45, 80.95],
        [9.00, 81.22], [8.58, 81.22], [8.10, 81.87], [7.72, 81.87],
        [6.93, 81.63], [6.50, 81.25], [6.10, 80.70], [5.92, 80.56],
        [5.85, 80.25], [5.90, 79.85], [6.18, 79.73], [6.50, 79.65],
        [7.00, 79.76], [7.40, 79.80], [7.83, 79.82], [8.28, 79.70],
        [8.60, 79.85], [9.15, 79.98], [9.50, 80.00], [9.78, 79.98],
        [9.83, 80.05]
    ];
    // Ray-casting algorithm
    $inside = false;
    $n = count($polygon);
    $j = $n - 1;
    for ($i = 0; $i < $n; $i++) {
        $xi = $polygon[$i][1];
        $yi = $polygon[$i][0];
        $xj = $polygon[$j][1];
        $yj = $polygon[$j][0];
        $intersect = (($yi > $lat) != ($yj > $lat)) &&
            ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);
        if ($intersect)
            $inside = !$inside;
        $j = $i;
    }
    return $inside;
}

// Handle Deletion
if (isset($_GET['delete'])) {
    $index = intval($_GET['delete']);
    $rows = [];
    $deletedLat = null;
    $deletedLng = null;

    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $row = 0;
        while (($data = fgetcsv($handle)) !== FALSE) {
            if ($row === ($index + 1)) {
                // Capture the lat/lng of the row being deleted
                $deletedLat = floatval($data[0]);
                $deletedLng = floatval($data[1]);
            }
            else {
                $rows[] = $data;
            }
            $row++;
        }
        fclose($handle);
    }

    // Record deleted coords in blocklist so re-import won't add them back
    if ($deletedLat !== null && $deletedLng !== null) {
        $blocklist = loadDeletedCoords($deletedFile);
        $blocklist[] = ['lat' => $deletedLat, 'lng' => $deletedLng];
        saveDeletedCoords($deletedFile, $blocklist);
    }

    // Write back the CSV without the deleted row
    if (($handle = fopen($csvFile, "w")) !== FALSE) {
        foreach ($rows as $r) {
            fputcsv($handle, $r);
        }
        fclose($handle);
    }
    header('Location: manage_training_data.php?msg=deleted');
    exit;
}

// Handle Addition or Editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $lat = floatval($_POST['latitude']);
    $lng = floatval($_POST['longitude']);
    $weather = $_POST['weather'];
    $road = $_POST['road'];
    $traffic = $_POST['traffic'];
    $risk = $_POST['risk'];

    // === Geographic Validation ===
    // Reject coordinates that fall outside Sri Lanka (e.g. in the sea)
    if (!isInsideSriLanka($lat, $lng)) {
        header('Location: manage_training_data.php?msg=invalid_location');
        exit;
    }

    if ($_POST['action'] === 'add') {
        // Remove this coord from blocklist if admin is deliberately re-adding it
        $blocklist = loadDeletedCoords($deletedFile);
        $blocklist = array_filter($blocklist, function ($b) use ($lat, $lng) {
            return !(abs($b['lat'] - $lat) < 0.001 && abs($b['lng'] - $lng) < 0.001);
        });
        saveDeletedCoords($deletedFile, array_values($blocklist));

        if (($handle = fopen($csvFile, "a")) !== FALSE) {
            fputcsv($handle, [$lat, $lng, $weather, $road, $traffic, $risk]);
            fclose($handle);
        }
        header('Location: manage_training_data.php?msg=added');
        exit;
    }
    elseif ($_POST['action'] === 'edit' && isset($_POST['edit_index'])) {
        $index = intval($_POST['edit_index']);
        $rows = [];
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            $row = 0;
            while (($data = fgetcsv($handle)) !== FALSE) {
                if ($row === ($index + 1)) {
                    $rows[] = [$lat, $lng, $weather, $road, $traffic, $risk];
                }
                else {
                    $rows[] = $data;
                }
                $row++;
            }
            fclose($handle);
        }

        if (($handle = fopen($csvFile, "w")) !== FALSE) {
            foreach ($rows as $r) {
                fputcsv($handle, $r);
            }
            fclose($handle);
        }
        header('Location: manage_training_data.php?msg=updated');
        exit;
    }
}

// Read CSV for display
$accidents = [];
if (file_exists($csvFile)) {
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $row = 0;
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row++;
            if ($row === 1)
                continue; // Skip header
            if (empty($data[0]))
                continue;

            $accidents[] = [
                'id' => $row - 2,
                'latitude' => $data[0],
                'longitude' => $data[1],
                'weather' => $data[2],
                'road' => $data[3],
                'traffic' => $data[4],
                'risk' => $data[5]
            ];
        }
        fclose($handle);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Training Data - Road Safety Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        #map {
            height: 400px;
            width: 100%;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 2px solid #fff;
        }
        .map-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        .btn-view-map {
            background: #3498db;
            color: white !important;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                <span>Road Safety Admin</span>
            </div>
            <nav>
                <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="manage_zones.php"><i class="fas fa-map-marked-alt"></i> Risk Zones</a>
                <a href="manage_accidents.php"><i class="fas fa-car-crash"></i> Accidents</a>
                <a href="manage_training_data.php" class="active"><i class="fas fa-brain"></i> Training Data</a>
                <a href="manage_users.php"><i class="fas fa-users"></i> Users</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <h1><i class="fas fa-brain"></i> Manage Training Dataset</h1>
                <div class="header-actions">
                    <button onclick="showAddForm()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Record
                    </button>
                    <button onclick="syncAndTrain()" class="btn btn-success">
                        <i class="fas fa-sync-alt"></i> Retrain Model
                    </button>
                </div>
            </header>

            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] === 'added'): ?>
                    <div class="alert alert-success">Record added successfully!</div>
                <?php
    elseif ($_GET['msg'] === 'deleted'): ?>
                    <div class="alert alert-success">Record deleted successfully! This location is now blocked from being re-imported.</div>
                <?php
    elseif ($_GET['msg'] === 'updated'): ?>
                    <div class="alert alert-success">Record updated successfully!</div>
                <?php
    elseif ($_GET['msg'] === 'invalid_location'): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Invalid Location!</strong> That coordinate is outside Sri Lanka's land area (possibly in the sea). Please select a valid land location.
                    </div>
                <?php
    endif; ?>
            <?php
endif; ?>

            <div class="map-card">
                <div class="flex justify-between align-center mb-10">
                    <h3><i class="fas fa-map-marked-alt"></i> Training Data Locations</h3>
                    <p style="color: #666; font-size: 14px;">Click on the map to set coordinates for a new record.</p>
                </div>
                <div id="map"></div>
            </div>

            <div id="add-form" class="form-card" style="display: none;">
                <h2 id="form-title">Add Training Record</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="edit_index" id="edit-index" value="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Latitude *</label>
                            <input type="number" name="latitude" id="lat-input" step="0.000001" required placeholder="e.g. 6.9271">
                        </div>
                        <div class="form-group">
                            <label>Longitude *</label>
                            <input type="number" name="longitude" id="lng-input" step="0.000001" required placeholder="e.g. 79.8612">
                        </div>
                        <div class="form-group">
                            <label>Weather *</label>
                            <select name="weather" required>
                                <option value="Clear">Clear</option>
                                <option value="Rainy">Rainy</option>
                                <option value="Cloudy">Cloudy</option>
                                <option value="Foggy">Foggy</option>
                                <option value="Stormy">Stormy</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Road *</label>
                            <select name="road" required>
                                <option value="Dry">Dry</option>
                                <option value="Wet">Wet</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Traffic *</label>
                            <select name="traffic" required>
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Risk Level *</label>
                            <select name="risk" required>
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" id="btn-submit" class="btn btn-primary">Save Record</button>
                        <button type="button" onclick="cancelEdit()" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>

            <div class="table-card">
                <div class="flex justify-between align-center mb-10">
                    <h2>Training Records (<?php echo count($accidents); ?>)</h2>
                    <div class="filters flex gap-10">
                        <select id="filter-risk" onchange="applyFilters()" style="padding: 5px; border-radius: 4px;">
                            <option value="all">All Risk Levels</option>
                            <option value="High">High Risk</option>
                            <option value="Medium">Medium Risk</option>
                            <option value="Low">Low Risk</option>
                        </select>
                        <select id="filter-weather" onchange="applyFilters()" style="padding: 5px; border-radius: 4px;">
                            <option value="all">All Weather</option>
                            <option value="Clear">Clear</option>
                            <option value="Rainy">Rainy</option>
                            <option value="Cloudy">Cloudy</option>
                            <option value="Foggy">Foggy</option>
                            <option value="Stormy">Stormy</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Lat</th>
                                <th>Lng</th>
                                <th>Weather</th>
                                <th>Road</th>
                                <th>Traffic</th>
                                <th>Risk Level</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($accidents) as $acc): ?>
                                <tr id="row-<?php echo $acc['id']; ?>">
                                    <td><?php echo $acc['latitude']; ?></td>
                                    <td><?php echo $acc['longitude']; ?></td>
                                    <td><?php echo $acc['weather']; ?></td>
                                    <td><?php echo $acc['road']; ?></td>
                                    <td><?php echo $acc['traffic']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($acc['risk']); ?>">
                                            <?php echo $acc['risk']; ?>
                                        </span>
                                    </td>
                                    <td class="flex gap-5">
                                        <button onclick="focusPoint(<?php echo $acc['latitude']; ?>, <?php echo $acc['longitude']; ?>, '<?php echo $acc['risk']; ?>')" class="btn-icon btn-view-map" title="View on Map">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                        <button onclick='enterEditMode(<?php echo json_encode($acc); ?>)' class="btn-icon btn-info" style="background:#2ecc71; color:white;" title="Edit Record">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $acc['id']; ?>" 
                                           onclick="return confirm('Remove this record from training set?')" 
                                           class="btn-icon btn-danger" title="Delete Record">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="training-status" class="alert" style="display: none; margin-top: 20px;"></div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        let map;
        const DEFAULT_COORDS = [6.9271, 79.8612];
        const markers = [];

        document.addEventListener('DOMContentLoaded', () => {
            initMap();
            loadMarkers();
        });

        function initMap() {
            map = L.map('map').setView(DEFAULT_COORDS, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Click listener
            map.on('click', (e) => {
                const lat = e.latlng.lat.toFixed(6);
                const lng = e.latlng.lng.toFixed(6);
                
                document.getElementById('lat-input').value = lat;
                document.getElementById('lng-input').value = lng;
                
                showAddForm();
                
                // Add a temporary marker for visual feedback
                L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'temp-marker',
                        html: '<i class="fas fa-plus-circle" style="color: #3498db; font-size: 20px;"></i>'
                    })
                }).addTo(map).bindPopup("New Point Location").openPopup();
            });
        }

        function loadMarkers() {
            // Clear existing markers
            markers.forEach(m => map.removeLayer(m));
            markers.length = 0;

            const data = <?php echo json_encode($accidents); ?>;
            const riskFilter = document.getElementById('filter-risk').value;
            const weatherFilter = document.getElementById('filter-weather').value;

            data.forEach(point => {
                // Apply filters
                if (riskFilter !== 'all' && point.risk !== riskFilter) return;
                if (weatherFilter !== 'all' && point.weather !== weatherFilter) return;

                const color = point.risk === 'High' ? '#f44336' : (point.risk === 'Medium' ? '#ff9800' : '#4caf50');
                const marker = L.circleMarker([point.latitude, point.longitude], {
                    radius: 8,
                    fillColor: color,
                    color: '#fff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                }).addTo(map);
                
                marker.bindPopup(`
                    <div style="min-width: 150px;">
                        <b>Training Point #${point.id}</b><br>
                        Risk: <span style="color:${color}">${point.risk}</span><br>
                        Weather: ${point.weather}<br>
                        Road: ${point.road}<br><br>
                        <div style="display: flex; gap: 5px;">
                            <button onclick='enterEditMode(${JSON.stringify(point)})' style="flex: 1; padding: 6px; background: #2ecc71; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="confirmDelete(${point.id})" style="flex: 1; padding: 6px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `);
                markers.push(marker);
            });
        }

        function applyFilters() {
            loadMarkers();
            
            const riskFilter = document.getElementById('filter-risk').value;
            const weatherFilter = document.getElementById('filter-weather').value;
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const cells = row.getElementsByTagName('td');
                if (cells.length < 6) return; // Skip empty/message rows

                const risk = cells[5].innerText.trim();
                const weather = cells[2].innerText.trim();

                let show = true;
                if (riskFilter !== 'all' && risk !== riskFilter) show = false;
                if (weatherFilter !== 'all' && weather !== weatherFilter) show = false;

                row.style.display = show ? '' : 'none';
            });
        }

        function enterEditMode(point) {
            document.getElementById('add-form').style.display = 'block';
            document.getElementById('form-title').innerText = `Edit Training Record #${point.id}`;
            document.getElementById('form-action').value = 'edit';
            document.getElementById('edit-index').value = point.id;
            document.getElementById('btn-submit').innerText = 'Update Record';
            document.getElementById('btn-submit').style.background = '#2ecc71';

            document.getElementById('lat-input').value = point.latitude;
            document.getElementById('lng-input').value = point.longitude;
            
            // Set dropdowns
            const selects = ['weather', 'road', 'traffic', 'risk'];
            selects.forEach(s => {
                document.querySelector(`select[name="${s}"]`).value = point[s];
            });

            document.getElementById('add-form').scrollIntoView({ behavior: 'smooth' });
        }

        function cancelEdit() {
            document.getElementById('add-form').style.display = 'none';
            document.getElementById('form-title').innerText = 'Add Training Record';
            document.getElementById('form-action').value = 'add';
            document.getElementById('edit-index').value = '';
            document.getElementById('btn-submit').innerText = 'Save Record';
            document.getElementById('btn-submit').style.background = '';
            
            // Clear inputs
            document.getElementById('lat-input').value = '';
            document.getElementById('lng-input').value = '';
        }

        function confirmDelete(id) {
            if (confirm('Are you sure you want to remove this record from the training set?')) {
                window.location.href = `?delete=${id}`;
            }
        }

        function focusPoint(lat, lng, risk) {
            map.setView([lat, lng], 15);
            L.circleMarker([lat, lng], {
                radius: 12,
                fillColor: '#3498db',
                color: '#fff',
                weight: 3,
                opacity: 1,
                fillOpacity: 0.5
            }).addTo(map).bindPopup("Current Selection").openPopup();
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showAddForm() { 
            document.getElementById('add-form').style.display = 'block';
            document.getElementById('add-form').scrollIntoView({ behavior: 'smooth' });
        }
        function hideAddForm() { document.getElementById('add-form').style.display = 'none'; }

        async function syncAndTrain() {
            const statusEl = document.getElementById('training-status');
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            statusEl.className = 'alert alert-info';
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Triggering model retraining...';
            statusEl.style.display = 'block';

            try {
                const response = await fetch('../api/trigger_training.php');
                const result = await response.json();
                if (result.success) {
                    statusEl.className = 'alert alert-success';
                    statusEl.innerHTML = `<strong>✓ Success!</strong> Model retrained with ${result.summary.dataset_size} records. Accuracy: ${result.summary.accuracy}`;
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                statusEl.className = 'alert alert-error';
                statusEl.innerHTML = `<strong>✗ Error:</strong> ${error.message}`;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Retrain Model';
            }
        }
    </script>

    <style>
        .sidebar nav a i { min-width: 25px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .badge-low { background: #4caf50; color: white; }
        .badge-medium { background: #ff9800; color: white; }
        .badge-high { background: #f44336; color: white; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; }
        .alert-success { background: #e8f5e9; border-color: #4caf50; color: #2e7d32; }
        .alert-info { background: #e3f2fd; border-color: #2196f3; color: #1565c0; }
        .alert-error { background: #ffebee; border-color: #f44336; color: #c62828; }
    </style>
</body>
</html>
