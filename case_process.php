<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;

// Verify user has access to this case
$access_sql = "SELECT 1 FROM case_assignments WHERE case_id = ? AND user_id = ?";
$stmt = $conn->prepare($access_sql);
$stmt->bind_param("ii", $case_id, $_SESSION['user_id']);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0 && $_SESSION['role'] !== 'registrar') {
    header("Location: unauthorized.php");
    exit();
}

// Handle milestone updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_milestone'])) {
        $name = mysqli_real_escape_string($conn, $_POST['milestone_name']);
        $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
        
        $insert_sql = "INSERT INTO case_milestones (case_id, milestone_name, due_date) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iss", $case_id, $name, $due_date);
        $stmt->execute();
    } elseif (isset($_POST['update_milestone'])) {
        $milestone_id = (int)$_POST['milestone_id'];
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $completion_date = $status === 'completed' ? date('Y-m-d') : NULL;
        
        $update_sql = "UPDATE case_milestones SET status = ?, completion_date = ? WHERE milestone_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssi", $status, $completion_date, $milestone_id);
        $stmt->execute();
    }
}

// Get case details
$case_sql = "SELECT c.*, u.full_name as judge_name FROM cases c LEFT JOIN users u ON c.judge_id = u.user_id WHERE c.case_id = ?";
$stmt = $conn->prepare($case_sql);
$stmt->bind_param("i", $case_id);
$stmt->execute();
$case = $stmt->get_result()->fetch_assoc();

// Get milestones
$milestones_sql = "SELECT * FROM case_milestones WHERE case_id = ? ORDER BY due_date";
$stmt = $conn->prepare($milestones_sql);
$stmt->bind_param("i", $case_id);
$stmt->execute();
$milestones = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Progress Tracker - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Case Progress Tracker: <?= htmlspecialchars($case['cin']) ?></h2>
        <h4><?= htmlspecialchars($case['defendant_name']) ?> - <?= htmlspecialchars($case['crime_type']) ?></h4>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Case Milestones</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Milestone</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Completed</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($milestone = $milestones->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($milestone['milestone_name']) ?></td>
                                    <td><?= $milestone['due_date'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $milestone['status'] === 'completed' ? 'success' : 
                                            ($milestone['status'] === 'in_progress' ? 'warning' : 
                                            ($milestone['status'] === 'delayed' ? 'danger' : 'secondary')) 
                                        ?>">
                                            <?= ucfirst(str_replace('_', ' ', $milestone['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= $milestone['completion_date'] ?? 'N/A' ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="milestone_id" value="<?= $milestone['milestone_id'] ?>">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="pending" <?= $milestone['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="in_progress" <?= $milestone['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                <option value="completed" <?= $milestone['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="delayed" <?= $milestone['status'] === 'delayed' ? 'selected' : '' ?>>Delayed</option>
                                            </select>
                                            <input type="hidden" name="update_milestone" value="1">
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        
                        <?php if ($_SESSION['role'] === 'registrar' || $_SESSION['role'] === 'judge'): ?>
                        <form method="POST" class="mt-4">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <input type="text" name="milestone_name" class="form-control" placeholder="Milestone Name" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="date" name="due_date" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" name="add_milestone" class="btn btn-primary">Add Milestone</button>
                                </div>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Case Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php 
                            $timeline_sql = "SELECT 'hearing' as type, hearing_date as date, hearing_type as title, NULL as description 
                                            FROM hearings WHERE case_id = ?
                                            UNION
                                            SELECT 'milestone' as type, completion_date as date, milestone_name as title, status as description 
                                            FROM case_milestones WHERE case_id = ? AND completion_date IS NOT NULL
                                            UNION
                                            SELECT 'judgment' as type, judgment_date as date, 'Judgment' as title, status as description
                                            FROM judgments WHERE case_id = ?
                                            ORDER BY date DESC";
                            $stmt = $conn->prepare($timeline_sql);
                            $stmt->bind_param("iii", $case_id, $case_id, $case_id);
                            $stmt->execute();
                            $timeline = $stmt->get_result();
                            
                            while($event = $timeline->fetch_assoc()): 
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-item-marker">
                                    <div class="timeline-item-marker-indicator bg-<?= 
                                        $event['type'] === 'hearing' ? 'info' : 
                                        ($event['type'] === 'judgment' ? 'primary' : 'success')) 
                                    ?>"></div>
                                </div>
                                <div class="timeline-item-content">
                                    <h6><?= htmlspecialchars($event['title']) ?></h6>
                                    <p class="text-muted"><?= $event['date'] ?></p>
                                    <?php if ($event['description']): ?>
                                    <p><?= htmlspecialchars($event['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <a href="case_details.php?id=<?= $case_id ?>" class="btn btn-secondary mt-4">Back to Case Details</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date picker
        flatpickr("input[type='date']", {
            dateFormat: "Y-m-d",
            minDate: "today"
        });
    </script>
</body>
</html>