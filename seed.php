<?php
include_once 'db_connect.php';

$zones = [
    ['Colombo Fort', 6.9344, 79.8428, 'High'],
    ['Maradana', 6.9271, 79.8612, 'Medium'],
    ['Borella', 6.9147, 79.8778, 'High'],
    ['Nugegoda', 6.8649, 79.8997, 'Medium'],
    ['Galle Face', 6.9275, 79.8434, 'Low']
];

foreach ($zones as $zone) {
    $name = $zone[0];
    $lat = $zone[1];
    $lng = $zone[2];
    $risk = $zone[3];

    $sql = "INSERT INTO risk_zones (location_name, latitude, longitude, risk_level) VALUES ('$name', $lat, $lng, '$risk')";

    if ($conn->query($sql) === TRUE) {
        echo "New record created successfully: $name<br>";
    }
    else {
        echo "Error: " . $sql . "<br>" . $conn->error . "<br>";
    }
}

$conn->close();
?>
