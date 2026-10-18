# transitous-route-map

A tiny web page with a map where you can calculate a public transport connection from A to B.
Nothing else. Routing data comes from [Transitous](https://transitous.org), the map from OpenStreetMap.

Developed by KENDESIGN for the juwesta Bildungszentrum, a vocational training provider in Felsberg, Germany.

What it does

- search a start and a destination (address or stop) or click them on the map
- calculate connections for now or for a chosen departure time
- show the route on the map and list the legs with times and lines

How it behaves towards Transitous

- every answer is cached on disk
- the number of real requests per day is capped (`daily_cap`, default 200)
- every request carries a User-Agent with your contact address
- the page shows the credit "Routing data: Transitous"

Run it

Needs PHP 7.4 or newer, with the `curl` extension.

```
cp config.sample.php config.php     # put your contact address in it
php -S 127.0.0.1:8080 -t public
```

Then open http://127.0.0.1:8080/ . The `cache/` folder must be writable.

Layout

```
public/index.html          the page (Leaflet map)
public/api.php             small JSON proxy for geocoding and trip planning
src/TransitousClient.php   polite client: cache, daily cap, contact User-Agent
src/FileCache.php          file cache
tests/test.php             tests for the client, no network needed
```

Tests

```
php tests/test.php
```

License

MIT, see `LICENSE`.
