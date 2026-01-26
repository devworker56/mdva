<?php
// Identify the protocol (http or https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

// Get the domain name (e.g., localhost or www.monsite.com)
$domain = $_SERVER['HTTP_HOST'];

// Get the project directory (e.g., /my_project_folder/)
// This line ensures that even if you move the project, the path updates automatically
$project_path = str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']);
$project_root = explode('/', trim($project_path, '/'))[0];

// Final Base URL (e.g., http://localhost/mdva_project/)
define('BASE_URL', $protocol . $domain . '/' . $project_root . '/');
?>