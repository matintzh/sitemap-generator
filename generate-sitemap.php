<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? '';

if (empty($url)) {
    echo json_encode(['error' => 'URL is required']);
    exit;
}

// Function to normalize URLs (convert relative URLs to absolute)
function normalizeUrl($baseUrl, $url) {
    if (strpos($url, 'http') === 0) {
        return $url; // Already absolute
    }
    return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
}

// Function to crawl the website recursively
function crawlWebsite($url, &$visited = []) {
    if (in_array($url, $visited)) {
        return []; // Skip already visited URLs
    }

    $visited[] = $url; // Mark this URL as visited
    $links = [];

    try {
        $html = file_get_contents($url);
        if (!$html) {
            return [];
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        foreach ($dom->getElementsByTagName('a') as $link) {
            $href = $link->getAttribute('href');
            if (!$href || strpos($href, '#') === 0) {
                continue; // Skip empty or anchor links
            }

            $href = normalizeUrl($url, $href);

            // Ensure the link belongs to the same domain
            if (parse_url($href, PHP_URL_HOST) === parse_url($url, PHP_URL_HOST)) {
                $links[] = $href;
                // Recursively crawl the new link
                $links = array_merge($links, crawlWebsite($href, $visited));
            }
        }
    } catch (Exception $e) {
        // Handle errors (e.g., invalid URLs or timeouts)
        error_log("Error crawling $url: " . $e->getMessage());
    }

    return $links;
}

// Function to generate sitemap XML
function generateSitemap($links) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

    foreach ($links as $link) {
        $url = $xml->addChild('url');
        $url->addChild('loc', htmlspecialchars($link));
    }

    return $xml->asXML();
}

try {
    $links = crawlWebsite($url);
    if (empty($links)) {
        throw new Exception('No links found or failed to crawl the website.');
    }

    // Add the initial URL to the list of links
    array_unshift($links, $url);

    // Remove duplicate links
    $links = array_unique($links);

    $sitemap = generateSitemap($links);
    echo json_encode(['sitemap' => $sitemap]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}