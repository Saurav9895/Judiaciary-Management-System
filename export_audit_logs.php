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

// Apply user filters from POST parameters
foreach ($_POST as $key => $value) {
    if (array_key_exists($key, $filters)) {
        $filters[$key] = trim($value);
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
$query .= " ORDER BY $sort_by $sort_order";

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=audit_logs_export_' . date('Y-m-d_H-i-s') . '.csv');

// Create output file pointer
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, [
    'Timestamp',
    'User',
    'User Role',
    'Action Type',
    'Table',
    'Record Reference',
    'Record ID',
    'IP Address',
    'Old Values',
    'New Values'
]);

try {
    // Execute the query
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $logs = $stmt->get_result();
    
    // Write data rows
    while ($log = $logs->fetch_assoc()) {
        fputcsv($output, [
            $log['action_timestamp'],
            $log['full_name'] . ' (' . $log['username'] . ')',
            $log['user_role'],
            $log['action_type'],
            $log['table_name'],
            $log['record_reference'],
            $log['record_id'],
            $log['ip_address'],
            $log['old_values'],
            $log['new_values']
        ]);
    }
    
} catch (Exception $e) {
    // If error occurs, output it as CSV row
    fputcsv($output, ['Error:', $e->getMessage()]);
}

fclose($output);
exit();