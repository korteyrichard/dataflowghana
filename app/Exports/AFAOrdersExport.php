<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class AFAOrdersExport implements FromCollection, WithHeadings
{
    protected $orders;

    public function __construct($orders)
    {
        $this->orders = $orders;
    }

    public function collection(): Collection
    {
        return $this->orders->map(function ($order) {
            return [
                'full_name' => $order->full_name,
                'ghana_card_number' => $order->ghana_card_number,
                'phone' => $order->phone,
                'dob' => $order->dob,
                'occupation' => $order->occupation,
                'region' => $order->region,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Full Name',
            'Ghana Card Number',
            'Phone',
            'Date of Birth',
            'Occupation',
            'Region',
        ];
    }
}
