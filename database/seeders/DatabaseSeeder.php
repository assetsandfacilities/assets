<?php

namespace Database\Seeders;

use App\Models\ActivityProposal;
use App\Models\Allocation;
use App\Models\Department;
use App\Models\AssetScanLog;
use App\Models\InventoryUsageLog;
use App\Models\FacilityReservation;
use App\Models\Facility;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

function bcryptSecure(string $value): string
{
    return Hash::driver('bcrypt')->make($value, ['rounds' => (int) config('security.bcrypt_rounds', 12)]);
}

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $it = Department::updateOrCreate(['code' => 'IT'], ['name' => 'IT Department', 'opex_limit' => 200]);
        $acct = Department::updateOrCreate(['code' => 'ACC'], ['name' => 'Accounting Office', 'opex_limit' => 150]);
        $amo = Department::updateOrCreate(['code' => 'AMO'], ['name' => 'Asset Management Office', 'opex_limit' => 300]);

        User::updateOrCreate(
            ['email' => 'superadmin@nuclark.local'],
            ['department_id' => $amo->id, 'name' => 'System Super Admin', 'password' => bcryptSecure('super123'), 'role' => 'super_admin', 'account_type' => 'asset_super_admin', 'access_scope' => 'asset', 'approver_type' => null, 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );
        User::updateOrCreate(
            ['email' => 'admin@nuclark.local'],
            ['department_id' => $amo->id, 'name' => 'Asset Management Admin', 'password' => bcryptSecure('admin123'), 'role' => 'admin', 'account_type' => 'asset_admin', 'access_scope' => 'asset', 'approver_type' => null, 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );
        $dean = User::updateOrCreate(
            ['email' => 'dean@nuclark.local'],
            ['department_id' => $acct->id, 'name' => 'College Dean Approver', 'password' => bcryptSecure('dean12345'), 'role' => 'approver', 'account_type' => 'approver', 'access_scope' => 'asset', 'approver_type' => 'dean', 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );
        $executive = User::updateOrCreate(
            ['email' => 'exec@nuclark.local'],
            // Campus-wide role: no department by design.
            ['department_id' => null, 'name' => 'Executive Director', 'password' => bcryptSecure('exec12345'), 'role' => 'approver', 'account_type' => 'approver', 'access_scope' => 'asset', 'approver_type' => 'executive', 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );
        $requestor = User::updateOrCreate(
            ['email' => 'requestor@nuclark.local'],
            ['department_id' => $acct->id, 'name' => 'Sample Requestor', 'password' => bcryptSecure('request123'), 'role' => 'requestor', 'account_type' => 'requestor', 'access_scope' => 'asset', 'approver_type' => null, 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );
        $adviser = User::updateOrCreate(
            ['email' => 'adviser@nuclark.local'],
            ['department_id' => $acct->id, 'name' => 'Student Org Adviser', 'password' => bcryptSecure('adviser123'), 'role' => 'approver', 'account_type' => 'approver', 'access_scope' => 'asset', 'approver_type' => 'adviser', 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );
        $sdao = User::updateOrCreate(
            ['email' => 'sdao@nuclark.local'],
            ['department_id' => $acct->id, 'name' => 'SDAO Officer', 'password' => bcryptSecure('sdao12345'), 'role' => 'approver', 'account_type' => 'approver', 'access_scope' => 'asset', 'approver_type' => 'sdao', 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );
        $academicDirector = User::updateOrCreate(
            ['email' => 'academicdirector@nuclark.local'],
            // Campus-wide role: no department by design.
            ['department_id' => null, 'name' => 'Priscilla M. Evangelista', 'password' => bcryptSecure('acaddir123'), 'role' => 'approver', 'account_type' => 'approver', 'access_scope' => 'asset', 'approver_type' => 'academic_director', 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );

        $electronics = ItemCategory::updateOrCreate(['name' => 'Electronics'], ['description' => 'Monitors, system units, peripherals']);
        $furniture = ItemCategory::updateOrCreate(['name' => 'Furniture'], ['description' => 'Campus chairs and tables']);
        $office = ItemCategory::updateOrCreate(['name' => 'Office Supplies'], ['description' => 'Bond paper, pens, folders and daily consumables']);

        $assetTypesByCategory = [
            $electronics->id => ['Desktop Computer', 'Laptop', 'Monitor', 'Printer', 'Scanner', 'Projector', 'Television', 'Router / Switch', 'UPS', 'Server', 'Speaker / Sound System', 'Other Electronics'],
            $furniture->id => ['Chair', 'Table', 'Cabinet', 'Whiteboard', 'Shelf', 'Podium', 'Other Furniture'],
            $office->id => ['Consumable Supply', 'Stationery', 'Paper Product', 'Other Supply'],
        ];
        foreach ($assetTypesByCategory as $categoryId => $names) {
            foreach ($names as $name) {
                \App\Models\AssetType::firstOrCreate(['item_category_id' => $categoryId, 'name' => $name]);
            }
        }

        $floorNames = ['4th Floor', '5th Floor', '6th Floor', '7th Floor', '8th Floor'];
        $floorModels = [];
        foreach ($floorNames as $i => $floorName) {
            $floorModels[$floorName] = \App\Models\Floor::firstOrCreate(['name' => $floorName], ['sort_order' => $i]);
        }
        $roomsByFloorName = [
            '4th Floor' => ['410', '411', '412'],
            '5th Floor' => ['510', '511', '512'],
            '6th Floor' => ['610', '611', '612'],
            '7th Floor' => ['718', '719', '720'],
            '8th Floor' => ['810', '811', '812'],
        ];
        $roomModels = [];
        foreach ($roomsByFloorName as $floorName => $roomNames) {
            foreach ($roomNames as $roomName) {
                $roomModels[$roomName] = \App\Models\Room::firstOrCreate([
                    'floor_id' => $floorModels[$floorName]->id,
                    'name' => $roomName,
                ]);
            }
        }

        $monitor = Item::updateOrCreate(['item_code' => '400101-1'], [
            'category_id' => $electronics->id,
            'name' => '24-inch Monitor', 'item_type' => 'CAPEX', 'description' => 'Assigned to laboratory room', 'specifications' => '24-inch LED monitor',
            'quantity' => 1, 'unit' => 'pc', 'unit_price' => 9500, 'brand' => 'Generic', 'availability_status' => 'Available', 'low_stock_threshold' => 0, 'qr_value' => '400101-1',
            'floor' => '7th Floor', 'floor_id' => $floorModels['7th Floor']->id, 'room_assigned' => '718', 'room_id' => $roomModels['718']->id, 'is_active' => true
        ]);
        Item::updateOrCreate(['item_code' => '300101-1'], [
            'category_id' => $furniture->id,
            'name' => 'Jasmine Chair', 'item_type' => 'CAPEX', 'description' => 'Student chair with QR room tracking', 'specifications' => 'School chair',
            'quantity' => 1, 'unit' => 'pc', 'unit_price' => 2500, 'brand' => 'Generic', 'availability_status' => 'Available', 'low_stock_threshold' => 0, 'qr_value' => '300101-1',
            'floor' => '7th Floor', 'floor_id' => $floorModels['7th Floor']->id, 'room_assigned' => '719', 'room_id' => $roomModels['719']->id, 'is_active' => true
        ]);

        $opexItems = [
            ['CERTIFICATE HOLDER','A4, 9" x 12"; NAVY BLUE','piece',36.00,'ADVENTURER','Available',25],
            ['CLIP, BINDER CLIP','1 5/8", BLACK; 12 pcs / box','box',43.20,'NO BRAND','Available',18],
            ['CLIP, BINDER CLIP','2", BLACK; 12 pcs / box','box',63.00,'NO BRAND','Available',15],
            ['CLIP, PAPER CLIP','BIG; VINYL COATED','box',22.00,'PRINCE','Out of Stock',0],
            ['CLIP, PAPER CLIP','SMALL; VINYL COATED','box',10.50,'PRINCE','Out of Stock',0],
            ['CORRECTION TAPE','J-863 5mm x 8m','piece',16.00,'JOY','Out of Stock',0],
            ['ENVELOPE','LONG; BROWN','piece',1.60,'NO BRAND','Available',100],
            ['ENVELOPE, EXPANDABLE','LONG, WITH GARTER; BLUE','piece',12.00,'COSMIC','Available',35],
            ['FILE DIVIDER','A4, 5s / pack; ASSORTED COLOR','pack',20.00,'NO BRAND','Available',20],
            ['FOLDER','LONG; WHITE','piece',4.80,'ASIAN','Available',50],
            ['FOLDER','SHORT; WHITE','piece',4.20,'ASIAN','Limited Stock',5],
            ['FOLDER, ARCH FILE','A4; 2 RINGS, 3"; BLUE','piece',85.00,'SNOWMAN','Available',12],
            ['FOLDER, ARCH FILE','LONG; 2 RINGS, 3"; BLUE','piece',90.00,'SNOWMAN','Available',12],
            ['FOLDER, EXPANDABLE','LONG; BLUE','piece',16.00,'PIX','Available',15],
            ['FOLDER, PLASTIC JACKET','LONG; CLEAR','piece',10.00,'ADVENTURER','Available',30],
            ['GLUE','130g, LIQUID WHITE','bottle',56.00,"ELMER'S",'Available',14],
            ['IN AND OUT TRAY','3-LAYER, METAL; BLACK','piece',550.00,'NO BRAND','Limited Stock',3],
            ['INK, STAMP PAD','PURPLE','bottle',14.50,'LCT','Available',10],
            ['LAMINATING FILM','A4, 100s per box; 125 MICRON','box',530.00,'NO BRAND','Available',8],
            ['MAGAZINE BOX','SINGLE; NAVY BLUE','piece',90.00,'NO BRAND','Available',10],
            ['PAPER, BOND PAPER','LONG (8.5" x 13"); 500s / ream','ream',190.00,'A PLUS','Limited Stock',5],
            ['PAPER, BOND PAPER','A4 (8.27" x 11.69"); 500s / ream','ream',171.00,'ADVANCE','Limited Stock',5],
            ['PAPER, BOND PAPER','SHORT / LETTER (8.5" x 11"); 500s / ream','ream',160.00,'A PLUS','Limited Stock',5],
            ['PAPER, SPECIALTY BOARD','A4, 10s per pack; PALE CREAM','pack',32.00,'NO BRAND','Limited Stock',4],
            ['PAPER, STICKER','A4, 10s per pack; WHITE MATTE','pack',32.00,'NO BRAND','Available',12],
            ['PEN, MARKER, PERMANENT','REFILLABLE; BLACK','piece',29.50,'PILOT','Limited Stock',4],
            ['PEN, MARKER, WHITEBOARD','REFILLABLE; BLACK','piece',46.00,'PILOT','Out of Stock',0],
            ['PENCIL','#2','piece',8.00,'MONGOL','Out of Stock',0],
            ['PUNCHER','BIG','piece',160.00,'HBW','Available',7],
            ['PUSHPIN','100s per box','box',30.00,'NO BRAND','Available',11],
            ['RECORD BOOK','500 pages','piece',85.00,'ASIAN','Available',6],
            ['RECORD BOOK','300 pages','piece',62.00,'ASIAN','Available',9],
            ['RULER','12 inches','piece',7.00,'PRINCE','Limited Stock',4],
            ['SCISSORS','8 1/4"','piece',37.00,'HBW','Limited Stock',4],
            ['STAMP PAD','WITH INK, BLUE; #2','piece',32.00,'LCT','Available',8],
            ['STAMP, MANUAL DATER','4mm','piece',36.00,'JOY','Limited Stock',2],
            ['STAPLE WIRE','#35-5M, 26/6; 5000s','box',74.00,'MAX','Available',9],
            ['STAPLER','HD-50/50R; WITH REMOVER','piece',390.00,'MAX','Limited Stock',2],
            ['STICKY NOTES','3" x 3"','pad',15.00,'NO BRAND','Out of Stock',0],
            ['TAPE DISPENSER','BIG','piece',87.00,'NO BRAND','Available',5],
            ['TAPE, CLEAR','1in. x 50y','roll',12.00,'APPLE','Available',16],
            ['TAPE, DOUBLE-SIDED','1in. WITHOUT FOAM','roll',20.50,'NO BRAND','Available',8],
            ['TAPE, MASKING','1in','roll',32.00,'CROCODILE','Available',10],
        ];

        foreach ($opexItems as $index => [$name, $specs, $unit, $price, $brand, $status, $qty]) {
            $code = 'OPEX-AMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            Item::updateOrCreate(['item_code' => $code], [
                'category_id' => $office->id,
                'name' => $name,
                'item_type' => 'OPEX',
                'description' => 'Current AMO supply item imported from the provided inventory screenshots.',
                'specifications' => $specs,
                'quantity' => $qty,
                'unit' => $unit,
                'unit_price' => $price,
                'brand' => $brand,
                'availability_status' => $status,
                'low_stock_threshold' => 5,
                'qr_value' => null,
                'room_assigned' => null,
                'is_active' => true,
            ]);
        }

        Allocation::updateOrCreate(['department_id' => $it->id, 'item_type' => 'CAPEX'], ['max_quantity' => 3, 'period_label' => 'Yearly']);
        Allocation::updateOrCreate(['department_id' => $it->id, 'item_type' => 'OPEX'], ['max_quantity' => 50, 'period_label' => 'Monthly']);
        Allocation::updateOrCreate(['department_id' => $acct->id, 'item_type' => 'CAPEX'], ['max_quantity' => 2, 'period_label' => 'Yearly']);
        Allocation::updateOrCreate(['department_id' => $acct->id, 'item_type' => 'OPEX'], ['max_quantity' => 40, 'period_label' => 'Monthly']);

        $supplier = Supplier::updateOrCreate(['name' => 'TechSource Trading'], [
            'contact_person' => 'Maria Santos', 'email' => 'maria@techsource.local', 'phone' => '09171234567', 'address' => 'Angeles City'
        ]);
        $supplier2 = Supplier::updateOrCreate(['name' => 'OfficeHub Supply'], [
            'contact_person' => 'John Cruz', 'email' => 'sales@officehub.local', 'phone' => '09179876543', 'address' => 'Clark, Pampanga'
        ]);

        $bond = Item::where('item_code', 'OPEX-AMO-021')->first();
        $stapler = Item::where('item_code', 'OPEX-AMO-038')->first();

        $requisition = Requisition::updateOrCreate(['requisition_no' => 'REQ-SAMPLE-001'], [
            'user_id' => $requestor->id,
            'department_id' => $acct->id,
            'branch' => 'NU Clark',
            'csf_no' => 'CSF-001',
            'requested_by_name' => $requestor->name,
            'checked_by_name' => $dean->name,
            'approved_by_name' => $executive->name,
            'status' => 'pending_asset_management',
            'purpose' => 'Daily operation / enrollment',
            'requested_at' => now(),
        ]);

        if ($bond) {
            RequisitionItem::updateOrCreate(['requisition_id' => $requisition->id, 'item_id' => $bond->id], [
                'quantity_requested' => 5,
                'quantity_approved' => null,
                'remarks' => 'For office printing',
                'unit_price' => $bond->unit_price,
                'total_amount' => round((float) $bond->unit_price * 5, 2),
            ]);
        }

        if ($stapler) {
            RequisitionItem::updateOrCreate(['requisition_id' => $requisition->id, 'item_id' => $stapler->id], [
                'quantity_requested' => 1,
                'quantity_approved' => null,
                'remarks' => 'For records and enrollment forms',
                'unit_price' => $stapler->unit_price,
                'total_amount' => round((float) $stapler->unit_price * 1, 2),
            ]);
        }


        $fmoDept = Department::updateOrCreate(['code' => 'FMO'], ['name' => 'Facilities Management Office', 'opex_limit' => 100]);
        // Dedicated Facilities Management Office Super Admin. Separate account,
        // separate domain -- it has no Asset Management privileges at all.
        User::updateOrCreate(
            ['email' => 'fmosuperadmin@nuclark.local'],
            ['department_id' => $fmoDept->id, 'name' => 'FMO Super Admin', 'password' => bcryptSecure('fmosuper123'), 'role' => 'fmo_super_admin', 'account_type' => 'fmo_super_admin', 'access_scope' => 'fmo', 'approver_type' => null, 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );
        $fmoUser = User::updateOrCreate(
            ['email' => 'fmo@nuclark.local'],
            ['department_id' => $fmoDept->id, 'name' => 'FMO Officer', 'password' => bcryptSecure('fmo12345'), 'role' => 'fmo', 'account_type' => 'fmo_staff', 'access_scope' => 'fmo', 'approver_type' => null, 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );
        $housekeeping = User::updateOrCreate(
            ['email' => 'housekeeping@nuclark.local'],
            ['department_id' => $fmoDept->id, 'name' => 'Housekeeping Scanner', 'password' => bcryptSecure('house123'), 'role' => 'housekeeping', 'account_type' => 'housekeeping', 'access_scope' => 'fmo', 'approver_type' => null, 'is_approved' => true, 'approved_at' => now(), 'email_verified_at' => now()]
        );

        // Reservation-form catalogue managed by the FMO Super Admin.
        $facilityCatalog = [
            ['Table', 'item', 'pc'], ['Chairs', 'item', 'pc'], ['Sound System', 'item', 'set'],
            ['Speaker', 'item', 'pc'], ['Projector', 'item', 'pc'], ['Extension Cord', 'item', 'pc'],
            ['Microphone', 'item', 'pc'], ['Flag', 'item', 'pc'], ['Whiteboard', 'item', 'pc'],
            ['ITSO Services', 'service', 'personnel'], ['Technical Assistance', 'service', 'personnel'],
            ['Audio / Visual Support', 'service', 'personnel'], ['Janitors', 'service', 'personnel'],
            ['Electricians', 'service', 'personnel'],
        ];
        foreach ($facilityCatalog as $i => [$catalogName, $catalogType, $catalogUnit]) {
            \App\Models\FacilityItem::updateOrCreate(
                ['name' => $catalogName, 'type' => $catalogType],
                ['unit' => $catalogUnit, 'allows_quantity' => true, 'is_active' => true, 'sort_order' => $i]
            );
        }

        $auditorium = Facility::updateOrCreate(['code' => 'AUD-001'], [
            'name' => 'Main Auditorium', 'location' => 'Ground Floor', 'capacity' => 250,
            'resources' => 'Stage, projector, sound system, chairs', 'is_active' => true,
        ]);
        $lab = Facility::updateOrCreate(['code' => 'LAB-718'], [
            'name' => 'Computer Laboratory 718', 'location' => '7th Floor', 'capacity' => 40,
            'resources' => 'Computers, projector, whiteboard', 'is_active' => true,
        ]);
        Facility::updateOrCreate(['code' => 'ROOM-719'], [
            'name' => 'Lecture Room 719', 'location' => '7th Floor', 'capacity' => 45,
            'resources' => 'Chairs, tables, TV display', 'is_active' => true,
        ]);
        Facility::updateOrCreate(['code' => 'PE-AREA'], [
            'name' => 'PE Area', 'location' => 'Ground Floor', 'capacity' => 500,
            'resources' => 'Open grounds', 'is_active' => true,
        ]);
        Facility::updateOrCreate(['code' => 'AVR'], [
            'name' => 'AVR', 'location' => '2nd Floor', 'capacity' => 120,
            'resources' => 'Projector, sound system, stage lighting', 'is_active' => true,
        ]);
        Facility::updateOrCreate(['code' => 'CASE-ROOM'], [
            'name' => 'Case Room', 'location' => '3rd Floor', 'capacity' => 60,
            'resources' => 'Round tables, whiteboard', 'is_active' => true,
        ]);
        Facility::updateOrCreate(['code' => 'LRC-DISC-1'], [
            'name' => 'LRC Discussion Room 1', 'location' => 'Learning Resource Center', 'capacity' => 25,
            'resources' => 'Whiteboard, TV screen', 'is_active' => true,
        ]);
        Facility::updateOrCreate(['code' => 'LRC-DISC-2'], [
            'name' => 'LRC Discussion Room 2', 'location' => 'Learning Resource Center', 'capacity' => 25,
            'resources' => 'Whiteboard, TV screen', 'is_active' => true,
        ]);
        Facility::updateOrCreate(['code' => 'SPORTS-COMPLEX'], [
            'name' => 'Sports Complex', 'location' => 'Clark Campus Grounds', 'capacity' => 800,
            'resources' => 'Bleachers, court markings, scoreboard', 'is_active' => true,
        ]);
        Facility::updateOrCreate(['code' => 'VENUE-OTHER'], [
            'name' => 'Others (To Be Specified)', 'location' => 'See remarks', 'capacity' => 0,
            'resources' => 'Specify in the proposal notes', 'is_active' => true,
        ]);

        FacilityReservation::updateOrCreate(['reservation_no' => 'FR-SAMPLE-001'], [
            'user_id' => $requestor->id,
            'facility_id' => $auditorium->id,
            'title' => 'Capstone Orientation',
            'purpose' => 'Academic orientation and project briefing',
            'resources_needed' => 'Projector, microphone, chairs',
            'start_at' => now()->addDays(3)->setTime(9, 0),
            'end_at' => now()->addDays(3)->setTime(12, 0),
            'status' => 'pending',
        ]);
        FacilityReservation::updateOrCreate(['reservation_no' => 'FR-SAMPLE-002'], [
            'user_id' => $fmoUser->id,
            'facility_id' => $lab->id,
            'title' => 'System Testing Session',
            'purpose' => 'Controlled testing for the integrated system',
            'resources_needed' => 'Lab computers',
            'start_at' => now()->addDays(5)->setTime(13, 0),
            'end_at' => now()->addDays(5)->setTime(15, 0),
            'status' => 'approved',
            'reviewed_by' => $fmoUser->id,
            'reviewed_at' => now(),
        ]);

        $sampleProposalReservation = FacilityReservation::updateOrCreate(['reservation_no' => 'FR-SAMPLE-003'], [
            'user_id' => $requestor->id,
            'facility_id' => $auditorium->id,
            'title' => 'Intramurals Opening Program',
            'purpose' => 'Activity Proposal: Intramurals Opening Program',
            'resources_needed' => 'Table, Chairs, Sound System, Flag',
            'start_at' => now()->addDays(10)->setTime(8, 0),
            'end_at' => now()->addDays(10)->setTime(12, 0),
            'status' => 'pending',
        ]);
        $sampleProposal = ActivityProposal::updateOrCreate(['proposal_no' => 'AP-SAMPLE-001'], [
            'user_id' => $requestor->id,
            'organization_name' => 'Supreme Student Council',
            'requester_position' => 'President',
            'department_id' => $acct->id,
            'adviser_id' => $adviser->id,
            'department_approver_id' => $dean->id,
            'sdao_id' => $sdao->id,
            'facilities_mgmt_id' => $fmoUser->id,
            'academic_director_id' => $academicDirector->id,
            'executive_director_id' => $executive->id,
            'title' => 'Intramurals Opening Program',
            'activity_days' => 'Monday',
            'speaker_name' => null,
            'program_flow' => "8:00 AM Registration\n8:30 AM Opening Remarks\n9:00 AM Parade of Athletes\n10:00 AM Opening Games",
            'participants_count' => 300,
            'equipment_needed' => 'Table, Chairs, Sound System, Flag',
            'facility_id' => $auditorium->id,
            'start_at' => now()->addDays(10)->setTime(8, 0),
            'end_at' => now()->addDays(10)->setTime(12, 0),
            'facility_reservation_id' => $sampleProposalReservation->id,
            'status' => 'pending_noted',
            'adviser_signed_by' => $adviser->id,
            'adviser_signed_at' => now()->subDay(),
            'department_signed_by' => $dean->id,
            'department_signed_at' => now()->subHours(6),
            // SDAO has not signed yet -> demonstrates the dual-signature "Noted By" stage waiting on one more signature
        ]);
        $sampleProposalReservation->update(['activity_proposal_id' => $sampleProposal->id]);

        if ($bond) {
            $usage = [95, 110, 126, 138, 154, 165];
            foreach ($usage as $idx => $qty) {
                InventoryUsageLog::updateOrCreate([
                    'item_id' => $bond->id,
                    'usage_date' => now()->subMonths(5 - $idx)->startOfMonth()->toDateString(),
                    'source' => 'seeded_history',
                ], [
                    'quantity_used' => $qty,
                    'remarks' => 'Monthly OPEX consumption history for Linear Regression forecasting demo.',
                ]);
            }
        }

        if ($monitor) {
            AssetScanLog::updateOrCreate([
                'item_id' => $monitor->id,
                'scanned_room' => '719',
                'status' => 'mismatch',
            ], [
                'user_id' => $housekeeping->id,
                'expected_room' => $monitor->room_assigned,
                'latitude' => 15.1850000,
                'longitude' => 120.5390000,
                'notes' => 'Seeded sample: monitor scanned outside assigned room.',
            ]);
        }

        // Fresh installations run migrations before this seeder, so populate
        // deterministic dummy signatures here as well. The migration handles
        // already-existing hard-coded accounts during upgrades.
        User::query()
            ->whereIn('role', ['super_admin', 'admin', 'approver', 'fmo_super_admin', 'fmo'])
            ->whereNull('signature_data')
            ->get()
            ->each(function (User $user) {
                $user->update([
                    'signature_data' => \App\Support\SignatureData::dummy($user->name),
                    'signature_updated_at' => now(),
                ]);
            });
    }
}
