<?php

error_reporting(0);

session_start();

if (!isset($_SESSION['email'])) {
    header('Location: connexion.html');
    exit();
}

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
    die("Query error: " . pg_last_error($conn));
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

$username = 'admin';
$opensearchPassword = getenv('OPENSEARCH_ADMIN_PASSWORD');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://localhost:9200/winlogbeat*/_count');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $opensearchPassword);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);

$nbEventVMWin = null;

if (curl_errno($ch)) {
    $nbEventVMWin = 'cURL Error: ' . curl_error($ch);
} else {
    $resultArray = json_decode($response, true);
    if (isset($resultArray['count'])) {
        $nbEventVMWin = $resultArray['count'];
    } else {
        $nbEventVMWin = 'Error: Unable to retrieve count - Response: ' . $response;
    }
}

curl_close($ch);





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
<html lang="en">
<head>
    <link rel="icon" href="MD_image/logowhiteV3.png" type="image/png">
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
    <script src="https://cdn.amcharts.com/lib/5/map.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldLow.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script>
  document.addEventListener("DOMContentLoaded", function(event) {
    var nbEventVMWin = <?php echo json_encode($nbEventVMWin ?? 0); ?>;
    console.log("nbEventVMWin value:", nbEventVMWin); // Debug
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
    <div class="nav-logo">
        <img src="MD_image/logowhiteV3.png" alt="Logo" /> 
    </div>

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
    <i class="fas fa-users" style="font-size: 80px; color: #10b981; margin-bottom: 10px;"></i>
    <div class="kpi-number" id="activeUsersCount">Loading...</div>
    <div class="kpi-title">Active Users</div>
</div>

<div class="index-card">
    <i class="fas fa-shield-alt" style="font-size: 80px; color: #f59e0b; margin-bottom: 10px;"></i>
    <div class="kpi-number" id="sigmaRulesKpiNumber"><?php echo $sigmaFileCount; ?></div>
    <div class="kpi-title">Sigma rules</div>
</div>

<div class="index-card">
    <i class="fas fa-book" style="font-size: 80px; color: #3b82f6; margin-bottom: 10px;"></i>
    <div class="kpi-number" id="mitre-technique-count">Loading...</div>
    <div class="kpi-title">Mitre Attack technique</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    fetch('/scripts/php/getMitreTechniqueCount.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('mitre-technique-count').innerText = data;
        })
        .catch(error => console.error('Error:', error));
    
    fetch('/scripts/php/getTechniqueCount.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('custom-payloads-count').innerText = data;
        })
        .catch(error => console.error('Error:', error));
    
    fetch('/scripts/php/getActiveUsersCount.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('activeUsersCount').innerText = data;
        })
        .catch(error => console.error('Error:', error));
});
</script>

<div class="index-card">
    <i class="fas fa-code" style="font-size: 80px; color: #667eea; margin-bottom: 10px;"></i>
    <div class="kpi-number" id="custom-payloads-count">Loading...</div>
    <div class="kpi-title">Custom Payloads</div>
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
            


<script>
document.addEventListener('DOMContentLoaded', (event) => {
    const sigmaRulesKpiNumberElement = document.getElementById('sigmaRulesKpiNumber');
    
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
async function loadDashboardData() {
    try {
        // Add timestamp to avoid browser cache
        const timestamp = new Date().getTime();
        const response = await fetch(`alienvault/dashboard_data.json?t=${timestamp}`);
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }
        const data = await response.json();
        
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

        createCopiableCVEs(data);
        createWorldMap(data.geo_data || {});
        const latestThreatsContainer = document.getElementById('latest-threats-container');
        
        if (data.recent_pulses && data.recent_pulses.length > 0) {
            const sortedPulses = [...data.recent_pulses].sort((a, b) => {
                return new Date(b.created) - new Date(a.created);
            });
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


function createThreatItem(container, pulse) {
    const div = document.createElement('div');
    div.className = 'threat-item';
    
    const tags = (pulse.tags || []).slice(0, 3).map(tag => 
        `<span class="badge-threat">${tag}</span>`
    ).join('');
    
    const date = new Date(pulse.created);
    const formattedDate = date.toLocaleString('en-US', { 
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


function truncateText(text, maxLength) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substr(0, maxLength) + '...';
}


function createWorldMap(geoData) {

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
    

    const root = am5.Root.new("world-map");
    
    // Set themes
    root.setThemes([
        am5themes_Animated.new(root),
        am5themes_Animated.new(root)
    ]);
    
    // Create map
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
    
    // Create country polygons
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
    
    // Color polygons based on value
    polygonSeries.set("heatRules", [{
        target: polygonSeries.mapPolygons.template,
        dataField: "value",
        min: am5.color(0x7B82F5),
        max: am5.color(0x5865F2),
        key: "fill"
    }]);
    
    // Add data
    polygonSeries.data.setAll(processedData);
    
    // Zoom controls
    chart.set("zoomControl", am5map.ZoomControl.new(root, {
        x: 10,
        y: 10,
        homeGeoPoint: { longitude: 0, latitude: 0 },
        homeZoomLevel: 1
    }));
    
    // Rotation animation
    chart.appear(1000, 100);
}

// Function to display a copy confirmation tooltip
function showCopyTooltip(event, text) {
    // Create tooltip if it doesn't exist
    let tooltip = document.getElementById('copy-tooltip');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'copy-tooltip';
        tooltip.className = 'copy-tooltip';
        document.body.appendChild(tooltip);
    }
    
    // Position tooltip
    tooltip.style.left = `${event.clientX + 10}px`;
    tooltip.style.top = `${event.clientY + 10}px`;
    tooltip.textContent = `${text} copied!`;
    
    // Show tooltip
    tooltip.classList.add('show');
    
    // Hide tooltip after delay
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
