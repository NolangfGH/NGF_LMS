<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\AchievementService;
use Illuminate\Console\Command;

class SyncAchievements extends Command
{
    protected $signature='achievements:sync';
    protected $description='Record learning badges from existing saved quizzes and final Code Lab reviews';
    public function handle(AchievementService $service): int
    {
        $students=0;$awards=0;
        Student::orderBy('id')->chunkById(100,function ($accounts) use ($service,&$students,&$awards) {
            foreach ($accounts as $account) {$awards+=$service->sync($account)['earned'];$students++;}
        });
        $this->info('Synced '.$students.' students; '.$awards.' earned badges recorded. XP unchanged.');
        return self::SUCCESS;
    }
}
