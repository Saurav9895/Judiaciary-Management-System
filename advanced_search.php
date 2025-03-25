<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$results = [];
$keyword = '';
$filters = [
    'case_status' => '',
    'crime_type' => '',
    'date_from' => '',
    'date_to' => '',
    'judge_id' => '',
    'severity' => '',
    'complexity' => '',
    'sort_by' => 'start_date',
    'sort_order' => 'DESC'
];

// Get filter options
$judges_sql = "SELECT user_id, full_name FROM users WHERE role = 'judge' ORDER BY full_name";
$judges = $conn->query($judges_sql);

$crime_types_sql = "SELECT DISTINCT crime_type FROM cases ORDER BY crime_type";
$crime_types = $conn->query($crime_types_sql);

// Process search request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $keyword = trim($_GET['keyword'] ?? '');
    
    // Get filters
    $filters = array_merge($filters, array_intersect_key($_GET, $filters));
    
    // Build query
    $query = "SELECT c.*, u.full_name as judge_name FROM cases c LEFT JOIN users u ON c.judge_id = u.user_id WHERE 1=1";
    $params = [];
    $types = '';
    
    // Keyword search
    if (!empty($keyword)) {
        $query .= " AND MATCH(c.defendant_name, c.crime_type, c.description) AGAINST(? IN BOOLEAN MODE)";
        $params[] = $keyword;
        $types .= 's';
    }
    
    // Apply filters
    if (!empty($filters['case_status'])) {
        $query .= " AND c.status = ?";
        $params[] = $filters['case_status'];
        $types .= 's';
    }
    
    if (!empty($filters['crime_type'])) {
        $query .= " AND c.crime_type = ?";
        $params[] = $filters['crime_type'];
        $types .= 's';
    }
    
    if (!empty($filters['date_from'])) {
        $query .= " AND c.start_date >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND c.start_date <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    if (!empty($filters['judge_id'])) {
        $query .= " AND c.judge_id = ?";
        $params[] = $filters['judge_id'];
        $types .= 'i';
    }
    
    if (!empty($filters['severity'])) {
        $query .= " AND c.severity_level = ?";
        $params[] = $filters['severity'];
        $types .= 's';
    }
    
    if (!empty($filters['complexity'])) {
        $query .= " AND c.complexity_level = ?";
        $params[] = $filters['complexity'];
        $types .= 's';
    }
    
    // Add sorting
    $valid_sort_columns = ['start_date', 'defendant_name', 'crime_type', 'status'];
    $sort_by = in_array($filters['sort_by'], $valid_sort_columns) ? $filters['sort_by'] : 'start_date';
    $sort_order = strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
    $query .= " ORDER BY $sort_by $sort_order";
    
    // Execute query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Case Search - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Advanced Case Search</h2>
        
        <form method="GET" class="mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Search Criteria</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="keyword" class="form-label">Keyword Search</label>
                            <input type="text" class="form-control" id="keyword" name="keyword" 
                                   value="<?= htmlspecialchars($keyword) ?>" placeholder="Search cases...">
                            <small class="text-muted">Search defendant names, crime types, or case descriptions</small>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="case_status" class="form-label">Case Status</label>
                            <select class="form-select" id="case_status" name="case_status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $filters['case_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="closed" <?= $filters['case_status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="crime_type" class="form-label">Crime Type</label>
                            <select class="form-select" id="crime_type" name="crime_type">
                                <option value="">All Types</option>
                                <?php while($type = $crime_types->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($type['crime_type']) ?>" 
                                        <?= $filters['crime_type'] === $type['crime_type'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['crime_type']) ?>
                                    </option>
                                <?php endwhile; ?>
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
                            <label for="judge_id" class="form-label">Judge</label>
                            <select class="form-select" id="judge_id" name="judge_id">
                                <option value="">All Judges</option>
                                <?php while($judge = $judges->fetch_assoc()): ?>
                                    <option value="<?= $judge['user_id'] ?>" 
                                        <?= $filters['judge_id'] == $judge['user_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($judge['full_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="severity" class="form-label">Severity</label>
                            <select class="form-select" id="severity" name="severity">
                                <option value="">All Levels</option>
                                <option value="low" <?= $filters['severity'] === 'low' ? 'selected' : '' ?>>Low</option>
                                <option value="medium" <?= $filters['severity'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                                <option value="high" <?= $filters['severity'] === 'high' ? 'selected' : '' ?>>High</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label for="complexity" class="form-label">Complexity</label>
                            <select class="form-select" id="complexity" name="complexity">
                                <option value="">All Levels</option>
                                <option value="simple" <?= $filters['complexity'] === 'simple' ? 'selected' : '' ?>>Simple</option>
                                <option value="moderate" <?= $filters['complexity'] === 'moderate' ? 'selected' : '' ?>>Moderate</option>
                                <option value="complex" <?= $filters['complexity'] === 'complex' ? 'selected' : '' ?>>Complex</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="sort_by" class="form-label">Sort By</label>
                            <select class="form-select" id="sort_by" name="sort_by">
                                <option value="start_date" <?= $filters['sort_by'] === 'start_date' ? 'selected' : '' ?>>Start Date</option>
                                <option value="defendant_name" <?= $filters['sort_by'] === 'defendant_name' ? 'selected' : '' ?>>Defendant Name</option>
                                <option value="crime_type" <?= $filters['sort_by'] === 'crime_type' ? 'selected' : '' ?>>Crime Type</option>
                                <option value="status" <?= $filters['sort_by'] === 'status' ? 'selected' : '' ?>>Status</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="sort_order" class="form-label">Sort Order</label>
                            <select class="form-select" id="sort_order" name="sort_order">
                                <option value="DESC" <?= $filters['sort_order'] === 'DESC' ? 'selected' : '' ?>>Descending</option>
                                <option value="ASC" <?= $filters['sort_order'] === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <?php if (!empty($results)): ?>
            <div class="card">
                <div class="card-header">
                    <h5>Search Results (<?= count($results) ?> cases found)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>CIN</th>
                                    <th>Defendant</th>
                                    <th>Crime Type</th>
                                    <th>Judge</th>
                                    <th>Start Date</th>
                                    <th>Status</th>
                                    <th>Severity</th>
                                    <th>Complexity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $case): ?>
                                <tr>
                                    <td><?= htmlspecialchars($case['cin']) ?></td>
                                    <td><?= htmlspecialchars($case['defendant_name']) ?></td>
                                    <td><?= htmlspecialchars($case['crime_type']) ?></td>
                                    <td><?= htmlspecialchars($case['judge_name'] ?? 'N/A') ?></td>
                                    <td><?= $case['start_date'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $case['status'] === 'pending' ? 'warning' : 'success' ?>">
                                            <?= ucfirst($case['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $case['severity_level'] === 'high' ? 'danger' : 
                                            ($case['severity_level'] === 'medium' ? 'warning' : 'success') 
                                        ?>">
                                            <?= ucfirst($case['severity_level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $case['complexity_level'] === 'complex' ? 'dark' : 
                                            ($case['complexity_level'] === 'moderate' ? 'info' : 'light text-dark') 
                                        ?>">
                                            <?= ucfirst($case['complexity_level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="case_details.php?id=<?= $case['case_id'] ?>" class="btn btn-sm btn-info">View</a>
                                        <?php if ($_SESSION['role'] === 'registrar'): ?>
                                            <a href="edit_case.php?id=<?= $case['case_id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)): ?>
            <div class="alert alert-info">No cases found matching your criteria</div>
        <?php endif; ?>
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
    </script>
</body>
</html>