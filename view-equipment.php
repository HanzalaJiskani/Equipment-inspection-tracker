<?php
require_once 'config.php';

// Define helper functions if they don't exist
if (!function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (empty($date) || $date === '0000-00-00') {
            return 'N/A';
        }
        return date('M j, Y', strtotime($date));
    }
}

if (!function_exists('isOverdue')) {
    function isOverdue($date) {
        if (empty($date) || $date === '0000-00-00') {
            return false;
        }
        return strtotime($date) < strtotime('today');
    }
}

if (!function_exists('isDueSoon')) {
    function isDueSoon($date) {
        if (empty($date) || $date === '0000-00-00') {
            return false;
        }
        $dueDate = strtotime($date);
        $today = strtotime('today');
        $oneWeekLater = strtotime('+1 week');
        return $dueDate >= $today && $dueDate <= $oneWeekLater;
    }
}

if (!function_exists('calculateInspectionScore')) {
    function calculateInspectionScore($inspection) {
        $questions = [
            isset($inspection['q1_safety_pin']) && $inspection['q1_safety_pin'] === 'Yes' ? 1 : 0,
            isset($inspection['q2_gauge_green']) && $inspection['q2_gauge_green'] === 'Yes' ? 1 : 0,
            isset($inspection['q3_weight_appropriate']) && $inspection['q3_weight_appropriate'] === 'Yes' ? 1 : 0,
            isset($inspection['q4_no_damage']) && $inspection['q4_no_damage'] === 'Yes' ? 1 : 0,
            isset($inspection['q5_hanging_clip']) && $inspection['q5_hanging_clip'] === 'Yes' ? 1 : 0,
            isset($inspection['q6_accessible']) && $inspection['q6_accessible'] === 'Yes' ? 1 : 0,
            isset($inspection['q7_refill_overdue']) && $inspection['q7_refill_overdue'] === 'No' ? 1 : 0,
            isset($inspection['q8_instructions_visible']) && $inspection['q8_instructions_visible'] === 'Yes' ? 1 : 0
        ];
        return array_sum($questions);
    }
}

// Initialize variables
$equipment_id = isset($_GET['id']) ? sanitize($_GET['id']) : '';
$equipment = null;
$last_inspection = null;
$recent_inspections = [];
$error_message = null;

// Validate equipment ID
if (empty($equipment_id)) {
    $error_message = "No equipment ID provided. Please scan a valid QR code.";
} else {
    try {
        // Get equipment details
        $equipment = $db->fetchOne(
            "SELECT * FROM equipments WHERE equipment_id = ?", 
            [$equipment_id]
        );

        if (!$equipment) {
            $error_message = "Equipment with ID '{$equipment_id}' not found in the system.";
        } else {
            // Get last inspection date
            $last_inspection = $db->fetchOne(
                "SELECT submitted_at FROM inspections 
                 WHERE equipment_id = ? 
                 ORDER BY submitted_at DESC LIMIT 1",
                [$equipment_id]
            );

            // Get recent inspections for this equipment
            /*$recent_inspections = $db->fetchAll(
                "SELECT * FROM inspections 
                 WHERE equipment_id = ? 
                 ORDER BY submitted_at DESC LIMIT 5",
                [$equipment_id]
            ) ?: [];*/
        }
    } catch (Exception $e) {
        // Log error and show user-friendly message
        error_log("Database error in equipment inspection: " . $e->getMessage());
        $error_message = "Unable to connect to the database. Please try again later.";
    }
}

// Helper function to get status badge class
function getStatusBadgeClass($date) {
    if (isOverdue($date)) return 'bg-danger';
    if (isDueSoon($date)) return 'bg-warning';
    return 'bg-success';
}

// Helper function to get status icon
function getStatusIcon($date) {
    if (isOverdue($date)) return 'fas fa-exclamation-triangle';
    if (isDueSoon($date)) return 'fas fa-clock';
    return 'fas fa-check';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(SITE_NAME); ?> - Equipment Inspection</title>
    <meta name="description" content="Equipment inspection system for <?php echo htmlspecialchars(SITE_NAME); ?>">
    <meta name="theme-color" content="#6366f1">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.95);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: rgba(255, 255, 255, 0.2);
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --animation-duration: 0.6s;
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            color: var(--text-primary);
            overflow-x: hidden;
            padding: 0;
            line-height: 1.5;
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

        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 20px 16px;
            position: relative;
            z-index: 1;
        }

        /* Header */
        .inspector-header {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 24px 20px;
            margin-bottom: 24px;
            text-align: center;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            animation: slideInDown var(--animation-duration) ease-out;
            position: relative;
            overflow: hidden;
        }

        .inspector-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .equipment-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .equipment-image::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: rotate 4s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .equipment-image i {
            font-size: 2.5rem;
            color: white;
            z-index: 1;
            position: relative;
        }

        .inspector-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 4px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .inspector-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            animation: slideInUp var(--animation-duration) ease-out;
            position: relative;
            overflow: hidden;
        }

        .alert-overdue {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .alert-warning {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .alert-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            box-shadow: var(--shadow-lg);
        }

        /* Equipment Card */
        .equipment-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            margin-bottom: 24px;
            overflow: hidden;
            animation: slideInUp 0.8s ease-out;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .equipment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 32px 64px -12px rgba(0, 0, 0, 0.25);
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .card-header-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: slideRight 2s infinite;
        }

        @keyframes slideRight {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .card-header-custom h4,
        .card-header-custom h5 {
            margin: 0;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .card-body-custom {
            padding: 24px 20px;
        }

        /* Detail Row */
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .detail-row:hover {
            background-color: rgba(99, 102, 241, 0.05);
            border-radius: 12px;
            margin: 0 -8px;
            padding: 16px 8px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            display: flex;
            align-items: center;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .detail-label i {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            margin-right: 12px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
            text-align: right;
            flex: 1;
            margin-left: 12px;
            word-break: break-word;
        }

        /* Status Badge */
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            box-shadow: var(--shadow-sm);
        }

        .bg-success {
            background: linear-gradient(135deg, var(--success), #059669) !important;
            color: white !important;
        }

        .bg-warning {
            background: linear-gradient(135deg, var(--warning), #d97706) !important;
            color: white !important;
        }

        .bg-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626) !important;
            color: white !important;
        }

        /* Button */
        .btn-inspect {
            background: linear-gradient(135deg, var(--success), #059669);
            border: none;
            border-radius: 20px;
            padding: 18px 32px;
            font-weight: 700;
            color: white;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            margin: 24px 0;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-lg);
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn-inspect::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-inspect:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px -12px rgba(16, 185, 129, 0.4);
            color: white;
            text-decoration: none;
        }

        .btn-inspect:hover::before {
            left: 100%;
        }

        .btn-inspect:active {
            transform: translateY(-1px);
        }

        .btn-inspect:focus {
            outline: 2px solid var(--success);
            outline-offset: 2px;
        }

        /* Inspection History */
        .inspection-history {
            max-height: 400px;
            overflow-y: auto;
        }

        .inspection-history::-webkit-scrollbar {
            width: 6px;
        }

        .inspection-history::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
        }

        .inspection-history::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }

        .inspection-item {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05));
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            border-left: 4px solid var(--primary);
            backdrop-filter: blur(10px);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .inspection-item:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-md);
        }

        .inspection-item:last-child {
            margin-bottom: 0;
        }

        /* Progress */
        .progress-custom {
            height: 12px;
            border-radius: 6px;
            background-color: rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .progress-bar-custom {
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
            transition: width 0.6s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-bar-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: progressShimmer 2s infinite;
        }

        @keyframes progressShimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* No Equipment */
        .no-equipment {
            text-align: center;
            padding: 48px 24px;
        }

        .no-equipment i {
            font-size: 4rem;
            color: var(--danger);
            margin-bottom: 24px;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 24px;
            color: white;
            font-weight: 500;
            opacity: 0.9;
        }

        /* Animations */
        @keyframes slideInDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes slideInUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Text utilities */
        .text-success {
            color: var(--success) !important;
            font-weight: 600;
        }

        .text-muted {
            color: var(--text-secondary) !important;
        }

        .text-white {
            color: white !important;
        }

        .small {
            font-size: 0.8rem;
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .container {
                padding: 16px 12px;
                max-width: 100%;
            }

            .inspector-header {
                padding: 20px 16px;
                border-radius: 20px;
                margin-bottom: 20px;
            }

            .equipment-image {
                width: 70px;
                height: 70px;
                border-radius: 16px;
            }

            .equipment-image i {
                font-size: 2rem;
            }

            .inspector-header h2 {
                font-size: 1.3rem;
            }

            .equipment-card {
                border-radius: 20px;
                margin-bottom: 20px;
            }

            .card-header-custom, .card-body-custom {
                padding: 16px;
            }

            .detail-row {
                padding: 12px 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .detail-value {
                text-align: left;
                margin-left: 0;
                font-size: 0.95rem;
                width: 100%;
            }

            .btn-inspect {
                padding: 16px 24px;
                font-size: 0.95rem;
                border-radius: 16px;
                margin: 20px 0;
            }

            .inspection-item {
                padding: 16px;
                border-radius: 12px;
                margin-bottom: 12px;
            }

            .no-equipment i {
                font-size: 3rem;
            }

            .no-equipment {
                padding: 36px 20px;
            }
        }

        @media (max-width: 400px) {
            .container {
                padding: 12px 8px;
            }

            .inspector-header, .equipment-card {
                margin-bottom: 16px;
            }

            .detail-label, .detail-value {
                font-size: 0.85rem;
            }

            .status-badge {
                font-size: 0.75rem;
                padding: 6px 12px;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            :root {
                --card-bg: rgba(15, 23, 42, 0.95);
                --text-primary: #f1f5f9;
                --text-secondary: #94a3b8;
                --border-color: rgba(255, 255, 255, 0.1);
            }

            body {
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            }

            .detail-row:hover {
                background-color: rgba(255, 255, 255, 0.05);
            }
        }

        /* Accessibility improvements */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            border: 2px solid var(--primary);
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Inspector Header -->
        <div class="inspector-header">
            <div class="equipment-image">
                <i class="fas fa-qrcode" aria-hidden="true"></i>
            </div>
            <h2><i class="fas fa-clipboard-check me-2" aria-hidden="true"></i><?php echo htmlspecialchars(SITE_NAME); ?></h2>
            <p class="mb-0">Scan • Inspect • Report</p>
        </div>

        <?php if ($error_message): ?>
            <!-- Error Alert -->
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            
        <?php elseif ($equipment): ?>
            <!-- Status Alerts -->
            <?php if (isOverdue($equipment['due_date'])): ?>
                <div class="alert alert-overdue" role="alert">
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3" aria-hidden="true"></i>
                        <h5><strong>🚨 URGENT: INSPECTION OVERDUE!</strong></h5>
                        <p class="mb-0">This equipment was due for inspection on <?php echo htmlspecialchars(formatDate($equipment['due_date'])); ?></p>
                    </div>
                </div>
            <?php elseif (isDueSoon($equipment['due_date'])): ?>
                <div class="alert alert-warning" role="alert">
                    <div class="text-center">
                        <i class="fas fa-clock fa-lg me-2" aria-hidden="true"></i>
                        <strong>⚠️ Inspection Due Soon:</strong> <?php echo htmlspecialchars(formatDate($equipment['due_date'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Equipment Details Card -->
            <div class="equipment-card">
                <div class="card-header-custom">
                    <h4 class="mb-0"><i class="fas fa-cogs me-2" aria-hidden="true"></i>Equipment Information</h4>
                </div>
                <div class="card-body-custom">
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-barcode" aria-hidden="true"></i>
                            Equipment ID
                        </div>
                        <div class="detail-value">
                            <strong><?php echo htmlspecialchars($equipment['equipment_id']); ?></strong>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-tag" aria-hidden="true"></i>
                            Type
                        </div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($equipment['type'] ?: 'Not specified'); ?>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            Location
                        </div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($equipment['location'] ?: 'Not specified'); ?>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-building" aria-hidden="true"></i>
                            Area
                        </div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($equipment['area'] ?: 'Not specified'); ?>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                            Inspection Frequency
                        </div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($equipment['frequency']); ?>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-calendar-check" aria-hidden="true"></i>
                            Due Date
                        </div>
                        <div class="detail-value">
                            <span class="status-badge <?php echo getStatusBadgeClass($equipment['due_date']); ?> text-white">
                                <?php echo htmlspecialchars(formatDate($equipment['due_date'])); ?>
                                <i class="<?php echo getStatusIcon($equipment['due_date']); ?>" aria-hidden="true"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-history" aria-hidden="true"></i>
                            Last Inspection
                        </div>
                        <div class="detail-value">
                            <?php if ($last_inspection && !empty($last_inspection['submitted_at'])): ?>
                                <span class="text-success">
                                    <?php echo htmlspecialchars(formatDate($last_inspection['submitted_at'])); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Never inspected</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Start Inspection Button -->
            <div class="text-center">
                <a href="login.php?equipment_id=<?php echo urlencode($equipment['equipment_id']); ?>" 
                   class="btn btn-inspect" 
                   role="button"
                   aria-label="Start inspection for equipment <?php echo htmlspecialchars($equipment['equipment_id']); ?>">
                    <i class="fas fa-play" aria-hidden="true"></i>Start Inspection Now
                </a>
            </div>
            
            <!-- Recent Inspections -->
            <?php if (!empty($recent_inspections)): ?>
            <div class="equipment-card">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-history me-2" aria-hidden="true"></i>Recent Inspection History</h5>
                </div>
                <div class="card-body-custom">
                    <div class="inspection-history">
                        <?php foreach ($recent_inspections as $inspection): 
                            $score = calculateInspectionScore($inspection);
                            $percentage = round(($score / 8) * 100);
                            $scoreClass = $percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                        ?>
                            <div class="inspection-item">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <strong><i class="fas fa-user me-2" aria-hidden="true"></i><?php echo htmlspecialchars($inspection['inspector_name'] ?? 'Unknown'); ?></strong>
                                    </div>
                                    <div class="status-badge bg-<?php echo $scoreClass; ?>">
                                        <?php echo $percentage; ?>%
                                    </div>
                                </div>
                                <div class="progress-custom mb-2">
                                    <div class="progress-bar-custom bg-<?php echo $scoreClass; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%" 
                                         aria-valuenow="<?php echo $percentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"
                                         aria-label="Inspection score: <?php echo $percentage; ?>%">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small text-muted"><?php echo htmlspecialchars(formatDate($inspection['submitted_at'] ?? '')); ?></span>
                                    <span class="small"><?php echo $score; ?>/8 checks passed</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        <?php endif; ?>
        
        <?php if (!$equipment && !$error_message): ?>
            <!-- Equipment Not Found (fallback) -->
            <div class="equipment-card">
                <div class="card-body-custom no-equipment">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <h4>Equipment Not Found</h4>
                    <p class="text-muted mb-4">Please scan a valid QR code or check the equipment ID.</p>
                    <p class="text-muted">Contact your supervisor if you continue to experience issues.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p><i class="fas fa-hospital me-2" aria-hidden="true"></i><?php echo htmlspecialchars(SITE_NAME); ?></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            'use strict';
            
            // Configuration
            const CONFIG = {
                autoRefreshInterval: 30000, // 30 seconds
                pullThreshold: 80,
                vibrationSupport: 'vibrate' in navigator,
                networkMonitoring: 'onLine' in navigator
            };

            // DOM elements
            const inspectBtn = document.querySelector('.btn-inspect');
            const body = document.body;
            
            // Initialize app
            function initializeApp() {
                setupEventListeners();
                setupAccessibility();
                setupNetworkMonitoring();
                setupAutoRefresh();
                setupProgressiveEnhancements();
                console.log('Equipment Inspection System initialized');
            }

            // Event listeners
            function setupEventListeners() {
                // Inspection button loading state
                if (inspectBtn) {
                    inspectBtn.addEventListener('click', handleInspectClick);
                }

                // Keyboard navigation
                document.addEventListener('keydown', handleKeyNavigation);
                
                // Touch feedback
                setupTouchFeedback();
                
                // Pull to refresh
                setupPullToRefresh();
            }

            // Handle inspect button click
            function handleInspectClick(e) {
                if (CONFIG.vibrationSupport) {
                    navigator.vibrate(50);
                }
                
                this.classList.add('loading');
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2" aria-hidden="true"></i>Loading...';
                this.setAttribute('aria-label', 'Loading inspection form...');
                
                // Allow navigation to proceed
                setTimeout(() => {
                    // Page will navigate, so we don't need to reset
                }, 500);
            }

            // Keyboard navigation
            function handleKeyNavigation(e) {
                if ((e.key === 'Enter' || e.key === ' ') && inspectBtn && document.activeElement === inspectBtn) {
                    e.preventDefault();
                    inspectBtn.click();
                }
                
                // Escape key to scroll to top
                if (e.key === 'Escape') {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }

            // Touch feedback for mobile
            function setupTouchFeedback() {
                if (!CONFIG.vibrationSupport) return;

                const interactiveElements = document.querySelectorAll('.detail-row, .inspection-item, .status-badge');
                
                interactiveElements.forEach(element => {
                    element.addEventListener('touchstart', () => {
                        navigator.vibrate(10);
                    }, { passive: true });
                });
            }

            // Pull to refresh functionality
            function setupPullToRefresh() {
                let startY = 0;
                let currentY = 0;
                let pullDistance = 0;
                let isPulling = false;
                
                // Create pull indicator
                const pullIndicator = document.createElement('div');
                pullIndicator.innerHTML = '<i class="fas fa-arrow-down" aria-hidden="true"></i>';
                pullIndicator.setAttribute('aria-hidden', 'true');
                pullIndicator.style.cssText = `
                    position: fixed;
                    top: -60px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 60px;
                    height: 60px;
                    background: linear-gradient(135deg, var(--primary), var(--secondary));
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 1.5rem;
                    z-index: 1000;
                    transition: all 0.3s ease;
                    box-shadow: var(--shadow-lg);
                `;
                body.appendChild(pullIndicator);

                document.addEventListener('touchstart', (e) => {
                    if (window.scrollY === 0) {
                        startY = e.touches[0].clientY;
                    }
                }, { passive: true });

                document.addEventListener('touchmove', (e) => {
                    if (window.scrollY === 0 && startY) {
                        currentY = e.touches[0].clientY;
                        pullDistance = currentY - startY;
                        
                        if (pullDistance > 0) {
                            isPulling = true;
                            const progress = Math.min(pullDistance / CONFIG.pullThreshold, 1);
                            pullIndicator.style.top = `${-60 + (progress * 80)}px`;
                            pullIndicator.style.transform = `translateX(-50%) rotate(${progress * 180}deg)`;
                            
                            if (progress >= 1) {
                                pullIndicator.innerHTML = '<i class="fas fa-sync-alt" aria-hidden="true"></i>';
                            } else {
                                pullIndicator.innerHTML = '<i class="fas fa-arrow-down" aria-hidden="true"></i>';
                            }
                        }
                    }
                }, { passive: true });

                document.addEventListener('touchend', () => {
                    if (isPulling && pullDistance >= CONFIG.pullThreshold) {
                        pullIndicator.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i>';
                        setTimeout(() => location.reload(), 1000);
                    }
                    
                    setTimeout(() => {
                        pullIndicator.style.top = '-60px';
                        pullIndicator.style.transform = 'translateX(-50%) rotate(0deg)';
                        pullIndicator.innerHTML = '<i class="fas fa-arrow-down" aria-hidden="true"></i>';
                    }, 300);
                    
                    startY = 0;
                    currentY = 0;
                    pullDistance = 0;
                    isPulling = false;
                }, { passive: true });
            }

            // Network status monitoring
            function setupNetworkMonitoring() {
                if (!CONFIG.networkMonitoring) return;

                const networkIndicator = document.createElement('div');
                networkIndicator.setAttribute('title', 'Network Status');
                networkIndicator.setAttribute('aria-label', 'Network status indicator');
                networkIndicator.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    width: 12px;
                    height: 12px;
                    border-radius: 50%;
                    background: var(--success);
                    z-index: 1000;
                    transition: all 0.3s ease;
                    box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
                `;
                body.appendChild(networkIndicator);

                function updateNetworkStatus() {
                    const isOnline = navigator.onLine;
                    networkIndicator.style.background = isOnline ? 'var(--success)' : 'var(--danger)';
                    networkIndicator.style.boxShadow = `0 0 10px ${isOnline ? 'rgba(16, 185, 129, 0.5)' : 'rgba(239, 68, 68, 0.5)'}`;
                    networkIndicator.setAttribute('title', isOnline ? 'Online' : 'Offline');
                    networkIndicator.setAttribute('aria-label', `Network status: ${isOnline ? 'Online' : 'Offline'}`);
                }

                window.addEventListener('online', updateNetworkStatus);
                window.addEventListener('offline', updateNetworkStatus);
                updateNetworkStatus();
            }

            // Auto-refresh functionality
            function setupAutoRefresh() {
                let refreshTimer;
                
                function startAutoRefresh() {
                    refreshTimer = setTimeout(() => {
                        location.reload();
                    }, CONFIG.autoRefreshInterval);
                }

                function stopAutoRefresh() {
                    clearTimeout(refreshTimer);
                }

                // Pause auto-refresh when page is not visible
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        stopAutoRefresh();
                    } else {
                        startAutoRefresh();
                    }
                });

                startAutoRefresh();
            }

            // Accessibility enhancements
            function setupAccessibility() {
                // Skip to main content link
                const skipLink = document.createElement('a');
                skipLink.href = '#main-content';
                skipLink.textContent = 'Skip to main content';
                skipLink.style.cssText = `
                    position: absolute;
                    left: -9999px;
                    z-index: 999999;
                    padding: 8px 16px;
                    background: var(--primary);
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                `;
                skipLink.addEventListener('focus', () => {
                    skipLink.style.left = '10px';
                    skipLink.style.top = '10px';
                });
                skipLink.addEventListener('blur', () => {
                    skipLink.style.left = '-9999px';
                });
                body.insertBefore(skipLink, body.firstChild);

                // Add main content landmark
                const container = document.querySelector('.container');
                if (container) {
                    container.id = 'main-content';
                    container.setAttribute('role', 'main');
                }

                // Announce page changes for screen readers
                const announcement = document.createElement('div');
                announcement.setAttribute('aria-live', 'polite');
                announcement.setAttribute('aria-atomic', 'true');
                announcement.style.cssText = `
                    position: absolute;
                    left: -9999px;
                    width: 1px;
                    height: 1px;
                    overflow: hidden;
                `;
                body.appendChild(announcement);

                // Announce loading state
                if (inspectBtn) {
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                                if (inspectBtn.classList.contains('loading')) {
                                    announcement.textContent = 'Loading inspection form, please wait...';
                                }
                            }
                        });
                    });
                    observer.observe(inspectBtn, { attributes: true });
                }
            }

            // Progressive enhancements
            function setupProgressiveEnhancements() {
                // Smooth scrolling
                document.documentElement.style.scrollBehavior = 'smooth';

                // Intersection observer for scroll animations
                if ('IntersectionObserver' in window) {
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.style.opacity = '1';
                                entry.target.style.transform = 'translateY(0)';
                            }
                        });
                    }, {
                        threshold: 0.1,
                        rootMargin: '0px 0px -50px 0px'
                    });

                    document.querySelectorAll('.equipment-card').forEach(card => {
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(30px)';
                        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                        observer.observe(card);
                    });
                }

                // Service Worker registration for PWA
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', () => {
                        navigator.serviceWorker.register('/sw.js')
                            .then(registration => console.log('SW registered:', registration))
                            .catch(err => console.log('SW registration failed:', err));
                    });
                }

                // Performance monitoring
                if ('performance' in window) {
                    window.addEventListener('load', () => {
                        setTimeout(() => {
                            try {
                                const navigation = performance.getEntriesByType('navigation')[0];
                                if (navigation) {
                                    const loadTime = navigation.loadEventEnd - navigation.loadEventStart;
                                    console.log(`Page load time: ${loadTime}ms`);
                                    
                                    if (loadTime > 3000) {
                                        showPerformanceWarning();
                                    }
                                }
                            } catch (error) {
                                console.log('Performance monitoring not available');
                            }
                        }, 100);
                    });
                }
            }

            // Show performance warning
            function showPerformanceWarning() {
                const warning = document.createElement('div');
                warning.setAttribute('role', 'alert');
                warning.style.cssText = `
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    background: var(--warning);
                    color: white;
                    padding: 8px 12px;
                    border-radius: 8px;
                    font-size: 0.8rem;
                    z-index: 1001;
                    font-weight: 500;
                `;
                warning.innerHTML = '<i class="fas fa-exclamation-triangle me-1" aria-hidden="true"></i>Slow connection detected';
                body.appendChild(warning);
                
                setTimeout(() => warning.remove(), 5000);
            }

            // Error handling
            window.addEventListener('error', (e) => {
                console.error('JavaScript error:', e.error);
                
                const errorNotification = document.createElement('div');
                errorNotification.setAttribute('role', 'alert');
                errorNotification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: var(--danger);
                    color: white;
                    padding: 16px 20px;
                    border-radius: 12px;
                    z-index: 1001;
                    max-width: 90%;
                    text-align: center;
                    box-shadow: var(--shadow-lg);
                `;
                errorNotification.innerHTML = '<i class="fas fa-exclamation-circle me-2" aria-hidden="true"></i>Something went wrong. Please try refreshing the page.';
                body.appendChild(errorNotification);
                
                setTimeout(() => errorNotification.remove(), 5000);
            });

            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeApp);
            } else {
                initializeApp();
            }

        })();
    </script>
</body>
</html>