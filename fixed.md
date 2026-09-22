# NU Clark CAPEX/OPEX System

## Run
1. Install dependencies: `composer install`
2. Use the included SQLite setup in `.env`
3. Run migrations and seed demo data: `php artisan migrate:fresh --seed`
4. Start the app: `php artisan serve`

## Demo Login
- Email: `admin@nuclark.local`
- Password: `admin123`

## Notes
- QR viewing is available for CAPEX assets through the Asset Catalog and QR Scanner pages.
- The UI is redesigned to match the requested modern dark-sidebar admin style.
