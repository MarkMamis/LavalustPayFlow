<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Simple file-based cache helper.
 * Stores JSON files in runtime/cache/ with structure: { expires: unix_ts, value: any }
 */
if (!function_exists('cache_dir')) {
    function cache_dir(): string {
        $dir = ROOT_DIR . 'runtime/cache/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('cache_key_to_path')) {
    function cache_key_to_path(string $key): string {
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $key);
        return cache_dir() . $safe . '.json';
    }
}

if (!function_exists('cache_set')) {
    function cache_set(string $key, $value, int $ttl = 300): bool {
        $payload = [
            'expires' => time() + $ttl,
            'value' => $value,
        ];
        $path = cache_key_to_path($key);
        $json = json_encode($payload);
        return @file_put_contents($path, $json) !== false;
    }
}

if (!function_exists('cache_get')) {
    function cache_get(string $key) {
        $path = cache_key_to_path($key);
        if (!is_file($path)) return null;
        $json = @file_get_contents($path);
        if (!$json) return null;
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['expires'])) return null;
        if (time() > intval($data['expires'])) {
            @unlink($path);
            return null;
        }
        return $data['value'] ?? null;
    }
}

if (!function_exists('cache_has')) {
    function cache_has(string $key): bool {
        return cache_get($key) !== null;
    }
}

if (!function_exists('cache_delete')) {
    function cache_delete(string $key): bool {
        $path = cache_key_to_path($key);
        if (is_file($path)) return @unlink($path);
        return false;
    }
}
