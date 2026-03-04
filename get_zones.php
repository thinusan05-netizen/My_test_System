<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../db/db_connect.php';

$query = "SELECT * FROM risk_zones";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $zones = array();
    while ($row = $result->fetch_assoc()) {
        $zone_item = array(
            "id" => $row['id'],
            "name" => $row['name'],
            "lat" => $row['lat'],
            "lng" => $row['lng'],
            "risk" => $row['risk_level'],
            "radius" => $row['radius'],
            "description" => $row['description'],
            "color" => $row['color']
        );
        array_push($zones, $zone_item);
    }
    http_response_code(200);
    echo json_encode(array("status" => "success", "data" => $zones));
}
else {
    http_response_code(200); // OK but empty
    echo json_encode(array("status" => "success", "data" => []));
}

$conn->close();
?>
