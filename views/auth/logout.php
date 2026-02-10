<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
session_destroy();
header("Location: " . BASE_URL . "/login");
exit;
?>
