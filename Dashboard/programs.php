<?php
// Allow up to 10 minutes
ini_set('max_execution_time', 600);
set_time_limit(600);

// Output as downloadable CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="academic_programmes_all.csv"');

// Open CSV output
$output = fopen('php://output', 'w');
fputcsv($output, ['Programme Name', 'Category', 'Location', 'Posted Date', 'Status', 'Details Link']);

function fetchPage($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20); // shorter timeout per request
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
    $response = curl_exec($ch);
    curl_close($ch);
    return $response ?: null;
}

function scrapePrograms($totalPages = 15, $output) {
    $baseUrl = "https://efiling.ur.ac.rw/programmes?page=";

    for ($page = 1; $page <= $totalPages; $page++) {
        echo "Scraping page $page...\n";
        $html = fetchPage($baseUrl . $page);
        if (!$html) {
            // skip if page can't be loaded
            continue;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $items = $xpath->query("//div[contains(@class,'item')]");

        foreach ($items as $item) {
            $nameNode = $xpath->query(".//h3/a", $item);
            $categoryNode = $xpath->query(".//div[contains(@class,'category')]", $item);
            $locationNode = $xpath->query(".//div[contains(@class,'location')]", $item);
            $dateNode = $xpath->query(".//div[contains(@class,'date')]", $item);
            $statusNode = $xpath->query(".//div[contains(@class,'expired')]", $item);

            $name = $nameNode->length > 0 ? trim($nameNode[0]->nodeValue) : "";
            $category = $categoryNode->length > 0 ? trim($categoryNode[0]->nodeValue) : "";
            $location = $locationNode->length > 0 ? trim($locationNode[0]->nodeValue) : "";
            $date = $dateNode->length > 0 ? trim($dateNode[0]->nodeValue) : "";
            $status = $statusNode->length > 0 ? trim($statusNode[0]->nodeValue) : "";

            $link = "";
            if ($nameNode->length > 0) {
                $link = $nameNode[0]->getAttribute('href');
                if ($link && !preg_match("/^https?:\/\//", $link)) {
                    $link = "https://efiling.ur.ac.rw" . $link;
                }
            }

            if ($name) {
                fputcsv($output, [$name, $category, $location, $date, $status, $link]);
            }
        }

        // Small delay to avoid overloading server
        usleep(200000); // 0.2 sec
    }
}

// Run scraper
scrapePrograms(15, $output);
fclose($output);
?>
