<?php

namespace Blalmal10a\DbZip\Commands;

use Illuminate\Console\Command;

class DbZipCommand extends Command
{
    public $signature = 'db-zip';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
