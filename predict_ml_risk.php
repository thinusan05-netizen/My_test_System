<?php
/**
 * Intelligent Road Safety System
 * ML Risk Prediction API Wrapper
 * Calls Python ML model and returns risk prediction
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get parameters
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$weather = isset($_GET['weather']) ? $_GET['weather'] : 'Clear';
$road = isset($_GET['road']) ? $_GET['road'] : 'Dry';
$traffic = isset($_GET['traffic']) ? $_GET['traffic'] : 'Medium';

// Validate parameters
if ($lat === null || $lng === null) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing latitude or longitude parameters'
    ]);
    exit;
}

// Path to Python script
$pythonScript = dirname(__FILE__) . '/../../machine_learning/predict_risk.py';
$pythonPath = 'python'; // Adjust if needed

// Build command
$command = sprintf(
    '%s "%s" %f %f %s %s %s 2>&1',
    $pythonPath,
    $pythonScript,
    $lat,
    $lng,
    escapeshellarg($weather),
    escapeshellarg($road),
    escapeshellarg($traffic)
);

// Execute Python script
$output = shell_exec($command);

// Parse JSON output
$result = json_decode($output, true);

if ($result === null) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to execute ML prediction',
        'debug' => $output
    ]);
}
else {
    echo json_encode($result);
}
?>
