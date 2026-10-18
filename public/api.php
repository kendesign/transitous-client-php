<?php
/* Small JSON proxy for the page: ?a=geocode&q=...  and  ?a=plan&from=lat,lon&to=lat,lon[&time=ISO] */
require __DIR__ . '/../src/TransitousClient.php';
require __DIR__ . '/../src/FileCache.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function out(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

$cfgFile = __DIR__ . '/../config.php';
$cfg = is_file($cfgFile) ? require $cfgFile : [];
$contact = (string)($cfg['contact'] ?? '');
if ($contact === '' || strpos($contact, '@') === false) out(['error' => 'config.php with a contact address is missing'], 500);

$client = new TransitousClient('transitous-route-map/1.0 (contact ' . $contact . ')', (int)($cfg['daily_cap'] ?? 200), new FileCache(__DIR__ . '/../cache'));
$a = (string)($_GET['a'] ?? '');
$err = null;

if ($a === 'config') out(['center' => $cfg['map_center'] ?? [51.16, 10.45], 'zoom' => (int)($cfg['map_zoom'] ?? 6)]);

if ($a === 'geocode') {
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q) < 3 || mb_strlen($q) > 120) out(['places' => []]);
    $d = $client->geocode($q, isset($cfg['near']) ? (string)$cfg['near'] : null, 604800, $err);
    if ($d === null) out(['error' => $err], 502);
    $places = [];
    foreach (array_slice($d, 0, 6) as $x) {
        if (!is_array($x) || !isset($x['lat'], $x['lon'])) continue;
        $areas = [];
        foreach ((array)($x['areas'] ?? []) as $ar) { if (!empty($ar['default']) || ($ar['adminLevel'] ?? 0) == 8) $areas[] = (string)($ar['name'] ?? ''); }
        $places[] = ['name' => (string)($x['name'] ?? ''), 'lat' => (float)$x['lat'], 'lon' => (float)$x['lon'], 'place' => implode(', ', array_unique(array_filter($areas)))];
    }
    out(['places' => $places]);
}

if ($a === 'plan') {
    $ok = function ($s) { return preg_match('/^-?\d{1,2}\.\d+,-?\d{1,3}\.\d+$/', (string)$s) === 1; };
    $from = (string)($_GET['from'] ?? ''); $to = (string)($_GET['to'] ?? '');
    if (!$ok($from) || !$ok($to)) out(['error' => 'from and to must be "lat,lon"'], 400);
    $time = null;
    if (!empty($_GET['time'])) { $t = strtotime((string)$_GET['time']); if ($t) $time = gmdate('Y-m-d\TH:i:s\Z', $t); }
    // Round the time to 5 minutes so that nearby requests share one cached answer.
    if ($time) { $t = strtotime($time); $time = gmdate('Y-m-d\TH:i:s\Z', (int)(floor($t / 300) * 300)); }
    $d = $client->plan($from, $to, $time, false, 4, 600, $err);
    if ($d === null) out(['error' => $err], 502);
    $its = [];
    foreach ((array)($d['itineraries'] ?? []) as $it) {
        $legs = [];
        foreach ((array)($it['legs'] ?? []) as $lg) {
            $g = (array)($lg['legGeometry'] ?? []);
            $legs[] = [
                'mode' => strtoupper((string)($lg['mode'] ?? '')),
                'line' => (string)($lg['routeShortName'] ?? ($lg['displayName'] ?? '')),
                'headsign' => (string)($lg['headsign'] ?? ''),
                'from' => (string)($lg['from']['name'] ?? ''), 'to' => (string)($lg['to']['name'] ?? ''),
                'start' => (string)($lg['startTime'] ?? ''), 'end' => (string)($lg['endTime'] ?? ''),
                'points' => (string)($g['points'] ?? ''), 'precision' => (int)($g['precision'] ?? 6),
            ];
        }
        $its[] = ['start' => (string)($it['startTime'] ?? ''), 'end' => (string)($it['endTime'] ?? ''), 'minutes' => (int)round(((int)($it['duration'] ?? 0)) / 60), 'transfers' => (int)($it['transfers'] ?? 0), 'legs' => $legs];
    }
    out(['itineraries' => $its]);
}
out(['error' => 'unknown action'], 400);
