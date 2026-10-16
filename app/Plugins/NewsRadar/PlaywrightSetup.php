<?php

namespace App\Plugins\NewsRadar;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Checks and installs the Playwright package and Chromium build used by HeadlessArticleReader.
 */
class PlaywrightSetup
{
    private const CHECK_TIMEOUT_SECONDS = 30;

    private const INSTALL_TIMEOUT_SECONDS = 900;

    /**
     * @return array{ready: bool, package: bool, browser: bool}
     */
    public function status(): array
    {
        try {
            $process = new Process(
                [(string) env('NEWSRADAR_NODE_BIN', 'node'), base_path('scripts/newsradar/check-playwright.mjs')],
                base_path(),
                null,
                null,
                self::CHECK_TIMEOUT_SECONDS,
            );
            $process->run();
        } catch (RuntimeException) {
            return $this->notReady();
        }

        $data = $process->isSuccessful() ? json_decode($process->getOutput(), true) : null;
        if (! is_array($data)) {
            return $this->notReady();
        }

        $package = (bool) ($data['package'] ?? false);
        $browser = (bool) ($data['browser'] ?? false);

        return ['ready' => $package && $browser, 'package' => $package, 'browser' => $browser];
    }

    /**
     * Installs the npm dependencies (playwright) and the Chromium build, then returns the new status.
     *
     * @return array{ready: bool, package: bool, browser: bool}
     */
    public function configure(): array
    {
        $this->run(
            [(string) env('NEWSRADAR_NPM_BIN', 'npm'), 'install', '--no-audit', '--no-fund'],
            'npm install',
        );
        $this->run(
            [(string) env('NEWSRADAR_NPX_BIN', 'npx'), 'playwright', 'install', 'chromium'],
            'playwright install chromium',
        );

        $status = $this->status();
        if (! $status['ready']) {
            throw new RuntimeException('Playwright sudah dipasang, tetapi pemeriksaan masih gagal. Periksa log server.');
        }

        return $status;
    }

    private function run(array $command, string $label): void
    {
        $process = new Process($command, base_path(), null, null, self::INSTALL_TIMEOUT_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput() ?: $process->getOutput());

            throw new RuntimeException("Gagal menjalankan {$label}: ".mb_substr($detail, -500));
        }
    }

    /**
     * @return array{ready: bool, package: bool, browser: bool}
     */
    private function notReady(): array
    {
        return ['ready' => false, 'package' => false, 'browser' => false];
    }
}
