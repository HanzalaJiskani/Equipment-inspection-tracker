<?php 
require_once 'config.php'; // Assuming config.php contains DB connection and helper functions like sanitize, formatDate, isOverdue, isDueSoon

$equipment = null;
$equipment_id = isset($_GET['id']) ? sanitize($_GET['id']) : '';

if ($equipment_id) {
    $equipment = $db->fetchOne(
        "SELECT * FROM equipments WHERE equipment_id = ?", 
        [$equipment_id]
    );
}

// Get statistics for dashboard
$equipment_records = $db->fetchAll("SELECT * FROM equipments");
$stats = [
    'current_count' => 0,
    'due_soon_count' => 0,
    'overdue_count' => 0,
    'inspected_count' => 0,
    'pending_count' => 0,
    'total_equipment' => count($equipment_records)
];

$status_data = [];
$inspection_data = [];

// Get last inspection data
$inspections_map = [];
$inspection_rows = $db->fetchAll("SELECT equipment_id, MAX(submitted_at) as submitted_at FROM inspections GROUP BY equipment_id");
foreach ($inspection_rows as $row) {
    $inspections_map[$row['equipment_id']] = $row['submitted_at'];
}

$all_equipment = [];

foreach ($equipment_records as $eq) {
    $due = strtotime($eq['due_date']);
    $today = strtotime(date('Y-m-d'));

    // Status
    if ($due < $today) {
        $status = 'Overdue';
        $stats['overdue_count']++;
    } elseif ($due <= strtotime('+7 days', $today)) {
        $status = 'Due Soon';
        $stats['due_soon_count']++;
    } else {
        $status = 'Current';
        $stats['current_count']++;
    }

    // Inspection
    if (isset($inspections_map[$eq['equipment_id']])) {
        $inspection_status = 'Inspected';
        $stats['inspected_count']++;
    } else {
        $inspection_status = 'Pending';
        $stats['pending_count']++;
    }

    $all_equipment[] = array_merge($eq, [
        'status' => $status,
        'inspection_status' => $inspection_status,
        'last_inspection' => $inspections_map[$eq['equipment_id']] ?? null
    ]);
}

// Grouped data for charts
$status_data = [
    ['status' => 'Current', 'count' => $stats['current_count']],
    ['status' => 'Due Soon', 'count' => $stats['due_soon_count']],
    ['status' => 'Overdue', 'count' => $stats['overdue_count']],
];
$inspection_data = [
    ['inspection_status' => 'Inspected', 'count' => $stats['inspected_count']],
    ['inspection_status' => 'Pending', 'count' => $stats['pending_count']],
];

// Get inspection question stats for dashboard
$question_stats = $db->fetchAll(
    "SELECT 
        SUM(CASE WHEN latest.q1_safety_pin = 'Yes' THEN 1 ELSE 0 END) as q1_yes,
        SUM(CASE WHEN latest.q1_safety_pin = 'No' THEN 1 ELSE 0 END) as q1_no,
        SUM(CASE WHEN latest.q2_gauge_green = 'Yes' THEN 1 ELSE 0 END) as q2_yes,
        SUM(CASE WHEN latest.q2_gauge_green = 'No' THEN 1 ELSE 0 END) as q2_no,
        SUM(CASE WHEN latest.q3_weight_appropriate = 'Yes' THEN 1 ELSE 0 END) as q3_yes,
        SUM(CASE WHEN latest.q3_weight_appropriate = 'No' THEN 1 ELSE 0 END) as q3_no,
        SUM(CASE WHEN latest.q4_no_damage = 'Yes' THEN 1 ELSE 0 END) as q4_yes,
        SUM(CASE WHEN latest.q4_no_damage = 'No' THEN 1 ELSE 0 END) as q4_no,
        SUM(CASE WHEN latest.q5_hanging_clip = 'Yes' THEN 1 ELSE 0 END) as q5_yes,
        SUM(CASE WHEN latest.q5_hanging_clip = 'No' THEN 1 ELSE 0 END) as q5_no,
        SUM(CASE WHEN latest.q6_accessible = 'Yes' THEN 1 ELSE 0 END) as q6_yes,
        SUM(CASE WHEN latest.q6_accessible = 'No' THEN 1 ELSE 0 END) as q6_no,
        SUM(CASE WHEN latest.q7_refill_overdue = 'No' THEN 1 ELSE 0 END) as q7_yes,
        SUM(CASE WHEN latest.q7_refill_overdue = 'Yes' THEN 1 ELSE 0 END) as q7_no,
        SUM(CASE WHEN latest.q8_instructions_visible = 'Yes' THEN 1 ELSE 0 END) as q8_yes,
        SUM(CASE WHEN latest.q8_instructions_visible = 'No' THEN 1 ELSE 0 END) as q8_no,
        COUNT(*) as total
    FROM (
        SELECT i.*,
               ROW_NUMBER() OVER (PARTITION BY i.equipment_id ORDER BY i.submitted_at DESC) as rn
        FROM inspections i
    ) latest
    WHERE latest.rn = 1"
);

// Get detailed equipment data for each question - ADD THIS NEW SECTION
$question_equipment_details = [];

// Initialize the structure
$questions_list = ['q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8'];
foreach ($questions_list as $q) {
    $question_equipment_details[$q] = ['yes' => [], 'no' => []];
}

// Get latest inspection for each equipment with equipment details
$latest_inspections_with_equipment = $db->fetchAll(
    "SELECT 
        i.*,
        e.equipment_id as eq_id,
        e.type,
        e.location,
        e.area
    FROM (
        SELECT *,
               ROW_NUMBER() OVER (PARTITION BY equipment_id ORDER BY submitted_at DESC) as rn
        FROM inspections
    ) i
    JOIN equipments e ON i.equipment_id = e.equipment_id
    WHERE i.rn = 1"
);

// Process each inspection and categorize equipment by question responses
foreach ($latest_inspections_with_equipment as $inspection) {
    $equipment_info = [
        'id' => $inspection['equipment_id'],
        'type' => $inspection['type'] ?: 'N/A',
        'location' => $inspection['location'] ?: 'N/A',
        'area' => $inspection['area'] ?: 'N/A',
        'inspector' => $inspection['inspector_name'],
        'date' => date('M j, Y', strtotime($inspection['submitted_at']))
    ];
    
    // Question 1: Safety Pin
    if ($inspection['q1_safety_pin'] === 'Yes') {
        $question_equipment_details['q1']['yes'][] = $equipment_info;
    } else {
        $question_equipment_details['q1']['no'][] = $equipment_info;
    }
    
    // Question 2: Gauge Green
    if ($inspection['q2_gauge_green'] === 'Yes') {
        $question_equipment_details['q2']['yes'][] = $equipment_info;
    } else {
        $question_equipment_details['q2']['no'][] = $equipment_info;
    }
    
    // Question 3: Weight Appropriate
    if ($inspection['q3_weight_appropriate'] === 'Yes') {
        $question_equipment_details['q3']['yes'][] = $equipment_info;
    } else {
        $question_equipment_details['q3']['no'][] = $equipment_info;
    }
    
    // Question 4: No Damage
    if ($inspection['q4_no_damage'] === 'Yes') {
        $question_equipment_details['q4']['yes'][] = $equipment_info;
    } else {
        $question_equipment_details['q4']['no'][] = $equipment_info;
    }
    
    // Question 5: Hanging Clip
    if ($inspection['q5_hanging_clip'] === 'Yes') {
        $question_equipment_details['q5']['yes'][] = $equipment_info;
    } else {
        $question_equipment_details['q5']['no'][] = $equipment_info;
    }
    
    // Question 6: Accessible
    if ($inspection['q6_accessible'] === 'Yes') {
        $question_equipment_details['q6']['yes'][] = $equipment_info;
    } else {
        $question_equipment_details['q6']['no'][] = $equipment_info;
    }
    
    // Question 7: Refill Not Overdue (Note: reversed logic)
    if ($inspection['q7_refill_overdue'] === 'No') {
        $question_equipment_details['q7']['yes'][] = $equipment_info;
    } else {
        $question_equipment_details['q7']['no'][] = $equipment_info;
    }
    
    // Question 8: Instructions Visible
    if ($inspection['q8_instructions_visible'] === 'Yes') {
        $question_equipment_details['q8']['yes'][] = $equipment_info;
    } else {
        $question_equipment_details['q8']['no'][] = $equipment_info;
    }
}

// Calculate most common problem
$most_common_problem = 'None';
$max_no_count = 0;
if (!empty($question_stats)) {
    $problems = [
        'Safety Pin Issues' => intval($question_stats[0]['q1_no']) ?? 0,
        'Gauge Not Green' => intval($question_stats[0]['q2_no']) ?? 0,
        'Weight Issues' => intval($question_stats[0]['q3_no']) ?? 0,
        'Damage/Rust Found' => intval($question_stats[0]['q4_no']) ?? 0,
        'Hanging Clip Issues' => intval($question_stats[0]['q5_no']) ?? 0,
        'Accessibility Issues' => intval($question_stats[0]['q6_no']) ?? 0,
        'Refill Overdue' => intval($question_stats[0]['q7_no']) ?? 0,
        'Instructions Not Visible' => intval($question_stats[0]['q8_no']) ?? 0
    ];
    
    $max_no_count = 0;
    $most_common_problem = 'None';

    foreach ($problems as $problem_name => $count) {
        if ($count > $max_no_count) {
            $max_no_count = $count;
            $most_common_problem = $problem_name;
        }
    }
}

// Get 5 most recent inspections with joined info
$recent_inspections = $db->fetchAll(
    "SELECT i.*, e.type, e.location FROM inspections i 
     JOIN equipments e ON i.equipment_id = e.equipment_id 
     ORDER BY i.submitted_at DESC LIMIT 5"
);

$total_inspections = count($db->fetchAll("SELECT * FROM inspections"));
$current_percentage = $stats['total_equipment'] > 0 ? round(($stats['current_count'] / $stats['total_equipment']) * 100, 1) : 0;
$inspected_percentage = $stats['total_equipment'] > 0 ? round(($stats['inspected_count'] / $stats['total_equipment']) * 100, 1) : 0;

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Equipment Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            height: auto; /* Ensure cards take full height of their column */
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: var(--white);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }
        
        /* Chart container styles */
        .chart-container {
            position: relative;
            height: 300px; /* Consistent height for individual question charts */
            width: 100%;
        }
        
        .main-chart-container {
            position: relative;
            height: 400px; /* Consistent height for main dashboard charts */
            width: 100%;
        }
        
        /* Most common problem card */
        .problem-card {
            background: linear-gradient(135deg, #f72585, #b5179e);
            color: white;
            text-align: center;
            padding: 87px;
            border-radius: 10px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .problem-value {
            font-size: 3rem;
            font-weight: 700;
            margin: 15px 0;
        }
        
        .problem-label {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .problem-description {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .equipment-details {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .detail-label {
            font-weight: 500;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }

        .shopify-stats-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .stat-item {
            display: flex;
            align-items: flex-start;
            padding: 16px;
            border-radius: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .stat-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 40px;
        }
        
        .stat-main-line {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 4px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            line-height: 1;
            margin: 0;
            color: #1f2937;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6b7280;
            margin: 0;
            font-weight: 500;
            line-height: 1;
        }
        
        .stat-change {
            font-size: 11px;
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-change.positive {
            color: #10b981;
        }
        
        .stat-change.negative {
            color: #ef4444;
        }
        
        /* Individual stat item colors - Shopify style */
        .stat-total { background-color: #f8fafc; }
        .stat-total .stat-icon { background-color: #3b82f6; }
        
        .stat-current { background-color: #ecfdf5; }
        .stat-current .stat-icon { background-color: #10b981; }
        
        .stat-due-soon { background-color: #fffbeb; }
        .stat-due-soon .stat-icon { background-color: #f59e0b; }
        
        .stat-overdue { background-color: #fef2f2; }
        .stat-overdue .stat-icon { background-color: #ef4444; }
        
        .stat-inspected { background-color: #f0f9ff; }
        .stat-inspected .stat-icon { background-color: #0ea5e9; }
        
        .stat-pending { background-color: #fafafa; }
        .stat-pending .stat-icon { background-color: #6b7280; }
        
        /* Custom scrollbar styling */
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

        /*.stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            color: var(--white);
            margin-bottom: 20px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 600;
            margin: 10px 0;
        }
        
        .stat-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }*/
        
        .btn-action {
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 15px;
            margin-right: 5px;
        }
        
        .table th {
            font-weight: 600;
            color: var(--gray);
            border-top: none;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .badge {
            font-weight: 500;
            padding: 5px 10px;
        }
        
        /* Equal height cards */
        .row-equal-height {
            display: flex;
            flex-wrap: wrap;
        }
        
        .row-equal-height > [class*='col-'] {
            display: flex;
            flex-direction: column;
        }
        
        /* Question chart card specific styles */
        .question-chart-card {
            margin-bottom: 1.5rem;
        }
        
        .question-chart-card .card-body {
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .content-wrapper {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            margin: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
    /* Add these styles to your existing CSS */
    .equipment-list {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 10px;
        background-color: #f8f9fa;
    }
    
    .equipment-item {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 5px;
        padding: 8px 12px;
        margin-bottom: 8px;
        transition: all 0.2s ease;
    }
    
    .equipment-item:hover {
        border-color: #007bff;
        box-shadow: 0 2px 4px rgba(0,123,255,0.1);
        transform: translateY(-1px);
    }
    
    .equipment-id {
        font-weight: 600;
        color: #007bff;
        text-decoration: none;
    }
    
    .equipment-id:hover {
        color: #0056b3;
        text-decoration: underline;
    }
    
    .equipment-meta {
        font-size: 0.85em;
        color: #6c757d;
        margin-top: 4px;
    }
    
    .equipment-meta span {
        margin-right: 10px;
    }
    
    /* Make charts clickable */
    .question-chart-card .card {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .question-chart-card .card:hover {
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    }
    
    /* Add click indicator */
    .chart-clickable {
        position: relative;
    }
    
    .chart-clickable::after {
        content: '\f05a';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        top: 10px;
        right: 10px;
        color: #007bff;
        font-size: 14px;
        opacity: 0.7;
    }
    /* Light toggle switch styling for better visibility */
    .form-switch .form-check-input {
        background-color: #5e6165ff !important; /* Light gray when OFF */
        border-color: #53575aff !important; /* Light border when OFF */
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e") !important;
    }

    .form-switch .form-check-input:checked {
        background-color: #0d6efd !important; /* Blue when ON */
        border-color: #0a58ca !important; /* Darker blue border when ON */
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e") !important;
    }

    .form-switch .form-check-input:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }

    /* Make sure the toggle actually moves */
    .form-switch .form-check-input:checked {
        background-position: right center !important;
    }

    .form-switch .form-check-input {
        background-position: left center !important;
    }


    </style>
</head>
<body>
<!-- Navigation - Updated with Staff Access Control -->
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
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Staff Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add-equipment.php">
                            <i class="fas fa-plus me-1"></i> Add Equipment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="inspection-history.php">
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
                    <!--<li class="nav-item">
                        <a class="nav-link" href="#" onclick="showStaffInfo()">
                            <i class="fas fa-info-circle me-1"></i> About
                        </a>
                    </li>-->
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if ($equipment): ?>
            
             <!-- Staff Notice for Individual Equipment View -->
            <div class="alert alert-info" role="alert">
                <i class="fas fa-user-tie me-2"></i>
                <strong>Staff View:</strong> You are viewing detailed equipment information with full management access.
                <a href="view-equipment.php?id=<?php echo $equipment['equipment_id']; ?>" target="_blank" class="alert-link ms-2">
                    <i class="fas fa-external-link-alt"></i> View Inspector Interface
                </a>
            </div>
            
            <!-- Individual Equipment View -->
            <div class="row">
                <div class="col-md-12">
                    <div class="equipment-details">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3><i class="fas fa-cogs text-primary me-2"></i> Equipment Manager</h3>
                            <div>
                                <a href="edit-equipment.php?id=<?php echo $equipment['equipment_id']; ?>" class="btn btn-action btn-warning">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <button class="btn btn-action btn-danger" onclick="deleteEquipment('<?php echo $equipment['equipment_id']; ?>')">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                                <!--<a href="generate-qr.php?id=<?php echo $equipment['equipment_id']; ?>" class="btn btn-action btn-info">
                                    <i class="fas fa-qrcode me-1"></i> Generate QR
                                </a>-->
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-label">Equipment ID</div>
                                <div class="detail-value"><?php echo $equipment['equipment_id']; ?></div>
                                
                                <div class="detail-label">Type</div>
                                <div class="detail-value"><?php echo $equipment['type'] ?: 'Not specified'; ?></div>
                                
                                <div class="detail-label">Location</div>
                                <div class="detail-value"><?php echo $equipment['location'] ?: 'Not specified'; ?></div>
                                
                                <div class="detail-label">Area</div>
                                <div class="detail-value"><?php echo $equipment['area'] ?: 'Not specified'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label">Inspection Frequency</div>
                                <div class="detail-value"><?php echo $equipment['frequency']; ?></div>
                                
                                <div class="detail-label">Due Date</div>
                                <div class="detail-value">
                                    <span class="<?php 
                                        if (isOverdue($equipment['due_date'])) echo 'badge bg-danger';
                                        elseif (isDueSoon($equipment['due_date'])) echo 'badge bg-warning';
                                        else echo 'badge bg-success';
                                    ?>">
                                        <?php echo formatDate($equipment['due_date']); ?>
                                        <?php if (isOverdue($equipment['due_date'])): ?>
                                            <i class="fas fa-exclamation-triangle ms-1"></i> OVERDUE
                                        <?php elseif (isDueSoon($equipment['due_date'])): ?>
                                            <i class="fas fa-clock ms-1"></i> DUE SOON
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="detail-label">Added On</div>
                                <div class="detail-value"><?php echo formatDate($equipment['created_at']); ?></div>
                                
                                <div class="detail-label">Last Updated</div>
                                <div class="detail-value"><?php echo formatDate($equipment['updated_at']); ?></div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="login.php?equipment_id=<?php echo $equipment['equipment_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-clipboard-check me-1"></i> Start Inspection
                            </a>
                            <a href="generate-qr.php?id=<?php echo $equipment['equipment_id']; ?>" class="btn btn-info">
                                <i class="fas fa-qrcode me-1"></i> Generate QR Code
                            </a>
                        </div>
                    </div>
                    
                    <!-- Recent Inspections for specific equipment -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i> Recent Inspections
                                <a href="inspection-history.php" class="btn btn-sm btn-outline-primary ms-2">
                                    <i class="fas fa-list me-1"></i> View Full History
                                </a>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Inspector</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $recent_equipment_inspections = $db->fetchAll(
                                            "SELECT * FROM inspections 
                                             WHERE equipment_id = ? 
                                             ORDER BY submitted_at DESC LIMIT 5",
                                            [$equipment_id]
                                        );
                                        
                                        if (empty($recent_equipment_inspections)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No recent inspections for this equipment.</td>
                                            </tr>
                                        <?php else:
                                            foreach ($recent_equipment_inspections as $inspection): 
                                                $score = 0;
                                                $questions = [
                                                    $inspection['q1_safety_pin'] === 'Yes' ? 1 : 0,
                                                    $inspection['q2_gauge_green'] === 'Yes' ? 1 : 0,
                                                    $inspection['q3_weight_appropriate'] === 'Yes' ? 1 : 0,
                                                    $inspection['q4_no_damage'] === 'Yes' ? 1 : 0,
                                                    $inspection['q5_hanging_clip'] === 'Yes' ? 1 : 0,
                                                    $inspection['q6_accessible'] === 'Yes' ? 1 : 0,
                                                    $inspection['q7_refill_overdue'] === 'No' ? 1 : 0, // Reverse logic
                                                    $inspection['q8_instructions_visible'] === 'Yes' ? 1 : 0
                                                ];
                                                $score = array_sum($questions);
                                                $percentage = round(($score / 8) * 100);
                                            ?>
                                                <tr>
                                                    <td><?php echo formatDate($inspection['submitted_at']); ?></td>
                                                    <td><?php echo $inspection['inspector_name']; ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar <?php echo $percentage >= 80 ? 'bg-success' : ($percentage >= 60 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                                 role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                                                 aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                                <?php echo $percentage; ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $percentage >= 80 ? 'bg-success' : ($percentage >= 60 ? 'bg-warning' : 'bg-danger'); ?>">
                                                            <?php echo $percentage >= 80 ? 'Good' : ($percentage >= 60 ? 'Fair' : 'Poor'); ?>
                                                        </span>
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
            </div>

            <!-- Equipment List (always shown, regardless of individual equipment view) -->
             
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i> All Equipment</h5>
                    
                    <div class="input-group" style="max-width: 300px;">
                        <input type="text" id="equipmentSearch" class="form-control" placeholder="Search equipment...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="equipmentTable">
                            <thead>
                                <tr>
                                    <th>Equipment ID</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Area</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Inspection</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_equipment)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No equipment found. Please add new equipment.</td>
                                    </tr>
                                <?php else:
                                    foreach ($all_equipment as $eq): ?>
                                    <tr>
                                        <td><a href="?id=<?php echo $eq['equipment_id']; ?>"><?php echo $eq['equipment_id']; ?></a></td>
                                        <td><?php echo $eq['type'] ?: '-'; ?></td>
                                        <td><?php echo $eq['location'] ?: '-'; ?></td>
                                        <td><?php echo $eq['area'] ?: '-'; ?></td>
                                        <td><?php echo formatDate($eq['due_date']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $eq['status'] === 'Overdue' ? 'bg-danger' : 
                                                    ($eq['status'] === 'Due Soon' ? 'bg-warning' : 'bg-success'); 
                                            ?>">
                                                <?php echo $eq['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $eq['inspection_status'] === 'Inspected' ? 'bg-success' : 'bg-secondary'; 
                                            ?>">
                                                <?php echo $eq['inspection_status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?id=<?php echo $eq['equipment_id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="login.php?equipment_id=<?php echo $eq['equipment_id']; ?>" class="btn btn-sm btn-outline-success" title="Inspect">
                                                <i class="fas fa-clipboard-check"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; 
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Dashboard View -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-tachometer-alt text-primary me-2"></i> Staff Dashboard</h2>
                    <p class="text-muted mb-0">Complete equipment management and analytics</p>
                </div>
                <div class="btn-group">
                    <a href="add-equipment.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Add Equipment
                    </a>
                    <!--<a href="view-equipment.php" target="_blank" class="btn btn-outline-info">
                        <i class="fas fa-external-link-alt me-1"></i> Inspector Interface
                    </a>-->
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="shopify-stats-container">
                        <div class="stats-grid">
                            <div class="stat-item stat-current">
                                <div class="stat-icon">
                                    <i class="fas fa-boxes"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-main-line">
                                        <p class="stat-value"><?php echo $stats['current_count'] ?? 0; ?></p>
                                        <p class="stat-label">Current</p>
                                    </div>
                                    <p class="stat-change positive">+<?php echo $current_percentage; ?>%</p>
                                </div>
                            </div>
                            
                            <div class="stat-item stat-due-soon">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-main-line">
                                        <p class="stat-value"><?php echo $stats['due_soon_count'] ?? 0; ?></p>
                                        <p class="stat-label">Due Soon</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stat-item stat-overdue">
                                <div class="stat-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-main-line">
                                        <p class="stat-value"><?php echo $stats['overdue_count'] ?? 0; ?></p>
                                        <p class="stat-label">Overdue</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stat-item stat-inspected">
                                <div class="stat-icon">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-main-line">
                                        <p class="stat-value"><?php echo $stats['inspected_count'] ?? 0; ?></p>
                                        <p class="stat-label">Inspected</p>
                                    </div>
                                    <p class="stat-change positive">+<?php echo $inspected_percentage; ?>%</p>
                                </div>
                            </div>
                            
                            <div class="stat-item stat-pending">
                                <div class="stat-icon">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-main-line">
                                        <p class="stat-value"><?php echo $stats['pending_count'] ?? 0; ?></p>
                                        <p class="stat-label">Pending</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row - Fixed to 3 columns with equal heights -->
                    <div class="row mb-4 row-equal-height">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Equipment Status</h5>
                                </div>
                                <div class="card-body d-flex align-items-center justify-content-center">
                                    <canvas id="statusChart" class="main-chart-container"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Inspection Status</h5>
                                </div>
                                <div class="card-body d-flex align-items-center justify-content-center">
                                    <canvas id="inspectionChart" class="main-chart-container"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i> Most Commonly Selected Problem</h5>
                                </div>
                                <div class="card-body d-flex align-items-center justify-content-center">
                                    <div class="problem-card w-100">
                                        <i class="fas fa-chart-line fa-2x mb-2"></i>
                                        <div class="problem-value"><?php echo $max_no_count; ?></div>
                                        <div class="problem-label"><?php echo $most_common_problem; ?></div>
                                        <div class="problem-description">times reported 'No'</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Individual Question Charts - 3 per row with equal heights -->
                    <div class="row mb-4 row-equal-height">
                        <?php
                        $questions = [
                            'q1' => ['icon' => 'shield-alt', 'text' => '1. Safety Pin Intact'],
                            'q2' => ['icon' => 'gauge-high', 'text' => '2. Gauge at Green Level'],
                            'q3' => ['icon' => 'weight-scale', 'text' => '3. Weight Appropriate'],
                            'q4' => ['icon' => 'tools', 'text' => '4. No Damage/Rust'],
                            'q5' => ['icon' => 'paperclip', 'text' => '5. Hanging Clip Intact'],
                            'q6' => ['icon' => 'hand', 'text' => '6. Easily Accessible'],
                            'q7' => ['icon' => 'clock', 'text' => '7. Refill Not Overdue'],
                            'q8' => ['icon' => 'eye', 'text' => '8. Instructions Visible']
                        ];
                        
                        foreach ($questions as $key => $question): ?>
                        <div class="col-lg-4 col-md-6 question-chart-card">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="fas fa-<?= $question['icon'] ?> me-2"></i> <?= $question['text'] ?></h6>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input chart-toggle" type="checkbox" data-chart-id="<?= $key ?>Chart" data-question-id="<?= $key ?>">
                                        <label class="form-check-label small">Bar</label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <canvas id="<?= $key ?>Chart" class="chart-container"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <!-- Summary Chart Row -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Question Response Summary</h5>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="summaryChartToggle">
                                <label class="form-check-label" for="summaryChartToggle">
                                    <span id="summaryToggleLabel">Show No Responses</span>
                                </label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="position: relative; height: 400px; width: 100%;">
                                <canvas id="summaryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Recent Inspections -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Inspections</h5>
                    <a href="inspection-history.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-list me-1"></i> View All History
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Equipment ID</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Inspector</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_inspections)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No recent inspections found.</td>
                                    </tr>
                                <?php else:
                                    foreach ($recent_inspections as $inspection): 
                                        $score = 0;
                                        $questions = [
                                            $inspection['q1_safety_pin'] === 'Yes' ? 1 : 0,
                                            $inspection['q2_gauge_green'] === 'Yes' ? 1 : 0,
                                            $inspection['q3_weight_appropriate'] === 'Yes' ? 1 : 0,
                                            $inspection['q4_no_damage'] === 'Yes' ? 1 : 0,
                                            $inspection['q5_hanging_clip'] === 'Yes' ? 1 : 0,
                                            $inspection['q6_accessible'] === 'Yes' ? 1 : 0,
                                            $inspection['q7_refill_overdue'] === 'No' ? 1 : 0, // Reverse logic
                                            $inspection['q8_instructions_visible'] === 'Yes' ? 1 : 0
                                        ];
                                        $score = array_sum($questions);
                                        $percentage = round(($score / 8) * 100);
                                    ?>
                                        <tr>
                                            <td><?php echo formatDate($inspection['submitted_at']); ?></td>
                                            <td><a href="?id=<?php echo $inspection['equipment_id']; ?>"><?php echo $inspection['equipment_id']; ?></a></td>
                                            <td><?php echo $inspection['type'] ?: '-'; ?></td>
                                            <td><?php echo $inspection['location'] ?: '-'; ?></td>
                                            <td><?php echo $inspection['inspector_name']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?php echo $percentage >= 80 ? 'bg-success' : ($percentage >= 60 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                         role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                                         aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $percentage; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $percentage >= 80 ? 'bg-success' : ($percentage >= 60 ? 'bg-warning' : 'bg-danger'); ?>">
                                                    <?php echo $percentage >= 80 ? 'Good' : ($percentage >= 60 ? 'Fair' : 'Poor'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; 
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- All Equipment List (always shown on dashboard) -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i> All Equipment</h5>
                    <div class="input-group" style="max-width: 300px;">
                        <input type="text" id="equipmentSearch" class="form-control" placeholder="Search equipment...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="equipmentTable">
                            <thead>
                                <tr>
                                    <th>Equipment ID</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Area</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Inspection</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_equipment)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No equipment found. Please add new equipment.</td>
                                    </tr>
                                <?php else:
                                    foreach ($all_equipment as $eq): ?>
                                    <tr>
                                        <td><a href="?id=<?php echo $eq['equipment_id']; ?>"><?php echo $eq['equipment_id']; ?></a></td>
                                        <td><?php echo $eq['type'] ?: '-'; ?></td>
                                        <td><?php echo $eq['location'] ?: '-'; ?></td>
                                        <td><?php echo $eq['area'] ?: '-'; ?></td>
                                        <td><?php echo formatDate($eq['due_date']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $eq['status'] === 'Overdue' ? 'bg-danger' : 
                                                    ($eq['status'] === 'Due Soon' ? 'bg-warning' : 'bg-success'); 
                                            ?>">
                                                <?php echo $eq['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $eq['inspection_status'] === 'Inspected' ? 'bg-success' : 'bg-secondary'; 
                                            ?>">
                                                <?php echo $eq['inspection_status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?id=<?php echo $eq['equipment_id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="login.php?equipment_id=<?php echo $eq['equipment_id']; ?>" class="btn btn-sm btn-outline-success" title="Inspect">
                                                <i class="fas fa-clipboard-check"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; 
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Global variables to store charts and data
    let chartInstances = {};
    let chartInitialized = false;
    
    // Question data with proper fallbacks
    const questionStats = <?php echo json_encode($question_stats[0] ?? []); ?>;
    
    // Equipment details for each question
    const questionEquipmentDetails = <?php echo json_encode($question_equipment_details); ?>;
    
    const questionData = {
        q1: { yes: parseInt(questionStats.q1_yes || 0), no: parseInt(questionStats.q1_no || 0) },
        q2: { yes: parseInt(questionStats.q2_yes || 0), no: parseInt(questionStats.q2_no || 0) },
        q3: { yes: parseInt(questionStats.q3_yes || 0), no: parseInt(questionStats.q3_no || 0) },
        q4: { yes: parseInt(questionStats.q4_yes || 0), no: parseInt(questionStats.q4_no || 0) },
        q5: { yes: parseInt(questionStats.q5_yes || 0), no: parseInt(questionStats.q5_no || 0) },
        q6: { yes: parseInt(questionStats.q6_yes || 0), no: parseInt(questionStats.q6_no || 0) },
        q7: { yes: parseInt(questionStats.q7_yes || 0), no: parseInt(questionStats.q7_no || 0) },
        q8: { yes: parseInt(questionStats.q8_yes || 0), no: parseInt(questionStats.q8_no || 0) }
    };

    // Question titles for modal display
    const questionTitles = {
        q1: 'Safety Pin Intact',
        q2: 'Gauge at Green Level', 
        q3: 'Weight Appropriate',
        q4: 'No Damage/Rust',
        q5: 'Hanging Clip Intact',
        q6: 'Easily Accessible',
        q7: 'Refill Not Overdue',
        q8: 'Instructions Visible'
    };

    // Safely destroy a chart
    function destroyChart(chartId) {
        if (chartInstances[chartId]) {
            try {
                chartInstances[chartId].destroy();
            } catch (e) {
                console.warn(`Error destroying chart ${chartId}:`, e);
            }
            delete chartInstances[chartId];
        }
    }
    // Create summary chart with toggle functionality - ADD THIS HERE
    function createSummaryChart(showNo = false) {
        console.log('createSummaryChart called with showNo:', showNo); // Debug line
        
        const summaryLabels = [
            'Q1: Safety Pin Intact',
            'Q2: Gauge at Green Level', 
            'Q3: Weight Appropriate',
            'Q4: No Damage/Rust',
            'Q5: Hanging Clip Intact',
            'Q6: Easily Accessible',
            'Q7: Refill Not Overdue',
            'Q8: Instructions Visible'
        ];
        
        const yesData = [
            questionData.q1.yes,
            questionData.q2.yes,
            questionData.q3.yes,
            questionData.q4.yes,
            questionData.q5.yes,
            questionData.q6.yes,
            questionData.q7.yes,
            questionData.q8.yes
        ];
        
        const noData = [
            questionData.q1.no,
            questionData.q2.no,
            questionData.q3.no,
            questionData.q4.no,
            questionData.q5.no,
            questionData.q6.no,
            questionData.q7.no,
            questionData.q8.no
        ];
        
        const totalData = [
            questionData.q1.yes + questionData.q1.no,
            questionData.q2.yes + questionData.q2.no,
            questionData.q3.yes + questionData.q3.no,
            questionData.q4.yes + questionData.q4.no,
            questionData.q5.yes + questionData.q5.no,
            questionData.q6.yes + questionData.q6.no,
            questionData.q7.yes + questionData.q7.no,
            questionData.q8.yes + questionData.q8.no
        ];

        const primaryData = showNo ? noData : yesData;
        const primaryLabel = showNo ? 'No Responses' : 'Yes Responses';
        const primaryColor = showNo ? '#dc3545' : '#28a745';
        const primaryBorderColor = showNo ? '#c82333' : '#1e7e34';

        console.log('Primary data:', primaryData, 'Label:', primaryLabel); // Debug line

        createChart('summaryChart', {
            labels: summaryLabels,
            datasets: [
                {
                    label: primaryLabel,
                    data: primaryData,
                    backgroundColor: primaryColor,
                    borderColor: primaryBorderColor,
                    borderWidth: 1
                },
                {
                    label: 'Total Responses',
                    data: totalData,
                    backgroundColor: '#c7ccd3ff',
                    borderColor: '#8f9397ff',
                    borderWidth: 0.5,
                    type: 'bar'
                }
            ]
        }, 'horizontalBar');
    }

    // Create or update a chart with click functionality
    function createChart(canvasId, data, type = 'pie', title = '') {
        try {
            const canvas = document.getElementById(canvasId);
            if (!canvas) {
                console.error(`Canvas ${canvasId} not found`);
                return;
            }

            // Destroy existing chart before creating a new one
            destroyChart(canvasId);

            const ctx = canvas.getContext('2d');
            if (!ctx) {
                console.error(`Could not get 2D context for ${canvasId}`);
                return;
            }

            let chartConfig;

            // Determine chart configuration based on type and data structure
            if (canvasId === 'statusChart' || canvasId === 'inspectionChart') {
                // Main dashboard charts (no click functionality)
                chartConfig = {
                    type: type,
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: !!title,
                                text: title,
                                font: { size: 16, weight: 'bold' }
                            },
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || context.dataset.label || '';
                                        const value = context.raw || 0;
                                        let total;
                                        if (context.chart.config.type === 'pie') {
                                            total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        } else {
                                            total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                        }
                                        const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        scales: type === 'bar' ? {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        } : undefined
                    }
                };
            } else if (type === 'horizontalBar') {
            // Summary horizontal bar chart
            chartConfig = {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', // This makes it horizontal
                    plugins: {
                        title: {
                            display: !!title,
                            text: title,
                            font: { size: 16, weight: 'bold' }
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.raw || 0;
                                    const dataIndex = context.dataIndex;
                                    
                                    if (label === 'Yes Responses') {
                                        const total = context.chart.data.datasets[1].data[dataIndex];
                                        const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                        return `${label}: ${value}/${total} (${percentage}%)`;
                                    } else {
                                        return `${label}: ${value}`;
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        y: {
                            ticks: {
                                maxRotation: 0,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            };
            } else if (type === 'pie') {
                // Individual question charts (pie type) with click functionality
                chartConfig = {
                    type: 'pie',
                    data: {
                        labels: data.labels || ['Yes', 'No'],
                        datasets: [{
                            data: data.values || [data.yes || 0, data.no || 0],
                            backgroundColor: data.colors || ['#28a745', '#dc3545'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: !!title,
                                text: title,
                                font: { size: 14, weight: 'bold' }
                            },
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                        return `${label}: ${value} (${percentage}%) - Click to view equipment`;
                                    }
                                }
                            }
                        },
                        onHover: (event, activeElements) => {
                            event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                        },
                        onClick: (event, activeElements) => {
                            if (activeElements.length > 0) {
                                const questionId = canvasId.replace('Chart', '');
                                const dataIndex = activeElements[0].index;
                                const category = dataIndex === 0 ? 'yes' : 'no';
                                showEquipmentDetails(questionId, category);
                            }
                        }
                    }
                };
            } else if (type === 'bar') {
                // Individual question charts (bar type) with click functionality
                chartConfig = {
                    type: 'bar',
                    data: {
                        labels: ['Results'],
                        datasets: [
                            {
                                label: 'Yes',
                                data: [data.yes || 0],
                                backgroundColor: '#28a745',
                                borderColor: '#1e7e34',
                                borderWidth: 1
                            },
                            {
                                label: 'No', 
                                data: [data.no || 0],
                                backgroundColor: '#dc3545',
                                borderColor: '#c82333',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: !!title,
                                text: title,
                                font: { size: 14, weight: 'bold' }
                            },
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.dataset.label || '';
                                        const value = context.raw || 0;
                                        return `${label}: ${value} - Click to view equipment`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        onHover: (event, activeElements) => {
                            event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                        },
                        onClick: (event, activeElements) => {
                            if (activeElements.length > 0) {
                                const questionId = canvasId.replace('Chart', '');
                                const datasetIndex = activeElements[0].datasetIndex;
                                const category = datasetIndex === 0 ? 'yes' : 'no';
                                showEquipmentDetails(questionId, category);
                            }
                        }
                    }
                };
            }

            if (chartConfig) {
                chartInstances[canvasId] = new Chart(ctx, chartConfig);
                
                // Add click indicator for question charts
                if (canvasId !== 'statusChart' && canvasId !== 'inspectionChart') {
                    canvas.closest('.card').classList.add('chart-clickable');
                }
            } else {
                console.error(`Failed to determine chart configuration for ${canvasId} with type ${type}`);
            }
            
        } catch (error) {
            console.error(`Error creating chart ${canvasId}:`, error);
        }
    }

    // Show equipment details in modal
    function showEquipmentDetails(questionId, category) {
        try {
            const questionTitle = questionTitles[questionId] || questionId;
            const modalTitle = `${questionTitle} - Equipment Details`;
            
            // Update modal title
            document.getElementById('equipmentDetailsModalLabel').innerHTML = 
                `<i class="fas fa-list me-2"></i>${modalTitle}`;
            
            // Get equipment data
            const yesEquipment = questionEquipmentDetails[questionId]?.yes || [];
            const noEquipment = questionEquipmentDetails[questionId]?.no || [];
            
            // Update counts
            document.getElementById('yesCount').textContent = yesEquipment.length;
            document.getElementById('noCount').textContent = noEquipment.length;
            
            // Populate equipment lists
            populateEquipmentList('yesEquipmentList', yesEquipment);
            populateEquipmentList('noEquipmentList', noEquipment);
            
            // Highlight the clicked category
            if (category === 'yes') {
                document.getElementById('yesEquipmentSection').classList.add('border', 'border-success', 'bg-light');
                document.getElementById('noEquipmentSection').classList.remove('border', 'border-danger', 'bg-light');
            } else {
                document.getElementById('noEquipmentSection').classList.add('border', 'border-danger', 'bg-light');
                document.getElementById('yesEquipmentSection').classList.remove('border', 'border-success', 'bg-light');
            }
            
            // Store current data for export
            window.currentEquipmentData = {
                questionTitle,
                questionId,
                yesEquipment,
                noEquipment,
                category
            };
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('equipmentDetailsModal'));
            modal.show();
            
        } catch (error) {
            console.error('Error showing equipment details:', error);
        }
    }

    // Populate equipment list in modal
    function populateEquipmentList(containerId, equipment) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        if (equipment.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3">No equipment in this category</div>';
            return;
        }
        
        const equipmentHtml = equipment.map(eq => `
            <div class="equipment-item">
                <a href="?id=${eq.id}" class="equipment-id">${eq.id}</a>
                <div class="equipment-meta">
                    <span><i class="fas fa-tag"></i> ${eq.type}</span>
                    <span><i class="fas fa-map-marker-alt"></i> ${eq.location}</span>
                    ${eq.area !== 'N/A' ? `<span><i class="fas fa-building"></i> ${eq.area}</span>` : ''}
                </div>
                <div class="equipment-meta">
                    <span><i class="fas fa-user"></i> ${eq.inspector}</span>
                    <span><i class="fas fa-calendar"></i> ${eq.date}</span>
                </div>
            </div>
        `).join('');
        
        container.innerHTML = equipmentHtml;
    }

    // Export equipment list functionality
    function exportEquipmentList() {
        if (!window.currentEquipmentData) return;
        
        const { questionTitle, yesEquipment, noEquipment, category } = window.currentEquipmentData;
        const timestamp = new Date().toISOString().slice(0, 16).replace('T', '_');
        
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += `Question: ${questionTitle}\n`;
        csvContent += `Export Date: ${new Date().toLocaleString()}\n\n`;
        
        // Export Yes responses
        csvContent += "YES RESPONSES\n";
        csvContent += "Equipment ID,Type,Location,Area,Inspector,Date\n";
        yesEquipment.forEach(eq => {
            csvContent += `"${eq.id}","${eq.type}","${eq.location}","${eq.area}","${eq.inspector}","${eq.date}"\n`;
        });
        
        csvContent += "\nNO RESPONSES\n";
        csvContent += "Equipment ID,Type,Location,Area,Inspector,Date\n";
        noEquipment.forEach(eq => {
            csvContent += `"${eq.id}","${eq.type}","${eq.location}","${eq.area}","${eq.inspector}","${eq.date}"\n`;
        });
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `equipment_${questionTitle.replace(/\s+/g, '_')}_${timestamp}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Initialize all charts
    function initializeCharts() {
        if (chartInitialized) return;

        try {
            // Status Chart
            createChart('statusChart', {
                labels: <?php echo json_encode(array_column($status_data, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($status_data, 'count')); ?>,
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            }, 'pie');

            // Inspection Chart
            createChart('inspectionChart', {
                labels: <?php echo json_encode(array_column($inspection_data, 'inspection_status')); ?>,
                datasets: [{
                    label: 'Count',
                    data: <?php echo json_encode(array_column($inspection_data, 'count')); ?>,
                    backgroundColor: ['#17a2b8', '#6c757d'],
                    borderWidth: 1
                }]
            }, 'bar');

            // Question Charts
            Object.entries(questionData).forEach(([questionId, data]) => {
                const chartId = `${questionId}Chart`;
                createChart(chartId, data, 'pie');
            });

            chartInitialized = true;
            createSummaryChart(false);
            
            // Summary Chart - Horizontal Bar Chart showing Yes counts
                
        } catch (error) {
            console.error('Error initializing charts:', error);
        }
    }

    // Setup chart toggles
    function setupChartToggles() {
        document.querySelectorAll('.chart-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                try {
                    const chartId = this.getAttribute('data-chart-id');
                    const questionId = this.getAttribute('data-question-id');
                    
                    if (questionId && questionData[questionId]) {
                        const data = questionData[questionId];
                        const chartType = this.checked ? 'bar' : 'pie';
                        createChart(chartId, data, chartType);
                    }
                } catch (error) {
                    console.error('Error handling chart toggle:', error);
                }
            });
        });
    }

    // Setup summary chart toggle
    function setupSummaryToggle() {
        const toggle = document.getElementById('summaryChartToggle');
        const label = document.getElementById('summaryToggleLabel');
        
        console.log('Setting up summary toggle...', toggle, label); // Debug line
        
        if (toggle && label) {
            toggle.addEventListener('change', function() {
                console.log('Toggle changed, checked:', this.checked); // Debug line
                try {
                    const showNo = this.checked;
                    label.textContent = showNo ? 'Show Yes Responses' : 'Show No Responses';
                    console.log('About to call createSummaryChart with showNo:', showNo); // Debug line
                    createSummaryChart(showNo);
                } catch (error) {
                    console.error('Error handling summary chart toggle:', error);
                }
            });
        } else {
            console.error('Toggle or label element not found!'); // Debug line
        }
}

    // Equipment search functionality
    function setupSearch() {
        const searchInput = document.getElementById('equipmentSearch');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                try {
                    const value = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#equipmentTable tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(value) ? '' : 'none';
                    });
                } catch (error) {
                    console.error('Error in search functionality:', error);
                }
            });
        }
    }

    // Setup export functionality
    function setupExportButton() {
        const exportBtn = document.getElementById('exportEquipmentBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', exportEquipmentList);
        }
    }

    // Delete Equipment Function
    function deleteEquipment(equipmentId) {
        try {
            if (confirm('Are you sure you want to delete this equipment? This action cannot be undone.')) {
                fetch('php/delete-equipment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({equipment_id: equipmentId})
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw new Error(err.message || 'Network response was not ok'); });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Equipment deleted successfully!');
                        window.location.href = 'index.php';
                    } else {
                        throw new Error(data.message || 'Unknown error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(`Error deleting equipment: ${error.message}`);
                });
            }
        } catch (error) {
            console.error('Error in deleteEquipment:', error);
            alert('An unexpected error occurred while initiating equipment deletion.');
        }
    }

    // Show overdue alert
    function showOverdueAlert() {
        <?php if ($equipment && function_exists('isOverdue') && isOverdue($equipment['due_date'])): ?>
            try {
                const alertHtml = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>WARNING:</strong> This equipment inspection is OVERDUE!<br>
                        Due Date: <?php echo formatDate($equipment['due_date']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                
                const container = document.querySelector('.container');
                if (container) {
                    const alertDiv = document.createElement('div');
                    alertDiv.innerHTML = alertHtml;
                    container.prepend(alertDiv);
                    
                    setTimeout(() => {
                        try {
                            const alertElement = alertDiv.querySelector('.alert');
                            if (alertElement) {
                                const bsAlert = bootstrap.Alert.getInstance(alertElement) || new bootstrap.Alert(alertElement);
                                bsAlert.close();
                            }
                        } catch (e) {
                            console.error('Error dismissing alert:', e);
                        }
                    }, 10000);
                }
            } catch (error) {
                console.error('Error showing overdue alert:', error);
            }
        <?php endif; ?>
    }

    // Initialize everything when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            initializeCharts();
            setupChartToggles();
            setupSummaryToggle();
            setupSearch();
            setupExportButton();
            showOverdueAlert();
        }, 100);
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        Object.keys(chartInstances).forEach(chartId => {
            destroyChart(chartId);
        });
    });
</script>
<!-- Equipment Details Modal -->
<div class="modal fade" id="equipmentDetailsModal" tabindex="-1" aria-labelledby="equipmentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="equipmentDetailsModalLabel">
                    <i class="fas fa-list me-2"></i>Equipment Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6" id="yesEquipmentSection">
                        <h6 class="text-success">
                            <i class="fas fa-check-circle me-2"></i>Yes Responses (<span id="yesCount">0</span>)
                        </h6>
                        <div id="yesEquipmentList" class="equipment-list">
                            <!-- Yes equipment will be loaded here -->
                        </div>
                    </div>
                    <div class="col-md-6" id="noEquipmentSection">
                        <h6 class="text-danger">
                            <i class="fas fa-times-circle me-2"></i>No Responses (<span id="noCount">0</span>)
                        </h6>
                        <div id="noEquipmentList" class="equipment-list">
                            <!-- No equipment will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="exportEquipmentBtn">
                    <i class="fas fa-download me-1"></i>Export List
                </button>
            </div>
        </div>
    </div>
</div>
</body>
</html>
