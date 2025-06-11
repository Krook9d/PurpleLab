<?php

error_reporting(0);

$conn_string = sprintf(
    "host=%s port=5432 dbname=%s user=%s password=%s",
    getenv('DB_HOST'),
    getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASS')
);

$conn = pg_connect($conn_string);

if (!$conn) {
    echo "0";
    exit();
}

function getCachedCount($cacheFile, $cacheLifetime = 86400) {
    global $conn;
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheLifetime) {
        return file_get_contents($cacheFile);
    } else {
        $query = "SELECT COUNT(*) AS count FROM users";
        $result = pg_query($conn, $query);
        
        if ($result && $row = pg_fetch_assoc($result)) {
            $count = $row['count'];
            
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            
            file_put_contents($cacheFile, $count);
            pg_free_result($result);
            return $count;
        } else {
            return 0;
        }
    }
}

$cacheFile = '/var/www/html/cache/active_users_count.cache';
$numberOfUsers = getCachedCount($cacheFile);

pg_close($conn);

echo $numberOfUsers;

?>
