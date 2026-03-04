<?php
/**
 * Migration Script: Create Admins Table
 * Purpose: Create a separate admins table for admin authentication
 * Run this once to set up the new admin authentication system
 */

$host = "localhost";
$user = "root";
$pass = "";
$db_name = "accident_prediction_db";

// Connect to database
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select database
$conn->select_db($db_name);

echo "<h2>Admin Table Migration</h2>";
echo "<hr>";

// Step 1: Create admins table
echo "<h3>Step 1: Creating admins table...</h3>";
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

if ($conn->query($sql) === TRUE) {
    echo "✅ Admins table created successfully<br>";
}
else {
    echo "❌ Error creating admins table: " . $conn->error . "<br>";
}

// Step 2: Check if admin exists in users table
echo "<h3>Step 2: Migrating existing admin from users table...</h3>";
$check_users = "SELECT * FROM users WHERE role = 'admin' LIMIT 1";
$result = $conn->query($check_users);

if ($result && $result->num_rows > 0) {
    $admin_user = $result->fetch_assoc();

    // Check if already migrated
    $check_admins = "SELECT * FROM admins WHERE username = '" . $admin_user['username'] . "'";
    $admin_exists = $conn->query($check_admins);

    if ($admin_exists->num_rows == 0) {
        // Migrate admin to admins table
        $migrate_sql = "INSERT INTO admins (username, password, email, full_name, created_at) 
                       VALUES (
                           '" . $conn->real_escape_string($admin_user['username']) . "',
                           '" . $admin_user['password'] . "',
                           NULL,
                           'System Administrator',
                           '" . $admin_user['created_at'] . "'
                       )";

        if ($conn->query($migrate_sql) === TRUE) {
            echo "✅ Admin migrated from users table: " . $admin_user['username'] . "<br>";
        }
        else {
            echo "❌ Error migrating admin: " . $conn->error . "<br>";
        }
    }
    else {
        echo "ℹ️ Admin already exists in admins table<br>";
    }
}
else {
    echo "ℹ️ No admin found in users table to migrate<br>";
}

// Step 3: Ensure default admin exists
echo "<h3>Step 3: Ensuring default admin account exists...</h3>";
$check_default = "SELECT * FROM admins WHERE username = 'admin'";
$default_exists = $conn->query($check_default);

if ($default_exists->num_rows == 0) {
    $default_password = password_hash("admin123", PASSWORD_DEFAULT);
    $create_default = "INSERT INTO admins (username, password, email, full_name) 
                      VALUES ('admin', '$default_password', 'admin@roadsafety.local', 'System Administrator')";

    if ($conn->query($create_default) === TRUE) {
        echo "✅ Default admin created (username: admin, password: admin123)<br>";
    }
    else {
        echo "❌ Error creating default admin: " . $conn->error . "<br>";
    }
}
else {
    echo "ℹ️ Default admin already exists<br>";
}

// Step 4: Display all admins
echo "<h3>Step 4: Current Admin Accounts:</h3>";
$list_admins = "SELECT id, username, email, full_name, created_at, last_login FROM admins";
$admins_result = $conn->query($list_admins);

if ($admins_result && $admins_result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #007bff; color: white;'>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Full Name</th>
            <th>Created At</th>
            <th>Last Login</th>
          </tr>";

    while ($admin = $admins_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $admin['id'] . "</td>";
        echo "<td><strong>" . htmlspecialchars($admin['username']) . "</strong></td>";
        echo "<td>" . ($admin['email'] ?? '<em>Not set</em>') . "</td>";
        echo "<td>" . ($admin['full_name'] ?? '<em>Not set</em>') . "</td>";
        echo "<td>" . $admin['created_at'] . "</td>";
        echo "<td>" . ($admin['last_login'] ?? '<em>Never</em>') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
else {
    echo "<p>No admin accounts found.</p>";
}

echo "<hr>";
echo "<h3 style='color: green;'>✅ Migration Complete!</h3>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>The login system will now use the <code>admins</code> table</li>";
echo "<li>Test login at: <a href='../admin/login.php'>Admin Login</a></li>";
echo "<li>Default credentials: <strong>admin / admin123</strong></li>";
echo "</ol>";

$conn->close();
?>
