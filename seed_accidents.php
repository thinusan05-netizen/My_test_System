<?php
/**
 * Intelligent Road Safety System
 * Sample Accident Data Seeder
 * Populates accident_records table with test data
 */

$host = "localhost";
$user = "root";
$pass = "";
$db_name = "accident_prediction_db";

// Connect to Database
$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sample accident data (Sri Lanka locations)
$accidents = [
    [
        'lat' => 6.9271,
        'lng' => 79.8612,
        'severity' => 'Fatal',
        'date' => '2026-02-10 14:30:00',
        'desc' => 'Head-on collision at Maradana junction',
        'weather' => 'Heavy Rain',
        'road' => 'Wet and slippery'
    ],
    [
        'lat' => 6.9344,
        'lng' => 79.8428,
        'severity' => 'High',
        'date' => '2026-02-12 09:15:00',
        'desc' => 'Multi-vehicle pileup near Colombo Fort',
        'weather' => 'Foggy',
        'road' => 'Poor visibility'
    ],
    [
        'lat' => 6.9147,
        'lng' => 79.8778,
        'severity' => 'Medium',
        'date' => '2026-02-13 18:45:00',
        'desc' => 'Side collision at Borella intersection',
        'weather' => 'Clear',
        'road' => 'Dry'
    ],
    [
        'lat' => 6.8649,
        'lng' => 79.8997,
        'severity' => 'High',
        'date' => '2026-02-11 22:00:00',
        'desc' => 'Pedestrian accident in Nugegoda',
        'weather' => 'Clear',
        'road' => 'Poor lighting'
    ],
    [
        'lat' => 6.9275,
        'lng' => 79.8434,
        'severity' => 'Medium',
        'date' => '2026-02-09 16:20:00',
        'desc' => 'Rear-end collision near Galle Face',
        'weather' => 'Sunny',
        'road' => 'Heavy traffic'
    ],
    [
        'lat' => 7.2906,
        'lng' => 80.6337,
        'severity' => 'Fatal',
        'date' => '2026-02-08 07:30:00',
        'desc' => 'Bus accident on Kandy road',
        'weather' => 'Rainy',
        'road' => 'Landslide debris'
    ],
    [
        'lat' => 6.0535,
        'lng' => 80.2210,
        'severity' => 'High',
        'date' => '2026-02-07 12:00:00',
        'desc' => 'Truck rollover on Galle highway',
        'weather' => 'Windy',
        'road' => 'Curved road'
    ]
];

$count = 0;

foreach ($accidents as $accident) {
    $lat = $accident['lat'];
    $lng = $accident['lng'];
    $severity = $conn->real_escape_string($accident['severity']);
    $date = $accident['date'];
    $desc = $conn->real_escape_string($accident['desc']);
    $weather = $conn->real_escape_string($accident['weather']);
    $road = $conn->real_escape_string($accident['road']);

    $sql = "INSERT INTO accident_records 
            (latitude, longitude, accident_severity, date_time, description, weather_condition, road_condition) 
            VALUES ($lat, $lng, '$severity', '$date', '$desc', '$weather', '$road')";

    if ($conn->query($sql) === TRUE) {
        $count++;
        echo "✓ Added accident: $desc<br>";
    }
    else {
        echo "✗ Error: " . $conn->error . "<br>";
    }
}

echo "<br><h3>✅ Seeding Complete!</h3>";
echo "<p>$count accident records added to database.</p>";
echo "<p><a href='../../frontend/index.html'>View on Map</a></p>";

$conn->close();
?>
