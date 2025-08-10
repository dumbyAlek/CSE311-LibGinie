<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the Composer autoloader.
// The path is relative to the `crud` directory.
require '../../vendor/autoload.php';

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

// Configure Cloudinary with your credentials.
// NOTE: Make sure these credentials are correct.
Configuration::instance([
    'cloud' => [
        'cloud_name' => 'dde5qpyti',
        'api_key' => '393211155922398',
        'api_secret' => 'ewM6B4n9GNu0kknFgiV6y3JaelE'
    ],
    'url' => [
        'secure' => true
    ]
]);

header('Content-Type: application/json');

// Check if a file was uploaded and there were no errors
if (isset($_FILES['book_cover']) && $_FILES['book_cover']['error'] === UPLOAD_ERR_OK) {
    $file_path = $_FILES['book_cover']['tmp_name'];
    
    try {
        $uploadApi = new UploadApi();
        // The upload folder on Cloudinary will be `book_covers`
        $result = $uploadApi->upload($file_path, ['folder' => 'book_covers']);
        
        echo json_encode(['success' => true, 'url' => $result['secure_url']]);
    } catch (Exception $e) {
        // Log the exact error for debugging
        error_log('Cloudinary Upload Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()]);
    }
} else {
    // Log the file upload error code
    $error_message = 'No file uploaded or upload error.';
    if (isset($_FILES['book_cover'])) {
        $error_code = $_FILES['book_cover']['error'];
        $error_message .= " (Error Code: $error_code)";
    }
    error_log($error_message);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
}
?>