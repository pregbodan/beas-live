# BEAS — Biometric Examination Authentication System
### Federal University Oye-Ekiti | Department of Computer Engineering

> A full-stack PHP/MySQL web application for biometric-based examination identity
> verification, implementing MINDTCT + BOZORTH3 fingerprint matching and
> SSD MobileNet face recognition (face-api.js).

---

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.0+ |
| Database | MySQL 5.7+ / MariaDB 10.4+ |
| Frontend | Vanilla JS + HTML5 Canvas |
| Fingerprint | NIST NBIS (MINDTCT + BOZORTH3) via Python wrapper |
| Face Recognition | face-api.js (TensorFlow.js / SSD MobileNet V1) |
| Hardware | DigitalPersona 4500 optical scanner (USB/OTG) |
| Web Server | Apache 2.4+ with mod_rewrite / Nginx |

---

## Quick Start (XAMPP / Laragon)

1. **Copy project** into your web root:
   ```
   C:\xampp\htdocs\beas\    (XAMPP Windows)
   C:\laragon\www\beas\     (Laragon)
   /var/www/html/beas/      (Linux Apache)
   ```

2. **Visit the installer**:
   ```
   http://localhost/beas/setup.php
   ```
   Fill in your MySQL credentials and an admin password. Click **Install BEAS**.
   The installer will:
   - Create the `beas_db` database
   - Run the full schema (5 tables + indexes)
   - Insert sample courses (CPE 501–511)
   - Insert 5 sample students with mock biometric templates
   - Create your superadmin account

3. **Delete setup.php** after installation:
   ```bash
   rm /var/www/html/beas/setup.php
   ```

4. **Log in** at `http://localhost/beas/` with your admin credentials.

---

## Manual Database Setup (alternative)

```bash
mysql -u root -p < config/schema.sql
```

Default admin: `admin` / `password` (bcrypt hash in schema.sql — change immediately)

---

## NBIS Integration (Production)

### Ubuntu/Debian
```bash
sudo apt update && sudo apt install nbis
# Verify:
mindtct --help
bozorth3 --help
```

### From Source
```bash
wget https://www.nist.gov/sites/default/files/documents/2016/12/06/nbis_v5.0.0.zip
unzip nbis_v5.0.0.zip && cd Rel_5.0.0
make it
sudo make install
```

### Python wrapper usage
```bash
# Enroll a fingerprint (WSQ → XYT minutiae template)
python3 python/fingerprint_match.py \
    --mode enroll \
    --wsq /path/to/scan.wsq \
    --out /path/to/template.xyt

# 1:1 verification
python3 python/fingerprint_match.py \
    --mode match \
    --probe probe.xyt \
    --gallery stored.xyt

# 1:N identification
python3 python/fingerprint_match.py \
    --mode identify \
    --probe probe.xyt \
    --gallery-dir /var/beas/biometric/student_templates/
```

Set `NBIS_BIN` env var if NBIS is not in `/usr/bin`:
```bash
export NBIS_BIN=/usr/local/nbis/bin
```

---

## DigitalPersona 4500 Integration

The application uses the DigitalPersona DPFJ SDK for real-time capture.
You can configure the local bridge endpoint with these environment variables or
their defaults in `config/config.php`:
- `FINGERPRINT_READER_HOST` defaults to `127.0.0.1`
- `FINGERPRINT_READER_PORT` defaults to `52181`
- `FINGERPRINT_READER_PROTOCOL` defaults to `ws`
- `FINGERPRINT_READER_CLIENT_PATH` defaults to `dpfpcapture`

In production, replace the JavaScript simulation in:
- `modules/students/enroll.php` — enrollment capture
- `modules/attendance/verify.php` — live verification scan

With calls to:
- **Windows**: DPFJ ActiveX / COM object or WebUSB bridge
- **Android (OTG)**: DigitalPersona Mobile SDK
- **Linux**: libfprint or DP Linux SDK

```javascript
// Production example (DigitalPersona Web SDK):
const reader = new DPReader();
await reader.open();
const template = await reader.capture();  // returns WSQ bytes
// POST template to /api/verify.php or /api/enroll_biometric.php
```

---

## File Structure

```
beas/
├── config/
│   ├── config.php          # DB credentials & constants
│   └── schema.sql          # Full database schema
├── includes/
│   ├── auth.php            # Session, login, helpers
│   ├── header.php          # Sidebar + topbar template
│   └── footer.php          # JS includes template
├── modules/
│   ├── students/
│   │   ├── index.php       # Student listing + search
│   │   ├── enroll.php      # Biometric enrollment form
│   │   └── view.php        # Student profile
│   ├── courses/
│   │   ├── index.php       # Course catalogue
│   │   └── register.php    # Register student for course
│   ├── attendance/
│   │   ├── verify.php      # ★ MAIN: Real-time exam verification
│   │   └── index.php       # Attendance log + filter
│   ├── reports/
│   │   └── index.php       # Analytics + charts
│   └── admin/
│       └── users.php       # Admin user management
├── api/
│   ├── verify.php          # POST: fingerprint match API
│   ├── mark_attendance.php # POST: record attendance
│   └── export.php          # GET:  CSV export
├── python/
│   └── fingerprint_match.py # NBIS wrapper (MINDTCT+BOZORTH3)
├── assets/
│   ├── css/main.css        # Full design system
│   └── js/main.js          # UI interactions
├── uploads/
│   ├── profiles/           # Student photos
│   └── biometric/          # XYT template files
├── index.php               # Login page
├── dashboard.php           # Overview + stats
├── logout.php
└── setup.php               # One-click installer (delete after use)
```

---

## Security Notes

- All passwords hashed with `password_hash()` (bcrypt, cost 10)
- Session-based authentication with role enforcement
- Biometric templates stored as base64-encoded strings (upgrade to QSM for production)
- All user input sanitized via `htmlspecialchars()` + `strip_tags()`
- PDO prepared statements throughout (SQL injection prevention)
- HTTPS recommended for all production deployments (biometric data in transit)
- For blockchain audit trail: integrate Hyperledger Fabric per Chapter 2 recommendations

---

## Roles

| Role | Permissions |
|------|------------|
| `superadmin` | All features + admin user management |
| `admin` | Students, courses, reports, attendance |
| `invigilator` | Verify page only |

---

## Theoretical Grounding (Chapter 2 Alignment)

| Feature | Theory |
|---------|--------|
| Offline-first verification | Edge Computing (Shi et al., 2016) |
| Fingerprint matching | BOZORTH3 (NIST NBIS) |
| Face recognition | SSD MobileNet V1 + 128-dim Euclidean distance |
| Queue reduction | M/M/1 Queuing Model (Kendall, 1953) |
| UI design | TAM — minimal steps, clear feedback (Davis, 1989) |
| Multi-modal | Fingerprint (primary) + Face (secondary) |
| Compliance note | NDPR-aligned template storage (upgrade to QSM) |

---

*BEAS v1.0.0 — B.Eng Final Year Project, FUOYE Computer Engineering*
