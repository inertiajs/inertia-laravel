<?php

namespace Inertia\Console;

use Illuminate\Console\Command;

class StopSsr extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'inertia:stop-ssr';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop the Inertia SSR server';

    /**
     * Stop the SSR server.
     */
    public function handle()
    {
        $url = str_replace('/render', '', config('inertia.ssr.url', 'http://127.0.0.1:13714')).'/shutdown';

        $ch = curl_init($url);
        curl_exec($ch);

        if (curl_error($ch) === 'Empty reply from server') {
            $this->info('Inertia SSR server stopped.');
        } else {
            $this->error('Unable to connect to Inertia SSR server.');

            return 1;
        }

        curl_close($ch);
    }
}
