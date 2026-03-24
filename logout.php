<?php
require_once 'includes/config.php';
session_destroy();
$base_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
header("Location: $base_dir/index.php");
exit;
