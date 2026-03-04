<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../db/db_connect.php';

$data = json_decode(file_get_contents("php://input"));

if (isset($data->username) && isset($data->password)) {
    $username = $data->username;
    $password = password_hash($data->password, PASSWORD_BCRYPT);
    $role = isset($data->role) ? $data->role : 'user';

    // Check availability
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode(array("status" => "error", "message" => "Username already exists."));
        $check->close();
        exit();
    }
    $check->close();

    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $password, $role);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(array("status" => "success", "message" => "User registered successfully."));
    }
    else {
        http_response_code(503);
        echo json_encode(array("status" => "error", "message" => "Unable to register user."));
    }
    $stmt->close();
}
else {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Incomplete data."));
}

$conn->close();
?>
