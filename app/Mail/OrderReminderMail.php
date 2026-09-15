<?php

namespace App\Mail;

use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class OrderReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $supplier;
    public $accumulatedSales;

    public function __construct(Supplier $supplier, Collection $accumulatedSales)
    {
        $this->supplier = $supplier;
        $this->accumulatedSales = $accumulatedSales;
    }

    public function build()
    {
        return $this->subject("Order Reminder: {$this->supplier->name}")
                    ->view('emails.order-reminder')
                    ->with([
                        'supplier' => $this->supplier,
                        'accumulatedSales' => $this->accumulatedSales,
                        'orderUrl' => route('orders.create', ['supplier_id' => $this->supplier->id]),
                    ]);
    }
}