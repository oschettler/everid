<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnwriteExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enwrite:export {--all} {--auth=} {--notebook=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export notes from Evernote';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $auth = $this->option('auth');
        if (!$auth) {
            $this->error('Please pass an authentication token');
        }

        if ($this->option('all')) {
            $this->comment('Exporting ALL notes');

            // ...
        }
        elseif ($notebook = $this->option('notebook')) {

            // ...
        }
        else {
            $this->error('Please pass either a notebook name or --all');
        }
    }
}
