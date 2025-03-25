<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$document_id = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : 0;
$document_type = isset($_GET['doc_type']) ? $_GET['doc_type'] : '';

// Validate document type
$valid_types = ['judgment', 'order', 'motion', 'other'];
if (!in_array($document_type, $valid_types)) {
    header("Location: unauthorized.php");
    exit();
}

// Verify user has permission to sign this document
$can_sign = false;
switch ($document_type) {
    case 'judgment':
        $check_sql = "SELECT 1 FROM judgments j 
                     JOIN cases c ON j.case_id = c.case_id 
                     WHERE j.judgment_id = ? AND (j.judge_id = ? OR c.lawyer_id = ?)";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("iii", $document_id, $_SESSION['user_id'], $_SESSION['user_id']);
        $stmt->execute();
        $can_sign = $stmt->get_result()->num_rows > 0;
        break;
        
    case 'order':
        // Only judges can sign orders
        if ($_SESSION['role'] === 'judge') {
            $check_sql = "SELECT 1 FROM orders WHERE order_id = ? AND judge_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ii", $document_id, $_SESSION['user_id']);
            $stmt->execute();
            $can_sign = $stmt->get_result()->num_rows > 0;
        }
        break;
        
    case 'motion':
        // Lawyers can sign motions for their cases
        if ($_SESSION['role'] === 'lawyer') {
            $check_sql = "SELECT 1 FROM motions m 
                         JOIN cases c ON m.case_id = c.case_id 
                         WHERE m.motion_id = ? AND c.lawyer_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ii", $document_id, $_SESSION['user_id']);
            $stmt->execute();
            $can_sign = $stmt->get_result()->num_rows > 0;
        }
        break;
        
    case 'other':
        // Registrar can sign other documents
        $can_sign = $_SESSION['role'] === 'registrar';
        break;
}

if (!$can_sign) {
    header("Location: unauthorized.php");
    exit();
}

// Handle signature submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signature_data'])) {
    $signature_data = mysqli_real_escape_string($conn, $_POST['signature_data']);
    $verification_hash = hash('sha256', $signature_data . time() . $_SESSION['user_id']);
    
    // Check if already signed
    $check_sql = "SELECT 1 FROM digital_signatures 
                 WHERE document_type = ? AND document_id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("sii", $document_type, $document_id, $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        // Insert new signature
        $insert_sql = "INSERT INTO digital_signatures 
                      (user_id, document_type, document_id, signature_data, verification_hash) 
                      VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("isiss", $_SESSION['user_id'], $document_type, $document_id, $signature_data, $verification_hash);
        
        if ($stmt->execute()) {
            // Update document status based on type
            switch ($document_type) {
                case 'judgment':
                    $update_sql = "UPDATE judgments SET status = 'signed' WHERE judgment_id = ?";
                    break;
                case 'order':
                    $update_sql = "UPDATE orders SET status = 'signed' WHERE order_id = ?";
                    break;
                case 'motion':
                    $update_sql = "UPDATE motions SET status = 'signed' WHERE motion_id = ?";
                    break;
                default:
                    $update_sql = "";
            }
            
            if (!empty($update_sql)) {
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("i", $document_id);
                $stmt->execute();
            }
            
            $_SESSION['success'] = "Document signed successfully!";
            header("Location: document_view.php?type=$document_type&id=$document_id");
            exit();
        } else {
            $error = "Failed to save signature. Please try again.";
        }
    } else {
        $error = "You have already signed this document.";
    }
}

// Get user details for signature display
$user_sql = "SELECT full_name, email FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Sign Document - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <style>
        .signature-container {
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f8f9fa;
        }
        canvas {
            width: 100%;
            height: 200px;
            background-color: white;
        }
        .signature-preview {
            max-width: 300px;
            border: 1px solid #ddd;
            margin: 20px auto;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Sign Document</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <h5>Document Information</h5>
                            <p><strong>Document Type:</strong> <?= ucfirst($document_type) ?></p>
                            <p><strong>Document ID:</strong> <?= $document_id ?></p>
                            <p><strong>Signing as:</strong> <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['email']) ?>)</p>
                        </div>
                        
                        <form method="POST" id="signatureForm">
                            <div class="mb-3">
                                <label class="form-label">Draw your signature below:</label>
                                <div class="signature-container">
                                    <canvas id="signatureCanvas"></canvas>
                                </div>
                                <div class="mt-2">
                                    <button type="button" id="clearBtn" class="btn btn-sm btn-outline-secondary">Clear Signature</button>
                                </div>
                            </div>
                            
                            <div id="signaturePreview" class="text-center" style="display: none;">
                                <h6>Your Signature</h6>
                                <div class="signature-preview">
                                    <img id="previewImage" src="" class="img-fluid">
                                </div>
                            </div>
                            
                            <input type="hidden" name="signature_data" id="signatureData">
                            
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">Submit Signature</button>
                                <a href="document_view.php?type=<?= $document_type ?>&id=<?= $document_id ?>" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('signatureCanvas');
            const signaturePad = new SignaturePad(canvas);
            const clearBtn = document.getElementById('clearBtn');
            const signatureForm = document.getElementById('signatureForm');
            const signatureData = document.getElementById('signatureData');
            const previewDiv = document.getElementById('signaturePreview');
            const previewImage = document.getElementById('previewImage');
            
            // Handle window resize
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
                signaturePad.clear(); // Clear on resize
            }
            
            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();
            
            // Clear signature
            clearBtn.addEventListener('click', function() {
                signaturePad.clear();
                previewDiv.style.display = 'none';
            });
            
            // Form submission
            signatureForm.addEventListener('submit', function(e) {
                if (signaturePad.isEmpty()) {
                    e.preventDefault();
                    alert('Please provide your signature first.');
                    return;
                }
                
                // Convert signature to data URL
                signatureData.value = signaturePad.toDataURL();
                
                // Show preview
                previewImage.src = signatureData.value;
                previewDiv.style.display = 'block';
            });
        });
    </script>
</body>
</html>