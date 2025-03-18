<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a registrar
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'registrar') {
    header('Location: login.php');
    exit();
}

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id = mysqli_real_escape_string($conn, $_POST['case_id']);
    $lawyer_id = mysqli_real_escape_string($conn, $_POST['lawyer_id']);

    // Insert assignment into the database
    $sql = "INSERT INTO case_assignments (case_id, user_id, role, assigned_date) 
            VALUES (?, ?, 'lawyer', CURDATE())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $case_id, $lawyer_id);

    if ($stmt->execute()) {
        $message = 'Case assigned successfully!';
    } else {
        $message = 'Error assigning case: ' . $conn->error;
    }
}

// Fetch all pending cases
$cases_sql = "SELECT * FROM cases WHERE status = 'pending'";
$cases_result = $conn->query($cases_sql);

// Fetch all lawyers
$lawyers_sql = "SELECT * FROM users WHERE role = 'lawyer'";
$lawyers_result = $conn->query($lawyers_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Case - JIS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Assign Case to Lawyer</h2>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" class="mt-4">
            <div class="form-group">
                <label for="case_id">Select Case</label>
                <select class="form-control" id="case_id" name="case_id" required>
                    <option value="">Select a case...</option>
                    <?php while ($case = $cases_result->fetch_assoc()): ?>
                        <option value="<?php echo $case['case_id']; ?>">
                            <?php echo htmlspecialchars($case['cin'] . ' - ' . $case['title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="lawyer_id">Select Lawyer</label>
                <select class="form-control" id="lawyer_id" name="lawyer_id" required>
                    <option value="">Select a lawyer...</option>
                    <?php while ($lawyer = $lawyers_result->fetch_assoc()): ?>
                        <option value="<?php echo $lawyer['user_id']; ?>">
                            <?php echo htmlspecialchars($lawyer['full_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Assign Case</button>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>