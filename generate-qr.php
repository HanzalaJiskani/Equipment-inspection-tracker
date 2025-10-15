<?php
require_once 'config.php';

$equipment_id = isset($_GET['id']) ? sanitize($_GET['id']) : '';
$error = '';
$success = '';
$qr_url = '';

if (empty($equipment_id)) {
    $error = 'Equipment ID is required.';
} else {
    $equipment = $db->fetchOne("SELECT * FROM equipments WHERE equipment_id = ?", [$equipment_id]);
    
    if (!$equipment) {
        $error = 'Equipment not found.';
    } else {
        // Build QR code URL pointing to Inspector Interface
        $qr_data = SITE_URL . 'view-equipment.php?id=' . $equipment_id;
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($qr_data) . "&size=200x200";
        $success = 'QR Code generated successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Generate QR Code</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .qr-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .qr-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .qr-body {
            padding: 40px;
            text-align: center;
        }
        .qr-image {
            border: 3px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            background: #f8f9fa;
            display: inline-block;
            margin: 20px 0;
        }
        .btn-action {
            margin: 5px;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
        }
        .inspector-notice {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="qr-card">
                <div class="qr-header">
                    <h3><i class="fas fa-qrcode"></i> QR Code Generator</h3>
                    <p class="mb-0">Equipment: <?php echo $equipment_id; ?></p>
                </div>
                <div class="qr-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        </div>
                        <a href="index.php" class="btn btn-primary btn-action">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>

                        <div class="inspector-notice">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Inspector Interface:</strong> This QR code will direct inspectors to a dedicated inspection interface with limited access.
                        </div>

                        <div class="qr-image">
                            <img src="<?php echo $qr_url; ?>" alt="QR Code" class="img-fluid" style="max-width: 250px;">
                        </div>

                        <?php if ($equipment): ?>
                            <div class="equipment-info mb-4">
                                <div class="row text-start">
                                    <div class="col-6">
                                        <strong>Type:</strong> <?php echo $equipment['type'] ?: 'Not specified'; ?><br>
                                        <strong>Location:</strong> <?php echo $equipment['location'] ?: 'Not specified'; ?>
                                    </div>
                                    <div class="col-6">
                                        <strong>Area:</strong> <?php echo $equipment['area'] ?: 'Not specified'; ?><br>
                                        <strong>Due Date:</strong> <?php echo formatDate($equipment['due_date']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="action-buttons">
                            <button onclick="printQR('<?php echo $qr_url; ?>')" class="btn btn-success btn-action">
                                <i class="fas fa-print"></i> Print QR Code
                            </button>
                            <a href="<?php echo $qr_url; ?>" download class="btn btn-info btn-action">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <a href="view-equipment.php?id=<?php echo $equipment_id; ?>" class="btn btn-primary btn-action" target="_blank">
                                <i class="fas fa-eye"></i> Preview Inspector View
                            </a>
                            <a href="index.php?id=<?php echo $equipment_id; ?>" class="btn btn-secondary btn-action">
                                <i class="fas fa-cogs"></i> Staff View
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary btn-action">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function printQR(src) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>QR Code - <?php echo $equipment_id; ?></title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
                    .header { margin-bottom: 30px; }
                    .qr-container { margin: 20px 0; }
                    .equipment-info { margin-top: 20px; font-size: 14px; }
                    .inspector-note { background: #e7f3ff; padding: 15px; border-radius: 5px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2><?php echo SITE_NAME; ?></h2>
                    <h3>Equipment QR Code</h3>
                </div>
                <div class="qr-container">
                    <img src="${src}" style="max-width: 300px;">
                </div>
                <div class="equipment-info">
                    <p><strong>Equipment ID:</strong> <?php echo $equipment_id; ?></p>
                    <p><strong>Type:</strong> <?php echo $equipment['type'] ?? 'Not specified'; ?></p>
                    <p><strong>Location:</strong> <?php echo $equipment['location'] ?? 'Not specified'; ?></p>
                    <p><strong>Due Date:</strong> <?php echo formatDate($equipment['due_date']); ?></p>
                </div>
                <div class="inspector-note">
                    <p><strong>For Inspectors:</strong> Scan this QR code to access the dedicated inspection interface.</p>
                    <p style="font-size: 12px; color: #666;">
                        This QR code provides limited access to equipment details and inspection tools only.
                    </p>
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>
</body>
</html>