<?php
session_start();
require_once 'db_connect.php';

// Verify user is logged in as registrar
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'registrar') {
    header("Location: login.php");
    exit();
}

// Initialize default filters
$filters = [
    'user_id' => '',
    'action_type' => '',
    'date_from' => '',
    'date_to' => '',
    'table_name' => '',
    'record_id' => '',
    'sort_by' => 'action_timestamp',
    'sort_order' => 'DESC'
];

// Apply user filters from GET parameters
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    foreach ($_GET as $key => $value) {
        if (array_key_exists($key, $filters)) {
            $filters[$key] = trim($value);
        }
    }
}

// Build the base query with joins for better readability
$query = "SELECT 
            al.*, 
            u.full_name, 
            u.username,
            u.role as user_role,
            CASE 
                WHEN al.table_name = 'cases' THEN c.cin
                WHEN al.table_name = 'hearings' THEN CONCAT('H-', h.hearing_id)
                ELSE CONCAT(al.table_name, '#', al.record_id)
            END as record_reference
          FROM audit_logs al 
          LEFT JOIN users u ON al.user_id = u.user_id
          LEFT JOIN cases c ON al.table_name = 'cases' AND al.record_id = c.case_id
          LEFT JOIN hearings h ON al.table_name = 'hearings' AND al.record_id = h.hearing_id
          WHERE 1=1";

$params = [];
$types = '';

// Add filters to query
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

if (!empty($filters['record_id'])) {
    $query .= " AND al.record_id = ?";
    $params[] = $filters['record_id'];
    $types .= 'i';
}

// Validate and add sorting
$valid_sort_columns = ['action_timestamp', 'user_id', 'action_type', 'table_name'];
$sort_by = in_array($filters['sort_by'], $valid_sort_columns) ? $filters['sort_by'] : 'action_timestamp';
$sort_order = strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
$query .= " ORDER BY $sort_by $sort_order LIMIT 500";

try {
    // Execute the query
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $logs = $stmt->get_result();
    
    // Check if we got results
    if ($logs->num_rows === 0) {
        $no_results_message = "No audit log entries found matching your criteria";
    }
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "Error retrieving audit logs. Please try again.";
}

// Get filter options for dropdowns
try {
    $users = $conn->query("SELECT user_id, full_name, role FROM users ORDER BY full_name");
    $action_types = $conn->query("SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type");
    $table_names = $conn->query("SELECT DISTINCT table_name FROM audit_logs WHERE table_name IN ('cases','hearings','judgments','users') ORDER BY table_name");
} catch (Exception $e) {
    error_log("Error getting filter options: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        .audit-log-table {
            font-size: 0.9rem;
        }
        .audit-log-table th {
            position: sticky;
            top: 0;
            background: white;
        }
        .action-insert {
            color: #28a745;
        }
        .action-update {
            color: #ffc107;
        }
        .action-delete {
            color: #dc3545;
        }
        .log-details pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
        }
        .table-container {
            max-height: 70vh;
            overflow-y: auto;
        }
        .badge-role {
            font-size: 0.7em;
            margin-left: 5px;
        }
        .judge-role {
            background: #6f42c1;
        }
        .lawyer-role {
            background: #20c997;
        }
        .registrar-role {
            background: #fd7e14;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
        <a class="navbar-brand" href="registrar_dashboard.php">JIS - Registrar Dashboard</a>
        <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_cases.php">Search Cases</a>
                <a class="nav-link" href="statistics.php">Statistics</a>
                <!-- <a class="nav-link" href="audit_logs.php">Log Audit</a> -->

                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col-md-12">
                <h2 class="mb-3"><i class="bi bi-journal-text"></i> System Audit Logs</h2>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?= $error_message ?></div>
                <?php endif; ?>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Logs</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="user_id" class="form-label">User</label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="">All Users</option>
                                    <?php while($user = $users->fetch_assoc()): ?>
                                        <option value="<?= $user['user_id'] ?>" 
                                            <?= $filters['user_id'] == $user['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['full_name']) ?>
                                            <small class="text-muted">(<?= $user['role'] ?>)</small>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
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
                            
                            <div class="col-md-2">
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
                            
                            <div class="col-md-2">
                                <label for="record_id" class="form-label">Record ID</label>
                                <input type="number" class="form-control" id="record_id" name="record_id" 
                                       value="<?= htmlspecialchars($filters['record_id']) ?>" placeholder="Specific ID">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="sort_by" class="form-label">Sort By</label>
                                <select class="form-select" id="sort_by" name="sort_by">
                                    <option value="action_timestamp" <?= $filters['sort_by'] === 'action_timestamp' ? 'selected' : '' ?>>Date</option>
                                    <option value="user_id" <?= $filters['sort_by'] === 'user_id' ? 'selected' : '' ?>>User</option>
                                    <option value="action_type" <?= $filters['sort_by'] === 'action_type' ? 'selected' : '' ?>>Action Type</option>
                                    <option value="table_name" <?= $filters['sort_by'] === 'table_name' ? 'selected' : '' ?>>Table</option>
                                </select>
                            </div>
                            
                            <div class="col-md-1">
                                <label for="sort_order" class="form-label">Order</label>
                                <select class="form-select" id="sort_order" name="sort_order">
                                    <option value="DESC" <?= $filters['sort_order'] === 'DESC' ? 'selected' : '' ?>>DESC</option>
                                    <option value="ASC" <?= $filters['sort_order'] === 'ASC' ? 'selected' : '' ?>>ASC</option>
                                </select>
                            </div>
                            
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
                            
                            <div class="col-md-6 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter-circle"></i> Apply Filters
                                </button>
                                <a href="audit_logs.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                                <button type="button" class="btn btn-success ms-auto" id="exportBtn">
                                    <i class="bi bi-download"></i> Export
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-check"></i> Audit Log Entries</h5>
                        <span class="badge bg-light text-dark">
                            <?= isset($logs) ? $logs->num_rows : 0 ?> records found
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (isset($no_results_message)): ?>
                            <div class="alert alert-info m-3"><?= $no_results_message ?></div>
                        <?php elseif (isset($logs) && $logs->num_rows > 0): ?>
                            <div class="table-container">
                                <table class="table table-hover audit-log-table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="160">Timestamp</th>
                                            <th width="180">User</th>
                                            <th width="100">Action</th>
                                            <th width="120">Table</th>
                                            <th>Record</th>
                                            <th width="120">Changes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($log = $logs->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <small><?= htmlspecialchars($log['action_timestamp']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                    <div><?= htmlspecialchars($log['full_name']) ?></div>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($log['username']) ?>
                                                        <span class="badge badge-role <?= $log['user_role'] ?>-role">
                                                            <?= $log['user_role'] ?>
                                                        </span>
                                                    </small>
                                                <?php else: ?>
                                                    <em>System</em>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $action_class = 'action-' . strtolower($log['action_type']);
                                                    $action_icon = [
                                                        'INSERT' => 'plus-circle',
                                                        'UPDATE' => 'pencil-square',
                                                        'DELETE' => 'trash'
                                                    ][$log['action_type']] ?? 'journal-text';
                                                ?>
                                                <span class="<?= $action_class ?>">
                                                    <i class="bi bi-<?= $action_icon ?>"></i>
                                                    <?= htmlspecialchars($log['action_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($log['table_name']) ?>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php if ($log['record_reference']): ?>
                                                        <?= htmlspecialchars($log['record_reference']) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">ID: <?= $log['record_id'] ?></small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#logDetailsModal" 
                                                        data-old='<?= htmlspecialchars($log['old_values']) ?>'
                                                        data-new='<?= htmlspecialchars($log['new_values']) ?>'
                                                        data-action='<?= htmlspecialchars($log['action_type']) ?>'
                                                        data-table='<?= htmlspecialchars($log['table_name']) ?>'
                                                        data-record='<?= htmlspecialchars($log['record_reference'] ?? $log['record_id']) ?>'
                                                        data-user='<?= htmlspecialchars($log['full_name'] ?? 'System') ?>'
                                                        data-timestamp='<?= htmlspecialchars($log['action_timestamp']) ?>'>
                                                    <i class="bi bi-eye"></i> Details
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Log Details Modal -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Audit Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <small class="text-muted">Action</small>
                            <h6 id="detail-action"></h6>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Table</small>
                            <h6 id="detail-table"></h6>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Record</small>
                            <h6 id="detail-record"></h6>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">User</small>
                            <h6 id="detail-user"></h6>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Timestamp</small>
                            <h6 id="detail-timestamp"></h6>
                        </div>
                    </div>
                    
                    <ul class="nav nav-tabs" id="detailTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="diff-tab" data-bs-toggle="tab" data-bs-target="#diff" type="button" role="tab">Changes</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="old-tab" data-bs-toggle="tab" data-bs-target="#old" type="button" role="tab">Old Values</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="new-tab" data-bs-toggle="tab" data-bs-target="#new" type="button" role="tab">New Values</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3 border border-top-0 rounded-bottom">
                        <div class="tab-pane fade show active" id="diff" role="tabpanel">
                            <div id="change-diff" class="log-details"></div>
                        </div>
                        <div class="tab-pane fade" id="old" role="tabpanel">
                            <pre id="old-values" class="log-details"></pre>
                        </div>
                        <div class="tab-pane fade" id="new" role="tabpanel">
                            <pre id="new-values" class="log-details"></pre>
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
    <script>
        // Handle log details modal
        document.getElementById('logDetailsModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const modal = this;
            
            // Set basic info
            modal.querySelector('#detail-action').textContent = button.dataset.action;
            modal.querySelector('#detail-table').textContent = button.dataset.table;
            modal.querySelector('#detail-record').textContent = button.dataset.record;
            modal.querySelector('#detail-user').textContent = button.dataset.user;
            modal.querySelector('#detail-timestamp').textContent = button.dataset.timestamp;
            
            // Parse JSON data
            const oldData = button.dataset.old ? JSON.parse(button.dataset.old) : null;
            const newData = button.dataset.new ? JSON.parse(button.dataset.new) : null;
            
            // Display raw values
            modal.querySelector('#old-values').textContent = oldData ? JSON.stringify(oldData, null, 2) : 'No old values';
            modal.querySelector('#new-values').textContent = newData ? JSON.stringify(newData, null, 2) : 'No new values';
            
            // Generate diff view
            const diffContainer = modal.querySelector('#change-diff');
            diffContainer.innerHTML = '';
            
            if (!oldData && !newData) {
                diffContainer.innerHTML = '<div class="alert alert-info">No change details available</div>';
                return;
            }
            
            if (button.dataset.action === 'INSERT') {
                diffContainer.innerHTML = '<div class="alert alert-success">New record created</div>';
                if (newData) {
                    const ul = document.createElement('ul');
                    ul.className = 'list-group';
                    for (const key in newData) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        li.innerHTML = `<strong>${key}:</strong> ${newData[key]}`;
                        ul.appendChild(li);
                    }
                    diffContainer.appendChild(ul);
                }
                return;
            }
            
            if (button.dataset.action === 'DELETE') {
                diffContainer.innerHTML = '<div class="alert alert-danger">Record deleted</div>';
                if (oldData) {
                    const ul = document.createElement('ul');
                    ul.className = 'list-group';
                    for (const key in oldData) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        li.innerHTML = `<strong>${key}:</strong> ${oldData[key]}`;
                        ul.appendChild(li);
                    }
                    diffContainer.appendChild(ul);
                }
                return;
            }
            
            // For UPDATE actions, show diff
            if (oldData && newData) {
                const table = document.createElement('table');
                table.className = 'table table-sm';
                
                const thead = document.createElement('thead');
                thead.innerHTML = `
                    <tr>
                        <th width="30%">Field</th>
                        <th width="35%">Old Value</th>
                        <th width="35%">New Value</th>
                    </tr>
                `;
                table.appendChild(thead);
                
                const tbody = document.createElement('tbody');
                
                const allKeys = new Set([...Object.keys(oldData), ...Object.keys(newData)]);
                
                allKeys.forEach(key => {
                    const oldVal = oldData[key];
                    const newVal = newData[key];
                    
                    if (oldVal !== newVal) {
                        const tr = document.createElement('tr');
                        
                        const tdField = document.createElement('td');
                        tdField.textContent = key;
                        tr.appendChild(tdField);
                        
                        const tdOld = document.createElement('td');
                        tdOld.innerHTML = oldVal !== undefined ? 
                            `<span class="text-danger">${oldVal}</span>` : 
                            '<span class="text-muted">(not set)</span>';
                        tr.appendChild(tdOld);
                        
                        const tdNew = document.createElement('td');
                        tdNew.innerHTML = newVal !== undefined ? 
                            `<span class="text-success">${newVal}</span>` : 
                            '<span class="text-muted">(removed)</span>';
                        tr.appendChild(tdNew);
                        
                        tbody.appendChild(tr);
                    }
                });
                
                table.appendChild(tbody);
                diffContainer.appendChild(table);
            }
        });
        
        // Date range constraints
        document.getElementById('date_from').addEventListener('change', function() {
            document.getElementById('date_to').min = this.value;
        });
        
        document.getElementById('date_to').addEventListener('change', function() {
            document.getElementById('date_from').max = this.value;
        });
        
        // Export functionality
        document.getElementById('exportBtn').addEventListener('click', function() {
            // Clone the filters form
            const form = document.querySelector('form').cloneNode(true);
            
            // Change method to POST and target to new window
            form.method = 'POST';
            form.target = '_blank';
            form.action = 'export_audit_logs.php';
            
            // Add to body and submit
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });
    </script>
</body>
</html>