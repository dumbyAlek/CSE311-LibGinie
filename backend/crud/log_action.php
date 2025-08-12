<?php
// Function to log an action to a text file in the project's root directory
function log_action($user_id, $action_type, $description) {
    // Define the log file path, assuming it's in the project's root
    $log_file = __DIR__ . '../../../log.txt';
    
    // Get the current date and time
    $timestamp = date('Y-m-d H:i:s');
    
    // Format the log entry
    $log_entry = sprintf("[%s] [UserID: %s] [%s]: %s\n", $timestamp, $user_id, $action_type, $description);
    
    // Append the log entry to the file
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
?>
