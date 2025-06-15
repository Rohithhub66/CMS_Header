<?php
// threat_intel.php - Full Threat Intelligence Collector with DB Save, Alert, IOC Enrichment, and Dashboard

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/autoload.php';

$mysqli = new mysqli("localhost", "root", "", "lms_db");
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

$infraKeywords = [];
$res = $mysqli->query("SELECT keyword FROM infra_keywords");
while ($row = $res->fetch_assoc()) {
    $infraKeywords[] = $row['keyword'];
}
$sectorKeywords = ['healthcare', 'hospital', 'medical', 'manufacturing', 'industrial', 'banking', 'finance'];

$rssFeeds = [
    "https://cyware.com/cyber-security-news-rss",
    "https://feeds.feedburner.com/TheHackersNews"
];

$virusTotalApiKey = "YOUR_VIRUSTOTAL_API_KEY";

function fetchViaCurl($url, $isJson = false) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($info['http_code'] !== 200 || empty($response)) {
        return null;
    }

    if ($isJson) {
        return json_decode($response, true);
    }

    if (stripos($info['content_type'], 'xml') === false && stripos($response, '<rss') === false) {
        return null;
    }

    return $response;
}

function parseRSSFeed($url) {
    $rssContent = fetchViaCurl($url);
    $rssItems = [];
    if ($rssContent) {
        $xml = simplexml_load_string($rssContent);
        if ($xml && isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $rssItems[] = [
                    'title' => (string)$item->title,
                    'description' => (string)$item->description,
                    'link' => (string)$item->link,
                    'pubDate' => (string)$item->pubDate,
                    'source' => parse_url($url, PHP_URL_HOST)
                ];
            }
        }
    }
    return $rssItems;
}

function isRelevant($text, $keywords) {
    foreach ($keywords as $kw) {
        if (stripos($text, $kw) !== false) return true;
    }
    return false;
}

function extractIOCs($text) {
    $matches = [];
    preg_match_all('/\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b/', $text, $ips);
    preg_match_all('/https?:\/\/[\S]+/', $text, $urls);
    preg_match_all('/\b[a-f0-9]{32,64}\b/i', $text, $hashes);
    $matches['ips'] = array_unique($ips[0]);
    $matches['urls'] = array_unique($urls[0]);
    $matches['hashes'] = array_unique($hashes[0]);
    return json_encode($matches);
}

function sendEmailAlert($subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'youremail@gmail.com';
        $mail->Password = 'yourapppassword';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('youremail@gmail.com', 'Threat Intel');
        $mail->addAddress('securityteam@example.com');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
    } catch (Exception $e) {
        error_log("Email send failed: {$mail->ErrorInfo}");
    }
}

foreach ($rssFeeds as $feed) {
    $items = parseRSSFeed($feed);
    foreach ($items as $item) {
        $text = $item['title'] . ' ' . $item['description'];
        $relevantInfra = isRelevant($text, $infraKeywords);
        $relevantSector = isRelevant($text, $sectorKeywords);
        $tags = [];
        if ($relevantInfra) $tags[] = "Your Infra";
        if ($relevantSector) $tags[] = "Your Sector";

        $stmt = $mysqli->prepare("SELECT COUNT(*) FROM threat_intel_logs WHERE title = ? AND link = ?");
        if (!$stmt) die("Prepare failed (SELECT): (" . $mysqli->errno . ") " . $mysqli->error);
        $stmt->bind_param("ss", $item['title'], $item['link']);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count == 0) {
            $iocText = extractIOCs($item['description']);
            $stmt = $mysqli->prepare("INSERT INTO threat_intel_logs (title, pub_date, source, relevance_tags, link, ioc_text) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) die("Prepare failed (INSERT): (" . $mysqli->errno . ") " . $mysqli->error);
            $relevance = implode(', ', $tags);
            $stmt->bind_param("ssssss", $item['title'], $item['pubDate'], $item['source'], $relevance, $item['link'], $iocText);
            $stmt->execute();
            $stmt->close();

            if (!empty($tags)) {
                $body = "<strong>{$item['title']}</strong><br>"
                      . "Published: {$item['pubDate']}<br>"
                      . "Source: {$item['source']}<br>"
                      . "Link: <a href='{$item['link']}'>View</a><br>"
                      . "Relevance: $relevance";
                sendEmailAlert("⚠️ Threat Alert: {$item['title']}", $body);
            }
        }
    }
}

// === DASHBOARD UI ===
echo "<h2>Threat Intelligence Dashboard</h2><form method='GET'><input type='text' name='q' placeholder='Search...' /> <input type='submit' value='Search'> <a href='?export=csv'>Export CSV</a></form>";

$q = isset($_GET['q']) ? "%" . $_GET['q'] . "%" : "%";
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="threat_intel.csv"');
    $stmt = $mysqli->prepare("SELECT title, pub_date, source, relevance_tags, link, ioc_text FROM threat_intel_logs WHERE title LIKE ? OR relevance_tags LIKE ?");
    $stmt->bind_param("ss", $q, $q);
    $stmt->execute();
    $result = $stmt->get_result();
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Title', 'Date', 'Source', 'Relevance', 'Link', 'IOCs']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$stmt = $mysqli->prepare("SELECT title, pub_date, source, relevance_tags, link, ioc_text FROM threat_intel_logs WHERE title LIKE ? OR relevance_tags LIKE ? ORDER BY pub_date DESC LIMIT 100");
$stmt->bind_param("ss", $q, $q);
$stmt->execute();
$result = $stmt->get_result();

$stats = ["ioc_count" => 0, "ips" => 0, "urls" => 0, "hashes" => 0];

echo "<table border='1' cellpadding='5'><tr><th>Title</th><th>Date</th><th>Source</th><th>Relevance</th><th>Link</th><th>IOCs</th></tr>";
while ($row = $result->fetch_assoc()) {
    $highlight = '';
    if (strpos($row['relevance_tags'], 'Infra') !== false) $highlight = "style='background:#ffeeba'";
    if (strpos($row['relevance_tags'], 'Sector') !== false) $highlight = "style='background:#f8d7da'";

    $ioc = json_decode($row['ioc_text'], true);
    $iocDisplay = "";
    if ($ioc) {
        $stats['ips'] += count($ioc['ips']);
        $stats['urls'] += count($ioc['urls']);
        $stats['hashes'] += count($ioc['hashes']);
        $stats['ioc_count']++;
        foreach ($ioc as $type => $list) {
            if (!empty($list)) {
                $iocDisplay .= "<strong>$type:</strong><ul>";
                foreach (array_slice($list, 0, 3) as $v) $iocDisplay .= "<li>" . htmlspecialchars($v) . "</li>";
                $iocDisplay .= "</ul>";
            }
        }
    }

    echo "<tr $highlight><td>" . htmlspecialchars($row['title']) . "</td><td>" . $row['pub_date'] . "</td><td>" . $row['source'] . "</td><td>" . $row['relevance_tags'] . "</td><td><a href='" . $row['link'] . "' target='_blank'>View</a></td><td>$iocDisplay</td></tr>";
}
echo "</table>";

// Display IOC stats
echo "<p><strong>IOC Summary:</strong> Articles with IOCs: {$stats['ioc_count']}, Total IPs: {$stats['ips']}, URLs: {$stats['urls']}, Hashes: {$stats['hashes']}</p>";
?>
