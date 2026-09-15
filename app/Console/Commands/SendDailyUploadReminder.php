<?php

namespace App\Console\Commands;

use App\Mail\DailyUploadReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendDailyUploadReminder extends Command
{
    protected $signature = 'sales:send-upload-reminder';
    protected $description = 'Send daily reminder to upload sales data';

    public function handle()
    {
        // Send to all active users (or configured admin email)
        $users = \App\Models\User::all();
        $today = Carbon::today()->format('l, F j, Y');
        
        foreach ($users as $user) {
            try {
                Mail::to($user->email)->send(new DailyUploadReminderMail($today));
                $this->info("Reminder sent to: {$user->email}");
            } catch (\Exception $e) {
                $this->error("Failed to send to {$user->email}: " . $e->getMessage());
            }
        }
    }
}