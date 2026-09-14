<?php

namespace App\Console\Commands;

use App\Support\UnspscCodeImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('unspsc:import {path : Path to a CSV with code and title columns}')]
#[Description('Import a licensed or curated UNSPSC code list from CSV')]
class ImportUnspscCodes extends Command
{
    public function handle(UnspscCodeImporter $importer): int
    {
        $path = $this->argument('path');

        if (! is_string($path) || $path === '') {
            $this->error('A CSV path is required.');

            return self::FAILURE;
        }

        $count = $importer->importFromCsv($path, markCurated: false);

        $this->info("Imported {$count} UNSPSC code(s). Confirm GS1 usage terms for the file version.");

        return self::SUCCESS;
    }
}
