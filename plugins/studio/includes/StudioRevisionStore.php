<?php
/**
 * File revision history (P23-M14/M15).
 */

require_once __DIR__ . '/StudioProjectRepository.php';

class StudioRevisionStore
{
    private StudioProjectRepository $repo;
    private int $maxPerFile = 20;

    public function __construct(?StudioProjectRepository $repo = null)
    {
        $this->repo = $repo ?? new StudioProjectRepository();
    }

    public function record(string $projectId, string $relativePath, string $previousContent): void
    {
        $dir = $this->repo->projectDir($projectId) . '/.revisions';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $key = hash('sha256', $relativePath);
        $file = $dir . '/' . $key . '.jsonl';
        $entry = json_encode([
            'path' => $relativePath,
            'at' => date('c'),
            'content' => $previousContent,
        ], JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
        $this->trim($file);
    }

    public function listForPath(string $projectId, string $relativePath): array
    {
        $dir = $this->repo->projectDir($projectId) . '/.revisions';
        $key = hash('sha256', $relativePath);
        $file = $dir . '/' . $key . '.jsonl';
        if (!is_file($file)) {
            return [];
        }
        $lines = array_filter(explode("\n", (string) file_get_contents($file)));
        $out = [];
        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $out[] = $decoded;
            }
        }
        return $out;
    }

    private function trim(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if (count($lines) <= $this->maxPerFile) {
            return;
        }
        $lines = array_slice($lines, -$this->maxPerFile);
        file_put_contents($file, implode("\n", $lines) . "\n");
    }
}
