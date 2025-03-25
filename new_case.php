<?php
session_start();
require_once 'db_connect.php';

// Define file upload constraints at the top
$allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'mp3', 'mp4', 'm4a', 'mov', 'avi'];
$maxFileSize = 10 * 1024 * 1024; // 10MB

// Redirect if not logged in or not registrar
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
} elseif ($_SESSION['role'] !== 'registrar') {
    $dashboard = ($_SESSION['role'] === 'judge') ? 'judge_dashboard.php' : 'lawyer_dashboard.php';
    header("Location: $dashboard");
    exit();
}

$message = '';
$formData = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $formData = array(
        'cin' => mysqli_real_escape_string($conn, $_POST['cin']),
        'defendant_name' => mysqli_real_escape_string($conn, $_POST['defendant_name']),
        'defendant_address' => mysqli_real_escape_string($conn, $_POST['defendant_address']),
        'crime_type' => mysqli_real_escape_string($conn, $_POST['crime_type']),
        'crime_date' => mysqli_real_escape_string($conn, $_POST['crime_date']),
        'crime_location' => mysqli_real_escape_string($conn, $_POST['crime_location']),
        'arresting_officer' => mysqli_real_escape_string($conn, $_POST['arresting_officer']),
        'arrest_date' => mysqli_real_escape_string($conn, $_POST['arrest_date']),
        'prosecutor_name' => mysqli_real_escape_string($conn, $_POST['prosecutor_name']),
        'start_date' => mysqli_real_escape_string($conn, $_POST['start_date']),
        'expected_completion_date' => mysqli_real_escape_string($conn, $_POST['expected_completion_date']),
        'description' => mysqli_real_escape_string($conn, $_POST['description'] ?? ''),
        'severity_level' => mysqli_real_escape_string($conn, $_POST['severity_level'] ?? 'medium'),
        'complexity_level' => mysqli_real_escape_string($conn, $_POST['complexity_level'] ?? 'moderate')
    );

    // Validate dates
    $valid = true;
    if (strtotime($formData['crime_date']) > strtotime($formData['arrest_date'])) {
        $message = '<div class="alert alert-danger">Crime date cannot be after arrest date</div>';
        $valid = false;
    } 
    if (strtotime($formData['start_date']) > strtotime($formData['expected_completion_date'])) {
        $message = '<div class="alert alert-danger">Start date cannot be after completion date</div>';
        $valid = false;
    }

    if ($valid) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert case record
            $sql = "INSERT INTO cases (cin, defendant_name, defendant_address, crime_type, crime_date, 
                    crime_location, arresting_officer, arrest_date, prosecutor_name, start_date, 
                    expected_completion_date, description, severity_level, complexity_level) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssssss", 
                $formData['cin'], $formData['defendant_name'], $formData['defendant_address'],
                $formData['crime_type'], $formData['crime_date'], $formData['crime_location'],
                $formData['arresting_officer'], $formData['arrest_date'], $formData['prosecutor_name'],
                $formData['start_date'], $formData['expected_completion_date'], $formData['description'],
                $formData['severity_level'], $formData['complexity_level']);

            if (!$stmt->execute()) {
                throw new Exception("Error inserting case: " . $conn->error);
            }
            
            $case_id = $conn->insert_id;
            
            // Handle file upload if files were submitted
            if (!empty($_FILES['evidence']['name'][0])) {
                $uploadDir = 'case_evidence.php';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Prepare statement for evidence insertion
                $evidenceStmt = $conn->prepare("INSERT INTO case_evidence 
                    (case_id, evidence_type, title, description, file_path, submitted_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'submitted')");
                
                foreach ($_FILES['evidence']['name'] as $i => $name) {
                    $fileTmp = $_FILES['evidence']['tmp_name'][$i];
                    $fileSize = $_FILES['evidence']['size'][$i];
                    $fileError = $_FILES['evidence']['error'][$i];
                    $fileExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    
                    if (!in_array($fileExt, $allowedTypes)) {
                        continue; // Skip invalid file types
                    }
                    
                    if ($fileError !== 0) {
                        continue; // Skip files with upload errors
                    }
                    
                    if ($fileSize > $maxFileSize) {
                        continue; // Skip files that are too large
                    }
                    
                    $fileName = uniqid('', true) . '.' . $fileExt;
                    $fileDest = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($fileTmp, $fileDest)) {
                        $evidenceType = getFileType($fileExt);
                        $evidenceTitle = "Evidence " . ($i + 1);
                        
                        $evidenceStmt->bind_param("issssi", 
                            $case_id, 
                            $evidenceType, 
                            $evidenceTitle,
                            '', // Empty description for now
                            $fileDest,
                            $_SESSION['user_id']
                        );
                        
                        if (!$evidenceStmt->execute()) {
                            throw new Exception("Error saving evidence: " . $conn->error);
                        }
                    }
                }
                $evidenceStmt->close();
            }
            
            // Commit transaction if everything succeeded
            $conn->commit();
            
            $message = '<div class="alert alert-success">Case registered! <a href="case_details.php?cin='.$formData['cin'].'" class="alert-link">View Case</a></div>';
            $formData = array(); // Clear form
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

function getFileType($ext) {
    $imageTypes = ['jpg', 'jpeg', 'png', 'gif'];
    $videoTypes = ['mp4', 'mov', 'avi'];
    $audioTypes = ['mp3', 'm4a'];
    
    if (in_array($ext, $imageTypes)) return 'photo';
    if (in_array($ext, $videoTypes)) return 'video';
    if (in_array($ext, $audioTypes)) return 'audio';
    return 'document';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Case - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }
        .navbar {
            background-color: var(--primary) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: var(--primary);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .section-title {
            color: var(--primary);
            border-bottom: 2px solid var(--secondary);
            padding-bottom: 8px;
            margin-bottom: 20px;
        }
        .required:after {
            content: " *";
            color: var(--accent);
        }
        .btn-primary {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-gavel me-2"></i>JIS - Case Registration</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_cases.php"><i class="fas fa-search me-1"></i> Search</a>
                <a class="nav-link" href="registrar_dashboard.php"><i class="fas fa-home me-1"></i> Dashboard</a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-file-alt me-2"></i>New Case Registration</h3>
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <!-- Case Information -->
                            <div class="mb-4">
                                <h4 class="section-title"><i class="fas fa-info-circle me-2"></i>Case Information</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="cin" class="form-label required">CIN</label>
                                        <input type="text" class="form-control" id="cin" name="cin" 
                                               value="<?php echo htmlspecialchars($formData['cin'] ?? ''); ?>" required>
                                        <div class="invalid-feedback">Please enter CIN</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="defendant_name" class="form-label required">Defendant</label>
                                        <input type="text" class="form-control" id="defendant_name" name="defendant_name"
                                               value="<?php echo htmlspecialchars($formData['defendant_name'] ?? ''); ?>" required>
                                        <div class="invalid-feedback">Please enter defendant name</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="defendant_address" class="form-label required">Address</label>
                                        <textarea class="form-control" id="defendant_address" name="defendant_address" 
                                                  rows="2" required><?php echo htmlspecialchars($formData['defendant_address'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="3"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Crime Details -->
                            <div class="mb-4">
                                <h4 class="section-title"><i class="fas fa-exclamation-triangle me-2"></i>Crime Details</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="crime_type" class="form-label required">Crime Type</label>
                                        <input type="text" class="form-control" id="crime_type" name="crime_type"
                                               value="<?php echo htmlspecialchars($formData['crime_type'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="crime_date" class="form-label required">Crime Date</label>
                                        <input type="date" class="form-control" id="crime_date" name="crime_date"
                                               value="<?php echo htmlspecialchars($formData['crime_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="crime_location" class="form-label required">Location</label>
                                        <textarea class="form-control" id="crime_location" name="crime_location"
                                                  rows="2" required><?php echo htmlspecialchars($formData['crime_location'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="severity_level" class="form-label">Severity</label>
                                        <select class="form-select" id="severity_level" name="severity_level">
                                            <option value="low" <?php echo ($formData['severity_level'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo ($formData['severity_level'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo ($formData['severity_level'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="complexity_level" class="form-label">Complexity</label>
                                        <select class="form-select" id="complexity_level" name="complexity_level">
                                            <option value="simple" <?php echo ($formData['complexity_level'] ?? '') === 'simple' ? 'selected' : ''; ?>>Simple</option>
                                            <option value="moderate" <?php echo ($formData['complexity_level'] ?? '') === 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                                            <option value="complex" <?php echo ($formData['complexity_level'] ?? '') === 'complex' ? 'selected' : ''; ?>>Complex</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
    <h4 class="section-title"><i class="fas fa-folder-open me-2"></i>Case Evidence</h4>
    <div class="mb-3">
        <label for="evidence" class="form-label">Upload Evidence</label>
        <input class="form-control" type="file" id="evidence" name="evidence[]" multiple 
               accept=".jpg,.jpeg,.png,.gif,.pdf,.mp3,.mp4,.m4a,.mov,.avi">
        <div class="form-text">Allowed formats: JPG, PNG, GIF, PDF, MP3, MP4 (Max 10MB each)</div>
    </div>
    
    <!-- Preview area for selected files -->
    <div id="filePreview" class="mb-3"></div>
</div>

                            <!-- Arrest Details -->
                            <div class="mb-4">
                                <h4 class="section-title"><i class="fas fa-handcuffs me-2"></i>Arrest Details</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="arresting_officer" class="form-label required">Officer</label>
                                        <input type="text" class="form-control" id="arresting_officer" name="arresting_officer"
                                               value="<?php echo htmlspecialchars($formData['arresting_officer'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="arrest_date" class="form-label required">Arrest Date</label>
                                        <input type="date" class="form-control" id="arrest_date" name="arrest_date"
                                               value="<?php echo htmlspecialchars($formData['arrest_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="prosecutor_name" class="form-label required">Prosecutor</label>
                                        <input type="text" class="form-control" id="prosecutor_name" name="prosecutor_name"
                                               value="<?php echo htmlspecialchars($formData['prosecutor_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Case Timeline -->
                            <div class="mb-4">
                                <h4 class="section-title"><i class="fas fa-calendar-alt me-2"></i>Case Timeline</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="start_date" class="form-label required">Start Date</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date"
                                               value="<?php echo htmlspecialchars($formData['start_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="expected_completion_date" class="form-label required">Completion Date</label>
                                        <input type="date" class="form-control" id="expected_completion_date" name="expected_completion_date"
                                               value="<?php echo htmlspecialchars($formData['expected_completion_date'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-2"></i>Register Case
                                </button>
                                <a href="registrar_dashboard.php" class="btn btn-secondary px-4">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });

            document.getElementById('evidence').addEventListener('change', function(e) {
    const preview = document.getElementById('filePreview');
    preview.innerHTML = '';
    
    if (this.files.length > 0) {
        const list = document.createElement('ul');
        list.className = 'list-group';
        
        Array.from(this.files).forEach(file => {
            const item = document.createElement('li');
            item.className = 'list-group-item d-flex justify-content-between align-items-center';
            
            const icon = document.createElement('i');
            icon.className = file.type.includes('image/') ? 'fas fa-image' : 
                            file.type.includes('video/') ? 'fas fa-video' :
                            file.type.includes('audio/') ? 'fas fa-music' : 'fas fa-file';
            
            const text = document.createElement('span');
            text.textContent = `${file.name} (${(file.size/1024/1024).toFixed(2)}MB)`;
            
            item.appendChild(icon);
            item.appendChild(text);
            list.appendChild(item);
        });
        
        preview.appendChild(list);
    }
});

            // Date validation
            function validateDates() {
                var crimeDate = new Date(document.getElementById('crime_date').value);
                var arrestDate = new Date(document.getElementById('arrest_date').value);
                var startDate = new Date(document.getElementById('start_date').value);
                var endDate = new Date(document.getElementById('expected_completion_date').value);

                if (crimeDate && arrestDate && crimeDate > arrestDate) {
                    alert('Error: Crime date cannot be after arrest date');
                    return false;
                }
                if (startDate && endDate && startDate > endDate) {
                    alert('Error: Start date cannot be after completion date');
                    return false;
                }
                return true;
            }

            document.querySelector('form').addEventListener('submit', function(e) {
                if (!validateDates()) {
                    e.preventDefault();
                }
            });
        })();
    </script>
</body>
</html>