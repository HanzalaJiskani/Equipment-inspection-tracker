<?php
// Hospital Equipment Management System Configuration
// For OMI (Orthopaedic and Medical Institute)

session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'hospital_equipment');
define('DB_USER', 'root');
define('DB_PASS', '');


// Application Configuration
define('SITE_NAME', 'Hospital Equipment Manager');
define('SITE_URL', 'http://localhost/project/');
define('QR_CODE_PATH', 'qr-codes/');
define('UPLOAD_PATH', 'uploads/');

// Create uploads and qr-codes directories if they don't exist
if (!file_exists(QR_CODE_PATH)) {
    mkdir(QR_CODE_PATH, 0777, true);
}
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}

// Database Connection Class
class Database {
    private $connection;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }
}

// Utility Functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function isOverdue($due_date) {
    return strtotime($due_date) < strtotime('today');
}

function isDueSoon($due_date) {
    $due_timestamp = strtotime($due_date);
    $week_from_now = strtotime('+7 days');
    return $due_timestamp <= $week_from_now && $due_timestamp >= strtotime('today');
}

function calculateDueDate($frequency) {
    $days = [
        'Monthly' => 30,
        'Quarterly' => 90,
        'Bi-Annually' => 180,
        'Annually' => 360
    ];
    
    return date('Y-m-d', strtotime('+' . $days[$frequency] . ' days'));
}

function generateQRCode($equipment_id) {
    // URL that QR will point to (Inspector Interface)
    $url = SITE_URL . 'view-equipment.php?id=' . urlencode($equipment_id);
    
    $qr_folder = __DIR__ . '/qr-codes';
    $qr_filename = $equipment_id . '.png';
    $qr_file_path = $qr_folder . '/' . $qr_filename;

    if (!file_exists($qr_folder)) {
        mkdir($qr_folder, 0755, true);
    }

    $api_url = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($url) . '&size=200x200';
    $qr_data = file_get_contents($api_url);

    if ($qr_data !== false) {
        if (file_put_contents($qr_file_path, $qr_data)) {
            return 'qr-codes/' . $qr_filename;
        }
    }

    return false;
}


function isLoggedIn() {
    return isset($_SESSION['inspector_name']) && !empty($_SESSION['inspector_name']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Check if user is staff (simple role-based access)
function isStaff() {
    // You can implement more sophisticated role checking here
    // For now, we'll check if they're accessing from staff interface
    return isset($_SESSION['staff_access']) && $_SESSION['staff_access'] === true;
}

function requireStaffAccess() {
    if (!isStaff()) {
        header('Location: staff-login.php');
        exit();
    }
}

// Initialize database connection
$db = new Database();

// Set timezone
date_default_timezone_set('Asia/Karachi');
?>