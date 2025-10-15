<?php 
require_once 'config.php';

$error = '';
$equipment_id = isset($_GET['equipment_id']) ? sanitize($_GET['equipment_id']) : '';

if ($_POST) {
    $name = sanitize($_POST['name']);
    $password = $_POST['password'];
    
    if (empty($name) || empty($password)) {
        $error = 'Please enter both name and password.';
    } else {
        // Simple password check - in production, use proper hashing
        if ($password === 'inspector123') {
            $_SESSION['inspector_name'] = $name;
            $_SESSION['login_time'] = time();
            
            // Redirect to inspection form
            if ($equipment_id) {
                header('Location: inspection-form.php?equipment_id=' . urlencode($equipment_id));
            } else {
                header('Location: inspection-form.php');
            }
            exit();
        } else {
            $error = 'Invalid password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Inspector Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .login-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-login {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: transform 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
        }
        .equipment-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card">
                    <div class="login-header">
                        <h3><i class="fas fa-user-shield"></i> Inspector Login</h3>
                        <p class="mb-0">Access Equipment Inspection System</p>
                    </div>
                    <div class="login-body">
                        <?php if ($equipment_id): ?>
                            <?php 
                            $equipment = $db->fetchOne("SELECT * FROM equipments WHERE equipment_id = ?", [$equipment_id]);
                            if ($equipment):
                            ?>
                            <div class="equipment-info">
                                <h6><i class="fas fa-qrcode"></i> Inspecting Equipment:</h6>
                                <p class="mb-1"><strong>ID:</strong> <?php echo $equipment['equipment_id']; ?></p>
                                <p class="mb-1"><strong>Type:</strong> <?php echo $equipment['type'] ?: 'Not specified'; ?></p>
                                <p class="mb-0"><strong>Location:</strong> <?php echo $equipment['location'] ?: 'Not specified'; ?></p>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    <i class="fas fa-user"></i> Inspector Name
                                </label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       placeholder="Enter your name" required 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter inspection password" required>
                                <div class="form-text">
                                    <small><i class="fas fa-info-circle"></i> Contact admin for password</small>
                                </div>
                            </div>
                            
                            <?php if ($equipment_id): ?>
                                <input type="hidden" name="equipment_id" value="<?php echo $equipment_id; ?>">
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-login w-100">
                                <i class="fas fa-sign-in-alt"></i> Login & Start Inspection
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="index.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <p class="text-white">
                        <i class="fas fa-hospital"></i> <?php echo SITE_NAME; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus on first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const nameField = document.getElementById('name');
            const passwordField = document.getElementById('password');
            
            if (!nameField.value) {
                nameField.focus();
            } else if (!passwordField.value) {
                passwordField.focus();
            }
        });
        
        // Show password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            // Simple feedback for demo
            if (this.value === 'inspector123') {
                this.style.borderColor = '#28a745';
            } else if (this.value.length > 0) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });
    </script>
</body>
</html>