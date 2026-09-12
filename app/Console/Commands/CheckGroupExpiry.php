<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckGroupExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'groups:check-expiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired course groups and activate them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredGroups = \App\Models\CourseGroup::whereIn('status', ['open_for_registration', 'waiting_for_students'])
            ->whereNotNull('registration_deadline')
            ->where('registration_deadline', '<=', now())
            ->get();

        foreach ($expiredGroups as $group) {
            \App\Services\CourseGroupService::activateAndSpawnNext($group);
            $this->info("Group {$group->id} activated due to expiry.");
        }
        
        $this->info('Checked ' . $expiredGroups->count() . ' expired groups.');
    }
}
