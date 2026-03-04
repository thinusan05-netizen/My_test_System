<?php
// backend/db/force_reset_admin.php
$host = "localhost";
$user = "root";
$pass = "";
$db_name = "accident_prediction_db";

$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Delete existing admin
$conn->query("DELETE FROM users WHERE username = 'admin'");

// 2. Create new admin hash
$password = "admin123";
$hash = password_hash($password, PASSWORD_DEFAULT);

// 3. Insert fresh admin
$stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
$role = 'admin';
$stmt->bind_param("sss", $role, $hash, $role); // username='admin'

// Oops, bind param args: username=admin, password=hash, role=admin
$username = 'admin';
$stmt->bind_param("sss", $username, $hash, $role);

if ($stmt->execute()) {
    echo "<h1>Admin User Reset Successfully!</h1>";
    echo "<p>Username: <b>admin</b></p>";
    echo "<p>Password: <b>admin123</b></p>";
    echo "<p><a href='../admin/login.php'>Go to Login</a></p>";
}
else {
    echo "Error: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>
