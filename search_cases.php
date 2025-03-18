<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$cases = [];

if (!empty($keyword)) {
    // Search by crime type, defendant name, or CIN
    $sql = "SELECT c.*, u.full_name as judge_name 
            FROM cases c 
            LEFT JOIN users u ON c.judge_id = u.user_id 
            WHERE c.crime_type LIKE ? OR c.defendant_name LIKE ? OR c.cin LIKE ? 
            ORDER BY c.start_date DESC";
    $stmt = $conn->prepare($sql);
    $search_term = "%$keyword%";
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    $cases = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Cases - JIS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Search Cases</h2>
        <form method="get" class="mb-4">
            <div class="input-group">
                <input type="text" name="keyword" class="form-control" placeholder="Search by crime type, defendant name, or CIN..." value="<?php echo htmlspecialchars($keyword); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>
        <?php if (!empty($cases)): ?>
            <div class="list-group">
                <?php foreach ($cases as $case): ?>
                    <a href="case_details.php?cin=<?php echo $case['cin']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">CIN: <?php echo $case['cin']; ?></h6>
                            <small>Started: <?php echo $case['start_date']; ?></small>
                        </div>
                        <p class="mb-1"><?php echo $case['defendant_name']; ?> - <?php echo $case['crime_type']; ?></p>
                        <small>Judge: <?php echo $case['judge_name']; ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No cases found</p>
        <?php endif; ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>