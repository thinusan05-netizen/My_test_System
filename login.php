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
    $password = $data->password;

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            http_response_code(200);
            echo json_encode(array(
                "status" => "success",
                "message" => "Login successful.",
                "user" => array(
                    "id" => $row['id'],
                    "username" => $row['username'],
                    "role" => $row['role']
                )
            ));
        }
        else {
            http_response_code(401);
            echo json_encode(array("status" => "error", "message" => "Incorrect password."));
        }
    }
    else {
        http_response_code(404); // User not found
        echo json_encode(array("status" => "error", "message" => "User not found."));
    }
    $stmt->close();
}
else {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Incomplete data."));
}

$conn->close();
?>
