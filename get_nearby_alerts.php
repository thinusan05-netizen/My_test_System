<?php
/**
 * Intelligent Road Safety System
 * Get Nearby Alerts API
 * Returns risk zones and accidents within specified radius
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

// Get parameters
$current_lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$current_lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$radius = isset($_GET['radius']) ? intval($_GET['radius']) : 300; // Default 300 meters

// Validate parameters
if ($current_lat === null || $current_lng === null) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing latitude or longitude parameters'
    ]);
    exit;
}

/**
 * Calculate distance between two coordinates using Haversine formula
 * Returns distance in meters
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371000; // Earth's radius in meters

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c; // Distance in meters
}

$alerts = [];

// 1. Fetch all risk zones
$zones_query = "SELECT id, name, lat, lng, risk_level, radius, description FROM risk_zones";
$zones_result = $conn->query($zones_query);

if ($zones_result && $zones_result->num_rows > 0) {
    while ($zone = $zones_result->fetch_assoc()) {
        $distance = calculateDistance(
            $current_lat,
            $current_lng,
            $zone['lat'],
            $zone['lng']
        );

        // Check if within radius
        if ($distance <= $radius) {
            $alerts[] = [
                'type' => 'risk_zone',
                'id' => $zone['id'],
                'name' => $zone['name'],
                'severity' => $zone['risk_level'],
                'distance' => round($distance),
                'lat' => floatval($zone['lat']),
                'lng' => floatval($zone['lng']),
                'description' => $zone['description'],
                'zone_radius' => $zone['radius']
            ];
        }
    }
}

// 2. Fetch all accident records
$accidents_query = "SELECT id, latitude, longitude, accident_severity, date_time, 
                    description, weather_condition, road_condition 
                    FROM accident_records 
                    ORDER BY date_time DESC";
$accidents_result = $conn->query($accidents_query);

if ($accidents_result && $accidents_result->num_rows > 0) {
    while ($accident = $accidents_result->fetch_assoc()) {
        $distance = calculateDistance(
            $current_lat,
            $current_lng,
            $accident['latitude'],
            $accident['longitude']
        );

        // Check if within radius
        if ($distance <= $radius) {
            $alerts[] = [
                'type' => 'accident',
                'id' => $accident['id'],
                'severity' => $accident['accident_severity'],
                'distance' => round($distance),
                'lat' => floatval($accident['latitude']),
                'lng' => floatval($accident['longitude']),
                'description' => $accident['description'],
                'date' => $accident['date_time'],
                'weather' => $accident['weather_condition'],
                'road_condition' => $accident['road_condition']
            ];
        }
    }
}

// Sort alerts by distance (closest first)
usort($alerts, function ($a, $b) {
    return $a['distance'] - $b['distance'];
});

// --- ML PREDICTION FOR CURRENT LOCATION ---
$mlPrediction = null;
$mlApiUrl = "http://localhost/final_project/backend/api/predict_ml_risk.php?lat={$current_lat}&lng={$current_lng}&weather=Clear&road=Dry&traffic=Medium";

try {
    $mlResponse = @file_get_contents($mlApiUrl);
    if ($mlResponse) {
        $mlData = json_decode($mlResponse, true);
        if ($mlData && isset($mlData['success']) && $mlData['success']) {
            $mlPrediction = [
                'risk_level' => $mlData['risk_level'],
                'confidence' => $mlData['confidence'],
                'probabilities' => $mlData['probabilities']
            ];
        }
    }
}
catch (Exception $e) {
// ML prediction failed, continue without it
}

// Return response
echo json_encode([
    'success' => true,
    'alerts' => $alerts,
    'count' => count($alerts),
    'radius' => $radius,
    'current_location' => [
        'lat' => $current_lat,
        'lng' => $current_lng
    ],
    'ml_prediction' => $mlPrediction
]);

$conn->close();
?>
