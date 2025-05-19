  <!-- start Connexion -->
<?php

error_reporting(0);

session_start();

if (!isset($_SESSION['email'])) {
    header('Location: connexion.html');
    exit();
}

// Connexion PostgreSQL
$conn_string = sprintf(
    "host=%s port=5432 dbname=%s user=%s password=%s",
    getenv('DB_HOST'),
    getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASS')
);

$conn = pg_connect($conn_string);

if (!$conn) {
    die("PostgreSQL connection failure");
}

$email = $_SESSION['email'];

$sql = "SELECT first_name, last_name, email, analyst_level, avatar FROM users WHERE email=$1";
$result = pg_query_params($conn, $sql, array($email));

if ($result && $row = pg_fetch_assoc($result)) {
    $first_name = $row['first_name'];
    $last_name = $row['last_name'];
    $email = $row['email'];
    $analyst_level = $row['analyst_level'];
    $avatar = $row['avatar'];
} else {
    die("Error retrieving user information.");
}

$chartDataQuery = "SELECT tactic, COUNT(technique_id) AS count FROM atomic_tests GROUP BY tactic";
$chartDataResult = pg_query($conn, $chartDataQuery);

if (!$chartDataResult) {
    die("Erreur de requête: " . pg_last_error($conn));
}

$chartData = array();
$tactics = array();
$testCounts = array();

while ($row = pg_fetch_assoc($chartDataResult)) {
    $tactics[] = $row['tactic'];
    $testCounts[] = $row['count'];
}

pg_free_result($result);
pg_free_result($chartDataResult);
pg_close($conn);
?>
<!-- End Connexion -->

  
<?php



$username = 'elastic';
$elasticPassword = getenv('ELASTIC_PASSWORD');

// KPI 1 gauge request

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://localhost:9200/winlogbeat-8.10.4/_count');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $elasticPassword);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For test environments with self-signed certificates

$response = curl_exec($ch);

$nbEventVMWin = null;

if (curl_errno($ch)) {
    $nbEventVMWin = 'Erreur cURL : ' . curl_error($ch);
} else {
    $resultArray = json_decode($response, true);
    if (isset($resultArray['count'])) {
        $nbEventVMWin = $resultArray['count'];
    } else {
        $nbEventVMWin = 'Error: Unable to retrieve total number of events';
    }
}

curl_close($ch);


//request KPI 2
$ch2 = curl_init();
// Set the URL
curl_setopt($ch2, CURLOPT_URL, 'https://localhost:9200/filebeat*/_search');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_USERPWD, $username . ':' . $elasticPassword);
curl_setopt($ch2, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false); // For test environments with self-signed certificates

// Set the method to POST
curl_setopt($ch2, CURLOPT_POST, true);

// Specify the data you want to send via POST
$data = json_encode([
    "size" => 0, // No documents to return, only aggregations
    "aggs" => [
        "unique_source_ips" => [
            "cardinality" => [
                "field" => "source_ip"
            ]
        ],
        "unique_destination_ips" => [
            "cardinality" => [
                "field" => "destination_ip"
            ]
        ]
    ]
]);

curl_setopt($ch2, CURLOPT_POSTFIELDS, $data); // Set the POST fields

// Execute the request and capture the response
$NbIp = curl_exec($ch2);

if (curl_errno($ch2)) {
    echo 'Error:' . curl_error($ch2);
} else {
    // Parse the JSON response to get the aggregation data
    $response_data = json_decode($NbIp, true);

    // Utilisez l'opérateur de coalescence nulle pour éviter les erreurs si les clés n'existent pas
    $unique_source_ips = $response_data['aggregations']['unique_source_ips']['value'] ?? 0;
    $unique_destination_ips = $response_data['aggregations']['unique_destination_ips']['value'] ?? 0;
}

// Close the connection
curl_close($ch2);
$unique_ip = $unique_source_ips + $unique_destination_ips;

// Query for distinct values of winlog.event_data.OriginalFileName
$ch4 = curl_init();

// Query configuration for specific index
curl_setopt($ch4, CURLOPT_URL, 'https://localhost:9200/winlogbeat-8.10.4/_search');
curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch4, CURLOPT_USERPWD, $username . ':' . $elasticPassword);
curl_setopt($ch4, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch4, CURLOPT_SSL_VERIFYPEER, false); // For test environments with self-signed certificates

curl_setopt($ch4, CURLOPT_POST, true);

$data = json_encode([
    "size" => 0, 
    "aggs" => [
        "original_filenames" => [
            "terms" => [
                "field" => "winlog.event_data.NewProcessName", 
                "size" => 10000 // Adjust according to the number of distinct values expected
            ]
        ]
    ]
]);

// Send request
curl_setopt($ch4, CURLOPT_POSTFIELDS, $data);
$responseOriginalFileNames = curl_exec($ch4);
curl_close($ch4);

// Response processing
$result = json_decode($responseOriginalFileNames, true);
if (isset($result['aggregations']['original_filenames']['buckets'])) {
    $originalFileNames = array_slice($result['aggregations']['original_filenames']['buckets'], 0, 25); 

}
?>

<?php
$wordCloudData = [];
if (is_array($originalFileNames)) {
    foreach ($originalFileNames as $filename) {
        $wordCloudData[] = [
            'tag' => htmlspecialchars($filename['key']),
            'count' => $filename['doc_count']
        ];
    }
}
?>


<?php
// Query for distinct values of winlog.event_data.OriginalFileName
$ch5 = curl_init();

curl_setopt($ch5, CURLOPT_URL, 'https://localhost:9200/winlogbeat-8.10.4/_search');
curl_setopt($ch5, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch5, CURLOPT_USERPWD, $username . ':' . $elasticPassword);
curl_setopt($ch5, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch5, CURLOPT_SSL_VERIFYPEER, false); // For test environments with self-signed certificates


curl_setopt($ch5, CURLOPT_POST, true);

$data = json_encode([
    "size" => 0, 
    "aggs" => [
        "host.ip" => [
            "terms" => [
                "field" => "host.ip", 
                "size" => 10000 
            ]
        ]
    ]
]);

// Send request
curl_setopt($ch5, CURLOPT_POSTFIELDS, $data);
$responseOriginalFileNames = curl_exec($ch5);
curl_close($ch5);

// Response processing
$resulthostip = json_decode($responseOriginalFileNames, true);
if (isset($resulthostip['aggregations']['host.ip']['buckets'])) {
    $host_ip = array_slice($resulthostip['aggregations']['host.ip']['buckets'], 0, 25); 

    // Iterate through the $host_ip array and remove IPv6 addresses
    foreach ($host_ip as $key => $value) {
        // Assuming the IP address is in $value['key'], adjust according to your data structure
        $ip = $value['key']; 

        // Check if the IP address is an IPv6 address
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // If it's an IPv6 address, remove it from the array
            unset($host_ip[$key]);
        }
    }

    // Re-index the array after removing elements
    $host_ip = array_values($host_ip);
}

?>

<?php
// Query to count the number of events per agent.type on all indexes
$ch6 = curl_init();

// Configure query to target all indexes
curl_setopt($ch6, CURLOPT_URL, 'https://localhost:9200/_search'); 
curl_setopt($ch6, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch6, CURLOPT_USERPWD, $username . ':' . $elasticPassword);
curl_setopt($ch6, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch6, CURLOPT_SSL_VERIFYPEER, false); 


curl_setopt($ch6, CURLOPT_POST, true);


$data = json_encode([
    "size" => 0, 
    "aggs" => [
        "agent_types" => [
            "terms" => [
                "field" => "agent.type", 
                "size" => 10000 
            ]
        ]
    ]
]);

curl_setopt($ch6, CURLOPT_POSTFIELDS, $data);
$responseAgentTypes = curl_exec($ch6);
curl_close($ch6);

$result = json_decode($responseAgentTypes, true);
if (isset($result['aggregations']['agent_types']['buckets'])) {
    $agentTypes = array_map(function ($bucket) {
        return [
            'agent_type' => $bucket['key'],
            'count' => $bucket['doc_count']
        ];
    }, $result['aggregations']['agent_types']['buckets']);
    
}





$ch7 = curl_init();

curl_setopt($ch7, CURLOPT_URL, 'https://localhost:9200/*/_search');
curl_setopt($ch7, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch7, CURLOPT_USERPWD, $username . ':' . $elasticPassword);
curl_setopt($ch7, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch7, CURLOPT_SSL_VERIFYPEER, false); 

$data7 = json_encode([
    "size" => 0,
    "aggs" => [
        "source_ips" => [
            "terms" => [
                "field" => "source_ip",
                "size" => 10
            ],
            "aggs" => [
                "destination_ips" => [
                    "terms" => [
                        "field" => "destination_ip",
                        "size" => 10
                    ],
                    "aggs" => [
                        "traffic_count" => [
                            "value_count" => [
                                "field" => "destination_ip" 
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]
]);

curl_setopt($ch7, CURLOPT_POSTFIELDS, $data7);

$response7 = curl_exec($ch7);
curl_close($ch7);

$responseData7 = json_decode($response7, true);
$graphData = [];

if (isset($responseData7['aggregations']['source_ips']['buckets'])) {
    foreach ($responseData7['aggregations']['source_ips']['buckets'] as $sourceBucket) {
        $sourceIp = $sourceBucket['key'];
        foreach ($sourceBucket['destination_ips']['buckets'] as $destinationBucket) {
            $destinationIp = $destinationBucket['key'];
            $trafficCount = $destinationBucket['traffic_count']['value'];
            $graphData[] = ['from' => $sourceIp, 'to' => $destinationIp, 'value' => $trafficCount];
        }
    }
}


$sigmaDirectory = 'Downloaded/Sigma/rules/';
$sigmaFileCount = 0;
$sigmaFiles = array();
function countYmlFiles($path) {
    global $sigmaFileCount, $sigmaFiles;
    $filesInDirectory = scandir($path);
    foreach ($filesInDirectory as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $path . '/' . $file;
            if (is_dir($filePath)) {
                countYmlFiles($filePath);
            } elseif (pathinfo($file, PATHINFO_EXTENSION) == 'yml' && !isset($sigmaFiles[$file])) {
                $sigmaFiles[$file] = true;
                $sigmaFileCount++;
            }
        }
    }
}
countYmlFiles($sigmaDirectory);

?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="MD_image/logowhite.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="css/main.css?v=<?= filemtime('css/main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/raphael/2.3.0/raphael.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/justgage@1.3.2/dist/justgage.min.js"></script>
    <script src="https://cdn.amcharts.com/lib/4/core.js"></script>
    <script src="https://cdn.amcharts.com/lib/4/charts.js"></script>
    <script src="https://cdn.amcharts.com/lib/4/plugins/wordCloud.js"></script>
    <script src="https://cdn.amcharts.com/lib/4/themes/animated.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/flow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/hierarchy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Ajout des bibliothèques pour les nouveaux KPI -->
    <script src="https://cdn.amcharts.com/lib/5/map.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldLow.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>




    <script>
  document.addEventListener("DOMContentLoaded", function(event) {
    var nbEventVMWin = <?php echo json_encode($nbEventVMWin ?? 0); ?>;
    var g = new JustGage({
      id: "gauge",
      value: nbEventVMWin,
      min: 0,
      max: 500000, 
      title: "Total Event from Windows VM",
      label: "",
      labelFontColor: "#FFFFFF", 
      valueFontColor: "#FFFFFF", 
      levelColors: [
        "#7B82F5", 
        "#5865F2", 
        "#FF0000"  
      ],
      titleFontColor: "#FFFFFF" 
    });
  });
</script>




</head>
<body>

<div class="nav-bar">
        <!-- Add logo to top of nav-bar -->
    <div class="nav-logo">
        <img src="MD_image/logowhite.png" alt="Logo" /> 
    </div>

    <!-- Display software version -->
    <?php include $_SERVER['DOCUMENT_ROOT'].'/scripts/php/version.php'; ?>
        <div class="software-version">
        <?php echo SOFTWARE_VERSION; ?>
    </div>

    <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="http://<?= $_SERVER['SERVER_ADDR'] ?>:5601" target="_blank"><i class="fas fa-crosshairs"></i> Hunting</a></li>
        <li><a href="mittre.php"><i class="fas fa-book"></i> Mitre Att&ck</a></li>
        <li><a href="custom_payloads.php"><i class="fas fa-code"></i> Custom Payloads</a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> Malware</a></li>
        <li><a href="simulation.php"><i class="fas fa-project-diagram"></i> Log Simulation</a></li>
        <li><a href="usecase.php"><i class="fas fa-lightbulb"></i> UseCase</a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> Sharing</a></li>
        <li><a href="sigma.php"><i class="fas fa-shield-alt"></i> Sigma Rules</a></li>
        <li><a href="rule_lifecycle.php" class="active"><i class="fas fa-cogs"></i> Rule Lifecycle</a></li>
        <li><a href="health.php"><i class="fas fa-heartbeat"></i> Health</a></li>
         <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@local.com'): ?>
        <li><a href="admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
    <?php endif; ?>
    </ul>

    <!-- Container for credits at the bottom of the nav-bar -->
    <div class="nav-footer">
        <a href="https://github.com/Krook9d" target="_blank">
            <img src="https://pngimg.com/uploads/github/github_PNG20.png" alt="GitHub Icon" class="github-icon"/> 
            Made by Krook9d
        </a>
    </div>


</div>

    <div class="user-info-bar">
        <div class="avatar-info">
            <img src="<?= $avatar ?>" alt="Avatar">
            <button id="user-button" class="user-button">
            <span><?= $first_name ?> <?= $last_name ?></span>
                <div class="dropdown-content">
                <a href="index.php" id="settings-link">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </button>
        </div>
    </div>

<div class="content">
 
<div class="index-dashboard">

<div class="index-card">
    <div id="gauge" class="gauge-container"></div>
    <div class="kpi-title">Total Event from Windows VM</div>
</div>

<div class="index-card">
    <img src="/uploads/adresse-ip.png" style="width: 80px; height: auto;">
    <div class="kpi-number" id="kpiNumber"><?php echo $unique_ip; ?></div>
    <div class="kpi-title">Unique IP Addresses</div>
</div>

<div class="index-card">
    <img src="/uploads/sigma.png" style="width: 80px; height: auto;">
    <div class="kpi-number" id="sigmaRulesKpiNumber"><?php echo $sigmaFileCount; ?></div>
    <div class="kpi-title">Sigma rules</div>
</div>

<div class="index-card">
    <img src="/uploads/book.png" style="width: 80px; height: auto;">
    <div class="kpi-number" id="mitre-technique-count">Loading...</div>
    <div class="kpi-title">Mitre Attack technique</div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    fetch('/scripts/php/getTechniqueCount.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('mitre-technique-count').innerText = data;
        })
        .catch(error => console.error('Erreur:', error));
});
</script>



<div class="index-card">
    <div id="chartdiv" style="width: 100%; height: 170px;"></div>
    <div class="kpi-title">sourcetype collected</div>
</div>


</div>
<br>
<div class="health-section-separator"></div>


<div class="index-dashboard">


<div class="index-card">
    <div style="width: 100%; height: 350px;"><canvas id="sigmaRulesChart"></canvas></div>
    
</div>

<script>
    async function fetchSigmaData(sigmaPath = '') {
        const response = await fetch(`scripts/php/getSigmaData.php?sigmaPath=${sigmaPath}`);
        const sigmaData = await response.json();
        return sigmaData;
    }

    function createSigmaChart(labels, data) {
        const ctx = document.getElementById('sigmaRulesChart').getContext('2d');
        if (window.sigmaChart) {
            window.sigmaChart.destroy();
        }
        window.sigmaChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Number of sigma rules by category',
                    data: data,
                    backgroundColor: 'rgba(0, 255, 255, 0.2)',
                    borderColor: 'rgba(0, 255, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                onClick: (e, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const label = labels[index];
                        fetchSigmaData(`/var/www/html/Downloaded/Sigma/rules/${label}`).then(updateSigmaChart);
                    }
                },

                animation: {
                    duration: 1500 
                    },

                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#cbd3da'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#cbd3da'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#ffffff'
                        }
                    }
                }
            }
        });
    }

    function updateSigmaChart(sigmaData) {
        const labels = Object.keys(sigmaData);
        const counts = Object.values(sigmaData);
        createSigmaChart(labels, counts);
    }

    fetchSigmaData().then(updateSigmaChart);
</script>


<div class="index-card">
    <div style="width: 100%; height: 350px;"><canvas id="atomicTestsChart"></canvas></div>
</div>

<script>
    const ctx = document.getElementById('atomicTestsChart').getContext('2d');
    const atomicTestsChart = new Chart(ctx, {
        type: 'bar', 
        data: {
            labels: <?php echo json_encode($tactics); ?>,
            datasets: [{
                label: 'Number of Atomic Tests by category',
                data: <?php echo json_encode($testCounts); ?>,
                backgroundColor: 'rgba(0, 255, 255, 0.2)', 
                borderColor: 'rgba(0, 255, 255, 1)', 
                borderWidth: 1 
            }]
        },
        options: {
            animation: {
                duration: 1500 
            },
            scales: {
                y: {
                    beginAtZero: true, 
                    ticks: {
                        color: '#cbd3da' 
                    }
                },
                x: {
                    ticks: {
                        color: '#cbd3da' 
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff' 
                    }
                }
            }
        }
    });
</script>



</div>


<br><br>
<div class="health-section-separator"></div>

<div class="index-dashboard">
    <div class="index-card">
        <div style="width: 100%; height: 350px;"><canvas id="chart-threats"></canvas></div>
        <div class="kpi-title">Main Threat Types <span class="time-scale">(Last 24h)</span></div>
    </div>

    <div class="index-card">
        <div style="width: 100%; height: 350px;"><canvas id="chart-targets"></canvas></div>
        <div class="kpi-title">Most Targeted Industries <span class="time-scale">(Last 24h)</span></div>
    </div>
    
    <div class="index-card">
        <div style="width: 100%; height: 350px;"><canvas id="chart-cves"></canvas></div>
        <div class="kpi-title">Most Exploited CVEs <span class="time-scale">(Last 24h)</span></div>
        <div id="cve-values" class="cve-values"></div>
    </div>
</div>

<br><br>
<div class="health-section-separator"></div>

<div class="latest-threats">
    <h2 class="chart-title">Latest Detected Threats</h2>
    <div id="latest-threats-container"></div>
</div>

<br><br>
<div class="health-section-separator"></div>

<div class="index-dashboard">
    <div class="index-card" id="index-card-sankey">
        <div id="world-map" style="width: 95%; height: 500px;"></div>
        <div class="kpi-title">Global Threat Map</div>
    </div>
</div>

</div>
            

<!-- word-cloud-graph -->
<script>
am4core.ready(function() {
    
    am4core.useTheme(am4themes_animated);
    

    var chart = am4core.create("wordcloud", am4plugins_wordCloud.WordCloud);
    var series = chart.series.push(new am4plugins_wordCloud.WordCloudSeries());

    series.accuracy = 4;
    series.step = 15;
    series.rotationThreshold = 0.7;
    series.maxCount = 200;
    series.minWordLength = 2;
    series.labels.template.tooltipText = "{word}: {value}";
    series.fontFamily = "Courier New";
    series.maxFontSize = am4core.percent(30);

    series.data = <?php echo json_encode($wordCloudData); ?>;

    series.dataFields.word = "tag";
    series.dataFields.value = "count";

    series.labels.template.adapter.add("fill", function(fill, target) {
        var value = target.dataItem.value;
        if (value > 100) {
            return am4core.color("#5865F2"); 
        } else if (value > 50) {
            return am4core.color("#A0AEC0"); 
        } else {
            return am4core.color("#b9bcf9"); 
        }
    });

});
</script>





<script>
am4core.ready(function() {

    am4core.useTheme(am4themes_animated);

    var chart = am4core.create("chartdiv", am4charts.PieChart);
    chart.hiddenState.properties.opacity = 0; 

    chart.data = chartData;

    chart.radius = am4core.percent(70);
    chart.innerRadius = am4core.percent(40);
    chart.startAngle = 180;
    chart.endAngle = 360;  

    var series = chart.series.push(new am4charts.PieSeries());
    series.dataFields.value = "count";
    series.dataFields.category = "agent_type";

    series.slices.template.cornerRadius = 10;
    series.slices.template.innerCornerRadius = 7;
    series.slices.template.draggable = true;
    series.slices.template.inert = true;
    series.alignLabels = false;

    series.hiddenState.properties.startAngle = 90;
    series.hiddenState.properties.endAngle = 90;

    series.labels.template.fontSize = 10; 
    series.labels.template.text = "{category}: {value.percent.formatNumber('#.0')}%"; 

    // Set the colors for the slices
    series.colors.list = [
        am4core.color("#7B82F5"), 
        am4core.color("#0056b3"),  
        am4core.color("#FF0000")
        
    ];

    
    series.labels.template.fill = am4core.color("#FFFFFF");
}); 
</script>






<script>
    var chartData = <?php echo json_encode($agentTypes); ?>;
</script>

<script>
var bubbleData = <?php 
    $bubbles = [];
    if (is_array($host_ip)) {
        foreach ($host_ip as $index => $item) {
            array_push($bubbles, [
                "x" => rand(10, 20), 
                "y" => rand(10, 90),              
                "r" => 20,  
                "ip" => $item['key'], 
                "backgroundColor" => 'rgba(' . rand(0, 255) . ', ' . rand(0, 255) . ', ' . rand(0, 255) . ', 0.5)' 
            ]);
        }
    }
    echo json_encode($bubbles);
?>;
</script>


<script>
var bubbleData = <?php 
    $bubbles = [];
    if (is_array($host_ip)) {
        foreach ($host_ip as $index => $item) {
            array_push($bubbles, [
                "x" => rand(20, 80), 
                "y" => rand(20, 80),               
                "r" => 20,  
                "ip" => $item['key'], 
                "backgroundColor" => 'rgba(' . rand(0, 255) . ', ' . rand(0, 255) . ', ' . rand(0, 255) . ', 0.5)' 
            ]);
        }
    }
    echo json_encode($bubbles);
?>;
</script>

<script>
var bubbleCtx = document.getElementById('myBubbleChart').getContext('2d');
var myBubbleChart = new Chart(bubbleCtx, {
    type: 'bubble',
    data: {
        datasets: [{
            data: bubbleData,
            backgroundColor: function(context) {
                
                if (context && context.raw) {
                    return context.raw.backgroundColor || 'rgba(0, 0, 0, 0.1)';
                }
                return 'rgba(0, 0, 0, 0.1)';
            }
        }]
    },
    options: {
        scales: {
            x: {
                display: false,
                min: 0,
                max: 100
            },
            y: {
                display: false,
                min: 0,
                max: 100
            }
        },
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                enabled: false, 
                mode: 'nearest',
                position: 'nearest',
                external: function(context) {
                  
                }
            }
        },
        layout: {
            padding: {
                left: 0,
                right: 0,
                top: 0,
                bottom: 0
            }
        }
    },
    plugins: [{
        afterDatasetsDraw: function(chart, easing) {
            var bubbleCtx = chart.ctx;
            chart.data.datasets.forEach(function(dataset, i) {
                var meta = chart.getDatasetMeta(i);
                if (!meta.hidden) {
                    meta.data.forEach(function(element, index) {
                        
                        bubbleCtx.fillStyle = 'rgb(255, 255, 255)';
                        var fontSize = 16;
                        var fontStyle = 'normal';
                        var fontFamily = 'Helvetica Neue';
                        bubbleCtx.font = Chart.helpers.fontString(fontSize, fontStyle, fontFamily);

                        
                        var data = dataset.data[index];
                        var label = data.ip;
                        
                        
                        var position = element.getCenterPoint();
                        
                        
                        bubbleCtx.textAlign = 'center';
                        bubbleCtx.textBaseline = 'middle';
                        
                        
                        bubbleCtx.fillText(label, position.x, position.y);
                    });
                }
            });
        }
    }]
});
</script>



<script>
document.addEventListener('DOMContentLoaded', (event) => {
    const kpiNumberElement = document.getElementById('kpiNumber');
    const sigmaRulesKpiNumberElement = document.getElementById('sigmaRulesKpiNumber');
    
    animateValue(kpiNumberElement, parseInt(kpiNumberElement.textContent, 10));
    animateValue(sigmaRulesKpiNumberElement, parseInt(sigmaRulesKpiNumberElement.textContent, 10));
});

function animateValue(element, finalValue) {
    let currentValue = 0;
    const duration = 1000; 
    let start = null;

    const step = (timestamp) => {
        if (!start) start = timestamp;
        const progress = Math.min((timestamp - start) / duration, 1);
        currentValue = Math.floor(progress * finalValue);
        element.textContent = currentValue;

        if (progress < 1) {
            window.requestAnimationFrame(step);
        } else {
            element.textContent = finalValue; 
        }
    };

    window.requestAnimationFrame(step);
}

</script>


<script>
am5.ready(function() {
    var root = am5.Root.new("sankeyChartdiv");

    root.setThemes([
        am5themes_Animated.new(root)
    ]);

    var series = root.container.children.push(am5flow.Sankey.new(root, {
        sourceIdField: "from",
        targetIdField: "to",
        valueField: "value",
        paddingRight: 120, 
        nodeAlign: "bottom",
        nodePadding: 30
    }));

    if (series.nodes && series.nodes.get("colors")) {
        series.nodes.get("colors").set("step", 2);
    }

    var seriesData = <?php echo json_encode($graphData); ?>;
    series.data.setAll(seriesData);

    if (series.nodes && series.nodes.template && series.nodes.template.events) {
        series.nodes.template.events.on("sizechanged", function(ev) {
            var label = ev.target.children.getIndex(0);
            if (label) {
                var cellWidth = ev.target.pixelWidth;
                label.maxWidth = cellWidth;
              
                label.set("fill", am5.color(0xFFFFFF));
            }
        });
    }

    if (series.labels && series.labels.template) {
        series.labels.template.setAll({
            fill: am5.color(0xFFFFFF), 
            truncate: true,
            maxWidth: 150
        });
    }

 
    series.nodes.labels.template.setAll({
        fill: am5.color(0xFFFFFF)
    });

    series.appear(1000, 100);
});
</script>

<!-- Chargement des données depuis dashboard_data.json -->
<script>
async function loadDashboardData() {
    try {
        // Ajout d'un timestamp pour éviter le cache du navigateur
        const timestamp = new Date().getTime();
        const response = await fetch(`alienvault/dashboard_data.json?t=${timestamp}`);
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }
        const data = await response.json();
        
        // Graphique des menaces
        new Chart(document.getElementById('chart-threats'), {
            type: 'doughnut',
            data: {
                labels: data.top_threats.map(t => t.name),
                datasets: [{
                    label: 'Number of detections',
                    data: data.top_threats.map(t => t.count),
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.4)', // Indigo
                        'rgba(236, 72, 153, 0.4)', // Pink
                        'rgba(139, 92, 246, 0.4)', // Purple
                        'rgba(16, 185, 129, 0.4)'  // Emerald
                    ],
                    borderColor: 'rgba(17, 24, 39, 0.5)',
                    borderWidth: 2,
                    hoverOffset: 15,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { 
                        display: true,
                        position: 'right',
                        labels: {
                            color: '#ffffff',
                            font: {
                                size: 12
                            },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        },
                        backgroundColor: 'rgba(17, 24, 39, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#5865F2',
                        borderWidth: 1
                    }
                }
            }
        });
        
        // Graphique des cibles
        new Chart(document.getElementById('chart-targets'), {
            type: 'radar',
            data: {
                labels: data.most_targeted.map(t => t.name),
                datasets: [{
                    label: 'Number of attacks',
                    data: data.most_targeted.map(t => t.count),
                    backgroundColor: 'rgba(88, 101, 242, 0.4)',
                    borderColor: '#5865F2',
                    borderWidth: 2,
                    pointBackgroundColor: '#7B82F5',
                    pointBorderColor: '#ffffff',
                    pointHoverBackgroundColor: '#ffffff',
                    pointHoverBorderColor: '#5865F2',
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        pointLabels: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            font: {
                                size: 12
                            }
                        },
                        ticks: {
                            backdropColor: 'transparent',
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                },
                plugins: {
                    legend: { 
                        display: false 
                    }
                }
            }
        });
        
        // Graphique des CVEs
        new Chart(document.getElementById('chart-cves'), {
            type: 'bar',
            data: {
                labels: data.top_cves.map(c => c.cve),
                datasets: [{
                    axis: 'y',
                    label: 'Exploitation frequency',
                    data: data.top_cves.map(c => c.count),
                    backgroundColor: 'rgba(236, 72, 153, 0.6)', // Rose pink
                    borderColor: '#ec4899',
                    borderWidth: 1,
                    borderRadius: 5,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: false 
                    }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { 
                            color: 'rgba(255, 255, 255, 0.7)',
                            font: {
                                family: 'monospace',
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: 'rgba(255, 255, 255, 0.7)' }
                    }
                }
            }
        });
        
        // Créer des éléments copiables pour les CVEs
        const createCopiableCVEs = (data) => {
            const cveContainer = document.getElementById('cve-values');
            if (!cveContainer) return;
            
            cveContainer.innerHTML = '';
            
            if (data.top_cves && data.top_cves.length > 0) {
                data.top_cves.forEach(cve => {
                    const cveElement = document.createElement('div');
                    cveElement.className = 'cve-item';
                    cveElement.textContent = cve.cve;
                    cveContainer.appendChild(cveElement);
                });
            }
        };

        // Appeler cette fonction après avoir chargé les données
        createCopiableCVEs(data);
        
        // Créer la carte mondiale
        createWorldMap(data.geo_data || {});
        
        // Afficher les dernières menaces détectées
        const latestThreatsContainer = document.getElementById('latest-threats-container');
        
        if (data.recent_pulses && data.recent_pulses.length > 0) {
            // Trier les pulses par date (les plus récents en premier)
            const sortedPulses = [...data.recent_pulses].sort((a, b) => {
                return new Date(b.created) - new Date(a.created);
            });
            
            // Prendre uniquement les 5 plus récents
            sortedPulses.slice(0, 5).forEach(pulse => {
                createThreatItem(latestThreatsContainer, pulse);
            });
        } else {
            latestThreatsContainer.innerHTML = '<div class="threat-item">No recent threats available</div>';
        }
        
    } catch (error) {
        console.error('Error loading dashboard data:', error);
    }
}

// Fonction pour créer un élément de menace
function createThreatItem(container, pulse) {
    const div = document.createElement('div');
    div.className = 'threat-item';
    
    const tags = (pulse.tags || []).slice(0, 3).map(tag => 
        `<span class="badge-threat">${tag}</span>`
    ).join('');
    
    const date = new Date(pulse.created);
    const formattedDate = date.toLocaleString('fr-FR', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    div.innerHTML = `
        <div class="threat-title">${pulse.name}</div>
        <div class="threat-date">Published on: ${formattedDate}</div>
        <div>${tags}</div>
        <div class="threat-desc">${truncateText(pulse.description, 200)}</div>
        <a href="https://otx.alienvault.com/pulse/${pulse.id}" target="_blank" 
           style="color: #7B82F5; text-decoration: none; margin-top: 0.5rem; display: inline-block;">
          View details →
        </a>
    `;
    container.appendChild(div);
}

// Helper pour tronquer le texte
function truncateText(text, maxLength) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substr(0, maxLength) + '...';
}

// Fonction pour créer la carte mondiale avec amCharts
function createWorldMap(geoData) {
    // Convertit geoData en format utilisable par amCharts
    const processedData = [];
    const countryCodeMap = {
        'United States': 'US',
        'USA': 'US',
        'Russia': 'RU',
        'China': 'CN',
        'United Kingdom': 'GB',
        'UK': 'GB',
        'Germany': 'DE',
        'France': 'FR',
        'Canada': 'CA',
        'Australia': 'AU',
        'India': 'IN',
        'Japan': 'JP',
        'Brazil': 'BR',
        'Italy': 'IT',
        'Spain': 'ES',
        'South Korea': 'KR'
    };
    
    for (const country in geoData) {
        const countryCode = countryCodeMap[country] || country;
        if (countryCode) {
            processedData.push({
                id: countryCode,
                value: geoData[country],
                name: country
            });
        }
    }
    
    // Créer root element
    const root = am5.Root.new("world-map");
    
    // Définir thèmes
    root.setThemes([
        am5themes_Animated.new(root),
        am5themes_Animated.new(root)
    ]);
    
    // Créer la carte
    const chart = root.container.children.push(
        am5map.MapChart.new(root, {
            panX: "rotateX",
            panY: "rotateY",
            projection: am5map.geoMercator(),
            paddingLeft: 0,
            paddingRight: 0,
            paddingBottom: 0
        })
    );
    
    // Créer polygones de pays
    const polygonSeries = chart.series.push(
        am5map.MapPolygonSeries.new(root, {
            geoJSON: am5geodata_worldLow,
            exclude: ["AQ"],
            valueField: "value",
            calculateAggregates: true,
            fill: am5.color(0x13254d),
            stroke: am5.color(0xffffff),
            strokeWidth: 0.5,
            strokeOpacity: 0.3
        })
    );
    
    polygonSeries.mapPolygons.template.setAll({
        tooltipText: "{name}: {value}",
        toggleKey: "active",
        interactive: true,
        fill: am5.color(0x13254d)
    });
    
    polygonSeries.mapPolygons.template.states.create("hover", {
        fill: am5.color(0x5865F2)
    });
    
    polygonSeries.mapPolygons.template.states.create("active", {
        fill: am5.color(0x5865F2)
    });
    
    // Coloration des polygones basée sur la valeur
    polygonSeries.set("heatRules", [{
        target: polygonSeries.mapPolygons.template,
        dataField: "value",
        min: am5.color(0x7B82F5),
        max: am5.color(0x5865F2),
        key: "fill"
    }]);
    
    // Ajouter les données
    polygonSeries.data.setAll(processedData);
    
    // Contrôles de zoom
    chart.set("zoomControl", am5map.ZoomControl.new(root, {
        x: 10,
        y: 10,
        homeGeoPoint: { longitude: 0, latitude: 0 },
        homeZoomLevel: 1
    }));
    
    // Animation de rotation
    chart.appear(1000, 100);
}

// Fonction pour afficher un tooltip de confirmation de copie
function showCopyTooltip(event, text) {
    // Créer le tooltip s'il n'existe pas
    let tooltip = document.getElementById('copy-tooltip');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'copy-tooltip';
        tooltip.className = 'copy-tooltip';
        document.body.appendChild(tooltip);
    }
    
    // Positionnement du tooltip
    tooltip.style.left = `${event.clientX + 10}px`;
    tooltip.style.top = `${event.clientY + 10}px`;
    tooltip.textContent = `${text} copied!`;
    
    // Afficher le tooltip
    tooltip.classList.add('show');
    
    // Cacher le tooltip après un délai
    setTimeout(() => {
        tooltip.classList.remove('show');
    }, 2000);
}

document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
});
</script>

</body>
</html>
