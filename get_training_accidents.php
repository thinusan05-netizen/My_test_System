<?php
/**
 * Intelligent Road Safety System
 * Get Training Data Accidents API
 * Returns accident locations from ML training dataset with predicted risk
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Path to training data CSV
$csvFile = dirname(__FILE__) . '/../../machine_learning/accident_data.csv';

if (!file_exists($csvFile)) {
    echo json_encode([
        'success' => false,
        'error' => 'Training data file not found'
    ]);
    exit;
}

$accidents = [];
$row = 0;

// Read CSV file
if (($handle = fopen($csvFile, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row++;

        // Skip header row
        if ($row === 1)
            continue;

        // Skip empty rows
        if (empty($data[0]))
            continue;

        $accidents[] = [
            'id' => $row - 1,
            'latitude' => floatval($data[0]),
            'longitude' => floatval($data[1]),
            'weather_condition' => $data[2] ?? 'Unknown',
            'road_condition' => $data[3] ?? 'Unknown',
            'traffic_volume' => $data[4] ?? 'Unknown',
            'risk_level' => $data[5] ?? 'Unknown'
        ];
    }
    fclose($handle);
}

echo json_encode([
    'success' => true,
    'accidents' => $accidents,
    'count' => count($accidents)
]);
?>
