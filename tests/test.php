<?php
/* php tests/test.php  (no network: the HTTP layer is replaced) */
require __DIR__ . '/../src/TransitousClient.php';
$ok = 0; $bad = 0;
function check($n, $x) { global $ok, $bad; if ($x) { $ok++; echo "ok $n\n"; } else { $bad++; echo "FAIL $n\n"; } }
$calls = []; $seenUa = '';
$http = function ($url, $ua) use (&$calls, &$seenUa) { $calls[] = $url; $seenUa = $ua; return [200, '{"match":[]}']; };
$c = new TransitousClient('demo/1.0 (contact me@example.org)', 3, new ArrayCache(), TransitousClient::BASE, $http);
$r = $c->geocode('Felsberg'); check('first call returns data', $r === ['match' => []] && count($calls) === 1);
check('User-Agent carries the contact address', strpos($seenUa, 'me@example.org') !== false);
$c->geocode('Felsberg'); check('same request is answered from cache', count($calls) === 1 && $c->requestsToday() === 1);
$c->geocode('A'); $c->geocode('B'); check('three real requests made', count($calls) === 3);
$err = null; $r = $c->geocode('C', null, 60, $err);
check('fourth distinct request is refused by the daily limit', $r === null && $err === 'daily limit reached' && count($calls) === 3);
check('cached answers still work above the limit', $c->geocode('A') === ['match' => []]);
try { new TransitousClient('anonymous'); check('User-Agent without contact is rejected', false); } catch (InvalidArgumentException $e) { check('User-Agent without contact is rejected', true); }
$c2 = new TransitousClient('demo/1.0 (me@example.org)', 3, null, TransitousClient::BASE, function () { return [503, '']; });
$r = $c2->plan('51.1,9.4', '51.2,9.5', null, false, 4, 0, $err); check('HTTP error is reported, no data', $r === null && strpos($err, '503') !== false);
$c3 = new TransitousClient('demo/1.0 (me@example.org)', 3, null, TransitousClient::BASE, function () { return [0, '']; });
check('connection failure is reported', $c3->stopTimes('x', 5, 0, $err) === null && $err === 'connection failed');
echo "\n$ok ok, $bad failed\n"; exit($bad ? 1 : 0);
