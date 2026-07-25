<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only viewer over the `security` log channel (storage/logs/security-*.log,
 * daily-rotated). Lines are streamed with fgets() rather than file_get_contents()
 * so a request never has to hold a whole day's file in memory at once.
 *
 * Only 3 call sites write to this channel today: failed/successful login
 * (AuthenticatedSessionController) and 5xx server errors (Exceptions\Handler).
 */
class AdminSecurityLogController extends Controller
{
    private const LINE_PATTERN = '/^\[(?<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(?<level>\w+): (?<message>.*?)(?:\s(?<context>\{.*\}))?\s*$/';

    /** How many of the most recent daily files an unfiltered/date-less request scans. */
    private const MAX_FILES_SCANNED = 30;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
            'email'    => 'nullable|string|max:255',
            'ip'       => 'nullable|string|max:64',
            'level'    => 'nullable|in:info,warning,error,critical',
            'q'        => 'nullable|string|max:255',
            'date'     => 'nullable|date_format:Y-m-d',
        ]);

        $page    = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);

        $rows = [];
        foreach ($this->logFiles($validated['date'] ?? null) as $file) {
            foreach ($this->parseFile($file) as $row) {
                if ($this->matchesFilters($row, $validated)) {
                    $rows[] = $row;
                }
            }
        }

        // Newest first — datetime strings are zero-padded "Y-m-d H:i:s" so a plain strcmp sorts correctly.
        usort($rows, fn ($a, $b) => strcmp($b['datetime'], $a['datetime']));

        $total  = count($rows);
        $offset = ($page - 1) * $perPage;

        return response()->json([
            'data'         => array_slice($rows, $offset, $perPage),
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
            'total'        => $total,
            'per_page'     => $perPage,
        ]);
    }

    /** @return string[] absolute file paths, newest first */
    private function logFiles(?string $date): array
    {
        if ($date) {
            $file = storage_path("logs/security-{$date}.log");

            return is_file($file) ? [$file] : [];
        }

        $files = glob(storage_path('logs/security-*.log')) ?: [];
        rsort($files); // filenames embed the date, so lexicographic sort == chronological sort

        return array_slice($files, 0, self::MAX_FILES_SCANNED);
    }

    /** @return \Generator<array{datetime:string,level:string,message:string,context:array}> */
    private function parseFile(string $path): \Generator
    {
        $handle = @fopen($path, 'r');
        if (!$handle) {
            return;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if (trim($line) === '' || !preg_match(self::LINE_PATTERN, $line, $m)) {
                    continue;
                }

                $context = [];
                if (!empty($m['context'])) {
                    $decoded = json_decode($m['context'], true);
                    if (is_array($decoded)) {
                        $context = $decoded;
                    }
                }

                yield [
                    'datetime' => $m['datetime'],
                    'level'    => strtolower($m['level']),
                    'message'  => trim($m['message']),
                    'context'  => $context,
                ];
            }
        } finally {
            fclose($handle);
        }
    }

    private function matchesFilters(array $row, array $filters): bool
    {
        if (!empty($filters['level']) && $row['level'] !== $filters['level']) {
            return false;
        }

        if (!empty($filters['email'])) {
            $email = strtolower((string) ($row['context']['email'] ?? ''));
            if (!str_contains($email, strtolower($filters['email']))) {
                return false;
            }
        }

        if (!empty($filters['ip'])) {
            $ip = (string) ($row['context']['ip'] ?? '');
            if (!str_contains($ip, $filters['ip'])) {
                return false;
            }
        }

        if (!empty($filters['q'])) {
            $haystack = strtolower($row['message'].' '.json_encode($row['context']));
            if (!str_contains($haystack, strtolower($filters['q']))) {
                return false;
            }
        }

        return true;
    }
}
