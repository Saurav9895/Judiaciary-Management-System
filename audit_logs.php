<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'registrar') {
    header("Location: login.php");
    exit();
}

// Handle filters
$filters = [
    'user_id' => '',
    'action_type' => '',
    'date_from' => '',
    'date_to' => '',
    'table_name' => '',
    'sort_by' => 'action_timestamp',
    'sort_order' => 'DESC'
];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $filters = array_merge($filters, array_intersect_key($_GET, $filters));
}

// Build query
$query = "SELECT al.*, u.full_name, u.username 
          FROM audit_logs al 
          LEFT JOIN users u ON al.user_id = u.user_id 
          WHERE 1=1";
$params = [];
$types = '';

if (!empty($filters['user_id'])) {
    $query .= " AND al.user_id = ?";
    $params[] = $filters['user_id'];
    $types .= 'i';
}

if (!empty($filters['action_type'])) {
    $query .= " AND al.action_type = ?";
    $params[] = $filters['action_type'];
    $types .= 's';
}

if (!empty($filters['date_from'])) {
    $query .= " AND al.action_timestamp >= ?";
    $params[] = $filters['date_from'] . ' 00:00:00';
    $types .= 's';
}

if (!empty($filters['date_to'])) {
    $query .= " AND al.action_timestamp <= ?";
    $params[] = $filters['date_to'] . ' 23:59:59';
    $types .= 's';
}

if (!empty($filters['table_name'])) {
    $query .= " AND al.table_name = ?";
    $params[] = $filters['table_name'];
    $types .= 's';
}

// Add sorting
$valid_sort_columns = ['action_timestamp', 'user_id', 'action_type', 'table_name'];
$sort_by = in_array($filters['sort_by'], $valid_sort_columns) ? $filters['sort_by'] : 'action_timestamp';
$sort_order = strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
$query .= " ORDER BY $sort_by $sort_order LIMIT 500";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// Get filter options
$users_sql = "SELECT user_id, full_name FROM users ORDER BY full_name";
$users = $conn->query($users_sql);

$action_types_sql = "SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type";
$action_types = $conn->query($action_types_sql);

$table_names_sql = "SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name";
$table_names = $conn->query($table_names_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">JIS - Registrar Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_cases.php">Search Cases</a>
                <a class="nav-link" href="statistics.php">Statistics</a>
                <!-- <a class="nav-link" href="audit_logs.php">Log Audit</a> -->

                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h2>System Audit Logs</h2>
        
        <form method="GET" class="mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Filter Logs</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="user_id" class="form-label">User</label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="">All Users</option>
                                <?php while($user = $users->fetch_assoc()): ?>
                                    <option value="<?= $user['user_id'] ?>" 
                                        <?= $filters['user_id'] == $user['user_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['full_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="action_type" class="form-label">Action Type</label>
                            <select class="form-select" id="action_type" name="action_type">
                                <option value="">All Types</option>
                                <?php while($type = $action_types->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($type['action_type']) ?>" 
                                        <?= $filters['action_type'] === $type['action_type'] ? 'selected' : '' ?>>
                                        <?= ucfirst(htmlspecialchars($type['action_type'])) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="table_name" class="form-label">Table</label>
                            <select class="form-select" id="table_name" name="table_name">
                                <option value="">All Tables</option>
                                <?php while($table = $table_names->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($table['table_name']) ?>" 
                                        <?= $filters['table_name'] === $table['table_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($table['table_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="sort_by" class="form-label">Sort By</label>
                            <select class="form-select" id="sort_by" name="sort_by">
                                <option value="action_timestamp" <?= $filters['sort_by'] === 'action_timestamp' ? 'selected' : '' ?>>Date</option>
                                <option value="user_id" <?= $filters['sort_by'] === 'user_id' ? 'selected' : '' ?>>User</option>
                                <option value="action_type" <?= $filters['sort_by'] === 'action_type' ? 'selected' : '' ?>>Action Type</option>
                                <option value="table_name" <?= $filters['sort_by'] === 'table_name' ? 'selected' : '' ?>>Table</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="sort_order" class="form-label">Sort Order</label>
                            <select class="form-select" id="sort_order" name="sort_order">
                                <option value="DESC" <?= $filters['sort_order'] === 'DESC' ? 'selected' : '' ?>>Newest First</option>
                                <option value="ASC" <?= $filters['sort_order'] === 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="card">
            <div class="card-header">
                <h5>Audit Log Entries</h5>
            </div>
            <div class="card-body">
                <?php if ($logs->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>Record ID</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $log['action_timestamp'] ?></td>
                                    <td>
                                        <?= $log['user_id'] ? htmlspecialchars($log['full_name'] . ' (' . $log['username'] . ')') : 'System' ?>
                                    </td>
                                    <td><?= ucfirst(htmlspecialchars($log['action_type'])) ?></td>
                                    <td><?= htmlspecialchars($log['table_name']) ?></td>
                                    <td><?= $log['record_id'] ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                data-bs-target="#logDetailsModal" 
                                                data-old="<?= htmlspecialchars($log['old_values']) ?>"
                                                data-new="<?= htmlspecialchars($log['new_values']) ?>">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No audit log entries found matching your criteria</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Log Details Modal -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1" aria-labelledby="logDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logDetailsModalLabel">Audit Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Old Values</h6>
                            <pre id="oldValues" class="p-2 bg-light rounded" style="min-height: 200px; max-height: 400px; overflow: auto;"></pre>
                        </div>
                        <div class="col-md-6">
                            <h6>New Values</h6>
                            <pre id="newValues" class="p-2 bg-light rounded" style="min-height: 200px; max-height: 400px; overflow: auto;"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date pickers
        flatpickr("#date_from", {
            dateFormat: "Y-m-d",
            maxDate: document.getElementById('date_to').value || new Date()
        });
        
        flatpickr("#date_to", {
            dateFormat: "Y-m-d",
            minDate: document.getElementById('date_from').value,
            defaultDate: new Date()
        });
        
        // Update date constraints when changed
        document.getElementById('date_from').addEventListener('change', function(e) {
            document.getElementById('date_to')._flatpickr.set('minDate', e.target.value);
        });
        
        document.getElementById('date_to').addEventListener('change', function(e) {
            document.getElementById('date_from')._flatpickr.set('maxDate', e.target.value);
        });
        
        // Handle log details modal
        const logDetailsModal = document.getElementById('logDetailsModal');
        if (logDetailsModal) {
            logDetailsModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const oldValues = button.getAttribute('data-old');
                const newValues = button.getAttribute('data-new');
                
                document.getElementById('oldValues').textContent = oldValues || 'No old values recorded';
                document.getElementById('newValues').textContent = newValues || 'No new values recorded';
            });
        }
    </script>
</body>
</html>