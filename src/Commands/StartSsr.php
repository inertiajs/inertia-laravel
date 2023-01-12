<?php

namespace Inertia\Commands;

use Illuminate\Console\Command;
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
        $ssrBundle = config('inertia.ssr.bundle', base_path('bootstrap/ssr/ssr.mjs'));

        if (! file_exists($ssrBundle)) {
            $this->components->error('Inertia SSR bundle not found: '.$ssrBundle);
            $this->components->info('Set the correct Inertia SSR bundle path in your `inertia.ssr.bundle` config.');

            return self::FAILURE;
        }

        $process = new Process(['node', $ssrBundle]);
        $process->setTimeout(null);
        $process->start();

        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $this->components->info(trim($data));
            } else {
                $this->components->error(trim($data));
            }
        }

        return self::SUCCESS;
    }
}
