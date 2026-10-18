<?php
// Copy to config.php and fill in. The contact address goes into the User-Agent of every request to Transitous.
return [
    'contact'    => 'you@example.org',   // required
    'daily_cap'  => 200,                 // real requests to Transitous per day, cached answers do not count
    'near'       => '51.13,9.41',        // optional bias for address search: "lat,lon"
    'map_center' => [51.13, 9.41],
    'map_zoom'   => 11,
];
