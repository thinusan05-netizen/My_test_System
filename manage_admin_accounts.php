<?php
/**
 * Admin Account Management Utility
 * Purpose: Add, update, and manage admin accounts
 */

$host = "localhost";
$user = "root";
$pass = "";
$db_name = "accident_prediction_db";

$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$error = "";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Add new admin
    if (isset($_POST['add_admin'])) {
        $username = $conn->real_escape_string($_POST['new_username']);
        $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $email = $conn->real_escape_string($_POST['new_email']);
        $full_name = $conn->real_escape_string($_POST['new_fullname']);

        $sql = "INSERT INTO admins (username, password, email, full_name) 
                VALUES ('$username', '$password', '$email', '$full_name')";

        if ($conn->query($sql) === TRUE) {
            $message = "✅ Admin account created successfully!";
        }
        else {
            $error = "❌ Error: " . $conn->error;
        }
    }

    // Reset password
    if (isset($_POST['reset_password'])) {
        $admin_id = intval($_POST['admin_id']);
        $new_password = password_hash($_POST['reset_pass'], PASSWORD_DEFAULT);

        $sql = "UPDATE admins SET password = '$new_password' WHERE id = $admin_id";

        if ($conn->query($sql) === TRUE) {
            $message = "✅ Password reset successfully!";
        }
        else {
            $error = "❌ Error: " . $conn->error;
        }
    }

    // Delete admin
    if (isset($_POST['delete_admin'])) {
        $admin_id = intval($_POST['delete_id']);

        // Check if this is the last admin
        $count_check = $conn->query("SELECT COUNT(*) as count FROM admins");
        $count = $count_check->fetch_assoc()['count'];

        if ($count <= 1) {
            $error = "❌ Cannot delete the last admin account!";
        }
        else {
            $sql = "DELETE FROM admins WHERE id = $admin_id";
            if ($conn->query($sql) === TRUE) {
                $message = "✅ Admin account deleted!";
            }
            else {
                $error = "❌ Error: " . $conn->error;
            }
        }
    }
}

// Fetch all admins
$admins = $conn->query("SELECT * FROM admins ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admin Accounts</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 20px; }
        h2 { color: #555; margin: 30px 0 15px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        tr:hover { background: #f5f5f5; }
        input, button { padding: 10px; margin: 5px 0; border: 1px solid #ccc; border-radius: 4px; }
        input[type="text"], input[type="email"], input[type="password"] { width: 100%; max-width: 300px; }
        button { background: #007bff; color: white; border: none; cursor: pointer; padding: 10px 20px; }
        button:hover { background: #0056b3; }
        .delete-btn { background: #dc3545; }
        .delete-btn:hover { background: #c82333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .back-link { display: inline-block; margin-top: 20px; color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Admin Account Management</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php
endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php
endif; ?>
        
        <!-- Add New Admin -->
        <h2>➕ Add New Admin</h2>
        <form method="POST">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="new_username" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="new_password" required>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="new_email">
            </div>
            <div class="form-group">
                <label>Full Name:</label>
                <input type="text" name="new_fullname">
            </div>
            <button type="submit" name="add_admin">Create Admin Account</button>
        </form>
        
        <!-- Existing Admins -->
        <h2>👥 Existing Admin Accounts</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Full Name</th>
                <th>Last Login</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
            <?php while ($admin = $admins->fetch_assoc()): ?>
            <tr>
                <td><?php echo $admin['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($admin['username']); ?></strong></td>
                <td><?php echo $admin['email'] ?? '<em>Not set</em>'; ?></td>
                <td><?php echo $admin['full_name'] ?? '<em>Not set</em>'; ?></td>
                <td><?php echo $admin['last_login'] ?? '<em>Never</em>'; ?></td>
                <td><?php echo date('Y-m-d', strtotime($admin['created_at'])); ?></td>
                <td>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                        <input type="password" name="reset_pass" placeholder="New password" required style="width: 150px;">
                        <button type="submit" name="reset_password">Reset Password</button>
                    </form>
                    
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this admin?');">
                        <input type="hidden" name="delete_id" value="<?php echo $admin['id']; ?>">
                        <button type="submit" name="delete_admin" class="delete-btn">Delete</button>
                    </form>
                </td>
            </tr>
            <?php
endwhile; ?>
        </table>
        
        <a href="../admin/login.php" class="back-link">← Back to Login</a>
    </div>
</body>
</html>
<?php $conn->close(); ?>
