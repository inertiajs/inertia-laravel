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
    protected $signature = 'inertia:start-ssr {--runtime=node : The runtime to use (`node` or `bun`)} {--bundle=default : The bundle config}';

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
        $bundleOption = $this->option('bundle');

        if (! config("inertia.ssr.$bundleOption.enabled", true)) {
            $this->error("Inertia SSR is not enabled. Enable it via the `inertia.ssr.$bundleOption.enabled` config option.");

            return self::FAILURE;
        }

        $bundle = config("inertia.ssr.$bundleOption.bundle");

        if ($bundle === null || !file_exists($bundle)) {
            $this->error(
                $bundle
                    ? 'Inertia SSR bundle not found at the configured path: "'.$bundle.'"'
                    : "Inertia SSR bundle not found. Set the correct Inertia SSR bundle path in your `inertia.ssr.$bundleOption.bundle` config."
            );

            return self::FAILURE;
        }

        $runtime = $this->option('runtime');

        if (! in_array($runtime, ['node', 'bun'])) {
            $this->error('Unsupported runtime: "'.$runtime.'". Supported runtimes are `node` and `bun`.');

            return self::INVALID;
        }

        $this->callSilently('inertia:stop-ssr', ['--bundle' => $bundleOption]);

        $process = new Process([$runtime, $bundle]);
        $process->setTimeout(null);
        $process->start();

        if (extension_loaded('pcntl')) {
            $stop = function () use ($process) {
                $process->stop();
            };
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, $stop);
            pcntl_signal(SIGQUIT, $stop);
            pcntl_signal(SIGTERM, $stop);
        }

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
