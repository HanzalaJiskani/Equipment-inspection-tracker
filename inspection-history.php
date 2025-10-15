<?php 
require_once 'config.php';

// Get filter parameters
$equipment_filter = isset($_GET['equipment']) ? sanitize($_GET['equipment']) : '';
$inspector_filter = isset($_GET['inspector']) ? sanitize($_GET['inspector']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build the query with filters
$query = "SELECT i.*, e.type, e.location, e.area 
          FROM inspections i 
          JOIN equipments e ON i.equipment_id = e.equipment_id 
          WHERE 1=1";
$params = [];

if ($equipment_filter) {
    $query .= " AND i.equipment_id LIKE ?";
    $params[] = "%$equipment_filter%";
}

if ($inspector_filter) {
    $query .= " AND i.inspector_name LIKE ?";
    $params[] = "%$inspector_filter%";
}

if ($date_from) {
    $query .= " AND DATE(i.submitted_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(i.submitted_at) <= ?";
    $params[] = $date_to;
}

if ($status_filter) {
    if ($status_filter === 'good') {
        // Good status means score >= 80%
        $query .= " AND (
            (CASE WHEN i.q1_safety_pin = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q2_gauge_green = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q3_weight_appropriate = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q4_no_damage = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q5_hanging_clip = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q6_accessible = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q7_refill_overdue = 'No' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q8_instructions_visible = 'Yes' THEN 1 ELSE 0 END)
        ) >= 7"; // 7 out of 8 = 87.5%
    } elseif ($status_filter === 'fair') {
        $query .= " AND (
            (CASE WHEN i.q1_safety_pin = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q2_gauge_green = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q3_weight_appropriate = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q4_no_damage = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q5_hanging_clip = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q6_accessible = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q7_refill_overdue = 'No' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q8_instructions_visible = 'Yes' THEN 1 ELSE 0 END)
        ) BETWEEN 5 AND 6"; // 5-6 out of 8 = 62.5-75%
    } elseif ($status_filter === 'poor') {
        $query .= " AND (
            (CASE WHEN i.q1_safety_pin = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q2_gauge_green = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q3_weight_appropriate = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q4_no_damage = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q5_hanging_clip = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q6_accessible = 'Yes' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q7_refill_overdue = 'No' THEN 1 ELSE 0 END) +
            (CASE WHEN i.q8_instructions_visible = 'Yes' THEN 1 ELSE 0 END)
        ) < 5"; // Less than 5 out of 8 = < 62.5%
    }
}

$query .= " ORDER BY i.submitted_at DESC";

// Get all inspections with filters
$inspections = $db->fetchAll($query, $params);

// Get unique inspectors for filter dropdown
$inspectors = $db->fetchAll("SELECT DISTINCT inspector_name FROM inspections ORDER BY inspector_name");

// Get unique equipment IDs for filter dropdown
$equipment_list = $db->fetchAll("SELECT DISTINCT equipment_id FROM equipments ORDER BY equipment_id");

// Calculate statistics
$total_inspections = count($inspections);
$good_count = 0;
$fair_count = 0;
$poor_count = 0;

foreach ($inspections as $inspection) {
    $score = 0;
    $questions = [
        $inspection['q1_safety_pin'] === 'Yes' ? 1 : 0,
        $inspection['q2_gauge_green'] === 'Yes' ? 1 : 0,
        $inspection['q3_weight_appropriate'] === 'Yes' ? 1 : 0,
        $inspection['q4_no_damage'] === 'Yes' ? 1 : 0,
        $inspection['q5_hanging_clip'] === 'Yes' ? 1 : 0,
        $inspection['q6_accessible'] === 'Yes' ? 1 : 0,
        $inspection['q7_refill_overdue'] === 'No' ? 1 : 0,
        $inspection['q8_instructions_visible'] === 'Yes' ? 1 : 0
    ];
    $score = array_sum($questions);
    $percentage = round(($score / 8) * 100);
    
    if ($percentage >= 80) $good_count++;
    elseif ($percentage >= 60) $fair_count++;
    else $poor_count++;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Inspection History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f8961e;
            --danger: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --white: #ffffff;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
            color: #4a5568;
        }
        
        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
            animation: backgroundMove 20s ease-in-out infinite;
            z-index: -1;
            will-change: transform;
        }

        @keyframes backgroundMove {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(180deg); }
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.25rem;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: var(--white);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .stats-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            color: var(--white);
            margin-bottom: 20px;
        }
        
        .stats-card .stat-value {
            font-size: 2rem;
            font-weight: 600;
            margin: 10px 0;
        }
        
        .stats-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .table th {
            font-weight: 600;
            color: var(--gray);
            border-top: none;
            position: sticky;
            top: 0;
            background-color: var(--white);
            z-index: 10;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .badge {
            font-weight: 500;
            padding: 5px 10px;
        }
        
        .filter-section {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        /* Enhanced CSS for Inspection Form Modal - Add to inspection-history.php <style> section */

        /* Modal Styles */
        .inspection-form-view .form-group {
            background-color: #f8f9fa;
            transition: all 0.2s ease;
            border: 1px solid #e9ecef !important;
        }

        .inspection-form-view .form-group:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .inspection-form-view .form-check-input:disabled {
            opacity: 0.8;
        }

        .inspection-header {
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .score-display {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 2px solid #dee2e6;
        }

        .form-check-label.fw-bold {
            font-weight: 600 !important;
        }

        .form-check-container {
            background-color: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }

        .form-check-inline {
            margin-right: 2rem;
        }

        .inspection-details .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 2px 0;
        }

        .inspection-details .detail-label {
            font-weight: 500;
            color: var(--gray);
        }

        .inspection-details .detail-value {
            font-weight: 500;
        }

        .inspection-details .detail-value.yes {
            color: #28a745;
        }

        .inspection-details .detail-value.no {
            color: #dc3545;
        }

        /* Print styles for inspection forms */
        @media print {
            .modal-header, .modal-footer {
                display: none !important;
            }
            
            .inspection-header {
                background: #4361ee !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            .score-display {
                border: 2px solid #dee2e6;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            .form-group {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            
            .badge {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }

        /* Responsive design for mobile */
        @media (max-width: 768px) {
            .inspection-header .row .col-md-4 {
                text-align: left !important;
                margin-top: 15px;
            }
            
            .form-check-inline {
                margin-right: 1rem;
            }
            
            .inspection-form-view .form-group {
                margin-bottom: 1rem;
                padding: 15px;
            }
        }
        
        .inspection-details {
            font-size: 0.85rem;
        }
        
        .inspection-details .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 2px 0;
        }
        
        .inspection-details .detail-label {
            font-weight: 500;
            color: var(--gray);
        }
        
        .inspection-details .detail-value {
            font-weight: 500;
        }
        
        .inspection-details .detail-value.yes {
            color: #28a745;
        }
        
        .inspection-details .detail-value.no {
            color: #dc3545;
        }
        
        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .table-responsive::-webkit-scrollbar {
            width: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .btn-action {
            border-radius: 8px;
            font-weight: 500;
            padding: 6px 12px;
            margin-right: 5px;
        }
        
        .export-buttons {
            gap: 10px;
        }

        /* Modal Styles */
        .inspection-form-view .form-group {
            background-color: #f8f9fa;
            transition: all 0.2s ease;
            border: 1px solid #e9ecef !important;
        }
        .inspection-form-view .form-group:hover {
            background-color: #e9ecef;
        }
        .inspection-form-view .form-check-input:disabled {
            opacity: 0.8;
        }
        .inspection-header {
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .score-display {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .filter-section .row > div {
                margin-bottom: 15px;
            }
            
            .export-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            
            .export-buttons .btn {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-hospital me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add-equipment.php">
                            <i class="fas fa-plus me-1"></i> Add Equipment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="inspection-history.php">
                            <i class="fas fa-history me-1"></i> Inspection History
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="inspectorDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-users me-1"></i> Inspector Access
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="login.php">
                                <i class="fas fa-clipboard-check me-1"></i> Start Inspection
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="view-equipment.php" target="_blank">
                                <i class="fas fa-external-link-alt me-1"></i> Preview Inspector Interface
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-history text-primary me-2"></i> Inspection History</h2>
                <p class="text-muted mb-0">Complete record of all equipment inspections</p>
            </div>
            <div class="d-flex export-buttons">
                <button class="btn btn-outline-success" onclick="exportToCSV()">
                    <i class="fas fa-file-csv me-1"></i> Export CSV
                </button>
                <!--<button class="btn btn-outline-primary" onclick="printReport()">
                    <i class="fas fa-print me-1"></i> Print Report
                </button>-->
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #4361ee, #3f37c9);">
                    <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                    <div class="stat-value"><?php echo $total_inspections; ?></div>
                    <div class="stat-label">Total Inspections</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <div class="stat-value"><?php echo $good_count; ?></div>
                    <div class="stat-label">Good (≥80%)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                    <div class="stat-value"><?php echo $fair_count; ?></div>
                    <div class="stat-label">Fair (60-79%)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #dc3545, #e91e63);">
                    <i class="fas fa-times-circle fa-2x mb-2"></i>
                    <div class="stat-value"><?php echo $poor_count; ?></div>
                    <div class="stat-label">Poor (<60%)</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i> Filter Inspections</h5>
            <form method="GET" id="filterForm">
                <div class="row">
                    <div class="col-md-3">
                        <label for="equipment" class="form-label">Equipment ID</label>
                        <select class="form-select" name="equipment" id="equipment">
                            <option value="">All Equipment</option>
                            <?php foreach ($equipment_list as $eq): ?>
                                <option value="<?php echo $eq['equipment_id']; ?>" 
                                        <?php echo $equipment_filter === $eq['equipment_id'] ? 'selected' : ''; ?>>
                                    <?php echo $eq['equipment_id']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="inspector" class="form-label">Inspector</label>
                        <select class="form-select" name="inspector" id="inspector">
                            <option value="">All Inspectors</option>
                            <?php foreach ($inspectors as $insp): ?>
                                <option value="<?php echo $insp['inspector_name']; ?>" 
                                        <?php echo $inspector_filter === $insp['inspector_name'] ? 'selected' : ''; ?>>
                                    <?php echo $insp['inspector_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" id="date_from" 
                               value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" id="date_to" 
                               value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="">All Status</option>
                            <option value="good" <?php echo $status_filter === 'good' ? 'selected' : ''; ?>>Good</option>
                            <option value="fair" <?php echo $status_filter === 'fair' ? 'selected' : ''; ?>>Fair</option>
                            <option value="poor" <?php echo $status_filter === 'poor' ? 'selected' : ''; ?>>Poor</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Apply Filters
                        </button>
                        <a href="inspection-history.php" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> Clear Filters
                        </a>
                        <span class="ms-3 text-muted">
                            Showing <?php echo count($inspections); ?> of <?php echo $total_inspections; ?> inspections
                        </span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Inspection History Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i> Inspection Records</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover" id="inspectionTable">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Equipment ID</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Area</th>
                                <th>Inspector</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Details</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inspections)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <i class="fas fa-info-circle text-muted me-2"></i>
                                        No inspections found matching the current filters.
                                    </td>
                                </tr>
                            <?php else:
                                foreach ($inspections as $inspection): 
                                    $score = 0;
                                    $questions = [
                                        $inspection['q1_safety_pin'] === 'Yes' ? 1 : 0,
                                        $inspection['q2_gauge_green'] === 'Yes' ? 1 : 0,
                                        $inspection['q3_weight_appropriate'] === 'Yes' ? 1 : 0,
                                        $inspection['q4_no_damage'] === 'Yes' ? 1 : 0,
                                        $inspection['q5_hanging_clip'] === 'Yes' ? 1 : 0,
                                        $inspection['q6_accessible'] === 'Yes' ? 1 : 0,
                                        $inspection['q7_refill_overdue'] === 'No' ? 1 : 0,
                                        $inspection['q8_instructions_visible'] === 'Yes' ? 1 : 0
                                    ];
                                    $score = array_sum($questions);
                                    $percentage = round(($score / 8) * 100);
                                    
                                    $statusClass = $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger');
                                    $statusText = $percentage >= 80 ? 'Good' : ($percentage >= 60 ? 'Fair' : 'Poor');
                                ?>
                                <tr>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($inspection['submitted_at'])); ?><br>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($inspection['submitted_at'])); ?></small>
                                    </td>
                                    <td>
                                        <a href="index.php?id=<?php echo $inspection['equipment_id']; ?>" class="text-decoration-none">
                                            <?php echo $inspection['equipment_id']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $inspection['type'] ?: '-'; ?></td>
                                    <td><?php echo $inspection['location'] ?: '-'; ?></td>
                                    <td><?php echo $inspection['area'] ?: '-'; ?></td>
                                    <td><?php echo $inspection['inspector_name']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $statusClass; ?>" 
                                                 role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                                 aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $percentage; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick='showInspectionDetails(<?php echo json_encode($inspection, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                                title="View complete inspection form">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                    <td>
                                        <!--<div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="generateInspectionReport(<?php echo $inspection['id']; ?>)"
                                                    title="Generate PDF report">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>-->
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="duplicateInspection('<?php echo $inspection['equipment_id']; ?>')"
                                                    title="Start new inspection for this equipment">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        <!--</div>-->
                                    </td>
                                </tr>
                                <?php endforeach; 
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Inspection Details Modal -->
    <div class="modal fade" id="inspectionDetailsModal" tabindex="-1" aria-labelledby="inspectionDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inspectionDetailsModalLabel">
                    <i class="fas fa-clipboard-check me-2"></i> Inspection Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="inspectionDetailsContent" style="max-height: 70vh; overflow-y: auto;">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
                    <!--<button type="button" class="btn btn-success" onclick="exportInspectionToPDF()">
                        <i class="fas fa-file-pdf me-1"></i> Save as PDF
                    </button>-->
                    <button type="button" class="btn btn-primary" onclick="exportInspectionToPDF()">
                        <i class="fas fa-print me-1"></i> Print Form
                    </button> 
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show inspection details in modal - Complete form view
        function showInspectionDetails(inspection) {
            const checklist = [
                { key: 'q1_safety_pin', icon: 'fa-heart', color: 'text-danger', label: '1. Safety pin is intact' },
                { key: 'q2_gauge_green', icon: 'fa-biohazard', color: 'text-success', label: '2. Gauge is at green level' },
                { key: 'q3_weight_appropriate', icon: 'fa-stopwatch', color: 'text-info', label: '3. Weight is appropriate' },
                { key: 'q4_no_damage', icon: 'fa-tools', color: 'text-warning', label: '4. No pinholes/damage/rust' },
                { key: 'q5_hanging_clip', icon: 'fa-paperclip', color: 'text-secondary', label: '5. Hanging clip is intact' },
                { key: 'q6_accessible', icon: 'fa-person-walking', color: 'text-primary', label: '6. Easily accessible / Not blocked' },
                { key: 'q7_refill_overdue', icon: 'fa-hourglass', color: 'text-danger', label: '7. Refill is overdue' },
                { key: 'q8_instructions_visible', icon: 'fa-eye', color: 'text-info', label: '8. Instructions are visible' }
            ];

            const formatDate = (dateStr) => {
                if (!dateStr) return '-';
                const d = new Date(dateStr);
                return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' });
            };

            let rows = '';
            checklist.forEach(q => {
                let answer = inspection[q.key];
                let iconHtml = `<i class="fas ${q.icon} ${q.color}"></i>`;
                let ansIcon = '';
                let ansClass = '';
                let ansText = '';

                if (q.key === 'q7_refill_overdue') {
                    if (answer === 'No') {
                        ansIcon = '<i class="fas fa-check-square text-success"></i>';
                        ansText = 'No (Good)';
                        ansClass = 'text-success fw-bold';
                    } else if (answer === 'Yes') {
                        ansIcon = '<i class="fas fa-times text-danger"></i>';
                        ansText = 'Yes';
                        ansClass = 'text-danger fw-bold';
                    } else {
                        ansText = '-';
                    }
                } else {
                    if (answer === 'Yes') {
                        ansIcon = '<i class="fas fa-check-square text-success"></i>';
                        ansText = 'Yes';
                        ansClass = 'text-success fw-bold';
                    } else if (answer === 'No') {
                        ansIcon = '<i class="fas fa-times text-danger"></i>';
                        ansText = 'No';
                        ansClass = 'text-danger fw-bold';
                    } else {
                        ansText = '-';
                    }
                }

                rows += `
                <div class="d-flex align-items-center py-2 px-3 border-bottom">
                    <div class="flex-grow-1 d-flex align-items-center">
                        ${iconHtml}
                        <span class="fw-bold ms-2">${q.label}</span>
                    </div>
                    <div class="text-end" style="min-width:120px;">
                        <span class="${ansClass}" style="font-size:1.1em;">${ansIcon} ${ansText}</span>
                    </div>
                </div>
                `;
            });

            let html = `
            <div>
                <div class="p-3 mb-2" style="background:linear-gradient(90deg,#21c08b,#28a745);border-radius:16px;">
                    <h3 class="mb-1 text-white fw-bold"><i class="fas fa-clipboard-check me-2"></i>Equipment Inspection</h3>
                    <p class="mb-0 text-white">Inspector: <strong>${inspection.inspector_name || '-'}</strong></p>
                </div>
                <div class="px-3 py-2 mb-2" style="background:linear-gradient(90deg,#17a2b8,#138496);color:white;border-radius:12px;">
                    <div class="row">
                        <div class="col-md-8">
                            <div><strong>ID:</strong> ${inspection.equipment_id || '-'}</div>
                            <div><strong>Location:</strong> ${inspection.location || '-'}</div>
                            <div><strong>Type:</strong> ${inspection.type || '-'}</div>
                            <div><strong>Area:</strong> ${inspection.area || '-'}</div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div><strong>Current Due Date:</strong>
                                <span class="ms-2">${formatDate(inspection.due_date)}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-2 px-3 py-2" style="background:#1565c0;color:#fff;border-radius:8px;">
                    <i class="fas fa-list-check me-2"></i><strong>Equipment Inspection Checklist</strong>
                </div>
                <div class="mb-4 border rounded bg-white">
                    ${rows}
                </div>
                <div class="mb-2 px-3">
                    <span><i class="fas fa-comment text-secondary"></i> <strong>Additional Remarks (Optional)</strong></span>
                    <div class="border rounded p-2 mt-1 bg-light">
                        ${inspection.remarks ? inspection.remarks : '<em class="text-muted">No remarks</em>'}
                    </div>
                </div>
            </div>
            `;

            document.getElementById('inspectionDetailsContent').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('inspectionDetailsModal'));
            modal.show();
        }
        
        

        // Export to CSV - Fixed version
        function exportToCSV() {
            try {
                const table = document.getElementById('inspectionTable');
                if (!table) {
                    alert('Table not found for export');
                    return;
                }
                
                const rows = Array.from(table.querySelectorAll('tr'));
                
                let csv = '';
                rows.forEach((row, index) => {
                    const cols = Array.from(row.querySelectorAll(index === 0 ? 'th' : 'td'));
                    // Exclude last 2 columns (Details & Actions)
                    const rowData = cols.slice(0, -2).map(col => {
                        // Clean the text content and escape quotes
                        let text = col.textContent.trim().replace(/\s+/g, ' ');
                        return '"' + text.replace(/"/g, '""') + '"';
                    });
                    csv += rowData.join(',') + '\n';
                });

                // Create and download the file
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `inspection_history_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                alert('CSV file has been downloaded successfully!');
            } catch (error) {
                console.error('Error exporting CSV:', error);
                alert('Error exporting CSV file. Please try again.');
            }
        }

        // Print report - Fixed version
        function printReport() {
            try {
                // Hide elements that shouldn't be printed
                const elementsToHide = document.querySelectorAll('.btn, .export-buttons, .navbar, .filter-section');
                elementsToHide.forEach(el => el.style.display = 'none');
                
                // Print the page
                window.print();
                
                // Restore hidden elements after printing
                setTimeout(() => {
                    elementsToHide.forEach(el => el.style.display = '');
                }, 1000);
            } catch (error) {
                console.error('Error printing report:', error);
                alert('Error printing report. Please try again.');
            }
        }

        // Print inspection details - Fixed version
        // Enhanced print inspection details function for inspection-history.php
        // Export inspection details to PDF using browser's print-to-PDF
        function exportInspectionToPDF() {
            try {
                const content = document.getElementById('inspectionDetailsContent').innerHTML;
                const title = document.querySelector('#inspectionDetailsModalLabel').textContent;
                
                // Get equipment ID from the content for filename
                const equipmentMatch = content.match(/ID:<\/strong>\s*([^<]+)</);
                const equipmentId = equipmentMatch ? equipmentMatch[1].trim() : 'unknown';
                
                const printWindow = window.open('', '_blank', 'width=800,height=600');
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>${title} - ${equipmentId}</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                        <style>
                            body { 
                                font-family: Arial, sans-serif; 
                                margin: 20px; 
                                color: #333;
                                font-size: 12px;
                                background: white;
                            }
                            
                            .inspection-header, .p-3.mb-2 {
                                background: linear-gradient(90deg, #21c08b, #28a745) !important;
                                -webkit-print-color-adjust: exact;
                                color-adjust: exact;
                                color: white !important;
                                padding: 20px !important;
                                border-radius: 10px !important;
                                margin-bottom: 20px !important;
                            }
                            
                            .px-3.py-2.mb-2 {
                                background: linear-gradient(90deg, #17a2b8, #138496) !important;
                                color: white !important;
                                -webkit-print-color-adjust: exact;
                                color-adjust: exact;
                            }
                            
                            .mb-2.px-3.py-2 {
                                background: #1565c0 !important;
                                color: white !important;
                                -webkit-print-color-adjust: exact;
                                color-adjust: exact;
                            }
                            
                            .d-flex.align-items-center.py-2.px-3.border-bottom {
                                border-bottom: 1px solid #dee2e6 !important;
                                padding: 8px 12px !important;
                            }
                            
                            .border.rounded.bg-white {
                                border: 1px solid #dee2e6 !important;
                                background: white !important;
                            }
                            
                            .text-success { color: #28a745 !important; }
                            .text-danger { color: #dc3545 !important; }
                            .text-warning { color: #ffc107 !important; }
                            .text-info { color: #17a2b8 !important; }
                            .text-primary { color: #007bff !important; }
                            .text-secondary { color: #6c757d !important; }
                            .text-muted { color: #6c757d !important; }
                            
                            .fw-bold { font-weight: bold !important; }
                            
                            .bg-light {
                                background-color: #f8f9fa !important;
                                padding: 15px !important;
                                border-radius: 5px !important;
                                border: 1px solid #e9ecef !important;
                            }
                            
                            @page {
                                margin: 0.75in;
                                size: A4;
                            }
                            
                            @media print {
                                body { 
                                    font-size: 11px; 
                                    margin: 0;
                                    padding: 20px;
                                }
                                
                                * {
                                    -webkit-print-color-adjust: exact !important;
                                    color-adjust: exact !important;
                                }
                            }
                            
                            .pdf-header {
                                text-align: center;
                                margin-bottom: 30px;
                                border-bottom: 2px solid #dee2e6;
                                padding-bottom: 20px;
                            }
                            
                            .pdf-footer {
                                margin-top: 30px;
                                text-align: center;
                                font-size: 10px;
                                color: #6c757d;
                                border-top: 1px solid #dee2e6;
                                padding-top: 15px;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="pdf-header">
                            <h2 style="margin-bottom: 5px; color: #4361ee;">${title}</h2>
                            <p style="color: #666; margin-bottom: 5px;">Equipment ID: ${equipmentId}</p>
                            <p style="color: #666; font-size: 10px;">Generated on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
                        </div>
                        
                        ${content}
                        
                        <div class="pdf-footer">
                            <p>This is a computer-generated inspection report.</p>
                        </div>
                        
                        <script>
                            window.onload = function() {
                                // Give time for styles to load
                                setTimeout(function() {
                                    // Show print dialog immediately
                                    window.print();
                                }, 500);
                                
                                // Close window after printing (user can cancel)
                                window.addEventListener('afterprint', function() {
                                    setTimeout(function() {
                                        window.close();
                                    }, 100);
                                });
                                
                                // Also close if user cancels print dialog
                                setTimeout(function() {
                                    if (!window.matchMedia('print').matches) {
                                        // Check if print dialog was likely cancelled
                                        setTimeout(function() {
                                            window.close();
                                        }, 2000);
                                    }
                                }, 1000);
                            };
                        
                    </body>
                    </html>
                `);
                printWindow.document.close();
                
                // Show instruction to user
                /*setTimeout(() => {
                    if (!printWindow.closed) {
                        alert('In the print dialog:\n1. Choose "Save as PDF" as destination\n2. Click Save\n3. Choose your desired location');
                    }
                }, 1000);*/
                
            } catch (error) {
                console.error('Error generating PDF:', error);
                alert('Error generating PDF. Please try again.');
            }
        }

        // Generate inspection report (PDF placeholder)
        // Generate inspection report (PDF) - Updated version
        function generateInspectionReport(inspectionId) {
            try {
                // Find the inspection data from the table row
                const rows = document.querySelectorAll('#inspectionTable tbody tr');
                let inspectionData = null;
                
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length > 0) {
                        const viewButton = cells[8]?.querySelector('button[onclick*="showInspectionDetails"]');
                        if (viewButton && viewButton.onclick.toString().includes(inspectionId)) {
                            // Extract inspection data from the view button's onclick attribute
                            const onclickStr = viewButton.getAttribute('onclick');
                            const jsonMatch = onclickStr.match(/showInspectionDetails\((.+)\)/);
                            if (jsonMatch) {
                                try {
                                    inspectionData = JSON.parse(jsonMatch[1]);
                                } catch (e) {
                                    console.error('Error parsing inspection data:', e);
                                }
                            }
                        }
                    }
                });
                
                if (inspectionData) {
                    // Temporarily show inspection details and then export to PDF
                    showInspectionDetails(inspectionData);
                    setTimeout(() => {
                        exportInspectionToPDF();
                    }, 500);
                } else {
                    alert('Could not find inspection data. Please try using the View button instead.');
                }
                
            } catch (error) {
                console.error('Error generating report:', error);
                alert('Error generating report. Please try again.');
            }
        }
        // Duplicate inspection (start new inspection for same equipment)
        function duplicateInspection(equipmentId) {
            try {
                if (confirm('This will start a new inspection for equipment ' + equipmentId + '. Continue?')) {
                    window.location.href = `login.php?equipment_id=${equipmentId}`;
                }
            } catch (error) {
                console.error('Error duplicating inspection:', error);
                alert('Error starting new inspection. Please try again.');
            }
        }

        // Auto-refresh functionality (optional)
        let autoRefresh = false;
        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;
            if (autoRefresh) {
                setTimeout(function refresh() {
                    if (autoRefresh) {
                        location.reload();
                        setTimeout(refresh, 30000); // Refresh every 30 seconds
                    }
                }, 30000);
            }
        }

        // Filter form auto-submit on change (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const filterInputs = document.querySelectorAll('#filterForm select, #filterForm input');
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    // Uncomment the line below if you want auto-submit on filter change
                    // document.getElementById('filterForm').submit();
                });
            });
        });
    </script>

    <style media="print">
        .navbar, .filter-section, .export-buttons, .btn, .modal { display: none !important; }
        .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
        .table { font-size: 12px; }
        body::before { display: none !important; }
        .stats-card { background: #f8f9fa !important; color: #000 !important; border: 1px solid #dee2e6; }
    </style>
</body>
</html>