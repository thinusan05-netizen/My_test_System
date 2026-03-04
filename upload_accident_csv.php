<?php
/**
 * Intelligent Road Safety System
 * CSV Upload Handler for Accident Training Data
 * Imports CSV file to database and training dataset
 */

session_start();
// Support both session formats (admin_logged_in and role-based)
if (!isset($_SESSION['admin_logged_in']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "accident_prediction_db";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No file uploaded'
    ]);
    exit;
}

$file = $_FILES['csv_file'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'error' => 'File upload error: ' . $file['error']
    ]);
    exit;
}

// Check file extension
$filename = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    echo json_encode([
        'success' => false,
        'error' => 'Only CSV files are allowed'
    ]);
    exit;
}

// Process CSV file
$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    echo json_encode([
        'success' => false,
        'error' => 'Could not read CSV file'
    ]);
    exit;
}

$rowCount = 0;
$addedCount = 0;
$skippedCount = 0;
$errors = [];

// Read header
$header = fgetcsv($handle);

// Validate header format
$expectedHeaders = ['latitude', 'longitude', 'weather_condition', 'road_condition', 'traffic_volume', 'risk_level'];
$headerLower = array_map('strtolower', array_map('trim', $header));

if ($headerLower !== $expectedHeaders) {
    fclose($handle);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid CSV format. Expected headers: latitude,longitude,weather_condition,road_condition,traffic_volume,risk_level',
        'found_headers' => $header
    ]);
    exit;
}

// Prepare insert statement
$stmt = $conn->prepare("INSERT INTO accident_records (latitude, longitude, weather_condition, road_condition, accident_severity, date_time, description) VALUES (?, ?, ?, ?, ?, NOW(), ?)");

// Also prepare to append to training CSV
$trainingCsvPath = dirname(__FILE__) . '/../../machine_learning/accident_data.csv';
$trainingHandle = fopen($trainingCsvPath, 'a');

// Process each row
while (($data = fgetcsv($handle)) !== FALSE) {
    $rowCount++;

    // Skip empty rows
    if (empty($data[0]) || empty($data[1])) {
        $skippedCount++;
        continue;
    }

    try {
        $lat = floatval(trim($data[0]));
        $lng = floatval(trim($data[1]));
        $weather = trim($data[2] ?? 'Clear');
        $road = trim($data[3] ?? 'Dry');
        $traffic = trim($data[4] ?? 'Medium');
        $risk = trim($data[5] ?? 'Medium');

        // Validate coordinates
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $errors[] = "Row $rowCount: Invalid coordinates ($lat, $lng)";
            $skippedCount++;
            continue;
        }

        // Map risk level to accident severity
        $severity = 'Medium';
        if ($risk === 'High') {
            $severity = 'High';
        }
        elseif ($risk === 'Low') {
            $severity = 'Low';
        }

        $description = "Imported from CSV - $weather weather, $road road, $traffic traffic";

        // Insert into database
        $stmt->bind_param("ddssss", $lat, $lng, $weather, $road, $severity, $description);

        if ($stmt->execute()) {
            $addedCount++;

            // Also add to training CSV
            fputcsv($trainingHandle, [$lat, $lng, $weather, $road, $traffic, $risk]);
        }
        else {
            $errors[] = "Row $rowCount: Database insert failed - " . $stmt->error;
            $skippedCount++;
        }

    }
    catch (Exception $e) {
        $errors[] = "Row $rowCount: " . $e->getMessage();
        $skippedCount++;
    }
}

fclose($handle);
fclose($trainingHandle);
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'total_rows' => $rowCount,
    'added' => $addedCount,
    'skipped' => $skippedCount,
    'errors' => $errors,
    'message' => "Successfully imported $addedCount accidents. Added to both database and training dataset."
]);
?>
