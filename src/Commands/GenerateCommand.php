<?php

namespace LaravelJsonApi\OpenApiSpec\Commands;

use Illuminate\Console\Command;
use OpenApiGenerator;

class GenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jsonapi:openapi:generate {serverKey}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates an Open API v3 spec';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $serverKey = $this->argument('serverKey');
        
        $this->info('Generating Open API spec...');

        OpenApiGenerator::generate($serverKey);

        $this->line('Complete! /storage/app/'.$serverKey.'_openapi.yaml');
        $this->newLine();
        $this->line('Run the following to see your API docs');
        $this->info('speccy serve storage/app/'.$serverKey.'_openapi.yaml');
        $this->newLine();

        return 0;
    }
}
