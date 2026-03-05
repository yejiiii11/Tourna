# User Registration System with Role-Based Dashboards

This project now supports three roles:

- **admin** – full access, created manually by site owner.
- **organizer** – can create tournaments for athletes.
- **athlete** – can browse and join tournaments.

## Setup Instructions

1. **Database**
   - Import `db_setup.sql` using phpMyAdmin or the MySQL CLI to create the necessary tables and add the `role` column.
   - If you already have a `users` table, run the `ALTER TABLE` statement in the SQL file to add the `role` column.
   - To register an **administrator** through the web interface, choose "Administrator" from the role dropdown and enter the secret code defined in `config.php` (the default value is `letmein_admin`; change this to something secure).
   - Alternatively you may still add an admin directly via SQL and use `password_hash()` to set a password.

2. **Configuration**
   - Edit `config.php` if your MySQL credentials are different; it currently points to `localhost`, user `root` with no password.

3. **Pages and Permissions**
   - Public: `index.php`, `register.php`, `login.php`.
   - Authenticated: `dashboard.php` (content varies by role).
   - Organizer/admin only: `create_tournament.php`, `save_tournament.php`.
  These pages now collect additional details (location, registration fee, slot capacity and any special requirements) and store them in the database.
   - Athlete/admin only: `browse_tournaments.php`.
   - Admin only: `admin_panel.php`.

4. **Icons and Design**
   - The dashboard uses FontAwesome icons instead of emojis and follows the provided design sample. The landing page and forms now mimic the exact orange look from your screenshot; sports appear in orange‑tinted boxes and the navbar includes a search/filter bar and a notification bell.

- **Booking flow**
   - Athletes (and admins) can "Apply" to a tournament from the browse page. The `apply.php` screen shows event details, including location (clickable link), fee, slots and dynamically rendered requirements that were entered by the organizer, along with an embedded Google Map based on the venue/address provided.
   - The browse page includes an interactive OpenStreetMap (Leaflet) map at the top. Each tournament with a location is geocoded to a pin with an info window linking to the apply page. Users can search places using the box above the map; pressing Enter pans/zooms the viewport.

**Maps now use OpenStreetMap/Leaflet** and require no API key. The creation page map is clickable – clicking anywhere places a draggable marker and writes coordinates (or a searched address) into the "Location / Venue" field. A simple search box powered by Nominatim allows you to look up addresses. The Google Maps code has been removed to avoid key hassles.

5. **Usage Notes**
   - The dashboard grid shows sport icons; it is available to all logged‑in users.
   - Role selection during registration only offers `athlete` and `organizer`. Use manual SQL edits to promote to `admin`.

---

Feel free to expand the tournament system or add additional features later.