<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$cin = isset($_GET['cin']) ? trim($_GET['cin']) : '';
if (empty($cin)) {
    header('Location: dashboard.php');
    exit();
}

// Fetch case details
$sql = "SELECT c.*, u1.full_name as judge_name, u2.full_name as lawyer_name 
        FROM cases c 
        LEFT JOIN users u1 ON c.judge_id = u1.user_id 
        LEFT JOIN users u2 ON c.lawyer_id = u2.user_id 
        WHERE c.cin = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $cin);
$stmt->execute();
$result = $stmt->get_result();
$case = $result->fetch_assoc();

if (!$case) {
    header('Location: dashboard.php');
    exit();
}

// Check access for lawyers
if ($_SESSION['role'] === 'lawyer') {
    $lawyer_id = $_SESSION['user_id'];
    
    $assigned_sql = "SELECT 1 FROM case_assignments WHERE case_id = ? AND user_id = ? AND role = 'lawyer'";
    $assigned_stmt = $conn->prepare($assigned_sql);
    $assigned_stmt->bind_param("ii", $case['case_id'], $lawyer_id);
    $assigned_stmt->execute();
    $assigned_result = $assigned_stmt->get_result();
    
    if ($assigned_result->num_rows === 0) {
        $paid_sql = "SELECT 1 FROM case_browsing_history WHERE lawyer_id = ? AND cin = ?";
        $paid_stmt = $conn->prepare($paid_sql);
        $paid_stmt->bind_param("is", $lawyer_id, $cin);
        $paid_stmt->execute();
        $paid_result = $paid_stmt->get_result();
    }
}

// Fetch hearings
$hearings_sql = "SELECT *, 
                DATE_FORMAT(hearing_date, '%M %d, %Y %h:%i %p') as formatted_date
                FROM hearings 
                WHERE case_id = ? 
                ORDER BY hearing_date DESC";
$hearings_stmt = $conn->prepare($hearings_sql);
$hearings_stmt->bind_param("i", $case['case_id']);
$hearings_stmt->execute();
$hearings_result = $hearings_stmt->get_result();
$hearings = $hearings_result->fetch_all(MYSQLI_ASSOC);

// Fetch judgments
$judgments_sql = "SELECT *, 
                 DATE_FORMAT(judgment_date, '%M %d, %Y') as formatted_date
                 FROM judgments 
                 WHERE case_id = ? 
                 ORDER BY judgment_date DESC";
$judgments_stmt = $conn->prepare($judgments_sql);
$judgments_stmt->bind_param("i", $case['case_id']);
$judgments_stmt->execute();
$judgments_result = $judgments_stmt->get_result();
$judgments = $judgments_result->fetch_all(MYSQLI_ASSOC);

// Fetch evidence/documents
$evidence_sql = "SELECT * FROM case_evidence WHERE case_id = ? ORDER BY submission_date DESC";
$evidence_stmt = $conn->prepare($evidence_sql);
$evidence_stmt->bind_param("i", $case['case_id']);
$evidence_stmt->execute();
$evidence_result = $evidence_stmt->get_result();
$evidence = $evidence_result->fetch_all(MYSQLI_ASSOC);

// Function to get file icon based on type
function getFileIcon($type) {
    switch($type) {
        case 'photo': return 'fa-image';
        case 'video': return 'fa-video';
        case 'audio': return 'fa-music';
        case 'document': 
        default: return 'fa-file';
    }
}

// Function to determine if file can be previewed
function canPreview($type) {
    return in_array($type, ['photo', 'video', 'audio']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Details - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background-color: var(--primary-color) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .case-header {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .case-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .case-card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .detail-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--primary-color);
            flex: 0 0 40%;
        }
        
        .detail-value {
            text-align: right;
            flex: 0 0 60%;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
            margin-top: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: var(--secondary-color);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: var(--secondary-color);
            border: 3px solid white;
        }
        
        .timeline-date {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .timeline-content {
            background-color: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .hearing-details {
            margin-top: 1rem;
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 1rem;
        }
        
        .hearing-detail-row {
            display: flex;
            margin-bottom: 0.5rem;
        }
        
        .hearing-detail-label {
            font-weight: 600;
            color: var(--primary-color);
            flex: 0 0 30%;
        }
        
        .hearing-detail-value {
            flex: 0 0 70%;
        }
        
        .badge-pill {
            padding: 0.5em 1em;
            font-weight: 600;
        }
        
        .btn-back {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background-color: var(--secondary-color);
            color: white;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #bdc3c7;
        }
        
        .evidence-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eee;
            transition: all 0.2s;
        }
        
        .evidence-item:hover {
            background-color: #f8f9fa;
        }
        
        .evidence-item:last-child {
            border-bottom: none;
        }
        
        .evidence-actions a {
            margin-left: 0.5rem;
            color: var(--secondary-color);
        }
        
        .evidence-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            margin-left: 0.5rem;
        }
        
        .evidence-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            display: block;
        }
        
        .evidence-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            color: var(--primary-color);
        }
        
        .evidence-info {
            flex-grow: 1;
        }
        
        .evidence-type-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            background-color: var(--secondary-color);
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-gavel me-2"></i>Judicial Information System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_cases.php"><i class="fas fa-search me-1"></i> Search Cases</a>
                <a class="nav-link" href="profile.php"><i class="fas fa-user me-1"></i> Profile</a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="case-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1"><?php echo htmlspecialchars($case['title'] ?? 'Case Details'); ?></h1>
                    <p class="mb-0">CIN: <?php echo htmlspecialchars($case['cin']); ?></p>
                </div>
                <span class="badge bg-light text-dark badge-pill">
                    <?php echo strtoupper(htmlspecialchars($case['status'])); ?>
                </span>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="case-card">
                    <div class="case-card-header">
                        <i class="fas fa-info-circle me-2"></i>Case Information
                    </div>
                    <div class="card-body p-0">
                        <div class="detail-item">
                            <span class="detail-label">Defendant Name</span>
                            <span class="detail-value"><?php echo htmlspecialchars($case['defendant_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Crime Type</span>
                            <span class="detail-value"><?php echo htmlspecialchars($case['crime_type']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Case Description</span>
                            <span class="detail-value text-end"><?php echo nl2br(htmlspecialchars($case['description'] ?? 'No description available')); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Filing Date</span>
                            <span class="detail-value"><?php echo date('F j, Y', strtotime($case['start_date'])); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="case-card">
                    <div class="case-card-header">
                        <i class="fas fa-gavel me-2"></i>Assigned Personnel
                    </div>
                    <div class="card-body p-0">
                        <div class="detail-item">
                            <span class="detail-label">Presiding Judge</span>
                            <span class="detail-value"><?php echo htmlspecialchars($case['judge_name'] ?? 'Not assigned'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Defense Lawyer</span>
                            <span class="detail-value"><?php echo htmlspecialchars($case['lawyer_name'] ?? 'Not assigned'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="case-card">
                    <div class="case-card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-calendar-day me-2"></i>Case Hearings</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($hearings)): ?>
                            <div class="timeline">
                                <?php foreach ($hearings as $hearing): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-date">
                                            <i class="fas fa-calendar-alt me-2"></i>
                                            <?php echo $hearing['formatted_date']; ?>
                                            <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($hearing['hearing_type']); ?></span>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="hearing-details">
                                                <div class="hearing-detail-row">
                                                    <span class="hearing-detail-label">Hearing Status:</span>
                                                    <span class="hearing-detail-value">
                                                        <?php echo htmlspecialchars($hearing['status'] ?? 'Not specified'); ?>
                                                        <?php if ($hearing['is_conflict']): ?>
                                                            <span class="badge bg-danger evidence-badge">Conflict</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($hearing['adjournment_reason'])): ?>
                                                    <div class="hearing-detail-row">
                                                        <span class="hearing-detail-label">Adjournment Reason:</span>
                                                        <span class="hearing-detail-value">
                                                            <?php echo htmlspecialchars($hearing['adjournment_reason']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="hearing-detail-row">
                                                    <span class="hearing-detail-label">Proceedings Summary:</span>
                                                    <span class="hearing-detail-value">
                                                        <?php echo nl2br(htmlspecialchars($hearing['proceedings_summary'] ?? 'No summary available')); ?>
                                                    </span>
                                                </div>
                                                <div class="hearing-detail-row">
                                                    <span class="hearing-detail-label">Next Steps:</span>
                                                    <span class="hearing-detail-value">
                                                        <?php echo nl2br(htmlspecialchars($hearing['next_steps'] ?? 'Not specified')); ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($hearing['notes'])): ?>
                                                    <div class="hearing-detail-row">
                                                        <span class="hearing-detail-label">Additional Notes:</span>
                                                        <span class="hearing-detail-value">
                                                            <?php echo nl2br(htmlspecialchars($hearing['notes'])); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No hearings scheduled</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="case-card">
                    <div class="case-card-header">
                        <i class="fas fa-file-contract me-2"></i>Judgments & Rulings
                    </div>
                    <div class="card-body">
                        <?php if (!empty($judgments)): ?>
                            <?php foreach ($judgments as $judgment): ?>
                                <div class="mb-4">
                                    <h6 class="d-flex justify-content-between align-items-center">
                                        <span><?php echo $judgment['formatted_date']; ?></span>
                                        <span class="badge bg-<?php echo $judgment['status'] === 'final' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst(htmlspecialchars($judgment['status'])); ?>
                                        </span>
                                    </h6>
                                    <div class="bg-light p-3 rounded">
                                        <?php echo nl2br(htmlspecialchars($judgment['judgment_text'])); ?>
                                    </div>
                                    <?php if (!empty($judgment['additional_notes'])): ?>
                                        <div class="mt-2 p-2 bg-white border rounded">
                                            <small class="text-muted">Judge's Notes:</small>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($judgment['additional_notes'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file-alt"></i>
                                <p>No judgments recorded</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="case-card">
                    <div class="case-card-header">
                        <i class="fas fa-paperclip me-2"></i>Case Evidence
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($evidence)): ?>
                            <?php foreach ($evidence as $item): ?>
                                <div class="evidence-item">
                                    <div class="d-flex align-items-center">
                                        <i class="evidence-icon <?php echo getFileIcon($item['evidence_type']); ?>"></i>
                                        <div class="evidence-info">
                                            <div class="d-flex align-items-center">
                                                <span><?php echo htmlspecialchars($item['title']); ?></span>
                                                <span class="evidence-type-badge ms-2"><?php echo ucfirst($item['evidence_type']); ?></span>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($item['submission_date'])); ?>
                                                <span class="badge bg-<?php 
                                                    switch($item['status']) {
                                                        case 'verified': echo 'success'; break;
                                                        case 'rejected': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?> evidence-badge">
                                                    <?php echo ucfirst($item['status']); ?>
                                                </span>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="evidence-actions">
                                        <a href="<?php echo htmlspecialchars($item['file_path']); ?>" title="Download" download class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php if (canPreview($item['evidence_type'])): ?>
                                            <button class="btn btn-sm btn-outline-secondary view-evidence" 
                                                    data-file="<?php echo htmlspecialchars($item['file_path']); ?>" 
                                                    data-type="<?php echo $item['evidence_type']; ?>"
                                                    title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>No evidence available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="<?php 
                if ($_SESSION['role'] === 'judge') {
                    echo 'judge_dashboard.php';
                } elseif ($_SESSION['role'] === 'lawyer') {
                    echo 'lawyer_dashboard.php';
                } else {
                    echo 'registrar_dashboard.php';
                }
            ?>" class="btn btn-back">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Evidence Preview Modal -->
    <div class="modal fade" id="evidencePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Evidence Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="evidencePreviewContent"></div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="evidenceDownloadLink" class="btn btn-primary" download>
                        <i class="fas fa-download me-2"></i>Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Handle evidence preview
        document.querySelectorAll('.view-evidence').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const filePath = this.getAttribute('data-file');
                const fileType = this.getAttribute('data-type');
                const previewModal = new bootstrap.Modal(document.getElementById('evidencePreviewModal'));
                const previewContent = document.getElementById('evidencePreviewContent');
                const downloadLink = document.getElementById('evidenceDownloadLink');
                
                // Set download link
                downloadLink.setAttribute('href', filePath);
                downloadLink.setAttribute('download', filePath.split('/').pop());
                
                // Clear previous content
                previewContent.innerHTML = '';
                
                // Show appropriate preview based on file type
                if (fileType === 'photo') {
                    previewContent.innerHTML = `
                        <img src="${filePath}" class="img-fluid" style="max-height: 70vh;" alt="Evidence Photo">
                        <p class="mt-2">${filePath.split('/').pop()}</p>
                    `;
                } else if (fileType === 'video') {
                    previewContent.innerHTML = `
                        <video controls class="w-100" style="max-height: 70vh;">
                            <source src="${filePath}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <p class="mt-2">${filePath.split('/').pop()}</p>
                    `;
                } else if (fileType === 'audio') {
                    previewContent.innerHTML = `
                        <audio controls class="w-100">
                            <source src="${filePath}" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                        <p class="mt-2">${filePath.split('/').pop()}</p>
                    `;
                }
                
                previewModal.show();
            });
        });
    </script>
</body>
</html>