<?php
$host = "localhost";
$user = "root";
$pass = "";

// 1. Create DB
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$sql = "CREATE DATABASE IF NOT EXISTS accident_prediction_db";
if ($conn->query($sql) === TRUE)
    echo "Database checking/creation... OK<br>";
else
    die("Error creating database: " . $conn->error);

$conn->select_db("accident_prediction_db");

// 2. Create Users Table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE)
    echo "Users table... OK<br>";
else
    echo "Error creating users table: " . $conn->error . "<br>";

// 3. Create Risk Zones Table
$sql = "CREATE TABLE IF NOT EXISTS risk_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    risk_level ENUM('Low', 'Medium', 'High') NOT NULL,
    radius INT DEFAULT 300,
    description TEXT,
    color VARCHAR(20) DEFAULT 'orange',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE)
    echo "Risk Zones table... OK<br>";
else
    echo "Error creating risk_zones table: " . $conn->error . "<br>";

// 4. Create Admins Table (Separate from Users)
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    full_name VARCHAR(100),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
)";
if ($conn->query($sql) === TRUE)
    echo "Admins table... OK<br>";
else
    echo "Error creating admins table: " . $conn->error . "<br>";

// 5. Create Default Admin Account
$pass = password_hash("admin123", PASSWORD_DEFAULT);
$sql = "INSERT IGNORE INTO admins (username, password, email, full_name) 
        VALUES ('admin', '$pass', 'admin@roadsafety.local', 'System Administrator')";

if ($conn->query($sql) === TRUE)
    echo "Admin account (admin/admin123)... OK<br>";
else
    echo "Error creating admin: " . $conn->error . "<br>";

// 6. Create Accident Records Table
$sql = "CREATE TABLE IF NOT EXISTS accident_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accident_severity ENUM('High', 'Medium', 'Fatal') NOT NULL,
    date_time DATETIME NOT NULL,
    description TEXT,
    weather_condition VARCHAR(50),
    road_condition VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_severity (accident_severity)
)";
if ($conn->query($sql) === TRUE)
    echo "Accident Records table... OK<br>";
else
    echo "Error creating accident_records table: " . $conn->error . "<br>";

echo "<br><h3>Setup Complete!</h3>";
echo "<a href='../admin/login.php'>Go to Admin Login</a>";

$conn->close();
?>
