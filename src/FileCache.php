<?php
/** Tiny file cache with the interface TransitousClient expects: get(key), set(key, value, ttlSeconds). */
final class FileCache
{
    private $dir;
    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
        if (!is_dir($this->dir)) @mkdir($this->dir, 0775, true);
    }
    private function file(string $key): string { return $this->dir . '/' . preg_replace('/[^a-z0-9_]/i', '_', $key) . '.json'; }
    public function get(string $key)
    {
        $f = $this->file($key);
        if (!is_file($f)) return null;
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) && ($d['until'] ?? 0) > time() ? $d['value'] : null;
    }
    public function set(string $key, $value, int $ttl): void
    {
        @file_put_contents($this->file($key), json_encode(['until' => time() + $ttl, 'value' => $value]), LOCK_EX);
    }
}
