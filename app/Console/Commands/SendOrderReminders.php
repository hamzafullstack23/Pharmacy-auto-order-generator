<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\AccumulatedSale;
use App\Mail\OrderReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendOrderReminders extends Command
{
    protected $signature = 'orders:send-reminders';
    protected $description = 'Send order reminders to suppliers on their order day';

    public function handle()
    {
        $today = Carbon::today();
        $dayName = strtolower($today->format('l')); // monday, tuesday, etc.
        
        $suppliers = Supplier::where('order_day', $dayName)
                             ->where('is_active', true)
                             ->get();
        
        foreach ($suppliers as $supplier) {
            $this->sendReminder($supplier);
        }
        
        $this->info('Order reminders sent for ' . $suppliers->count() . ' suppliers.');
    }

    protected function sendReminder($supplier)
    {
        try {
            $accumulatedSales = AccumulatedSale::where('supplier_id', $supplier->id)
                                               ->where('is_cleared', false)
                                               ->with('medicine')
                                               ->get();
            
            if ($accumulatedSales->isEmpty()) {
                $this->warn("No accumulated sales for supplier: {$supplier->name}");
                return;
            }
            
            Mail::to(auth()->user()->email ?? $supplier->email)
                ->send(new OrderReminderMail($supplier, $accumulatedSales));
                
            $this->info("Reminder sent for supplier: {$supplier->name}");
            
        } catch (\Exception $e) {
            $this->error("Failed to send reminder for supplier {$supplier->name}: " . $e->getMessage());
            \Log::error('Order reminder failed', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}