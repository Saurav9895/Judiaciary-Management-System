<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'registrar') {
    header('Location: login.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cin = mysqli_real_escape_string($conn, $_POST['cin']);
    $defendant_name = mysqli_real_escape_string($conn, $_POST['defendant_name']);
    $defendant_address = mysqli_real_escape_string($conn, $_POST['defendant_address']);
    $crime_type = mysqli_real_escape_string($conn, $_POST['crime_type']);
    $crime_date = mysqli_real_escape_string($conn, $_POST['crime_date']);
    $crime_location = mysqli_real_escape_string($conn, $_POST['crime_location']);
    $arresting_officer = mysqli_real_escape_string($conn, $_POST['arresting_officer']);
    $arrest_date = mysqli_real_escape_string($conn, $_POST['arrest_date']);
    $prosecutor_name = mysqli_real_escape_string($conn, $_POST['prosecutor_name']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $expected_completion_date = mysqli_real_escape_string($conn, $_POST['expected_completion_date']);

    $sql = "INSERT INTO cases (cin, defendant_name, defendant_address, crime_type, crime_date, crime_location, arresting_officer, arrest_date, prosecutor_name, start_date, expected_completion_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssss", $cin, $defendant_name, $defendant_address, $crime_type, $crime_date, $crime_location, $arresting_officer, $arrest_date, $prosecutor_name, $start_date, $expected_completion_date);

    if ($stmt->execute()) {
        $message = 'Case registered successfully!';
    } else {
        $message = 'Error registering case: ' . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Case - JIS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">JIS - Registrar Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_cases.php">Search Cases</a>
                <!-- <a class="nav-link" href="statistics.php">Statistics</a> -->
                <!-- <a class="nav-link" href="audit_logs.php">Log Audit</a> -->

                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-5">
        <h2>Register New Case</h2>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="POST" class="mt-4">
            <div class="form-group">
                <label for="cin">Case Identification Number (CIN)</label>
                <input type="text" class="form-control" id="cin" name="cin" required>
            </div>
            <div class="form-group">
                <label for="defendant_name">Defendant Name</label>
                <input type="text" class="form-control" id="defendant_name" name="defendant_name" required>
            </div>
            <div class="form-group">
                <label for="defendant_address">Defendant Address</label>
                <textarea class="form-control" id="defendant_address" name="defendant_address" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label for="crime_type">Crime Type</label>
                <input type="text" class="form-control" id="crime_type" name="crime_type" required>
            </div>
            <div class="form-group">
                <label for="crime_date">Crime Date</label>
                <input type="date" class="form-control" id="crime_date" name="crime_date" required>
            </div>
            <div class="form-group">
                <label for="crime_location">Crime Location</label>
                <textarea class="form-control" id="crime_location" name="crime_location" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label for="arresting_officer">Arresting Officer</label>
                <input type="text" class="form-control" id="arresting_officer" name="arresting_officer" required>
            </div>
            <div class="form-group">
                <label for="arrest_date">Arrest Date</label>
                <input type="date" class="form-control" id="arrest_date" name="arrest_date" required>
            </div>
            <div class="form-group">
                <label for="prosecutor_name">Prosecutor Name</label>
                <input type="text" class="form-control" id="prosecutor_name" name="prosecutor_name" required>
            </div>
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" required>
            </div>
            <div class="form-group">
                <label for="expected_completion_date">Expected Completion Date</label>
                <input type="date" class="form-control" id="expected_completion_date" name="expected_completion_date" required>
            </div>
            <button type="submit" class="btn btn-primary">Register Case</button>
            <a href="registrar_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </form>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>