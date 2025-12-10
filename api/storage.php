<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

class Storage {
    public $basePath;
    
    public function __construct($basePath = '../content') {
        $this->basePath = rtrim($basePath, '/');
        if (!file_exists($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }
    
    public function read($path) {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        if (!file_exists($fullPath)) {
            return null;
        }
        $content = file_get_contents($fullPath);
        return json_decode($content, true);
    }
    
    public function write($path, $data) {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        $dir = dirname($fullPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        $tmpPath = $fullPath . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $fp = fopen($tmpPath, 'c');
        if (!$fp) return false;
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            rename($tmpPath, $fullPath);
            return true;
        } else {
            fclose($fp);
            return false;
        }
    }
    
    public function delete($path) {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
    
    public function list($path) {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        if (!file_exists($fullPath)) {
            return [];
        }
        $files = scandir($fullPath);
        return array_filter($files, function($file) {
            return $file !== '.' && $file !== '..';
        });
    }
} 