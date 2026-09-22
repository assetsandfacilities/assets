<?php

namespace Database\Seeders;

use App\Models\AssetType;
use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class AssetTypeSeeder extends Seeder
{
    /**
     * Predefined Asset Type choices per Item Category.
     * These populate the dependent "Asset Type" dropdown once a Category
     * is selected on the CAPEX item form, so staff no longer have to type
     * the asset type manually.
     */
    public function run(): void
    {
        $map = [
            'Electronics' => [
                'Desktop Computer', 'Laptop Computer', 'Monitor', 'All-in-One PC',
                'Printer', 'Scanner', 'Photocopier', 'Projector', 'Television',
                'Router', 'Network Switch', 'Wireless Access Point', 'Server',
                'Uninterruptible Power Supply (UPS)', 'CCTV Camera', 'Speaker System',
                'Microphone', 'Air Conditioning Unit', 'Electric Fan', 'Telephone Unit',
                'Biometric Scanner', 'Tablet',
            ],
            'Furniture' => [
                'Office Chair', 'Student Chair', 'Office Table', 'Conference Table',
                'Computer Desk', 'Filing Cabinet', 'Storage Cabinet', 'Bookshelf',
                'Sofa', 'Locker', 'Whiteboard', 'Bulletin Board', 'Podium', 'Cubicle Partition',
            ],
            'Office Supplies' => [
                'Paper Shredder', 'Binding Machine', 'Laminating Machine', 'Calculator',
                'Time Clock / Bundy Clock', 'Label Printer',
            ],
        ];

        foreach ($map as $categoryName => $types) {
            $category = ItemCategory::firstOrCreate(['name' => $categoryName]);
            foreach ($types as $typeName) {
                AssetType::firstOrCreate([
                    'item_category_id' => $category->id,
                    'name' => $typeName,
                ]);
            }
        }
    }
}
