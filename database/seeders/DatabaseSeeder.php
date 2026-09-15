<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Company;
use App\Models\Medicine;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create admin user only if it doesn't exist
        $user = User::firstOrCreate(
            ['email' => 'admin@pharmacy.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
            ]
        );

        // Check if suppliers already exist
        if (Supplier::count() === 0) {
            // Create suppliers
            $suppliers = [
                [
                    'name' => 'Medico Pharma Distributors',
                    'contact_person' => 'John Doe',
                    'email' => 'john@medicopharma.com',
                    'phone' => '+1234567890',
                    'address' => '123 Pharma Street, City',
                    'order_day' => 'monday',
                ],
                [
                    'name' => 'HealthCare Logistics',
                    'contact_person' => 'Jane Smith',
                    'email' => 'jane@healthcareplus.com',
                    'phone' => '+0987654321',
                    'address' => '456 Health Avenue, City',
                    'order_day' => 'wednesday',
                ],
                [
                    'name' => 'IBL OPERATIONS (PVT) LTD',
                    'contact_person' => 'Mr. Ibrahim',
                    'email' => 'info@ibloperations.com',
                    'phone' => '+923001234567',
                    'address' => '456 Industrial Area, Karachi',
                    'order_day' => 'tuesday',
                ],
                [
                    'name' => 'VIKOR ENTERPRISES (PVT) LTD',
                    'contact_person' => 'Mr. Vikor',
                    'email' => 'info@vikor.com',
                    'phone' => '+923001234568',
                    'address' => '789 Business Hub, Lahore',
                    'order_day' => 'thursday',
                ],
            ];

            foreach ($suppliers as $supplierData) {
                Supplier::firstOrCreate(
                    ['name' => $supplierData['name']],
                    $supplierData
                );
            }
        }

        // Check if companies already exist
        if (Company::count() === 0) {
            // Get supplier IDs
            $supplier1 = Supplier::where('name', 'Medico Pharma Distributors')->first();
            $supplier2 = Supplier::where('name', 'HealthCare Logistics')->first();
            $supplier3 = Supplier::where('name', 'IBL OPERATIONS (PVT) LTD')->first();
            $supplier4 = Supplier::where('name', 'VIKOR ENTERPRISES (PVT) LTD')->first();

            $companies = [
                [
                    'name' => 'Searle Pakistan (Pvt) Ltd.',
                    'contact_person' => 'Dr. Ahmed',
                    'email' => 'info@searle.com.pk',
                    'phone' => '+92213456789',
                    'address' => '456 Industrial Area, Karachi',
                    'supplier_id' => $supplier3 ? $supplier3->id : 1,
                ],
                [
                    'name' => 'Nisa.SF Pvt Ltd.',
                    'contact_person' => 'Dr. Nisa',
                    'email' => 'info@nisa.com.pk',
                    'phone' => '+92213456780',
                    'address' => '789 Business Hub, Lahore',
                    'supplier_id' => $supplier4 ? $supplier4->id : 1,
                ],
                [
                    'name' => 'XYZ Pharmaceuticals',
                    'contact_person' => 'Dr. Khan',
                    'email' => 'info@xyzpharma.com',
                    'phone' => '+92213456781',
                    'address' => '123 Pharma Road, Islamabad',
                    'supplier_id' => $supplier1 ? $supplier1->id : 1,
                ],
                [
                    'name' => 'YZ Healthcare',
                    'contact_person' => 'Dr. Sara',
                    'email' => 'info@yzhealthcare.com',
                    'phone' => '+92213456782',
                    'address' => '456 Health Street, City',
                    'supplier_id' => $supplier2 ? $supplier2->id : 1,
                ],
            ];

            foreach ($companies as $companyData) {
                Company::firstOrCreate(
                    ['name' => $companyData['name']],
                    $companyData
                );
            }
        }

        // Check if medicines already exist
        if (Medicine::count() === 0) {
            $company1 = Company::where('name', 'Searle Pakistan (Pvt) Ltd.')->first();
            $company2 = Company::where('name', 'Nisa.SF Pvt Ltd.')->first();
            $company3 = Company::where('name', 'XYZ Pharmaceuticals')->first();
            $company4 = Company::where('name', 'YZ Healthcare')->first();

            $medicines = [
                // Searle Pakistan products
                [
                    'name' => 'Serenace Inj 25 s',
                    'company_id' => $company1 ? $company1->id : 1,
                    'unit' => '25s',
                    'cost' => 6.39,
                    'pack_type' => 'pack',
                    'pack_size' => 25,
                    'max_stock_limit' => 500,
                    'current_stock' => 200,
                ],
                [
                    'name' => 'Serenace 1.5 Mg Tab 50 s',
                    'company_id' => $company1 ? $company1->id : 1,
                    'unit' => '50s',
                    'cost' => 0.73,
                    'pack_type' => 'pack',
                    'pack_size' => 50,
                    'max_stock_limit' => 1000,
                    'current_stock' => 500,
                ],
                // Nisa.SF products
                [
                    'name' => 'Disp Syringe 5cc 100s (BM)',
                    'company_id' => $company2 ? $company2->id : 1,
                    'unit' => '100s',
                    'cost' => 12.92,
                    'pack_type' => 'pack',
                    'pack_size' => 100,
                    'max_stock_limit' => 300,
                    'current_stock' => 150,
                ],
                [
                    'name' => 'Disp Syringe 10cc 100s (BM)',
                    'company_id' => $company2 ? $company2->id : 1,
                    'unit' => '100s',
                    'cost' => 15.19,
                    'pack_type' => 'pack',
                    'pack_size' => 100,
                    'max_stock_limit' => 300,
                    'current_stock' => 100,
                ],
                // XYZ Pharmaceuticals products
                [
                    'name' => 'Panadol 500mg',
                    'company_id' => $company3 ? $company3->id : 1,
                    'unit' => 'tablet',
                    'cost' => 0.50,
                    'pack_type' => 'pack',
                    'pack_size' => 100,
                    'max_stock_limit' => 1000,
                    'current_stock' => 500,
                ],
                [
                    'name' => 'Panadol Extra 500mg',
                    'company_id' => $company3 ? $company3->id : 1,
                    'unit' => 'tablet',
                    'cost' => 0.60,
                    'pack_type' => 'pack',
                    'pack_size' => 100,
                    'max_stock_limit' => 800,
                    'current_stock' => 300,
                ],
                // YZ Healthcare products
                [
                    'name' => 'Risek 20mg',
                    'company_id' => $company4 ? $company4->id : 1,
                    'unit' => 'capsule',
                    'cost' => 0.75,
                    'pack_type' => 'pack',
                    'pack_size' => 50,
                    'max_stock_limit' => 500,
                    'current_stock' => 200,
                ],
                [
                    'name' => 'Risek 40mg',
                    'company_id' => $company4 ? $company4->id : 1,
                    'unit' => 'capsule',
                    'cost' => 0.90,
                    'pack_type' => 'pack',
                    'pack_size' => 50,
                    'max_stock_limit' => 400,
                    'current_stock' => 150,
                ],
            ];

            foreach ($medicines as $medicineData) {
                Medicine::firstOrCreate(
                    [
                        'name' => $medicineData['name'],
                        'company_id' => $medicineData['company_id'],
                    ],
                    $medicineData
                );
            }
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin credentials: admin@pharmacy.com / password123');
    }
}