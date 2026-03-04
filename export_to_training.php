<?php
/**
 * Intelligent Road Safety System
 * Export Accident Records to Training Dataset
 * Syncs database accidents to ML training CSV
 */

header('Content-Type: application/json');

// Database Configuration
$host = "localhost";
$user = "root";
$pass = "";
$db_name = "accident_prediction_db";

// Connect to Database
$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

// Get parameters
$includeAll = isset($_GET['all']) && $_GET['all'] === 'true';
$minSeverity = isset($_GET['min_severity']) ? $_GET['min_severity'] : 'Low';

// Severity ranking for filtering
$severityRank = ['Low' => 1, 'Medium' => 2, 'High' => 3, 'Fatal' => 4];

// Fetch accident records
$query = "SELECT id, latitude, longitude, accident_severity, 
          weather_condition, road_condition, date_time, description 
          FROM accident_records 
          ORDER BY date_time DESC";

$result = $conn->query($query);

if (!$result) {
    echo json_encode([
        'success' => false,
        'error' => 'Query failed: ' . $conn->error
    ]);
    exit;
}

$accidents = [];
while ($row = $result->fetch_assoc()) {
    // Map accident severity to risk level
    $riskLevel = 'Medium';
    switch ($row['accident_severity']) {
        case 'Fatal':
        case 'High':
            $riskLevel = 'High';
            break;
        case 'Medium':
            $riskLevel = 'Medium';
            break;
        case 'Low':
            $riskLevel = 'Low';
            break;
    }

    // Estimate traffic volume based on accident severity and description
    $traffic = 'Medium';
    if ($row['accident_severity'] === 'Fatal' || $row['accident_severity'] === 'High') {
        $traffic = 'High';
    }
    elseif ($row['accident_severity'] === 'Low') {
        $traffic = 'Low';
    }

    $accidents[] = [
        'id' => $row['id'],
        'latitude' => floatval($row['latitude']),
        'longitude' => floatval($row['longitude']),
        'weather_condition' => $row['weather_condition'] ?? 'Clear',
        'road_condition' => $row['road_condition'] ?? 'Dry',
        'traffic_volume' => $traffic,
        'risk_level' => $riskLevel,
        'severity' => $row['accident_severity'],
        'date' => $row['date_time']
    ];
}

$conn->close();

// Path to training CSV
$csvPath = dirname(__FILE__) . '/../../machine_learning/accident_data.csv';

// Load the blocklist of manually-deleted coordinates
// These were explicitly removed by an admin and must NOT be re-imported
$deletedFile = dirname(__FILE__) . '/../../machine_learning/deleted_coords.json';
$blocklist = [];
if (file_exists($deletedFile)) {
    $raw = json_decode(file_get_contents($deletedFile), true);
    if (is_array($raw))
        $blocklist = $raw;
}

function isBlocklisted($lat, $lng, $blocklist)
{
    foreach ($blocklist as $b) {
        if (abs($b['lat'] - $lat) < 0.0005 && abs($b['lng'] - $lng) < 0.0005) {
            return true;
        }
    }
    return false;
}

// Read existing CSV to avoid duplicates
$existingData = [];
if (file_exists($csvPath)) {
    if (($handle = fopen($csvPath, "r")) !== FALSE) {
        $header = fgetcsv($handle); // Skip header
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (!empty($data[0])) {
                $key = $data[0] . ',' . $data[1]; // lat,lng as key
                $existingData[$key] = true;
            }
        }
        fclose($handle);
    }
}

// Append new accidents to CSV
$newCount = 0;
$skippedBlocklist = 0;
$fileExists = file_exists($csvPath);
$isEmpty = $fileExists ? filesize($csvPath) == 0 : true;

if (($handle = fopen($csvPath, "a")) !== FALSE) {
    // If file is empty, add header
    if ($isEmpty) {
        fputcsv($handle, ['latitude', 'longitude', 'weather_condition', 'road_condition', 'traffic_volume', 'risk_level']);
    }

    foreach ($accidents as $accident) {
        // Skip coords that were manually deleted by the admin
        if (isBlocklisted($accident['latitude'], $accident['longitude'], $blocklist)) {
            $skippedBlocklist++;
            continue;
        }

        $key = $accident['latitude'] . ',' . $accident['longitude'];

        // ... rest of the code ...
        $isDuplicate = false;
        foreach (array_keys($existingData) as $existingKey) {
            $parts = explode(',', $existingKey);
            if (count($parts) < 2)
                continue;

            $existLat = floatval($parts[0]);
            $existLng = floatval($parts[1]);

            $latDiff = abs($existLat - $accident['latitude']);
            $lngDiff = abs($existLng - $accident['longitude']);

            if ($latDiff < 0.0001 && $lngDiff < 0.0001) {
                $isDuplicate = true;
                break;
            }
        }

        if (!$isDuplicate) {
            fputcsv($handle, [
                $accident['latitude'],
                $accident['longitude'],
                $accident['weather_condition'],
                $accident['road_condition'],
                $accident['traffic_volume'],
                $accident['risk_level']
            ]);
            $newCount++;
            $existingData[$key] = true;
        }
    }
    fclose($handle);
}

echo json_encode([
    'success' => true,
    'total_accidents' => count($accidents),
    'new_records_added' => $newCount,
    'skipped_blocklist' => $skippedBlocklist,
    'csv_path' => $csvPath,
    'message' => "Added {$newCount} new records. Skipped {$skippedBlocklist} deleted/blocked records."
]);
?>
