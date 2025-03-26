<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'judge')) {
    header("Location: login.php");
    exit();
}

// Get filter parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$judge_id = isset($_GET['judge_id']) ? (int)$_GET['judge_id'] : null;
$crime_type = isset($_GET['crime_type']) ? $_GET['crime_type'] : null;

// Build base query for case statistics
$query = "SELECT 
            COUNT(c.case_id) as total_cases,
            AVG(cs.days_to_resolution) as avg_resolution_days,
            SUM(CASE WHEN cs.outcome = 'conviction' THEN 1 ELSE 0 END) as convictions,
            SUM(CASE WHEN cs.outcome = 'acquittal' THEN 1 ELSE 0 END) as acquittals,
            SUM(CASE WHEN cs.outcome = 'dismissal' THEN 1 ELSE 0 END) as dismissals,
            SUM(CASE WHEN cs.outcome = 'settlement' THEN 1 ELSE 0 END) as settlements
          FROM cases c
          LEFT JOIN case_statistics cs ON c.case_id = cs.case_id
          WHERE YEAR(c.start_date) = ?";
$params = [$year];
$types = 'i';

// Add judge filter if specified
if ($judge_id) {
    $query .= " AND c.judge_id = ?";
    $params[] = $judge_id;
    $types .= 'i';
}

// Add crime type filter if specified
if ($crime_type) {
    $query .= " AND c.crime_type = ?";
    $params[] = $crime_type;
    $types .= 's';
}

// Execute main statistics query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Calculate conviction rate
$total_outcomes = $stats['convictions'] + $stats['acquittals'] + $stats['dismissals'] + $stats['settlements'];
$conviction_rate = $total_outcomes > 0 ? ($stats['convictions'] / $total_outcomes) * 100 : 0;

// Get judge performance metrics
$judges_query = "SELECT 
                    u.user_id, u.full_name,
                    COUNT(jm.cases_handled) as total_cases,
                    AVG(jm.avg_resolution_days) as avg_days,
                    AVG(jm.conviction_rate) as avg_conviction_rate
                 FROM users u
                 LEFT JOIN judge_metrics jm ON u.user_id = jm.judge_id
                 WHERE u.role = 'judge'";
$judges_params = [];
$judges_types = '';

if ($year) {
    $judges_query .= " AND jm.year = ?";
    $judges_params[] = $year;
    $judges_types .= 'i';
}

$judges_query .= " GROUP BY u.user_id, u.full_name ORDER BY avg_conviction_rate DESC";

$judges_stmt = $conn->prepare($judges_query);
if (!empty($judges_params)) {
    $judges_stmt->bind_param($judges_types, ...$judges_params);
}
$judges_stmt->execute();
$judges_metrics = $judges_stmt->get_result();

// Get crime type distribution
$crime_dist_query = "SELECT 
                        crime_type, 
                        COUNT(*) as case_count,
                        AVG(cs.days_to_resolution) as avg_days,
                        SUM(CASE WHEN cs.outcome = 'conviction' THEN 1 ELSE 0 END) as convictions
                     FROM cases c
                     LEFT JOIN case_statistics cs ON c.case_id = cs.case_id
                     WHERE YEAR(c.start_date) = ?";
$crime_dist_params = [$year];
$crime_dist_types = 'i';

if ($judge_id) {
    $crime_dist_query .= " AND c.judge_id = ?";
    $crime_dist_params[] = $judge_id;
    $crime_dist_types .= 'i';
}

$crime_dist_query .= " GROUP BY crime_type ORDER BY case_count DESC";

$crime_dist_stmt = $conn->prepare($crime_dist_query);
$crime_dist_stmt->bind_param($crime_dist_types, ...$crime_dist_params);
$crime_dist_stmt->execute();
$crime_dist = $crime_dist_stmt->get_result();

// Get resolution time trends
$resolution_query = "SELECT 
                        YEAR(c.start_date) as year,
                        AVG(cs.days_to_resolution) as avg_days,
                        COUNT(*) as case_count
                     FROM cases c
                     LEFT JOIN case_statistics cs ON c.case_id = cs.case_id
                     GROUP BY YEAR(c.start_date)
                     ORDER BY year DESC
                     LIMIT 5";
$resolution_trends = $conn->query($resolution_query);

// Get filter options
$years_query = "SELECT DISTINCT YEAR(start_date) as year FROM cases ORDER BY year DESC";
$years = $conn->query($years_query);

$judges_list_query = "SELECT user_id, full_name FROM users WHERE role = 'judge' ORDER BY full_name";
$judges_list = $conn->query($judges_list_query);

$crime_types_query = "SELECT DISTINCT crime_type FROM cases ORDER BY crime_type";
$crime_types = $conn->query($crime_types_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Judicial Statistics - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="registrar_dashboard.php">JIS - Registrar Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_cases.php">Search Cases</a>
                <!-- <a class="nav-link" href="statistics.php">Statistics</a> -->
                <a class="nav-link" href="audit_logs.php">Log Audit</a>

                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h2>Judicial Performance Statistics</h2>
        
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <?php while($y = $years->fetch_assoc()): ?>
                            <option value="<?= $y['year'] ?>" <?= $year == $y['year'] ? 'selected' : '' ?>>
                                <?= $y['year'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="judge_id" class="form-label">Judge (optional)</label>
                    <select class="form-select" id="judge_id" name="judge_id">
                        <option value="">All Judges</option>
                        <?php while($j = $judges_list->fetch_assoc()): ?>
                            <option value="<?= $j['user_id'] ?>" <?= $judge_id == $j['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($j['full_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="crime_type" class="form-label">Crime Type (optional)</label>
                    <select class="form-select" id="crime_type" name="crime_type">
                        <option value="">All Types</option>
                        <?php while($ct = $crime_types->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($ct['crime_type']) ?>" 
                                <?= $crime_type === $ct['crime_type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ct['crime_type']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </div>
        </form>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Case Statistics for <?= $year ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h6>Total Cases</h6>
                                        <h3><?= $stats['total_cases'] ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h6>Avg. Resolution Time</h6>
                                        <h3><?= round($stats['avg_resolution_days'] ?? 0) ?> days</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card bg-light mb-3">
                                    <div class="card-body text-center">
                                        <h6>Conviction Rate</h6>
                                        <h3><?= round($conviction_rate, 1) ?>%</h3>
                                        <small>(<?= $stats['convictions'] ?> convictions out of <?= $total_outcomes ?> resolved cases)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <h6>Case Outcomes</h6>
                            <canvas id="outcomesChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Judge Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Judge</th>
                                        <th>Cases</th>
                                        <th>Avg. Days</th>
                                        <th>Conviction Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($judge = $judges_metrics->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($judge['full_name']) ?></td>
                                        <td><?= $judge['total_cases'] ?></td>
                                        <td><?= round($judge['avg_days'] ?? 0) ?></td>
                                        <td><?= round($judge['avg_conviction_rate'] ?? 0, 1) ?>%</td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Crime Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="crimeTypeChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Resolution Time Trends (Last 5 Years)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="resolutionTrendChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($_SESSION['role'] === 'registrar'): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5>Case Predictions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Case CIN</th>
                                <th>Defendant</th>
                                <th>Predicted Outcome</th>
                                <th>Predicted Resolution Days</th>
                                <th>Confidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $predictions_query = "SELECT c.cin, c.defendant_name, cp.predicted_outcome, 
                                                 cp.predicted_resolution_days, cp.confidence_score
                                                 FROM case_predictions cp
                                                 JOIN cases c ON cp.case_id = c.case_id
                                                 WHERE c.status = 'pending'
                                                 ORDER BY cp.confidence_score DESC
                                                 LIMIT 10";
                            $predictions = $conn->query($predictions_query);
                            
                            while($prediction = $predictions->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($prediction['cin']) ?></td>
                                <td><?= htmlspecialchars($prediction['defendant_name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $prediction['predicted_outcome'] === 'conviction' ? 'danger' : 
                                        ($prediction['predicted_outcome'] === 'acquittal' ? 'success' : 
                                        ($prediction['predicted_outcome'] === 'dismissal' ? 'warning' : 'info')) 
                                    ?>">
                                        <?= ucfirst($prediction['predicted_outcome']) ?>
                                    </span>
                                </td>
                                <td><?= $prediction['predicted_resolution_days'] ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?= $prediction['confidence_score'] ?>%" 
                                             aria-valuenow="<?= $prediction['confidence_score'] ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?= round($prediction['confidence_score'], 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <a href="registrar_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Outcomes Chart
        const outcomesCtx = document.getElementById('outcomesChart').getContext('2d');
        const outcomesChart = new Chart(outcomesCtx, {
            type: 'doughnut',
            data: {
                labels: ['Convictions', 'Acquittals', 'Dismissals', 'Settlements'],
                datasets: [{
                    data: [
                        <?= $stats['convictions'] ?>,
                        <?= $stats['acquittals'] ?>,
                        <?= $stats['dismissals'] ?>,
                        <?= $stats['settlements'] ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
        
        // Crime Type Chart
        const crimeTypeCtx = document.getElementById('crimeTypeChart').getContext('2d');
        const crimeTypeChart = new Chart(crimeTypeCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    $crime_dist->data_seek(0);
                    while($crime = $crime_dist->fetch_assoc()): 
                        echo "'" . addslashes($crime['crime_type']) . "',";
                    endwhile; 
                    ?>
                ],
                datasets: [{
                    label: 'Number of Cases',
                    data: [
                        <?php 
                        $crime_dist->data_seek(0);
                        while($crime = $crime_dist->fetch_assoc()): 
                            echo $crime['case_count'] . ",";
                        endwhile; 
                        ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Conviction Rate (%)',
                    data: [
                        <?php 
                        $crime_dist->data_seek(0);
                        while($crime = $crime_dist->fetch_assoc()): 
                            $rate = $crime['case_count'] > 0 ? ($crime['convictions'] / $crime['case_count']) * 100 : 0;
                            echo round($rate, 1) . ",";
                        endwhile; 
                        ?>
                    ],
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Cases'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Conviction Rate (%)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
        
        // Resolution Trend Chart
        const trendCtx = document.getElementById('resolutionTrendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    $resolution_trends->data_seek(0);
                    while($trend = $resolution_trends->fetch_assoc()): 
                        echo "'" . $trend['year'] . "',";
                    endwhile; 
                    ?>
                ],
                datasets: [{
                    label: 'Average Resolution Days',
                    data: [
                        <?php 
                        $resolution_trends->data_seek(0);
                        while($trend = $resolution_trends->fetch_assoc()): 
                            echo round($trend['avg_days']) . ",";
                        endwhile; 
                        ?>
                    ],
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    tension: 0.1
                }, {
                    label: 'Number of Cases',
                    data: [
                        <?php 
                        $resolution_trends->data_seek(0);
                        while($trend = $resolution_trends->fetch_assoc()): 
                            echo $trend['case_count'] . ",";
                        endwhile; 
                        ?>
                    ],
                    backgroundColor: 'rgba(153, 102, 255, 0.2)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Resolution Days'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Cases'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>