<table>
    <tr><td colspan="4" style="font-weight: bold; font-size: 16pt;">Medica Plus Pharmacy LMDC</td></tr>
    <tr><td colspan="4" style="font-weight: bold;">Supplier: {{ $supplierName }}</td></tr>
    <tr><td colspan="4" style="font-style: italic;">Exported on: {{ now()->format('Y-m-d H:i:s') }}</td></tr>
    <tr><td colspan="4"></td></tr>

    <tr>
        <td style="font-weight: bold; background-color: #E5E7EB;">Product Name</td>
        <td style="font-weight: bold; background-color: #E5E7EB;">Packs</td>
        <td style="font-weight: bold; background-color: #E5E7EB;">Pack Size</td>
        <td style="font-weight: bold; background-color: #E5E7EB;">Total Units</td>
    </tr>
    @foreach($rows as $r)
        <tr>
            <td>{{ $r->product_name }}</td>
            <td>{{ $r->full_packs }}</td>
            <td>{{ $r->pack_size }}</td>
            <td>{{ $r->full_packs * $r->pack_size }}</td>
        </tr>
    @endforeach
    <tr>
        <td style="font-weight: bold;">TOTAL</td>
        <td style="font-weight: bold;">{{ $rows->sum('full_packs') }}</td>
        <td></td>
        <td style="font-weight: bold;">{{ $rows->sum(fn($r) => $r->full_packs * $r->pack_size) }}</td>
    </tr>
</table>