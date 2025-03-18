<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle evidence upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id = mysqli_real_escape_string($conn, $_POST['case_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $evidence_type = mysqli_real_escape_string($conn, $_POST['evidence_type']);

    // Handle file upload
    if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/evidence/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['evidence_file']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('evidence_') . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['evidence_file']['tmp_name'], $file_path)) {
            $sql = "INSERT INTO case_evidence (case_id, evidence_type, title, description, file_path, submitted_by) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issssi', $case_id, $evidence_type, $title, $description, $file_path, $user_id);

            if ($stmt->execute()) {
                $message = 'Evidence uploaded successfully!';
            } else {
                $message = 'Error adding evidence record: ' . $conn->error;
            }
        } else {
            $message = 'Error uploading file';
        }
    }
}

// Fetch cases accessible to the user
$cases_sql = "SELECT c.case_id, c.cin, c.title 
             FROM cases c 
             JOIN case_assignments ca ON c.case_id = ca.case_id 
             WHERE ca.user_id = ? AND ca.status = 'active'";
$cases_stmt = $conn->prepare($cases_sql);
$cases_stmt->bind_param('i', $user_id);
$cases_stmt->execute();
$cases_result = $cases_stmt->get_result();

// Fetch evidence records
$evidence_sql = "SELECT ce.*, c.cin, c.title as case_title, u.username as submitter 
                FROM case_evidence ce 
                JOIN cases c ON ce.case_id = c.case_id 
                JOIN users u ON ce.submitted_by = u.user_id 
                WHERE ce.case_id IN (
                    SELECT case_id FROM case_assignments WHERE user_id = ? AND status = 'active'
                ) 
                ORDER BY ce.submission_date DESC";
$evidence_stmt = $conn->prepare($evidence_sql);
$evidence_stmt->bind_param('i', $user_id);
$evidence_stmt->execute();
$evidence_result = $evidence_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Evidence Management - JIS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Case Evidence Management</h2>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <h3>Upload Evidence</h3>
                <form method="POST" enctype="multipart/form-data" class="mt-4">
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
                        <label for="evidence_type">Evidence Type</label>
                        <select class="form-control" id="evidence_type" name="evidence_type" required>
                            <option value="document">Document</option>
                            <option value="photo">Photo</option>
                            <option value="video">Video</option>
                            <option value="audio">Audio</option>
                            <option value="physical">Physical Evidence Record</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="evidence_file">Evidence File</label>
                        <input type="file" class="form-control-file" id="evidence_file" name="evidence_file" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Upload Evidence</button>
                </form>
            </div>

            <div class="col-md-6">
                <h3>Evidence Records</h3>
                <div class="table-responsive mt-4">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Case</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($evidence = $evidence_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($evidence['submission_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($evidence['cin'] . ' - ' . $evidence['case_title']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($evidence['evidence_type'])); ?></td>
                                    <td><?php echo htmlspecialchars($evidence['title']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($evidence['status'])); ?></td>
                                    <td>
                                        <?php if ($evidence['file_path']): ?>
                                            <a href="<?php echo htmlspecialchars($evidence['file_path']); ?>" class="btn btn-sm btn-info" target="_blank">View</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <a href="dashboard.php" class="btn btn-secondary mt-4">Back to Dashboard</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>