CAPEX/OPEX WEB SYSTEM - READY TO RUN

Default setup in this package:
- Database: SQLite (database/database.sqlite)
- Mailer: log (safe for local testing)
- Sample accounts after seeding:
  admin@nuclark.local / admin123
  dean@nuclark.local / dean12345
  exec@nuclark.local / exec12345
  requestor@nuclark.local / request123

Run steps on Windows/XAMPP terminal:
1. Open project folder in terminal
2. composer install
3. copy .env.example .env   (skip if .env already exists)
4. php artisan key:generate
5. php artisan migrate:fresh --seed
6. php artisan serve
7. Open http://127.0.0.1:8000

What was fixed in this package:
- Added mb_split fallback so Laravel commands can run even on PHP builds missing mb_split.
- Fixed dashboard pending requisition count.
- Added room/location verification flow to QR module.
- Added assigned room display on item detail page.
- Added asset location report and approval tracking report sections.

New CAPEX QR scan API for mobile:
- GET /api/scan/{asset_tag_or_qr_value}
- Returns: asset_tag_id, description, date_acquired, department, asset_type, room_assigned, qr_value

After pulling this zip, run:
- php artisan migrate
