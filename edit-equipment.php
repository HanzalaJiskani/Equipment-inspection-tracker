<?php
require_once 'config.php';

$equipment_id = isset($_GET['id']) ? sanitize($_GET['id']) : '';
$equipment = null;
$error = '';
$success = '';

if (empty($equipment_id)) {
    header('Location: index.php');
    exit();
}

// Fetch equipment details
$equipment = $db->fetchOne("SELECT * FROM equipments WHERE equipment_id = ?", [$equipment_id]);

if (!$equipment) {
    $error = 'Equipment not found.';
} else {
    // Handle form submission
    if ($_POST) {
        $type = sanitize($_POST['type']);
        $location = sanitize($_POST['location']);
        $area = sanitize($_POST['area']);
        $frequency = sanitize($_POST['frequency']);
        $due_date = sanitize($_POST['due_date']);
        
        // Validate frequency
        $valid_frequencies = ['Monthly', 'Quarterly', 'Bi-Annually', 'Annually'];
        if (!in_array($frequency, $valid_frequencies)) {
            $frequency = 'Monthly';
        }
        
        // Validate due date
        if (empty($due_date)) {
            $due_date = calculateDueDate($frequency);
        }
        
        try {
            $sql = "UPDATE equipments SET type = ?, location = ?, area = ?, frequency = ?, due_date = ?, updated_at = CURRENT_TIMESTAMP WHERE equipment_id = ?";
            $params = [$type, $location, $area, $frequency, $due_date, $equipment_id];
            
            if ($db->execute($sql, $params)) {
                $success = 'Equipment updated successfully!';
                // Refresh equipment data
                $equipment = $db->fetchOne("SELECT * FROM equipments WHERE equipment_id = ?", [$equipment_id]);
            } else {
                $error = 'Failed to update equipment.';
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get inspection history
$inspections = [];
if ($equipment) {
    $inspections = $db->fetchAll(
        "SELECT * FROM inspections WHERE equipment_id = ? ORDER BY submitted_at DESC LIMIT 10", 
        [$equipment_id]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Edit Equipment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        .edit-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .card-header {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .btn-update {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
        }
        .inspection-item {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .status-badge {
            font-size: 0.8em;
            padding: 4px 8px;
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2><i class="fas fa-edit"></i> Edit Equipment</h2>
                    <p class="mb-0">Equipment ID: <strong><?php echo $equipment_id; ?></strong></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="index.php?id=<?php echo $equipment_id; ?>" class="btn btn-light me-2">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($equipment): ?>
            <div class="row">
                <!-- Edit Form -->
                <div class="col-lg-8">
                    <div class="edit-card card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-keyboard"></i> Equipment Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="equipment_id_display" class="form-label">
                                                <i class="fas fa-tag text-danger"></i> Equipment ID
                                            </label>
                                            <input type="text" class="form-control" id="equipment_id_display" 
                                                   value="<?php echo $equipment['equipment_id']; ?>" disabled>
                                            <div class="form-text">Equipment ID cannot be changed</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="type" class="form-label">
                                                <i class="fas fa-cogs text-primary"></i> Equipment Type
                                            </label>
                                            <input type="text" class="form-control" id="type" name="type" 
                                                   value="<?php echo htmlspecialchars($equipment['type']); ?>"
                                                   placeholder="e.g., Fire Extinguisher, Safety Equipment">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="location" class="form-label">
                                                <i class="fas fa-map-marker-alt text-success"></i> Location
                                            </label>
                                            <input type="text" class="form-control" id="location" name="location" 
                                                   value="<?php echo htmlspecialchars($equipment['location']); ?>"
                                                   placeholder="e.g., Emergency Ward, Operation Theater">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="area" class="form-label">
                                                <i class="fas fa-building text-info"></i> Area
                                            </label>
                                            <input type="text" class="form-control" id="area" name="area" 
                                                   value="<?php echo htmlspecialchars($equipment['area']); ?>"
                                                   placeholder="e.g., Ward A, Ground Floor">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="frequency" class="form-label">
                                                <i class="fas fa-clock text-warning"></i> Inspection Frequency
                                            </label>
                                            <select class="form-select" id="frequency" name="frequency" onchange="updateDueDate()">
                                                <option value="Monthly" <?php echo $equipment['frequency'] === 'Monthly' ? 'selected' : ''; ?>>Monthly (30 days)</option>
                                                <option value="Quarterly" <?php echo $equipment['frequency'] === 'Quarterly' ? 'selected' : ''; ?>>Quarterly (90 days)</option>
                                                <option value="Bi-Annually" <?php echo $equipment['frequency'] === 'Bi-Annually' ? 'selected' : ''; ?>>Bi-Annually (180 days)</option>
                                                <option value="Annually" <?php echo $equipment['frequency'] === 'Annually' ? 'selected' : ''; ?>>Annually (360 days)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="due_date" class="form-label">
                                                <i class="fas fa-calendar text-secondary"></i> Next Due Date
                                            </label>
                                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                                   value="<?php echo $equipment['due_date']; ?>">
                                            <div class="form-text">Current status: 
                                                <?php if (isOverdue($equipment['due_date'])): ?>
                                                    <span class="text-danger"><strong>OVERDUE</strong></span>
                                                <?php elseif (isDueSoon($equipment['due_date'])): ?>
                                                    <span class="text-warning"><strong>DUE SOON</strong></span>
                                                <?php else: ?>
                                                    <span class="text-success"><strong>CURRENT</strong></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-update btn-lg">
                                        <i class="fas fa-save"></i> Update Equipment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Equipment Status & Actions -->
                <div class="col-lg-4">
                    <!-- Current Status -->
                    <div class="edit-card card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Equipment Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <?php if (isOverdue($equipment['due_date'])): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                                        <h6 class="mt-2">OVERDUE</h6>
                                        <small>Inspection required immediately</small>
                                    </div>
                                <?php elseif (isDueSoon($equipment['due_date'])): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-clock fa-2x"></i>
                                        <h6 class="mt-2">DUE SOON</h6>
                                        <small>Inspection needed within 7 days</small>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                        <h6 class="mt-2">CURRENT</h6>
                                        <small>Next inspection: <?php echo formatDate($equipment['due_date']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="login.php?equipment_id=<?php echo $equipment_id; ?>" class="btn btn-success">
                                    <i class="fas fa-clipboard-check"></i> Start Inspection
                                </a>
                                <a href="generate-qr.php?id=<?php echo $equipment_id; ?>" class="btn btn-info">
                                    <i class="fas fa-qrcode"></i> Generate QR Code
                                </a>
                                <button class="btn btn-danger" onclick="deleteEquipment('<?php echo $equipment_id; ?>')">
                                    <i class="fas fa-trash"></i> Delete Equipment
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="edit-card card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Inspection History</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($inspections): ?>
                                <p class="mb-3">Total Inspections: <strong><?php echo count($inspections); ?></strong></p>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($inspections as $inspection): ?>
                                        <div class="inspection-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo $inspection['inspector_name']; ?></strong><br>
                                                    <small class="text-muted"><?php echo formatDate($inspection['submitted_at']); ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <?php
                                                    $questions = ['q1_safety_pin', 'q2_gauge_green', 'q3_weight_appropriate', 'q4_no_damage', 'q5_hanging_clip', 'q6_accessible', 'q7_refill_overdue', 'q8_instructions_visible'];
                                                    $yes_count = 0;
                                                    foreach ($questions as $q) {
                                                        if ($inspection[$q] === 'Yes') $yes_count++;
                                                        if ($q === 'q7_refill_overdue' && $inspection[$q] === 'No') $yes_count++; // Reverse logic for this question
                                                    }
                                                    $score = ($yes_count / 8) * 100;
                                                    ?>
                                                    <span class="badge <?php echo $score >= 80 ? 'bg-success' : ($score >= 60 ? 'bg-warning' : 'bg-danger'); ?>">
                                                        <?php echo round($score); ?>%
                                                    </span>
                                                </div>
                                            </div>
                                            <?php if ($inspection['remarks']): ?>
                                                <div class="mt-2">
                                                    <small><strong>Remarks:</strong> <?php echo htmlspecialchars($inspection['remarks']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">No inspections recorded yet</p>
                                <div class="text-center">
                                    <a href="login.php?equipment_id=<?php echo $equipment_id; ?>" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-plus"></i> Add First Inspection
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update due date based on frequency
        function updateDueDate() {
            const frequency = document.getElementById('frequency').value;
            const days = {
                'Monthly': 30,
                'Quarterly': 90,
                'Bi-Annually': 180,
                'Annually': 360
            };
            
            const today = new Date();
            const dueDate = new Date(today.getTime() + (days[frequency] * 24 * 60 * 60 * 1000));
            
            // Only update if current due date field is empty or user confirms
            const dueDateField = document.getElementById('due_date');
            if (!dueDateField.value || confirm('Update due date based on new frequency?')) {
                dueDateField.value = dueDate.toISOString().split('T')[0];
            }
        }

        // Delete equipment function
        function deleteEquipment(equipmentId) {
            if (confirm('Are you sure you want to delete this equipment?\n\nThis will also delete all inspection records associated with it.\n\nThis action cannot be undone.')) {
                fetch('php/delete-equipment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({equipment_id: equipmentId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Equipment deleted successfully!');
                        window.location.href = 'index.php';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the equipment.');
                });
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(e) {
                const dueDateField = document.getElementById('due_date');
                
                if (dueDateField.value) {
                    const selectedDate = new Date(dueDateField.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (selectedDate < today) {
                        if (!confirm('The selected due date is in the past. This will mark the equipment as overdue. Do you want to continue?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>