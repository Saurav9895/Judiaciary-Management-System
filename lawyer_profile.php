<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_password'])) {
        // Handle password change
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords
        if ($new_password !== $confirm_password) {
            $message = 'New password and confirmation do not match.';
        } else {
            // Verify current password
            $sql = "SELECT password FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            
            if (password_verify($current_password, $user_data['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param('si', $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $message = 'Password changed successfully!';
                } else {
                    $message = 'Error changing password: ' . $conn->error;
                }
            } else {
                $message = 'Current password is incorrect.';
            }
        }
    } else {
        // Handle profile update
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $specialization = mysqli_real_escape_string($conn, $_POST['specialization']);

        // Check if profile exists
        $check_sql = "SELECT profile_id FROM user_profiles WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('i', $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing profile
            $sql = "UPDATE user_profiles SET full_name = ?, email = ?, phone = ?, address = ?, specialization = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssssi', $full_name, $email, $phone, $address, $specialization, $user_id);
        } else {
            // Create new profile
            $sql = "INSERT INTO user_profiles (user_id, full_name, email, phone, address, specialization) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('isssss', $user_id, $full_name, $email, $phone, $address, $specialization);
        }

        if ($stmt->execute()) {
            $message = 'Profile updated successfully!';
        } else {
            $message = 'Error updating profile: ' . $conn->error;
        }
    }
}

// Fetch user details
$sql = "SELECT u.username, u.role, u.full_name, u.email, up.phone, up.address, up.specialization 
        FROM users u 
        LEFT JOIN user_profiles up ON u.user_id = up.user_id 
        WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lawyer Profile - JIS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .password-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="lawyer_dashboard.php">JIS - Lawyer Dashboard</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="search_cases.php">Search Cases</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>
<div class="container mt-5">
    <h2>User Profile</h2>
    <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Profile Details</h5>
            <p class="card-text"><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p class="card-text"><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
            <p class="card-text"><strong>Full Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
            <p class="card-text"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p class="card-text"><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
            <p class="card-text"><strong>Address:</strong> <?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></p>
            <p class="card-text"><strong>Specialization:</strong> <?php echo htmlspecialchars($user['specialization'] ?? 'Not provided'); ?></p>
        </div>
    </div>

    <h3>Update Profile</h3>
    <form method="POST" class="mt-4">
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="specialization">Specialization</label>
            <input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo htmlspecialchars($user['specialization'] ?? ''); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Update Profile</button>
        <a href="lawyer_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </form>

    <div class="password-section">
        <h3>Change Password</h3>
        <form method="POST" class="mt-4">
            <input type="hidden" name="change_password" value="1">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
                <small class="form-text text-muted">Password must be at least 8 characters long.</small>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-warning">Change Password</button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>