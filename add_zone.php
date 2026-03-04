<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../db/db_connect.php';

$data = json_decode(file_get_contents("php://input"));

if (
!empty($data->name) &&
!empty($data->lat) &&
!empty($data->lng) &&
!empty($data->risk)
) {
    // Default values
    $radius = !empty($data->radius) ? $data->radius : 300;
    $description = !empty($data->description) ? $data->description : "Caution: High accident risk area.";

    // Determine color
    $color = "orange";
    if ($data->risk === "High")
        $color = "red";
    if ($data->risk === "Low")
        $color = "green";

    $query = "INSERT INTO risk_zones (name, lat, lng, risk_level, radius, description, color) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);

    $stmt->bind_param("sdddiss", $data->name, $data->lat, $data->lng, $data->risk, $radius, $description, $color);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(array("status" => "success", "message" => "Zone added successfully."));
    }
    else {
        http_response_code(503);
        echo json_encode(array("status" => "error", "message" => "Unable to add zone."));
    }
}
else {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Incomplete data."));
}
?>
