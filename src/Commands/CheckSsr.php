<?php

namespace Inertia\Commands;

use Illuminate\Console\Command;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\HasHealthCheck;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'inertia:check-ssr')]
class CheckSsr extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'inertia:check-ssr';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the Inertia SSR server health status';

    /**
     * check the SSR server via a Node process.
     */
    public function handle(Gateway $gateway): int
    {
        if (! $gateway instanceof HasHealthCheck) {
            $this->error('The SSR gateway does not support health checks.');

            return self::FAILURE;
        }

        ($check = $gateway->isHealthy())
            ? $this->info('Inertia SSR server is running.')
            : $this->error('Inertia SSR server is not running.');

        return $check ? self::SUCCESS : self::FAILURE;
    }
}
