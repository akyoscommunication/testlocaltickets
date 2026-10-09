<?php

namespace App\Agent;

final class AgentRunErrorFormatter
{
    public static function collectStderr(AgentRunRequest $request, string $processStderr): string
    {
        $codexLog = dirname($request->resultFilePath).'/codex-exec.stderr.log';
        $codexContent = is_file($codexLog) ? trim((string) file_get_contents($codexLog)) : '';
        $wrapper = trim($processStderr);

        if ('' === $wrapper && '' === $codexContent) {
            return '';
        }

        if ('' === $wrapper) {
            return $codexContent;
        }

        if ('' === $codexContent || str_contains($wrapper, $codexContent)) {
            return $wrapper;
        }

        return $wrapper."\n\n--- codex exec ---\n\n".$codexContent;
    }

    public static function summarize(string $stderr, int $exitCode = 1): string
    {
        if ('' === trim($stderr)) {
            return sprintf('code sortie %d (pas de stderr)', $exitCode);
        }

        foreach (self::extractCodexErrorBlocks($stderr) as $data) {
            $error = $data['error'] ?? $data;
            if (!is_array($error)) {
                continue;
            }
            $message = $error['message'] ?? null;
            $code = $error['code'] ?? null;
            $status = $data['status'] ?? null;
            if (is_string($message) && '' !== $message) {
                $prefix = is_string($code) && '' !== $code ? '['.$code.'] ' : '';
                $suffix = is_int($status) ? ' (HTTP '.$status.')' : '';

                return $prefix.$message.$suffix;
            }
        }

        if (preg_match('/Sortie JSON invalide[^\n]*/', $stderr, $m)) {
            return trim($m[0]);
        }

        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $stderr))));
        for ($i = count($lines) - 1; $i >= 0; --$i) {
            $line = $lines[$i];
            if (str_starts_with($line, 'ERROR:') || str_starts_with($line, 'user') || str_starts_with($line, '---')) {
                continue;
            }
            if (self::isNoiseLine($line) || self::isCodexFooterLine($line)) {
                continue;
            }
            if (strlen($line) <= 400) {
                return $line;
            }
        }

        $trimmed = trim($stderr);

        return mb_strlen($trimmed) > 280
            ? mb_substr($trimmed, 0, 280).'…'
            : $trimmed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function extractCodexErrorBlocks(string $stderr): array
    {
        $blocks = [];
        foreach (preg_split('/\nERROR:\s*/', $stderr) as $chunk) {
            $chunk = trim($chunk);
            if (!str_starts_with($chunk, '{')) {
                continue;
            }
            $depth = 0;
            $json = '';
            foreach (str_split($chunk) as $char) {
                $json .= $char;
                if ('{' === $char) {
                    ++$depth;
                } elseif ('}' === $char) {
                    --$depth;
                    if (0 === $depth) {
                        break;
                    }
                }
            }
            $data = json_decode($json, true);
            if (is_array($data)) {
                $blocks[] = $data;
            }
        }

        return array_reverse($blocks);
    }

    private static function isNoiseLine(string $line): bool
    {
        if ('' === $line) {
            return true;
        }

        return (bool) preg_match('/^[\{\}\[\],":\s]+$/', $line);
    }

    private static function isCodexFooterLine(string $line): bool
    {
        if (preg_match('/^tokens used$/i', $line)) {
            return true;
        }

        // Ligne « 12 270 » ou « 12 270 » (compteur tokens Codex)
        return (bool) preg_match('/^[\d\s\u00a0\u202f.,]+$/u', $line);
    }

    public static function tail(string $text, int $maxChars = 4000): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return '…'.mb_substr($text, -$maxChars);
    }
}
