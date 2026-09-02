<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class LegacyAccountingStaticAuditTest extends TestCase
{
    public function test_application_has_no_legacy_accounting_write_or_import_paths(): void
    {
        $appPath = app_path();
        $legacyModels = [
            realpath($appPath . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Account.php'),
            realpath($appPath . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'JournalEntry.php'),
            realpath($appPath . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'GeneralLedger.php'),
        ];

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appPath, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $realPath = realpath($file->getPathname());
            if ($realPath !== false && in_array($realPath, array_filter($legacyModels), true)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            $patterns = [
                '/\bJournalEntry\s*::\s*/',
                '/\bAccount\s*::\s*(?:create|query|where|first|find|all|with|pluck|get|orderBy)/',
                '/\bGeneralLedger\s*::\s*/',
                '/\bnew\s+(?:JournalEntry|Account|GeneralLedger)\b/',
                '/\buse\s+App\\Models\\(?:Account|JournalEntry|GeneralLedger)\s*;/',
                "/['\"]journal_entries['\"])/",
                "/['\"]general_ledgers['\"])/",
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE)) {
                    $line = substr_count(substr($contents, 0, $match[0][1]), "\n") + 1;
                    $violations[] = sprintf('%s:%d matches %s', $file->getPathname(), $line, $match[0][0]);
                }
            }
        }

        $this->assertSame([], $violations, "Legacy accounting paths detected:\n" . implode("\n", $violations));
    }
}
