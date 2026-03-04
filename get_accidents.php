<?php
/**
 * Intelligent Road Safety System
 * Get Accidents API
 * Returns all accident records from database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

// Fetch all accident records
$sql = "SELECT id, latitude, longitude, accident_severity, date_time, 
        description, weather_condition, road_condition, created_at 
        FROM accident_records 
        ORDER BY date_time DESC";

$result = $conn->query($sql);

$accidents = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $accidents[] = [
            'id' => $row['id'],
            'latitude' => floatval($row['latitude']),
            'longitude' => floatval($row['longitude']),
            'accident_severity' => $row['accident_severity'],
            'date_time' => $row['date_time'],
            'description' => $row['description'],
            'weather_condition' => $row['weather_condition'],
            'road_condition' => $row['road_condition'],
            'created_at' => $row['created_at']
        ];
    }
}

echo json_encode([
    'success' => true,
    'accidents' => $accidents,
    'count' => count($accidents)
]);

$conn->close();
?>
