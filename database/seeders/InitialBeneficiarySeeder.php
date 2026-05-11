<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BeneficiaryIdentification;
use App\Models\Beneficiary;
use App\Models\AddrsBrgy;
use Carbon\Carbon;

class InitialBeneficiarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run()
    {
        // DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // Benefeficiary::truncate();
        // DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $csvFile = fopen(base_path("database/csv/beneficiary.csv"), "r");

        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ",")) !== FALSE) {
            if (!$firstline) {
                $exist = BeneficiaryIdentification::where('number', $data[0])
                ->whereHas('identification', function($query){
                    $query->where('name', 'RSBSA ID');
                })->exists();
                if(!$exist){
                    $city = $data[9];
                    $brgy = AddrsBrgy::where('name', $data[8])
                    ->whereHas('city', function($query) use($city){
                        $query->where('name', $city);
                    })
                    ->first();
                    
                    if($brgy){
                        $cais_number = $this->CreateUniqueCaisNumber('PRO');
                        $newBeneficiary = Beneficiary::create([
                            'cais_number' => $cais_number,
                            'firstName' => $data[2],
                            'middleName' => $data[3],
                            'lastName' => $data[1],
                            'suffix' => $data[4],
                            'sex' => $data[5],
                            'birthday' => Carbon::parse($data[6]),
                            'ethnicity' => $data[7],
                            'brgy_id' => $brgy->id,
                        ]);
            
                        $newIdentity = new BeneficiaryIdentification;
                        $newIdentity->beneficiary_id = $newBeneficiary->id;
                        $newIdentity->identification_id = 1;
                        $newIdentity->number = $data[0];
                        $newIdentity->save();
                    }
                }
            }
            $firstline = false;
        }

        fclose($csvFile);
    }
}
