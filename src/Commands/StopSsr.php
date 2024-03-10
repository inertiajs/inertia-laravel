<?php

namespace Inertia\Commands;

use Illuminate\Console\Command;

class StopSsr extends Command
{

    protected $signature = 'inertia:stop-ssr {--bundle=default : The path to the Inertia SSR bundle to use (defaults to the configured path)}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop the Inertia SSR server';

    /**
     * Stop the SSR server.
     */
    public function handle(): int
    {
        $bundleOption = $this->option('bundle');

        $url = str_replace('/render', '', config("inertia.ssr.$bundleOption.url")).'/shutdown';

        $ch = curl_init($url);
        curl_exec($ch);

        if (curl_error($ch) !== 'Empty reply from server') {
            $this->error('Unable to connect to Inertia SSR server.');

            return self::FAILURE;
        }

        $this->info('Inertia SSR server stopped.');

        curl_close($ch);

        return self::SUCCESS;
    }
}
