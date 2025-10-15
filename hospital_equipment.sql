-- Simplified Hospital Equipment Management System Database

-- Table for storing equipment information
CREATE TABLE equipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(100),
    location VARCHAR(100),
    area VARCHAR(100),
    frequency VARCHAR(20),         -- replaced ENUM with VARCHAR
    due_date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table for storing inspection records
CREATE TABLE inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id VARCHAR(50) NOT NULL,
    inspector_name VARCHAR(100) NOT NULL,
    q1_safety_pin VARCHAR(3) NOT NULL,         -- Yes/No stored as text
    q2_gauge_green VARCHAR(3) NOT NULL,
    q3_weight_appropriate VARCHAR(3) NOT NULL,
    q4_no_damage VARCHAR(3) NOT NULL,
    q5_hanging_clip VARCHAR(3) NOT NULL,
    q6_accessible VARCHAR(3) NOT NULL,
    q7_refill_overdue VARCHAR(3) NOT NULL,
    q8_instructions_visible VARCHAR(3) NOT NULL,
    remarks TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(equipment_id)
);

-- Table for storing user credentials
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'inspector',    -- replaced ENUM with VARCHAR
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Default inspector account
INSERT INTO users (name, password_hash, role) VALUES
('Inspector', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'inspector');

-- Sample equipment data
INSERT INTO equipments (equipment_id, type, location, area, frequency, due_date) VALUES
('EQ001', 'Fire Extinguisher', 'Emergency Ward', 'Ward A', 'Monthly', '2025-08-28'),
('EQ002', 'Fire Extinguisher', 'Operation Theater', 'OT-1', 'Monthly', '2025-08-15'),
('EQ003', 'Safety Equipment', 'ICU', 'ICU-A', 'Quarterly', '2025-10-28'),
('EQ004', 'Emergency Kit', 'Reception', 'Ground Floor', 'Bi-Annually', '2026-01-28');

/*-- Simplified Hospital Equipment Management System Database

-- Table for storing equipment information
CREATE TABLE equipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(100),
    location VARCHAR(100),
    area VARCHAR(100),
    frequency VARCHAR(20),        
    due_date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table for storing inspection records
CREATE TABLE inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id VARCHAR(50) NOT NULL,
    inspector_name VARCHAR(100) NOT NULL,
    q1_safety_pin VARCHAR(3) NOT NULL,         
    q2_gauge_green VARCHAR(3) NOT NULL,
    q3_weight_appropriate VARCHAR(3) NOT NULL,
    q4_no_damage VARCHAR(3) NOT NULL,
    q5_hanging_clip VARCHAR(3) NOT NULL,
    q6_accessible VARCHAR(3) NOT NULL,
    q7_refill_overdue VARCHAR(3) NOT NULL,
    q8_instructions_visible VARCHAR(3) NOT NULL,
    remarks TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(equipment_id)
);

-- Table for storing user credentials
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'inspector',    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Default inspector account
INSERT INTO users (name, password_hash, role) VALUES
('Inspector', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'inspector');

-- Sample equipment data
INSERT INTO equipments (equipment_id, type, location, area, frequency, due_date) VALUES
('EQ001', 'Fire Extinguisher', 'Emergency Ward', 'Ward A', 'Monthly', '2025-08-28'),
('EQ002', 'Fire Extinguisher', 'Operation Theater', 'OT-1', 'Monthly', '2025-08-15'),
('EQ003', 'Safety Equipment', 'ICU', 'ICU-A', 'Quarterly', '2025-10-28'),
('EQ004', 'Emergency Kit', 'Reception', 'Ground Floor', 'Bi-Annually', '2026-01-28');
*/