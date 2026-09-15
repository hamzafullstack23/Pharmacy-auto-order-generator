<!-- resources/views/emails/order-reminder.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Order Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .table th { background: #4F46E5; color: white; padding: 10px; text-align: left; }
        .table td { padding: 10px; border-bottom: 1px solid #ddd; }
        .button { display: inline-block; padding: 10px 20px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px; }
        .footer { margin-top: 20px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Reminder</h1>
            <p>Supplier: {{ $supplier->name }}</p>
        </div>
        <div class="content">
            <h2>Accumulated Sales</h2>
            <p>Period: {{ $accumulatedSales->first()->accumulation_start_date->format('M d, Y') }} 
               to {{ $accumulatedSales->first()->accumulation_end_date->format('M d, Y') }}</p>
            
            @if($accumulatedSales->count() > 0)
                <table class="table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Quantity</th>
                            <th>Pack Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accumulatedSales as $sale)
                            <tr>
                                <td>{{ $sale->medicine->name }}</td>
                                <td>{{ $sale->total_quantity }}</td>
                                <td>{{ ucfirst($sale->medicine->pack_type) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p>No accumulated sales found.</p>
            @endif

            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ $orderUrl }}" class="button">View & Modify Order</a>
            </div>
        </div>
        <div class="footer">
            <p>This is an automated reminder from your Pharmacy Management System.</p>
            <p>Please log in to view and manage your orders.</p>
        </div>
    </div>
</body>
</html>