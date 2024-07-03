<?php
function sigmaCountYmlFiles($dir) {
    $sigmaResult = array();
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'yml') {
                $path = $file->getPath();
                if (!isset($sigmaResult[$path])) {
                    $sigmaResult[$path] = 0;
                }
                $sigmaResult[$path]++;
            }
        }
    }
    return $sigmaResult;
}

function sigmaGetFolderData($dir) {
    $sigmaResult = array();
    $sigmaFilesCount = sigmaCountYmlFiles($dir);
    foreach ($sigmaFilesCount as $folder => $count) {
        $relativePath = str_replace($dir . '/', '', $folder);
        $parts = explode('/', $relativePath);
        $mainFolder = $parts[0];
        if (!isset($sigmaResult[$mainFolder])) {
            $sigmaResult[$mainFolder] = 0;
        }
        $sigmaResult[$mainFolder] += $count;
    }
    return $sigmaResult;
}

if (isset($_GET['sigmaPath']) && !empty($_GET['sigmaPath'])) {
    $sigmaPath = $_GET['sigmaPath'];
} else {
    $sigmaPath = '/var/www/html/Downloaded/Sigma/rules';
}

$sigmaData = sigmaGetFolderData($sigmaPath);

header('Content-Type: application/json');
echo json_encode($sigmaData);
?>
