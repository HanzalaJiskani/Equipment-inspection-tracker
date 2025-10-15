<?php 
require_once 'config.php';

$success = '';
$error = '';
$generated_qr = '';

if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_single') {
        // Handle single equipment addition
        $equipment_id = sanitize($_POST['equipment_id']);
        $type = sanitize($_POST['type']);
        $location = sanitize($_POST['location']);
        $area = sanitize($_POST['area']);
        $frequency = sanitize($_POST['frequency']);
        $due_date = sanitize($_POST['due_date']);
        
        if (empty($equipment_id)) {
            $error = 'Equipment ID is required.';
        } else {
            // Check if equipment ID already exists
            $existing = $db->fetchOne("SELECT equipment_id FROM equipments WHERE equipment_id = ?", [$equipment_id]);
            if ($existing) {
                $error = 'Equipment ID already exists. Please use a different ID.';
            } else {
                // Set due date if empty
                if (empty($due_date)) {
                    $due_date = calculateDueDate($frequency ?: 'Monthly');
                }
                
                try {
                    $sql = "INSERT INTO equipments (equipment_id, type, location, area, frequency, due_date) VALUES (?, ?, ?, ?, ?, ?)";
                    $params = [$equipment_id, $type, $location, $area, $frequency, $due_date];
                    
                    if ($db->execute($sql, $params)) {
                        // Generate QR Code
                        $qr_file = generateQRCode($equipment_id);
                        if ($qr_file) {
                            $generated_qr = $qr_file;
                            $success = 'Equipment added successfully! QR code generated.';
                        } else {
                            $success = 'Equipment added successfully! (QR code generation failed)';
                        }
                    } else {
                        $error = 'Failed to add equipment.';
                    }
                } catch (Exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Add Equipment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        .add-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .card-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
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
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-add {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            color: white;
        }
        .qr-display {
            background: white;
            border: 3px dashed #007bff;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-top: 20px;
        }
        .upload-area {
            border: 3px dashed #28a745;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-area:hover {
            background: #e9ecef;
            border-color: #20c997;
        }
        .upload-area.dragover {
            background: #d4edda;
            border-color: #28a745;
        }
        .frequency-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .equipment-preview {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2><i class="fas fa-plus-circle"></i> Add New Equipment</h2>
                    <p class="mb-0">Add equipment manually or upload Excel file</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="index.php" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Success/Error Messages -->
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

        <!-- QR Code Display -->
        <?php if ($generated_qr): ?>
            <div class="qr-display">
                <h4><i class="fas fa-qrcode"></i> QR Code Generated</h4>
                <img src="<?php echo $generated_qr; ?>" alt="QR Code" class="img-fluid mb-3" style="max-width: 200px;">
                <div>
                    <button onclick="printQR()" class="btn btn-primary me-2">
                        <i class="fas fa-print"></i> Print QR Code
                    </button>
                    <a href="<?php echo $generated_qr; ?>" download class="btn btn-outline-primary">
                        <i class="fas fa-download"></i> Download
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Manual Entry Form -->
            <div class="col-lg-6">
                <div class="add-card card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-keyboard"></i> Manual Entry</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="manualForm">
                            <input type="hidden" name="action" value="add_single">
                            
                            <div class="mb-3">
                                <label for="equipment_id" class="form-label">
                                    <i class="fas fa-tag text-danger"></i> Equipment ID <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="equipment_id" name="equipment_id" 
                                       placeholder="e.g., EQ001, FE-001" required>
                                <div class="form-text">Must be unique identifier</div>
                            </div>

                            <div class="mb-3">
                                <label for="type" class="form-label">
                                    <i class="fas fa-cogs text-primary"></i> Equipment Type
                                </label>
                                <input type="text" class="form-control" id="type" name="type" 
                                       placeholder="e.g., Fire Extinguisher, Safety Equipment">
                            </div>

                            <div class="mb-3">
                                <label for="location" class="form-label">
                                    <i class="fas fa-map-marker-alt text-success"></i> Location
                                </label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="e.g., Emergency Ward, Operation Theater">
                            </div>

                            <div class="mb-3">
                                <label for="area" class="form-label">
                                    <i class="fas fa-building text-info"></i> Area
                                </label>
                                <input type="text" class="form-control" id="area" name="area" 
                                       placeholder="e.g., Ward A, Ground Floor">
                            </div>

                            <div class="mb-3">
                                <label for="frequency" class="form-label">
                                    <i class="fas fa-clock text-warning"></i> Inspection Frequency
                                </label>
                                <select class="form-select" id="frequency" name="frequency" onchange="updateDueDate()">
                                    <option value="Monthly" selected>Monthly (30 days)</option>
                                    <option value="Quarterly">Quarterly (90 days)</option>
                                    <option value="Bi-Annually">Bi-Annually (180 days)</option>
                                    <option value="Annually">Annually (360 days)</option>
                                </select>
                                <div class="frequency-info">
                                    <small><i class="fas fa-info-circle"></i> 
                                    <span id="frequency-text">Next inspection will be due in 30 days from today</span>
                                    </small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="due_date" class="form-label">
                                    <i class="fas fa-calendar text-secondary"></i> Due Date (Optional)
                                </label>
                                <input type="date" class="form-control" id="due_date" name="due_date">
                                <div class="form-text">Leave blank to auto-calculate based on frequency</div>
                            </div>

                            <!-- Equipment Preview -->
                            <div class="equipment-preview" id="equipmentPreview" style="display: none;">
                                <h6><i class="fas fa-eye"></i> Preview</h6>
                                <div id="previewContent"></div>
                            </div>

                            <button type="submit" class="btn btn-add w-100">
                                <i class="fas fa-plus"></i> Add Equipment & Generate QR
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Excel Upload -->
            <div class="col-lg-6">
                <div class="add-card card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-excel"></i> Bulk Excel Upload</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Excel Format Requirements:</strong><br>
                            Your Excel file must include these columns:<br>
                            <code>Equipment ID</code>, <code>Type</code>, <code>Location</code>, 
                            <code>Area</code>, <code>Due Date</code>, <code>Frequency</code>
                        </div>

                        <div class="upload-area" id="uploadArea" onclick="document.getElementById('excelFile').click()">
                            <i class="fas fa-cloud-upload-alt fa-3x text-success mb-3"></i>
                            <h5>Click to upload or drag Excel file here</h5>
                            <p class="text-muted">Supports .xlsx, .xls files</p>
                            <input type="file" id="excelFile" accept=".xlsx,.xls" style="display: none;" onchange="handleExcelUpload(this)">
                        </div>

                        <div id="uploadProgress" class="mt-3" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <div class="text-center mt-2">
                                <small id="progressText">Processing...</small>
                            </div>
                        </div>

                        <div id="uploadResults" class="mt-3" style="display: none;">
                            <div class="alert alert-success">
                                <h6><i class="fas fa-check"></i> Upload Results</h6>
                                <div id="resultsContent"></div>
                            </div>
                        </div>

                        <!-- Sample Excel Template -->
                        <div class="mt-3">
                            <h6><i class="fas fa-download"></i> Download Sample Template</h6>
                            <button class="btn btn-outline-primary btn-sm" onclick="downloadTemplate()">
                                <i class="fas fa-file-excel"></i> Download Excel Template
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Additions -->
                <div class="add-card card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Recent Additions</h5>
                    </div>
                    <div class="card-body">
                        <div id="recentEquipment">
                            <?php
                            $recent = $db->fetchAll("SELECT * FROM equipments ORDER BY created_at DESC LIMIT 5");
                            if ($recent):
                            ?>
                                <?php foreach ($recent as $eq): ?>
                                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                        <div>
                                            <strong><?php echo $eq['equipment_id']; ?></strong><br>
                                            <small class="text-muted"><?php echo $eq['type'] ?: 'No type'; ?> - <?php echo formatDate($eq['created_at']); ?></small>
                                        </div>
                                        <a href="index.php?id=<?php echo $eq['equipment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center">No equipment added yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update due date calculation
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
            
            document.getElementById('frequency-text').textContent = 
                `Next inspection will be due in ${days[frequency]} days (${dueDate.toLocaleDateString()})`;
        }

        // Equipment preview
        function updatePreview() {
            const form = document.getElementById('manualForm');
            const preview = document.getElementById('equipmentPreview');
            const content = document.getElementById('previewContent');
            
            const id = form.equipment_id.value;
            const type = form.type.value;
            const location = form.location.value;
            const area = form.area.value;
            const frequency = form.frequency.value;
            
            if (id) {
                preview.style.display = 'block';
                content.innerHTML = `
                    <div class="row">
                        <div class="col-6">
                            <small><strong>ID:</strong> ${id}</small><br>
                            <small><strong>Type:</strong> ${type || 'Not specified'}</small>
                        </div>
                        <div class="col-6">
                            <small><strong>Location:</strong> ${location || 'Not specified'}</small><br>
                            <small><strong>Frequency:</strong> ${frequency}</small>
                        </div>
                    </div>
                `;
            } else {
                preview.style.display = 'none';
            }
        }

        // Add event listeners for preview
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('manualForm');
            const inputs = form.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });
            
            updateDueDate();
        });

        // Excel Upload Handling
        function handleExcelUpload(input) {
            const file = input.files[0];
            if (!file) return;

            const progress = document.getElementById('uploadProgress');
            const results = document.getElementById('uploadResults');
            
            progress.style.display = 'block';
            results.style.display = 'none';
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, {type: 'array'});
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet);
                    
                    processExcelData(jsonData);
                } catch (error) {
                    alert('Error reading Excel file: ' + error.message);
                    progress.style.display = 'none';
                }
            };
            reader.readAsArrayBuffer(file);
        }

        function processExcelData(data) {
            const progress = document.querySelector('#uploadProgress .progress-bar');
            const progressText = document.getElementById('progressText');
            const results = document.getElementById('uploadResults');
            const resultsContent = document.getElementById('resultsContent');
            
            let processed = 0;
            let successful = 0;
            let errors = [];
            
            // Process each row
            data.forEach((row, index) => {
                setTimeout(() => {
                    const equipmentData = {
                        action: 'add_bulk',
                        equipment_id: row['Equipment ID'] || row['equipment_id'] || '',
                        type: row['Type'] || row['type'] || '',
                        location: row['Location'] || row['location'] || '',
                        area: row['Area'] || row['area'] || '',
                        frequency: row['Frequency'] || row['frequency'] || 'Monthly',
                        due_date: row['Due Date'] || row['due_date'] || ''
                    };
                    
                    // Send to server
                    fetch('php/process-excel.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(equipmentData)
                    })
                    .then(response => response.json())
                    .then(result => {
                        processed++;
                        if (result.success) {
                            successful++;
                        } else {
                            errors.push(`Row ${index + 1}: ${result.message}`);
                        }
                        
                        // Update progress
                        const percentage = (processed / data.length) * 100;
                        progress.style.width = percentage + '%';
                        progressText.textContent = `Processing ${processed}/${data.length}...`;
                        
                        // Show results when complete
                        if (processed === data.length) {
                            setTimeout(() => {
                                document.getElementById('uploadProgress').style.display = 'none';
                                results.style.display = 'block';
                                
                                resultsContent.innerHTML = `
                                    <p><strong>Successfully added:</strong> ${successful} equipment(s)</p>
                                    ${errors.length > 0 ? `<p><strong>Errors:</strong></p><ul>${errors.map(e => `<li>${e}</li>`).join('')}</ul>` : ''}
                                `;
                                
                                // Refresh recent equipment list
                                location.reload();
                            }, 500);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        errors.push(`Row ${index + 1}: Network error`);
                        processed++;
                    });
                }, index * 100); // Stagger requests
            });
        }

        // Download template
        function downloadTemplate() {
            const template = [
                {
                    'Equipment ID': 'EQ001',
                    'Type': 'Fire Extinguisher',
                    'Location': 'Emergency Ward',
                    'Area': 'Ward A',
                    'Frequency': 'Monthly',
                    'Due Date': '2025-08-28'
                },
                {
                    'Equipment ID': 'EQ002',
                    'Type': 'Safety Equipment',
                    'Location': 'Operation Theater',
                    'Area': 'OT-1',
                    'Frequency': 'Quarterly',
                    'Due Date': '2025-10-28'
                }
            ];
            
            const ws = XLSX.utils.json_to_sheet(template);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Equipment Template');
            XLSX.writeFile(wb, 'equipment_template.xlsx');
        }

        // Print QR Code
        function printQR() {
            const qrImg = document.querySelector('.qr-display img');
            if (qrImg) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head><title>QR Code - Equipment</title></head>
                        <body style="text-align: center; font-family: Arial, sans-serif;">
                            <h2><?php echo SITE_NAME; ?></h2>
                            <h3>Equipment QR Code</h3>
                            <img src="${qrImg.src}" style="max-width: 300px;">
                            <p>Scan to access equipment details</p>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        }

        // Drag and drop functionality
        const uploadArea = document.getElementById('uploadArea');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            uploadArea.classList.add('dragover');
        }
        
        function unhighlight(e) {
            uploadArea.classList.remove('dragover');
        }
        
        uploadArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                document.getElementById('excelFile').files = files;
                handleExcelUpload(document.getElementById('excelFile'));
            }
        }
    </script>
</body>
</html>