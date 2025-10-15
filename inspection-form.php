<?php 
require_once 'config.php';
requireLogin();

$equipment_id = isset($_GET['equipment_id']) ? sanitize($_GET['equipment_id']) : '';
$equipment = null;
$success = false;
$error = '';

if ($equipment_id) {
    $equipment = $db->fetchOne("SELECT * FROM equipments WHERE equipment_id = ?", [$equipment_id]);
}

if ($_POST) {
    $equipment_id = sanitize($_POST['equipment_id']);
    $inspector_name = $_SESSION['inspector_name'];
    
    // Validate required fields
    $required_fields = ['q1_safety_pin', 'q2_gauge_green', 'q3_weight_appropriate', 'q4_no_damage', 
                       'q5_hanging_clip', 'q6_accessible', 'q7_refill_overdue', 'q8_instructions_visible'];
    
    $form_data = [];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $error = 'Please answer all inspection questions.';
            break;
        }
        $form_data[$field] = sanitize($_POST[$field]);
    }
    
    if (!$error && !empty($equipment_id)) {
        // Check if equipment exists
        $equipment = $db->fetchOne("SELECT * FROM equipments WHERE equipment_id = ?", [$equipment_id]);
        if (!$equipment) {
            $error = 'Equipment not found.';
        } else {
            try {
                // Insert inspection record
                $sql = "INSERT INTO inspections (equipment_id, inspector_name, q1_safety_pin, q2_gauge_green, 
                        q3_weight_appropriate, q4_no_damage, q5_hanging_clip, q6_accessible, 
                        q7_refill_overdue, q8_instructions_visible, remarks) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $params = [
                    $equipment_id, $inspector_name,
                    $form_data['q1_safety_pin'], $form_data['q2_gauge_green'],
                    $form_data['q3_weight_appropriate'], $form_data['q4_no_damage'],
                    $form_data['q5_hanging_clip'], $form_data['q6_accessible'],
                    $form_data['q7_refill_overdue'], $form_data['q8_instructions_visible'],
                    sanitize($_POST['remarks'] ?? '')
                ];
                
                if ($db->execute($sql, $params)) {
                    // Update equipment due date based on frequency
                    $new_due_date = calculateDueDate($equipment['frequency']);
                    $db->execute("UPDATE equipments SET due_date = ? WHERE equipment_id = ?", 
                               [$new_due_date, $equipment_id]);
                    
                    $success = true;
                } else {
                    $error = 'Failed to save inspection data.';
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get all equipment for selection dropdown
$all_equipment = $db->fetchAll("SELECT equipment_id, type, location FROM equipments ORDER BY equipment_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Equipment Inspection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .inspector-header {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .inspection-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .question-row {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }
        .question-row:hover {
            background-color: #f8f9fa;
        }
        .question-row:last-child {
            border-bottom: none;
        }
        .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }
        .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .btn-submit {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
        }
        .equipment-info {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .success-animation {
            animation: bounce 0.6s ease-in-out;
        }
        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            80% { transform: translateY(-5px); }
        }
    </style>
</head>
<body>
    <!-- Inspector Header -->
    <div class="inspector-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3><i class="fas fa-clipboard-check"></i> Equipment Inspection</h3>
                    <p class="mb-0">Inspector: <strong><?php echo $_SESSION['inspector_name']; ?></strong></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <!--<a href="php/logout.php" class="btn btn-outline-light">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>-->
                    <!--<a href="index.php" class="btn btn-light ms-2">
                        <i class="fas fa-home"></i> Dashboard
                    </a>-->
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <!-- Success Message -->
            <div class="alert alert-success success-animation" role="alert">
                <div class="text-center">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h4>Inspection Completed Successfully!</h4>
                    <p>Equipment <strong><?php echo $equipment_id; ?></strong> has been inspected and the next due date has been updated.</p>
                    <div class="mt-3">
                        <!--<a href="inspection-form.php" class="btn btn-success me-2">
                            <i class="fas fa-plus"></i> Inspect Another Equipment
                        </a>-->
                        <!--<a href="index.php?id=<?php echo $equipment_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye"></i> View Equipment Details
                        </a>-->
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Equipment Selection or Info -->
            <?php if ($equipment): ?>
                <div class="equipment-info">
                    <div class="row">
                        <div class="col-md-8">
                            <h5><i class="fas fa-qrcode"></i> Inspecting Equipment</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>ID:</strong> <?php echo $equipment['equipment_id']; ?></p>
                                    <p class="mb-1"><strong>Type:</strong> <?php echo $equipment['type'] ?: 'Not specified'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Location:</strong> <?php echo $equipment['location'] ?: 'Not specified'; ?></p>
                                    <p class="mb-1"><strong>Area:</strong> <?php echo $equipment['area'] ?: 'Not specified'; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <p class="mb-1"><strong>Current Due Date:</strong></p>
                            <h6><?php echo formatDate($equipment['due_date']); ?></h6>
                            <?php if (isOverdue($equipment['due_date'])): ?>
                                <span class="badge bg-danger">OVERDUE</span>
                            <?php elseif (isDueSoon($equipment['due_date'])): ?>
                                <span class="badge bg-warning">DUE SOON</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Inspection Form -->
            <div class="inspection-card card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list-check"></i> Equipment Inspection Checklist</h5>
                </div>
                <div class="card-body p-0">
                    <form method="POST" action="" id="inspectionForm">
                        <!-- Equipment Selection -->
                        <?php if (!$equipment): ?>
                            <div class="question-row">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label mb-0">
                                            <i class="fas fa-search text-primary"></i>
                                            <strong>Select Equipment to Inspect</strong>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="equipment_id" required onchange="if(this.value) window.location.href='?equipment_id='+this.value">
                                            <option value="">Choose Equipment...</option>
                                            <?php foreach ($all_equipment as $eq): ?>
                                                <option value="<?php echo $eq['equipment_id']; ?>">
                                                    <?php echo $eq['equipment_id']; ?> - <?php echo $eq['type']; ?> (<?php echo $eq['location']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="equipment_id" value="<?php echo $equipment['equipment_id']; ?>">
                            
                            <!-- Inspection Questions -->
                            <div class="question-row">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label mb-0">
                                            <i class="fas fa-shield-alt text-danger"></i>
                                            <strong>1. Safety pin is intact</strong>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="q1_safety_pin" required>
                                            <option value="">Select...</option>
                                            <option value="Yes">✅ Yes</option>
                                            <option value="No">❌ No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="question-row">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label mb-0">
                                            <i class="fas fa-gauge text-success"></i>
                                            <strong>2. Gauge is at green level</strong>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="q2_gauge_green" required>
                                            <option value="">Select...</option>
                                            <option value="Yes">✅ Yes</option>
                                            <option value="No">❌ No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="question-row">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label mb-0">
                                            <i class="fas fa-weight text-info"></i>
                                            <strong>3. Weight is appropriate</strong>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="q3_weight_appropriate" required>
                                            <option value="">Select...</option>
                                            <option value="Yes">✅ Yes</option>
                                            <option value="No">❌ No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="question-row">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label mb-0">
                                            <i class="fas fa-tools text-warning"></i>
                                            <strong>4. No pinholes/damage/rust</strong>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="q4_no_damage" required>
                                            <option value="">Select...</option>
                                            <option value="Yes">✅ Yes</option>
                                            <option value="No">❌ No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="question-row">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label mb-0">
                                            <i class="fas fa-paperclip text-secondary"></i>
                                            <strong>5. Hanging clip is intact</strong>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="q5_hanging_clip" required>
                                            <option value="">Select...</option>
                                            <option value="Yes">✅ Yes</option>
                                            <option value="No">❌ No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="question-row">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label mb-0">
                                            <i class="fas fa-hand text-primary"></i>
                                            <strong>6. Easily accessible / Not blocked</strong>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="q6_accessible" required>
                                            <option value="">Select...</option>
                                            <option value="Yes">✅ Yes</option>
                                            <option value="No">❌ No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="question-row">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label mb-0">
                                            <i class="fas fa-clock text-danger"></i>
                                            <strong>7. Refill is overdue</strong>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="q7_refill_overdue" required>
                                            <option value="">Select...</option>
                                            <option value="No">✅ No (Good)</option>
                                            <option value="Yes">❌ Yes (Needs Refill)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="question-row">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label mb-0">
                                            <i class="fas fa-eye text-info"></i>
                                            <strong>8. Instructions are visible</strong>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="q8_instructions_visible" required>
                                            <option value="">Select...</option>
                                            <option value="Yes">✅ Yes</option>
                                            <option value="No">❌ No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="question-row">
                                <div class="row">
                                    <div class="col-md-12">
                                        <label class="form-label">
                                            <i class="fas fa-comment text-secondary"></i>
                                            <strong>Additional Remarks (Optional)</strong>
                                        </label>
                                        <textarea class="form-control" name="remarks" rows="3" 
                                                  placeholder="Enter any additional observations or comments..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="question-row text-center">
                                <button type="submit" class="btn btn-submit btn-lg">
                                    <i class="fas fa-save"></i> Submit Inspection Report
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation and progress tracking
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('inspectionForm');
            const selects = form.querySelectorAll('select[required]');
            
            // Track completion progress
            function updateProgress() {
                const completed = Array.from(selects).filter(select => select.value !== '').length;
                const total = selects.length;
                const percentage = (completed / total) * 100;
                
                // Update progress indicator if exists
                const progressBar = document.querySelector('.progress-bar');
                if (progressBar) {
                    progressBar.style.width = percentage + '%';
                    progressBar.textContent = Math.round(percentage) + '%';
                }
            }
            
            // Add change listeners
            selects.forEach(select => {
                select.addEventListener('change', updateProgress);
            });
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                const emptyFields = [];
                
                selects.forEach(select => {
                    if (select.value === '') {
                        isValid = false;
                        emptyFields.push(select.closest('.question-row').querySelector('strong').textContent);
                        select.style.borderColor = '#dc3545';
                    } else {
                        select.style.borderColor = '#28a745';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please complete all required fields:\n\n' + emptyFields.join('\n'));
                    // Scroll to first empty field
                    const firstEmpty = Array.from(selects).find(select => select.value === '');
                    if (firstEmpty) {
                        firstEmpty.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstEmpty.focus();
                    }
                }
            });
            
            // Initial progress update
            updateProgress();
        });
        
        // Auto-save functionality (localStorage)
        const autoSave = {
            save: function() {
                const formData = {};
                const form = document.getElementById('inspectionForm');
                const inputs = form.querySelectorAll('input, select, textarea');
                
                inputs.forEach(input => {
                    if (input.name && input.value) {
                        formData[input.name] = input.value;
                    }
                });
                
                localStorage.setItem('inspection_draft', JSON.stringify(formData));
            },
            
            restore: function() {
                const saved = localStorage.getItem('inspection_draft');
                if (saved) {
                    const formData = JSON.parse(saved);
                    const form = document.getElementById('inspectionForm');
                    
                    Object.keys(formData).forEach(name => {
                        const input = form.querySelector(`[name="${name}"]`);
                        if (input) {
                            input.value = formData[name];
                        }
                    });
                }
            },
            
            clear: function() {
                localStorage.removeItem('inspection_draft');
            }
        };
        
        // Restore saved data on page load
        <?php if ($equipment && !$success): ?>
            autoSave.restore();
        <?php endif; ?>
        
        // Save data on form changes
        document.addEventListener('change', autoSave.save);
        
        // Clear saved data on successful submission
        <?php if ($success): ?>
            autoSave.clear();
        <?php endif; ?>
    </script>
</body>
</html>