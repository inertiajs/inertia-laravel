<?php

namespace Inertia\Commands;

use Inertia\Ssr\SsrException;
use Illuminate\Console\Command;
use Inertia\Ssr\BundleDetector;
use Symfony\Component\Process\Process;

class StartSsr extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'inertia:start-ssr';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Inertia SSR server';

    /**
     * Start the SSR server via a Node process.
     */
    public function handle(): int
    {
        $bundle = (new BundleDetector())->detect();
        $configuredBundle = config('inertia.ssr.bundle');

        if ($bundle === null) {
            $this->error(
                $configuredBundle
                    ? 'Inertia SSR bundle not found at the configured path: "'.$configuredBundle.'"'
                    : 'Inertia SSR bundle not found. Set the correct Inertia SSR bundle path in your `inertia.ssr.bundle` config.'
            );

            return self::FAILURE;
        } elseif ($configuredBundle && $bundle !== $configuredBundle) {
            $this->warn('Inertia SSR bundle not found at the configured path: "'.$configuredBundle.'"');
            $this->warn('Using a default bundle instead: "'.$bundle.'"');
        }

        $process = new Process(['node', $bundle]);
        $process->setTimeout(null);
        $process->start();

        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $this->info(trim($data));
            } else {
                $this->error(trim($data));
                report(new SsrException($data));
            }
        }

        return self::SUCCESS;
    }
}
