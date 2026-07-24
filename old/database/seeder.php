<?php
// database/seeder.php
require_once __DIR__ . '/../config/database.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Seeding database...\n";

// 1. Create Admin
$admin_email = 'admin@ppti.me';
$admin_pass = password_hash('admin', PASSWORD_DEFAULT);
$admin_name = 'Super Admin';
$admin_role = 'admin';
$admin_position = 'IT Administrator';

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role, position) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=name");
$stmt->bind_param("sssss", $admin_name, $admin_email, $admin_pass, $admin_role, $admin_position);

if ($stmt->execute()) {
    echo "Admin user seeded successfully.\n";
}
else {
    echo "Error seeding admin: " . $stmt->error . "\n";
}

// 2. Create Default User
$user_email = 'user@example.com';
$user_pass = password_hash('password', PASSWORD_DEFAULT);
$user_name = 'John Doe';
$user_role = 'user';
$user_position = 'Staff';

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role, position, signature_prefix) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=name");
$prefix = 'DS';
$stmt->bind_param("ssssss", $user_name, $user_email, $user_pass, $user_role, $user_position, $prefix);

if ($stmt->execute()) {
    echo "Default user seeded successfully.\n";
}
else {
    echo "Error seeding user: " . $stmt->error . "\n";
}

echo "Seeding completed.\n";
?>