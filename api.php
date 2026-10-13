<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dataDir = __DIR__ . '/matches_data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}
$indexFile = $dataDir . '/matches_index.json';
$legacyFile = __DIR__ . '/match_live_data.json';

function sanitizeMatchId($id) {
    $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$id);
    return !empty($clean) ? substr($clean, 0, 50) : 'default';
}

function loadMatchesIndex($indexFile) {
    if (file_exists($indexFile)) {
        $content = @file_get_contents($indexFile);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return [];
}

function saveMatchesIndex($indexFile, $index) {
    @file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$rawMatchId = isset($_GET['match']) ? trim($_GET['match']) : (isset($_POST['match']) ? trim($_POST['match']) : '');

// 1. ACTION: LIST ALL MATCHES
if ($action === 'list') {
    $index = loadMatchesIndex($indexFile);
    // Sort by updatedAt descending
    usort($index, function($a, $b) {
        $tA = isset($a['updatedAt']) ? (int)$a['updatedAt'] : 0;
        $tB = isset($b['updatedAt']) ? (int)$b['updatedAt'] : 0;
        return $tB - $tA;
    });
    echo json_encode(["status" => "success", "matches" => $index], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. ACTION: DELETE A MATCH
if ($action === 'delete') {
    $matchId = sanitizeMatchId($rawMatchId);
    $targetFile = $dataDir . '/match_' . $matchId . '.json';
    if (file_exists($targetFile)) {
        @unlink($targetFile);
    }
    $index = loadMatchesIndex($indexFile);
    $newIndex = array_values(array_filter($index, function($m) use ($matchId) {
        return isset($m['id']) && $m['id'] !== $matchId;
    }));
    saveMatchesIndex($indexFile, $newIndex);
    echo json_encode(["status" => "success", "message" => "Match deleted", "matchId" => $matchId]);
    exit;
}

// 3. POST: SAVE MATCH DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $decoded = json_decode($rawInput, true);
        if ($decoded !== null) {
            // Determine match ID from query param or inside JSON body
            $matchId = !empty($rawMatchId) ? sanitizeMatchId($rawMatchId) : (isset($decoded['matchId']) ? sanitizeMatchId($decoded['matchId']) : 'default');
            $decoded['matchId'] = $matchId;
            $targetFile = $dataDir . '/match_' . $matchId . '.json';

            // Save match details file
            @file_put_contents($targetFile, json_encode($decoded, JSON_UNESCAPED_UNICODE));

            // Also keep legacy file updated for default
            if ($matchId === 'default' || count(loadMatchesIndex($indexFile)) === 0) {
                @file_put_contents($legacyFile, json_encode($decoded, JSON_UNESCAPED_UNICODE));
            }

            // Update match summary in index
            $index = loadMatchesIndex($indexFile);
            $legalBalls = isset($decoded['legalBalls']) ? (int)$decoded['legalBalls'] : 0;
            $oversStr = floor($legalBalls / 6) . '.' . ($legalBalls % 6);
            $maxOvers = isset($decoded['maxOvers']) ? (int)$decoded['maxOvers'] : 20;

            $summary = [
                'id' => $matchId,
                'teamAName' => isset($decoded['teamAName']) ? $decoded['teamAName'] : 'Team A',
                'teamBName' => isset($decoded['teamBName']) ? $decoded['teamBName'] : 'Team B',
                'batFirst' => isset($decoded['batFirst']) ? $decoded['batFirst'] : 'A',
                'totalRuns' => isset($decoded['totalRuns']) ? (int)$decoded['totalRuns'] : 0,
                'totalWickets' => isset($decoded['totalWickets']) ? (int)$decoded['totalWickets'] : 0,
                'overs' => $oversStr,
                'maxOvers' => $maxOvers,
                'currentInnings' => isset($decoded['currentInnings']) ? (int)$decoded['currentInnings'] : 1,
                'matchFinished' => !empty($decoded['matchFinished']),
                'matchResultText' => isset($decoded['matchResultText']) ? $decoded['matchResultText'] : '',
                'scorerPin' => isset($decoded['scorerPin']) ? (string)$decoded['scorerPin'] : '1234',
                'updatedAt' => time()
            ];

            $found = false;
            foreach ($index as $k => $item) {
                if (isset($item['id']) && $item['id'] === $matchId) {
                    $index[$k] = $summary;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $index[] = $summary;
            }
            saveMatchesIndex($indexFile, $index);

            echo json_encode([
                "status" => "success",
                "matchId" => $matchId,
                "timestamp" => time()
            ]);
            exit;
        }
    }
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON payload"]);
    exit;
}

// 4. GET: FETCH MATCH DATA
$matchId = !empty($rawMatchId) ? sanitizeMatchId($rawMatchId) : 'default';
$targetFile = $dataDir . '/match_' . $matchId . '.json';

if (file_exists($targetFile)) {
    echo file_get_contents($targetFile);
} elseif ($matchId === 'default' && file_exists($legacyFile)) {
    echo file_get_contents($legacyFile);
} else {
    // If not found, return empty with matchId
    echo json_encode([
        "status" => "empty",
        "matchId" => $matchId,
        "message" => "No active match data found for ID: " . $matchId
    ]);
}
?>
