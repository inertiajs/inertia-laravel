<?php

namespace Inertia\Commands;

use Illuminate\Console\Command;
use Inertia\Ssr\HttpGateway;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'inertia:stop-ssr')]
class StopSsr extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'inertia:stop-ssr {--graceful : Return a successful exit code when the SSR server is not running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop the Inertia SSR server';

    /**
     * Stop the Inertia SSR server.
     */
    public function handle(HttpGateway $gateway): int
    {
        $url = $gateway->getUrl('/shutdown');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $errno = curl_errno($ch);

        if ($errno === CURLE_GOT_NOTHING) {
            $this->info('Inertia SSR server stopped.');

            return self::SUCCESS;
        }

        if ($this->option('graceful') && $errno !== CURLE_OK) {
            $this->comment('Inertia SSR server is not running.');

            return self::SUCCESS;
        }

        $this->error('Unable to connect to Inertia SSR server.');

        return self::FAILURE;
    }
}
