<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Department;
use App\Models\SourceOfFund;
use App\Models\Project;
use App\Models\Item;
use Carbon\Carbon;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        
        $departments = Department::all();
        $projects = array();
        foreach($departments as $department){
            $sourceOfFund = SourceOfFund::create([
                'name' => 'Unspecified',
                'department_id' => $department->id,
            ]);
            
            $item = Item::create([
                'name' => 'Unspecified',
                'department_id' => $department->id,
            ]);

            $individual = Project::create([
                'name' => 'Request Poll',
                'descriptions' => 'List Of Requests',
                'dateStarted' => Carbon::now(),
                'department_id' => $department->id,
                'is_organization' => false,
                'is_request_only' => true,
            ]);
            $individual->sourceOfFund()->attach($sourceOfFund->id);
            $individual->item()->attach($item->id);

            $organization = Project::create([
                'name' => 'Request Poll',
                'descriptions' => 'List Of Requests',
                'dateStarted' => Carbon::now(),
                'department_id' => $department->id,
                'is_organization' => true,
                'is_request_only' => true,
            ]);
            $organization->sourceOfFund()->attach($sourceOfFund->id);
            $organization->item()->attach($item->id);
        }

        // DB::table('projects')->insert($projects);
    }
}
