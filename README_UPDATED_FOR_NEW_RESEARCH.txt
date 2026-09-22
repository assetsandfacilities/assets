UPDATED SYSTEM: Asset + Inventory + Facilities Management with Linear Regression Forecasting

What was changed:
1. Retained old CAPEX/OPEX Laravel features:
   - CAPEX asset records
   - OPEX inventory records
   - Requisition and approval workflow
   - QR-based CAPEX tracking fields
   - Users, roles, departments, suppliers, acquisitions, issuances, reports

2. Added new research-paper features:
   - Facilities Management module
   - Facility reservation form
   - Schedule conflict detection
   - FMO approval/rejection workflow
   - Linear Regression-based OPEX consumption forecasting
   - Automated restocking suggestion
   - Inventory usage history table
   - Asset scan logs
   - Room mismatch detection and reporting
   - Housekeeping role for misplaced asset checks
   - FMO role for facility management

Demo accounts after seeding:
- admin@nuclark.local / admin123        = Asset Management Admin
- requestor@nuclark.local / request123  = Requestor
- dean@nuclark.local / dean12345        = Dean Approver
- exec@nuclark.local / exec12345        = Executive Approver
- fmo@nuclark.local / fmo12345          = Facilities Management Office
- housekeeping@nuclark.local / house123 = Housekeeping Scanner

Run commands:
1. Open terminal inside this folder.
2. Run:
   composer install
   copy .env.example .env
   php artisan key:generate
   type nul > database\database.sqlite
   php artisan config:clear
   php artisan cache:clear
   php artisan migrate:fresh --seed
   php artisan serve

If Windows says database/database.sqlite already exists, skip the "type nul" command.

Important fix for "Nothing to migrate" but tables are missing:
- Delete database/database.sqlite
- Create it again using: type nul > database\database.sqlite
- Then run: php artisan migrate:fresh --seed

Main URLs:
- /dashboard
- /items?type=CAPEX
- /items?type=OPEX
- /requisitions
- /facilities
- /forecasting
- /asset-scans
- /reports

Notes:
- Forecasting uses Linear Regression on inventory_usage_logs monthly usage data.
- Facility reservations check conflicts against pending and approved schedules.
- Asset scan monitoring compares assigned room vs. scanned/current room.
- The web system is updated. The Android/mobile app is still a separate project if you want a native Kotlin app later.

===================================================================
PENDING / DEFERRED WORK (noted per developer request, not yet built)
===================================================================
MOBILE APP — ASSET SCANNING WORKFLOW (deferred, web system prioritized first)
- Requirement: housekeeping/asset custodians should ONLY be able to scan assets
  via the mobile app (not the web). Every scan performed on mobile must appear
  in both the mobile app and the web system's Scans module.
- Additional requirement (not yet designed): when a scanned item is moved to
  its correct/designated room or location, the system should reflect that the
  item has been relocated. The exact workflow for this (e.g. scan source room
  -> scan destination room -> system logs a "relocation" event vs. a plain
  "verification" scan) still needs to be defined before development starts.
- Status: intentionally not started. Web system (roles, nav, registration,
  activity proposals, forecasting, floor-based asset tags) was prioritized
  first per instruction. Revisit mobile app + relocation workflow next.

===================================================================
THIS ROUND: merged your deployed/hosting version as the new base
===================================================================
Started from your uploaded "ETO YUNG NAKA DEPLOY SA HOSTING" zip (the one
with entrypoint.sh, Dockerfile, Procfile, nixpacks.toml) instead of my old
working copy, so your deployment-specific setup is preserved. Confirmed from
your own code (EXTRACT()/TO_CHAR() usage, NOT is_approved fix) that your
actual host runs PostgreSQL -- not MySQL as I'd assumed in an earlier round.

1. DATABASE PORTABILITY (real fix, not just config)
   - Removed a duplicate "add_floor_to_items_table" migration (two files were
     doing the same thing).
   - Built app/Support/DateSql.php: one helper that picks the correct SQL for
     whichever database is connected -- SQLite (local testing), MySQL
     (general portability), or PostgreSQL (your actual host). Replaces your
     hand-written Postgres-only EXTRACT()/TO_CHAR() calls in Dashboard,
     Reports, and Forecasting with driver-aware calls that work on all three
     without manual editing when moving between environments.
   - Verified for real: installed an actual PostgreSQL server in a test
     environment, ran migrate:fresh --seed (16 migrations, including tables
     with 6+ foreign keys), then a full page-by-page regression pass against
     it -- Dashboard, Reports, Forecasting, Activity Proposals, Facilities,
     Users, and the mobile API. All confirmed working, not just assumed.

2. ASSET TYPES -- finished something that was half-built
   Found an asset_types migration + model that existed but was never
   actually wired into anything (the item form was still reading from my old
   hardcoded PHP list). Connected it properly: the dropdown is now database-
   backed, and picking "Other, specify" and typing a new type saves it for
   future reuse (same self-growing pattern as the category field). Seeded
   with the original starter list so it's not empty on first run.

3. EMAIL BUG -- found the actual root cause
   Your .env has MAIL_MAILER=log, which writes "sent" emails to
   storage/logs/laravel.log on the server instead of actually sending them.
   That's why it worked on localhost (you could read the log file directly)
   but never reached anyone once deployed. This needs a real SMTP provider --
   see the new block in .env.example for Brevo setup (free tier, no 2FA/
   App Password hassle like Gmail). Any SMTP provider works the same way
   (Gmail, Outlook, Mailgun, etc.) -- these settings only control who is
   SENDING mail, not who can receive it, so recipients on Gmail, Outlook, or
   anywhere else all work regardless of which provider you pick to send from.

4. PREMIUM VISUAL REDESIGN (web + mobile)
   Full design-token rewrite of resources/views/layouts/admin.blade.php (the
   shared stylesheet loaded by every admin page) plus the login and register
   pages -- refined navy/gold palette built on your existing NU Clark brand
   identity (not replaced), proper Inter+Lexend typography (Inter was
   referenced before but never actually loaded, so it was silently falling
   back to system fonts), consistent shadows/radius/spacing system, refined
   status pill colors, personalized avatar initials instead of a generic
   icon. Same CSS class names throughout, so none of the ~30 view files
   needed touching individually. Verified visually with real screenshots
   (login, dashboard, items, activity proposals, facilities, users, settings,
   reports, forecasting, register, plus a mobile-viewport check) -- not
   guessed blind. Mobile app colors.xml updated to match the same palette
   exactly for brand consistency between web and app.

STILL TO DO
- Actually create the Brevo account and plug in real SMTP credentials (I
  can't do that step for you -- needs your own account signup).
- Android Studio build/emulator verification for the mobile app -- I syntax-
  checked the Kotlin and confirmed all XML is well-formed, but cannot
  compile an APK in this environment (no Android SDK here).

===================================================================
THIS ROUND: Floor/Room master data, Super Admin ownership, dropdown
automation, and the Acquisition Cost Summary removal
===================================================================
Started from your latest uploaded zip (with your database.php edits for
TiDB/production hosting).

1. REMOVED: Acquisition Cost Summary + the whole Acquisitions module
   Confirmed it wasn't in your research paper, had no link anywhere in the
   sidebar (unreachable in normal use, so it was always empty/₱0), and had
   duplicate/inconsistent model classes (Acquisition vs ItemAcquisition)
   left over from the original template. Removed both models, both
   controllers (web + API), the views, the routes, and every reference to
   them in Dashboard/Reports. Also found and fixed two broken relationship
   methods this left behind (Item::acquisitions(), Supplier::acquisitions())
   that pointed at the now-deleted class -- these would have thrown a fatal
   error the moment anyone viewed certain pages.

2. FIXED a real bug in your own database.php edit
   'charset' => env('utf8mb4') was looking up a variable literally named
   "utf8mb4" (returns null) instead of the string itself. Fixed to literal
   strings. Also found your SSL CA setting was forced ON unconditionally --
   this breaks ANY plain local MySQL connection (would hit the same
   confusing "MySQL server has gone away" error on XAMPP too), not just in
   my test environment. Fixed it to the same safe, conditional pattern your
   own 'mariadb' block already uses (only applies when MYSQL_ATTR_SSL_CA is
   actually set) -- your TiDB Cloud connection still gets SSL by setting
   that one env var on the host; local/XAMPP connections are no longer
   broken by default.

3. NEW: Floors and Rooms are real, Super-Admin-owned master data
   New floors and rooms tables. Rooms belong to a Floor. Items keep their
   existing floor/room_assigned TEXT columns (so nothing that already reads
   them -- asset tag generation, mismatch detection, reports, the mobile
   API -- breaks), but now also have floor_id/room_id as the real source of
   truth, auto-synced whenever a dropdown selection is made.

4. NEW: Reference Data page (Super Admin only, sidebar link only they see)
   One page to manage Floors, Rooms, Categories, and Asset Types -- add/
   remove, with guards that block deleting anything still in use by real
   items. Department management was also moved from shared Admin access to
   Super-Admin-only, matching what you described.

5. CAPEX/OPEX item form -- dropdowns replace manual typing
   - Category: strict dropdown now (no more typing a new one inline --
     that's Super Admin's job now, per your instructions)
   - Assigned Department: was free text, now a dropdown of real departments
   - Asset Type: strict dropdown, "Other, specify" escape hatch removed
   - Room: new dropdown that cascades from the selected Floor (same
     Category -> Asset Type pattern, extended to Floor -> Room)
   - Floor stays locked after creation (keeps the asset tag consistent),
     Room stays editable afterward (for legitimate relocations via the
     item's own edit page, as distinct from the mobile scan-and-relocate
     flow from a previous round)

6. Floor/Room filtering added to the CAPEX list and Asset Scan Monitoring,
   as requested.

7. Dashboard and Reports charts redesigned to match the rest of the app's
   premium visual system (same navy/gold/refined palette instead of the
   old generic violet/pink/cyan Chart.js defaults) -- added a genuinely
   useful "CAPEX Assets by Floor" chart and an "Unresolved Mismatches" live
   stat in place of the removed dead Acquisition widgets. Dashboard's
   "Recent Acquisitions" panel (also always empty, same root cause) was
   replaced with "Recent Activity Proposals" -- real, live data instead.

VERIFIED FOR REAL, NOT ASSUMED
Installed an actual MySQL server, ran migrate:fresh --seed (18 migrations,
including the new Floor/Room tables), then tested end-to-end over real HTTP:
Super Admin add/delete Floor+Room, deletion guards blocking in-use records,
CAPEX item creation through the full cascading dropdown chain, Floor/Room
filtering on the CAPEX list, Room filtering on Asset Scan Monitoring, the
mobile API still functioning correctly after the Item model changes, and a
full regression pass across every other module (Activity Proposals,
Facilities, Requisitions, Users, Forecasting, Suppliers) -- all clean, no
PHP warnings or SQL errors leaking into any page.

===================================================================
THIS ROUND: two fixes on top of your own Floor/Room/no-GPS scan work
===================================================================
Your own Floor/Room + no-GPS mobile scanning implementation looked solid --
left it as-is, just fixed two separate things you flagged:

1. FIXED: 500 error on Requisitions > New Request (and OPEX pages touching it)
   Root cause: app/Http/Controllers/Web/RequisitionController.php still had
   Item::with('acquisitions') left over from before the Acquisitions module
   was removed a few rounds back -- that relationship no longer exists on
   the Item model, so loading the create-requisition page threw a fatal
   "undefined relationship" error every time. Replaced it with the item's
   own unit_price field (which is what the page actually needed to display
   an estimated cost) -- no functional change to what you see, just fixed
   the crash. Also swept the whole codebase for any other leftover
   Acquisition references; this was the only one.

2. NEW: Bulk CAPEX creation (add 45 keyboards in one submission, not 45 times)
   The CAPEX form now has a "How Many Units?" field (create screen only, not
   on edit). Enter 45, submit once, and the system creates 45 separate item
   records -- each with its own randomly-generated, collision-checked asset
   tag ID, all assigned to the same floor and room you picked, named
   "Keyboard (Unit 1 of 45)" through "(Unit 45 of 45)" so they're easy to
   tell apart in the list. Quantity per record stays 1, consistent with how
   CAPEX assets are individually tracked/QR-tagged in this system --  this
   isn't changing that model, just automating the repetitive part of
   creating many of them at once.

ALSO RE-FIXED: config/database.php had the same unconditional SSL-forcing
bug from a previous round again (breaks any non-TLS local MySQL connection,
e.g. testing on XAMPP). Re-applied the conditional fix.

IMPORTANT -- ACTION NEEDED ON RAILWAY:
Your current Railway environment variables do NOT include MYSQL_ATTR_SSL_CA.
Your database.php was forcing SSL unconditionally before (which is why TiDB
worked), but this fix makes it conditional instead, for portability. If you
deploy this zip WITHOUT adding that variable, your production TiDB
connection could break. Before deploying: add this to Railway's env vars:
  MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

VERIFIED FOR REAL: installed MySQL, ran migrate:fresh --seed (all your
migrations including your own new floor/room scan one), then tested over
real HTTP -- requisition create page loads clean for both requestor and
admin roles, bulk-created 45 keyboards and confirmed via direct DB query
that all 45 have unique asset tags, the same room/floor, and quantity=1
each, confirmed single-item creation (unit_count=1) still works normally
with no naming suffix, and ran a full sweep across Dashboard/Reports/
Activity Proposals/Facilities/Scans/Users/Reference Data -- all clean.
