<?php
$logFile = __DIR__ . '/../logs/cron.log';
$logMessage = date('[Y-m-d H:i:s]') . " --- Test log entry --- \n";

echo "Attempting to write to: " . realpath(dirname($logFile)) . '/' . basename($logFile) . "\n";

if (file_put_contents($logFile, $logMessage, FILE_APPEND)) {
    echo "Successfully wrote to log file.\n";
} else {
    echo "Failed to write to log file. Check permissions and path.\n";
}
?>
