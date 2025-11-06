<?php
// Cache Manager for Performance Optimization
class CacheManager {
    private static $instance = null;
    private $cache_dir;
    private $default_ttl = 3600; // 1 hour

    private function __construct() {
        $this->cache_dir = __DIR__ . '/../cache/';
        $this->ensureCacheDir();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function ensureCacheDir() {
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }

    // File-based caching
    public function set($key, $data, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->default_ttl;
        }

        $filename = $this->getCacheFilename($key);
        $cache_data = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        $result = file_put_contents($filename, serialize($cache_data), LOCK_EX);
        return $result !== false;
    }

    public function get($key) {
        $filename = $this->getCacheFilename($key);

        if (!file_exists($filename)) {
            return null;
        }

        $cache_data = unserialize(file_get_contents($filename));

        if ($cache_data === false || time() > $cache_data['expires']) {
            @unlink($filename);
            return null;
        }

        return $cache_data['data'];
    }

    public function delete($key) {
        $filename = $this->getCacheFilename($key);
        return @unlink($filename);
    }

    public function clear($pattern = '*') {
        $files = glob($this->cache_dir . $pattern . '.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private function getCacheFilename($key) {
        return $this->cache_dir . md5($key) . '.cache';
    }

    // APCu caching (if available)
    public function setApcu($key, $data, $ttl = null) {
        if (!function_exists('apcu_store')) {
            return false;
        }

        if ($ttl === null) {
            $ttl = $this->default_ttl;
        }

        return apcu_store($key, $data, $ttl);
    }

    public function getApcu($key) {
        if (!function_exists('apcu_fetch')) {
            return null;
        }

        $success = false;
        $data = apcu_fetch($key, $success);
        return $success ? $data : null;
    }

    public function deleteApcu($key) {
        if (!function_exists('apcu_delete')) {
            return false;
        }

        return apcu_delete($key);
    }

    // Smart caching - tries APCu first, falls back to file
    public function smartSet($key, $data, $ttl = null) {
        if (function_exists('apcu_store')) {
            return $this->setApcu($key, $data, $ttl);
        } else {
            return $this->set($key, $data, $ttl);
        }
    }

    public function smartGet($key) {
        if (function_exists('apcu_fetch')) {
            return $this->getApcu($key);
        } else {
            return $this->get($key);
        }
    }

    public function smartDelete($key) {
        if (function_exists('apcu_delete')) {
            return $this->deleteApcu($key);
        } else {
            return $this->delete($key);
        }
    }

    // Cache invalidation helpers
    public function invalidateByPattern($pattern) {
        $this->clear($pattern);
        if (function_exists('apcu_iterator')) {
            $iterator = new APCUIterator('/^' . preg_quote($pattern) . '/');
            foreach ($iterator as $item) {
                apcu_delete($item['key']);
            }
        }
    }

    // Cache statistics
    public function getStats() {
        $stats = [
            'type' => function_exists('apcu_cache_info') ? 'APCu' : 'File',
            'cache_dir_size' => 0,
            'file_count' => 0
        ];

        if (function_exists('apcu_cache_info')) {
            $apcu_info = apcu_cache_info();
            $stats['apcu'] = [
                'hits' => $apcu_info['num_hits'] ?? 0,
                'misses' => $apcu_info['num_misses'] ?? 0,
                'memory_usage' => $apcu_info['mem_size'] ?? 0,
                'entries' => $apcu_info['num_entries'] ?? 0
            ];
        } else {
            // File-based stats
            $files = glob($this->cache_dir . '*.cache');
            $stats['file_count'] = count($files);
            foreach ($files as $file) {
                $stats['cache_dir_size'] += filesize($file);
            }
        }

        return $stats;
    }

    // Cleanup expired cache files
    public function cleanup() {
        $files = glob($this->cache_dir . '*.cache');
        $cleaned = 0;

        foreach ($files as $file) {
            $cache_data = @unserialize(file_get_contents($file));
            if ($cache_data === false || time() > $cache_data['expires']) {
                @unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}

// Convenience functions
function cache_set($key, $data, $ttl = null) {
    return CacheManager::getInstance()->smartSet($key, $data, $ttl);
}

function cache_get($key) {
    return CacheManager::getInstance()->smartGet($key);
}

function cache_delete($key) {
    return CacheManager::getInstance()->smartDelete($key);
}

// Database query caching
function cache_query($sql, $params = [], $ttl = 3600) {
    $cache_key = 'query_' . md5($sql . serialize($params));

    $result = cache_get($cache_key);
    if ($result !== null) {
        return $result;
    }

    $result = db_query($sql, $params);

    if ($result) {
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        cache_set($cache_key, $data, $ttl);
        return $data;
    }

    return null;
}

// Auto-cleanup on shutdown
register_shutdown_function(function() {
    CacheManager::getInstance()->cleanup();
});
?>