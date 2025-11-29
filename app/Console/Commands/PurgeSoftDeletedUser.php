<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgeSoftDeletedUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-soft-deleted-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete a user after soft delete activation of 30 days.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $threshold = Carbon::now()->subDays(30);

        $usersToDelete = User::onlyTrashed()
            ->where('deleted_at', '<', $threshold)
            ->get();

        if ($usersToDelete->isEmpty()) {
            $this->info('No user');
            return;
        }

        foreach ($usersToDelete as $user) {
            // Optional: delete user photos or related data before force deleting
            if ($user->user_photo && file_exists(public_path($user->user_photo))) {
                unlink(public_path($user->user_photo));
            }

            $user->forceDelete(); // permanently removes user
        }

        $this->info(count($usersToDelete) . ' users permanently deleted.');
    }
}
