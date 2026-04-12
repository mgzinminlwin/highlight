<?php
/**
 * DasFootball MP4 Link Scraper for GitHub Actions
 */

// Set timezone to Myanmar (Yangon)
date_default_timezone_set('Asia/Yangon');

class DasFootballScraper {
    private $headers;
    
    public function __construct() {
        $this->headers = array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: https://dasfootball.com/'
        );
    }
    
    private function fetchUrl($url, $timeout = 15) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: " . $httpCode . " for " . $url);
        }
        return $response;
    }
    
    private function convertToMyanmarTime($utcDatetime) {
        if ($utcDatetime == "N/A" || empty($utcDatetime)) return "N/A";
        try {
            $datetime = new DateTime($utcDatetime, new DateTimeZone('UTC'));
            $datetime->setTimezone(new DateTimeZone('Asia/Yangon'));
            return $datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return $utcDatetime;
        }
    }
    
    private function getMp4Link($pageUrl) {
        try {
            $html = $this->fetchUrl($pageUrl, 15);
            if (preg_match('/data-item="({.*?})"/s', $html, $jsonMatch)) {
                $jsonStr = html_entity_decode(str_replace(['&quot;', '&amp;'], ['"', '&'], $jsonMatch[1]));
                $data = json_decode($jsonStr, true);
                if ($data && isset($data['sources'][0]['src'])) return $this->cleanUrl($data['sources'][0]['src']);
            }
            if (preg_match('/"src":"(https?:\\\\?\/\\\\?\/[^"]+\.mp4[^"]*?)"/', $html, $match)) {
                return $this->cleanUrl($match[1]);
            }
            return "No mp4 link found";
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
    
    private function cleanUrl($url) {
        $url = str_replace('\\/', '/', $url);
        $url = preg_replace('/\\\\u0026/', '&', $url);
        return html_entity_decode(str_replace('\\', '', $url));
    }
    
    private function scrapeMatch($articleHtml) {
        try {
            if (!preg_match('/href="([^"]+)"\s+itemprop="url"/', $articleHtml, $urlMatch)) return null;
            $postUrl = $urlMatch[1];
            
            preg_match('/itemprop="headline">([^<]+)<\/a>/', $articleHtml, $titleMatch);
            $title = $titleMatch[1] ?? "Unknown Title";
            
            preg_match('/class="agh-cat"[^>]*>([^<]+)<\/a>/', $articleHtml, $leagueMatch);
            $league = trim($leagueMatch[1] ?? "N/A");
            
            preg_match('/datetime="([^"]+)"/', $articleHtml, $dateMatch);
            $utcDateTime = $dateMatch[1] ?? "N/A";
            
            echo "  > Scraping: $title\n";
            $mp4Link = $this->getMp4Link($postUrl);
            
            return [
                "Title" => trim($title),
                "LeagueName" => $league,
                "UTC_DateTime" => $utcDateTime,
                "MyanmarTime" => $this->convertToMyanmarTime($utcDateTime),
                "PageUrl" => $postUrl,
                "StreamLink" => $mp4Link
            ];
        } catch (Exception $e) {
            return null;
        }
    }
    
    public function scrape() {
        echo "Starting Scraper: " . date('Y-m-d H:i:s') . "\n";
        try {
            $html = $this->fetchUrl("https://dasfootball.com/", 20);
            preg_match_all('/<article\s+class="agh-item"[^>]*>.*?<\/article>/s', $html, $matches);
            $articles = $matches[0];
            
            $finalData = [];
            foreach ($articles as $index => $article) {
                $result = $this->scrapeMatch($article);
                if ($result && $result['StreamLink'] !== "No mp4 link found") {
                    $finalData[] = $result;
                }
                usleep(500000); 
            }
            
            $jsonFile = __DIR__ . '/football_data.json';
            file_put_contents($jsonFile, json_encode($finalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            echo "Successfully saved " . count($finalData) . " matches to football_data.json\n";
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

$scraper = new DasFootballScraper();
$scraper->scrape();
