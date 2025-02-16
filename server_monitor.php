<?php
// Unified PHP File with Real-Time Updates and Logs

// Set headers
header('Content-Type: text/html; charset=utf-8');

// Log files and paths
$activityLog = 'logs/activity.log'; // Log file for file activities
$fatalLog = 'logs/fatal.log';       // Log file for fatal errors
$warningLog = 'logs/warning.log';   // Log file for warnings
$noticeLog = 'logs/notice.log';     // Log file for notices
$customLog = 'logs/custom.log';     // Log file for custom errors
$assetsDir = __DIR__ . '/assets'; // Path to assets folder
$indexFile = __DIR__ . '/index.php'; // Path to index.php

// Initialize logs
if (!file_exists($activityLog)) file_put_contents($activityLog, "Activity Log Initialized\n");
if (!file_exists($fatalLog)) file_put_contents($fatalLog, "No Fatal Logs Found\n");
if (!file_exists($warningLog)) file_put_contents($warningLog, "No Warning Logs Found\n");
if (!file_exists($noticeLog)) file_put_contents($noticeLog, "No Notice Logs Found\n");
if (!file_exists($customLog)) file_put_contents($customLog, "No Custom Logs Found\n");

// Function to log activities
function logActivity($message) {
    global $activityLog;
    $time = date('Y-m-d H:i:s');
    file_put_contents($activityLog, "[$time] $message\n", FILE_APPEND);
}

// Monitor files: index.php and assets folder
function checkFileExistence($filePath) {
    return file_exists($filePath) ? 'exists' : 'missing';
}

// Function to check folder permissions
function checkFolderPermissions($folderPath) {
    return is_writable($folderPath) ? 'writable' : 'not writable';
}

$filesToCheck = [
    'index.php' => $indexFile,
    'ordercode.php' => $assetsDir . '/ordercode.php',
    'message.php' => $assetsDir . '/message.php',
    'login.php' => $assetsDir . '/login.php',
    'gemini.php' => $assetsDir . '/gemini.php',
    'db.php' => $assetsDir . '/db.php',
    'admin.php' => $assetsDir . '/admin.php',
    'config.yml' => $assetsDir . '/config.yml',
    'endpoint.php' => __DIR__ . '/endpoint.php', // New File
    'endpoint.php.template' => $assetsDir . '/endpoint.php.template', // New File
    'config.yml.template' => $assetsDir . '/config.yml.template', // New File
    'install.php' => $assetsDir . '/install.php', // New File
    'prefix.json' => $assetsDir . '/prefix.json', // New File
    'assets/js/chat.js' => $assetsDir . '/js/chat.js',
    'assets/css/admin-style.css' => $assetsDir . '/css/admin-style.css',
    'assets/css/login.css' => $assetsDir . '/css/login.css',
    'assets/css/style.css' => $assetsDir . '/css/style.css',
    'assets/css/style2.css' => $assetsDir . '/css/style2.css',
];

// Check file existence
$fileStatuses = [];
foreach ($filesToCheck as $fileName => $filePath) {
    if (checkFileExistence($filePath) === 'missing') {
        $fileStatuses[$fileName] = 'missing';
    }
}

// Folder Permissions Check
$foldersToCheck = [
    'assets Folder' => $assetsDir,
    'assets/css Folder' => $assetsDir . '/css',
    'assets/js Folder' => $assetsDir . '/js',
    'logs Folder' => __DIR__ . '/logs', // Assuming logs folder is in the same directory as server_monitor.php
];

$folderPermissions = [];
foreach ($foldersToCheck as $folderName => $folderPath) {
    $folderPermissions[$folderName] = checkFolderPermissions($folderPath);
}


// Server Info
$phpVersion = phpversion();
$hostOS = PHP_OS;
// Modify disk check to use a directory within the allowed path
$diskTotal = disk_total_space('/home/ixfvzerb');
$diskFree = disk_free_space('/home/ixfvzerb');
$isConfigWritable = is_writable(__FILE__) ? "Yes" : "No";

// HTTP Status Check
$http_status_code = 200;
$errorMessage = 'No HTTP Error';

$headers = @get_headers("https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
if(isset($headers[0])){
    preg_match('/HTTP\/1\.1 (\d{3})/', $headers[0], $matches);
    if (isset($matches[1])) {
        $http_status_code = intval($matches[1]);
        if ($http_status_code >= 400) {
            $errorMessage = "HTTP Error: " . $http_status_code;
        }
    } else {
        $errorMessage = "Could not determine HTTP status";
    }
} else {
    $errorMessage = "Failed to get headers";
}


// Format disk space
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $power = floor(($bytes ? log($bytes) : 0) / log(1024));
    $power = min($power, count($units) - 1);
    $bytes /= pow(1024, $power);
    return round($bytes, $precision) . ' ' . $units[$power];
}

$diskUsage = formatBytes($diskTotal - $diskFree);
$totalDisk = formatBytes($diskTotal);

// CPU Usage (Linux only)
function getCpuUsage() {
    $load = sys_getloadavg(); // Average CPU load (1, 5, 15 minutes)
    return "CPU Usage: {$load[0]}% (1 min), {$load[1]}% (5 min), {$load[2]}% (15 min)";
}

// RAM Usage
function getRamUsage() {
    $ramUsage = memory_get_usage();
    return formatBytes($ramUsage);
}

// Cron Job Console (try an alternative check for cron jobs if shell_exec() is unavailable)
function getCronJobInfo() {
    // Fallback method: if shell_exec is disabled, try reading the crontab from a file or another method
    $cronFile = '/var/spool/cron/crontabs/username'; // Replace with the correct file path
    if (file_exists($cronFile)) {
        return file_get_contents($cronFile);
    } else {
        return 'No cron jobs found or permission denied.';
    }
}

$cpuUsage = getCpuUsage();
$ramUsage = getRamUsage();
$cronJobInfo = getCronJobInfo();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Monitoring Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            background: #fff;
            margin: 10px 0;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #555;
        }
        .log-section pre {
            background: #f9f9f9;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
        }
        .info-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .info-box {
            flex: 1 1 45%;
            background: #e0f7fa; /* Light blue for normal state */
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .info-box.error {
            background: #ffebee; /* Light red background for error */
            border: 1px solid #f44336; /* Red border for errors */
        }
        .info-box i {
            margin-right: 10px;
        }
        .info-box strong {
            color: #555;
        }
        .info-box i.fa-check-circle {
            color: #4caf50; /* Green color for success */
        }
        .info-box i.fa-times-circle {
            color: #f44336; /* Red color for error */
        }
        .info-box.error i.fa-check-circle {
            color: #ffeb3b; /* Yellow color for warning in error state */
        }
        .info-box.error i.fa-times-circle {
            color: #f44336; /* Red color for error icon */
        }
        .console {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            margin-top: 20px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
         .log-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(45%, 1fr)); /* Two columns, adjust minmax as needed */
            gap: 20px; /* Space between grid items */
        }

        .log-section, .log-title {
            margin-bottom: 10px; /* Space below each log section */
        }

        .log-title {
            font-weight: bold;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Server Monitoring Dashboard</h1>
            <p>Track server, CPU, RAM, file activities, and logs.</p>
        </div>

        <!-- Server Info -->
        <div class="card">
            <h2>Environment Information</h2>
            <div class="info-grid">
                <div class="info-box <?= $phpVersion < 7.4 ? 'error' : '' ?>">
                    <i class="fa <?= $phpVersion < 7.4 ? 'fa-times-circle' : 'fa-check-circle' ?>"></i>
                    <strong>PHP Version:</strong> <?= $phpVersion ?>
                </div>
                <div class="info-box <?= $errorMessage === 'No HTTP Error' ? '' : 'error' ?>">
                    <i class="fa <?= $errorMessage === 'No HTTP Error' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                    <strong>HTTP Status:</strong> <?= $errorMessage ?>
                </div>
                <div class="info-box">
                    <strong>Host OS:</strong> <?= $hostOS ?>
                </div>
                <div class="info-box">
                    <strong>Disk Usage:</strong> <?= $diskUsage ?> / <?= $totalDisk ?>
                </div>
                <div class="info-box">
                    <strong>Config Writable:</strong> <?= $isConfigWritable ?>
                </div>
                <div class="info-box">
                    <strong><?= $cpuUsage ?></strong>
                </div>
                <div class="info-box">
                    <strong>RAM Usage:</strong> <?= $ramUsage ?>
                </div>
            </div>
        </div>

        <!-- File Status -->
        <div class="card">
            <h2>Files Status</h2>
            <div class="info-grid">
                <?php foreach ($fileStatuses as $fileName => $status): ?>
                    <div class="info-box error">
                        <i class="fa fa-times-circle"></i>
                        <strong><?= $fileName ?>:</strong> Missing
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

         <!-- Folder Permissions Status -->
        <div class="card">
            <h2>Folder Permissions</h2>
            <div class="info-grid">
                <?php foreach ($folderPermissions as $folderName => $permissionStatus): ?>
                    <div class="info-box <?= $permissionStatus === 'writable' ? '' : 'error' ?>">
                        <i class="fa <?= $permissionStatus === 'writable' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                        <strong><?= $folderName ?>:</strong> <?= ucfirst($permissionStatus) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Logs -->
        <div class="card log-container">
            <div class="log-title">Fatal Log</div>
            <div class="log-section"><pre><?= htmlspecialchars(file_get_contents($fatalLog)) ?></pre></div>

            <div class="log-title">Warning Log</div>
            <div class="log-section"><pre><?= htmlspecialchars(file_get_contents($warningLog)) ?></pre></div>

            <div class="log-title">Notice Log</div>
            <div class="log-section"><pre><?= htmlspecialchars(file_get_contents($noticeLog)) ?></pre></div>

            <div class="log-title">Custom Log</div>
            <div class="log-section"><pre><?= htmlspecialchars(file_get_contents($customLog)) ?></pre></div>
        </div>


        <!-- Console for Cron Jobs -->
        <div class="card">
            <h2>Cron Jobs Console</h2>
            <div class="console">
                <pre><?= htmlspecialchars($cronJobInfo) ?></pre>
            </div>
        </div>
    </div>

    <script>
        // Auto-update system info every 10 second
        setInterval(function() {
            fetch('server_monitor.php')
                .then(response => response.text())
                .then(data => {
                    document.body.innerHTML = data;
              });
        }, 10000);
    </script>
</body>
</html>