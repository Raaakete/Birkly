<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// Simple file-based rate limiting for Birkly

class RateLimiter {
    private $key;
    private $limit;
    private $window;
    private $storageDir;

    public function __construct($key, $limit = 10, $window = 60) {
        $this->key = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
        $this->limit = $limit;
        $this->window = $window; // seconds
        $this->storageDir = sys_get_temp_dir() . '/birkly_rate_limit';
        if (!file_exists($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    private function getFilePath() {
        return $this->storageDir . '/' . $this->key . '.json';
    }

    public function check() {
        $file = $this->getFilePath();
        $now = time();
        $data = [ 'count' => 0, 'start' => $now ];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: $data;
            if ($now - $data['start'] > $this->window) {
                $data = [ 'count' => 0, 'start' => $now ];
            }
        }
        if ($data['count'] >= $this->limit) {
            return false;
        }
        $data['count']++;
        file_put_contents($file, json_encode($data));
        return true;
    }

    public function getRetryAfter() {
        $file = $this->getFilePath();
        $now = time();
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $now - $data['start'] < $this->window) {
                return $this->window - ($now - $data['start']);
            }
        }
        return 0;
    }
} 