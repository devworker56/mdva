<?php
// admin/debug_qr_path.php
echo "<h2>Debug QR Path from admin folder</h2>";

// Show current directory
echo "__DIR__: " . __DIR__ . "<br>";
echo "Current file: " . __FILE__ . "<br><br>";

// Test the exact path we're using
$test_path = __DIR__ . '/../qrlib/phpqrcode/qrlib.php';
echo "Testing path: $test_path<br>";

if (file_exists($test_path)) {
    echo "✓ File exists!<br>";
    
    // Check file permissions
    $perms = fileperms($test_path);
    echo "Permissions: " . substr(sprintf('%o', $perms), -4) . "<br>";
    
    // Try to include
    try {
        require_once $test_path;
        echo "✓ File included successfully<br>";
        
        if (class_exists('QRcode')) {
            echo "✓ QRcode class found!<br>";
            
            // Test creating a QR code
            $test_qr_file = __DIR__ . '/../qr_codes/test_debug.png';
            QRcode::png('Test QR Code', $test_qr_file, QR_ECLEVEL_L, 10, 2);
            
            if (file_exists($test_qr_file)) {
                echo "✓ QR code created successfully!<br>";
                echo "<img src='../qr_codes/test_debug.png' alt='Test QR'><br>";
            }
        } else {
            echo "✗ QRcode class NOT found after include<br>";
        }
    } catch (Exception $e) {
        echo "✗ Error including file: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ File does not exist<br>";
    
    // Let's explore the directory structure
    echo "<h3>Exploring directory structure:</h3>";
    $qrlib_path = __DIR__ . '/../qrlib/';
    echo "Checking: $qrlib_path<br>";
    
    if (is_dir($qrlib_path)) {
        echo "✓ qrlib directory exists<br>";
        
        // List contents
        $files = scandir($qrlib_path);
        echo "Contents of qrlib:<br>";
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "- $file<br>";
            }
        }
    } else {
        echo "✗ qrlib directory does not exist<br>";
    }
}
?>