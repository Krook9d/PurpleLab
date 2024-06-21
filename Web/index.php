  <!-- start Connexion -->
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['email'])) {
    header('Location: connexion.html');
    exit();
}

$conn = new mysqli(
    getenv('DB_HOST'), 
    getenv('DB_USER'), 
    getenv('DB_PASS'), 
    getenv('DB_NAME')
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = $_SESSION['email'];

$sql = "SELECT first_name, last_name, email, analyst_level, avatar FROM users WHERE email=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($first_name, $last_name, $email, $analyst_level, $avatar);

if (!$stmt->fetch()) {
    die("Error retrieving user information.");
}

$stmt->close();
$conn->close();


?>
<!-- End Connexion -->

  
<?php

// Retrieve password from environment variable
$elasticPassword = getenv('ELASTIC_PASSWORD');

if (!$elasticPassword) {
    die("Elastic password not set in environment variables.");
}

$username = 'elastic';

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
                                "field" => "destination_ip" // Ou un autre champ pour calculer le volume/le nombre
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




<script>
  document.addEventListener("DOMContentLoaded", function(event) {
    var nbEventVMWin = <?php echo json_encode($nbEventVMWin ?? 0); ?>;
    var g = new JustGage({
      id: "gauge",
      value: nbEventVMWin,
      min: 0,
      max: 500000, 
      title: "Events",
      label: "",
      labelFontColor: "#000000", 
      levelColors: [
        "#a9d70b",
        "#f9c802",
        "#ff0000"
      ]
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
        <li><a href="malware.php"><i class="fas fa-virus"></i> Malware</a></li>
        <li><a href="simulation.php"><i class="fas fa-project-diagram"></i> Log Simulation</a></li>
        <li><a href="usecase.php"><i class="fas fa-lightbulb"></i> UseCase</a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> Sharing</a></li>
        <li><a href="sigma.php"><i class="fas fa-shield-alt"></i> Sigma Rules</a></li>
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
<br><br>
<div class="health-section-separator"></div>

<div class="index-dashboard">

    <div class="index-card">
        <div id="wordcloud" style="width: 100%; height: 500px;"></div>
        <div class="kpi-number"></div>
        <div class="kpi-title">ProcessCreate Name On Windows VM</div>
    </div>



<div class="index-card">
    <canvas id="myBubbleChart" style="width: 100%; height: 100%;"></canvas>
    <div class="kpi-title">Asset map </div>
</div>



</div>
<br><br><br><br>
<div class="health-section-separator"></div>

<div class="index-dashboard">
    <div class="index-card" id="index-card-sankey">
        <div id="sankeyChartdiv" style="width: 95%; height: 500px;"></div>
        <div class="kpi-title">Traffic Flow</div>
    </div>
</div>

</div>
            

<!-- word-cloud-graph -->
<script>
am4core.ready(function() {
    // Themes begin
    am4core.useTheme(am4themes_animated);
    // Themes end

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
            return am4core.color("#004f8a"); 
        } else if (value > 50) {
            return am4core.color("#0074d9"); 
        } else {
            return am4core.color("#7abaf2"); 
        }
    });

});
</script>






<!-- start sidebar JS -->
<script>
    // When the document is ready
    document.addEventListener('DOMContentLoaded', function() {
        // When the settings link is clicked
        document.querySelector('#settings-link').addEventListener('click', function(e) {
            // Prevent the default link behavior
            e.preventDefault();

            // Toggle the active class on the sidebar
            let sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');

            // Stop propagation of the event to parent elements
            e.stopPropagation();
        });

        // When a click is detected outside the sidebar
        document.addEventListener('click', function(e) {
            let sidebar = document.getElementById('sidebar');
            if (!sidebar.contains(e.target) && sidebar.classList.contains('active')) {
                // Remove the active class from the sidebar
                sidebar.classList.remove('active');
            }
        });
    });
</script>

<script>
am4core.ready(function() {
    // Themes begin
    am4core.useTheme(am4themes_animated);
    // Themes end

    var chart = am4core.create("chartdiv", am4charts.PieChart);
    chart.hiddenState.properties.opacity = 0; // this creates initial fade-in

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




}); // end am4core.ready()
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
var ctx = document.getElementById('myBubbleChart').getContext('2d');
var myBubbleChart = new Chart(ctx, {
    type: 'bubble',
    data: {
        datasets: [{
            data: bubbleData,
            backgroundColor: function(context) {
                return context.raw.backgroundColor;
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
            var ctx = chart.ctx;
            chart.data.datasets.forEach(function(dataset, i) {
                var meta = chart.getDatasetMeta(i);
                if (!meta.hidden) {
                    meta.data.forEach(function(element, index) {
                        // Draw the text in black, with the specified font
                        ctx.fillStyle = 'rgb(0, 0, 0)';
                        var fontSize = 16;
                        var fontStyle = 'normal';
                        var fontFamily = 'Helvetica Neue';
                        ctx.font = Chart.helpers.fontString(fontSize, fontStyle, fontFamily);

                        // Get the data for this element
                        var data = dataset.data[index];
                        var label = data.ip;
                        
                        // Calculate the position of the label
                        var position = element.getCenterPoint();
                        
                        // Make sure alignment settings are correct
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        
                        // Draw the label text
                        ctx.fillText(label, position.x, position.y);
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

    series.nodes.get("colors").set("step", 2);

    var seriesData = <?php echo json_encode($graphData); ?>;
    series.data.setAll(seriesData);

    series.nodes.template.events.on("sizechanged", function(ev) {
        var label = ev.target.children.getIndex(0);
        var cellWidth = ev.target.pixelWidth;
        label.maxWidth = cellWidth;
    });

    series.labels.template.truncate = true;
    series.labels.template.maxWidth = 150; 

    series.appear(1000, 100);
}); // end am5.ready()
</script>



</body>
</html>
