# SOMS Mobile — Setup Guide

Flutter mobile client for SOMS SGO Edition (Laravel API). This repo is a
**`lib/` drop-in**, not a full `flutter create` output, so the `android/`,
`ios/`, and platform glue files must be generated locally once.

---

## 1. First-time setup

```bash
# 1. Generate platform scaffolding into a fresh folder
flutter create --org com.pac.soms --project-name soms_mobile soms_mobile_app
cd soms_mobile_app

# 2. Delete the generated lib/ and main.dart, then copy this repo's
#    lib/ and pubspec.yaml over them

# 3. Install dependencies
flutter pub get
```

---

## 2. Choose your API target

The app reads the API URL from `--dart-define=API_BASE_URL=...`.

| Target | `API_BASE_URL` |
|---|---|
| Android emulator (aliases host `localhost`) | `http://10.0.2.2:8000/api/v1` |
| Physical device on your **laptop's hotspot** | `http://192.168.137.1:8000/api/v1` |
| Physical device on the same home/office Wi-Fi | `http://<laptop-LAN-IP>:8000/api/v1` |
| Deployed (Render) | `https://soms-zlyx.onrender.com/api/v1` |

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

---

## 3. Run the local backend

1. Start **MySQL** from the XAMPP control panel (Apache is not needed).
2. From the Laravel project folder:

```powershell
# --host=0.0.0.0 is required so devices outside this machine can connect
php artisan serve --host=0.0.0.0 --port=8000
```

> `0.0.0.0` is a bind address, not a URL. Never browse to it
> (`ERR_ADDRESS_INVALID`). Use `http://127.0.0.1:8000` on the laptop, or the
> laptop's IP from the phone.

3. Check `CORS_ALLOWED_ORIGINS` in `.env` if you see connection errors.
   Mobile uses Bearer tokens, not cookies, so CORS matters less than for the
   web app.

---

## 4. Run on a physical device via laptop hotspot (Windows)

The laptop hosts the hotspot, the phone joins it, and the phone reaches
Laravel at the laptop's hotspot IP.

### 4.1 Find the hotspot IP

```powershell
ipconfig
# Use the "Local Area Connection* N" adapter, normally 192.168.137.1
# (NOT the "Wi-Fi" adapter, which is your upstream network)
```

### 4.2 Open port 8000 in Windows Firewall (admin PowerShell)

```powershell
# Allow inbound TCP 8000 so the phone can reach artisan serve
New-NetFirewallRule -DisplayName "Laravel 8000" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow -Profile Any

# Remove it when you're done testing
Remove-NetFirewallRule -DisplayName "Laravel 8000"
```

The hotspot network is often classed as Public, hence `-Profile Any`.
Do not leave this rule open on untrusted networks.

### 4.3 Verify from the phone's browser

With the phone on the laptop's hotspot, open `http://192.168.137.1:8000`.
If it loads, the network path works. If it times out, check the firewall
rule, the `--host=0.0.0.0` flag, and that the phone is on the right network.

### 4.4 Allow cleartext HTTP (dev only)

Android 9+ blocks `http://` by default. Without this, Dio fails with a
"cleartext not permitted" error.

```xml
<!-- android/app/src/main/AndroidManifest.xml -->
<!-- Add the attribute to the existing <application> tag -->
<application
    android:usesCleartextTraffic="true"
    ...>
```

Remove it for release builds. The Render URL is HTTPS and does not need it.

### 4.5 Connect the phone with Wireless debugging

On the phone: **Developer options → Wireless debugging**.

```powershell
# Point $adb at adb.exe (this variable is per PowerShell session; re-run it in new terminals)
$adb = "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe"
Test-Path $adb    # must print True

# Pair once: "Pair device with pairing code" gives IP:PORT and a 6-digit code
& $adb pair 192.168.137.173:<pairing-port>

# Connect: use the IP:PORT shown on the MAIN Wireless debugging screen
& $adb connect 192.168.137.173:<connect-port>

# Confirm the device shows as "device"
& $adb devices
```

Notes:
- The pairing port and connect port are different.
- Both ports change when Wireless debugging is toggled off and on; re-read
  them from the phone, and re-pair if connect is refused.
- If `& $adb` throws `BadExpression`, `$adb` is empty in this session. Define
  it first, as above.

### 4.6 Run the app

```powershell
flutter devices    # the phone should be listed

flutter run -d 192.168.137.173:<connect-port> --dart-define=API_BASE_URL=http://192.168.137.1:8000/api/v1
```

The hotspot IP can change after a restart. Re-check with `ipconfig` and
update `API_BASE_URL` if it does.

---

## 5. Troubleshooting

### 5.1 Diagnostic flow: "the app can't reach the server"

Run these in order and stop at the first failure. Each step isolates one
layer: server → binding → firewall → app config.

**Step 1: Is artisan listening on all interfaces?**
```powershell
netstat -ano | findstr :8000
```
- `0.0.0.0:8000 LISTENING`: good, go to step 2.
- `127.0.0.1:8000`: restart with `php artisan serve --host=0.0.0.0 --port=8000`.
- No output: artisan isn't running.

**Step 2: Does the server answer on the hotspot IP, from the laptop itself?**
```powershell
curl.exe http://192.168.137.1:8000
# Expected: an HTML redirect to /login
```
- Fails: the binding or server is wrong. Go back to step 1.
- Works: the server is fine. The problem is between phone and laptop.

**Step 3: Does the phone's browser load `http://192.168.137.1:8000`?**
- Loads: the network path is fine. Skip to step 6.
- Times out: go to step 4.
- Also confirm the phone is on the laptop's hotspot (check its Wi-Fi name),
  not mobile data or another Wi-Fi.

**Step 4: Look for Block rules on PHP (most common cause)**

Windows creates Block rules when you click **Cancel** on the first-run
firewall prompt. A Block rule overrides every Allow rule, including your
port 8000 rule.
```powershell
# List inbound Block rules that point at php.exe
Get-NetFirewallRule -Direction Inbound -Action Block -Enabled True |
  Get-NetFirewallApplicationFilter |
  Where-Object { $_.Program -like "*php*" }

# If rows appear, remove those rules (admin PowerShell)
Get-NetFirewallRule -Direction Inbound -Action Block -Enabled True |
  Where-Object { ($_ | Get-NetFirewallApplicationFilter).Program -like "*php*" } |
  Remove-NetFirewallRule
```
Then retry step 3. When Windows prompts again, click **Allow access** and
tick Private and Public.

**Step 5: Confirm the firewall is the cause (temporary test)**
```powershell
# Admin PowerShell. Do not leave the firewall off.
Set-NetFirewallProfile -Profile Public -Enabled False
# Retry the phone browser, then immediately:
Set-NetFirewallProfile -Profile Public -Enabled True
```
Loads with the firewall off: a firewall rule is the cause, so recheck steps
4 and 4.2. Still times out: check third-party antivirus (Avast, Kaspersky,
etc.), which runs its own firewall.

**Step 6: Browser works but the app fails. Check the app config**
1. `android:usesCleartextTraffic="true"` is in the `<application>` tag of
   `AndroidManifest.xml`. Fully stop and rebuild after changing it.
2. `API_BASE_URL` uses `http://` (not `https://`), the current hotspot IP,
   and ends in `/api/v1`.
3. Fully restart the app. `--dart-define` values are compile-time, so a hot
   reload or hot restart will not pick up changes.
4. Read the error type:
   - Timeout / connection refused: still a network problem, go back to step 3.
   - `CLEARTEXT communication not permitted`: manifest attribute missing.
   - 404: wrong path. Verify the route with `php artisan route:list`.
   - 500: server-side error. Read `storage/logs/laravel.log`.
   - 401 on login: wrong credentials, or the token isn't being stored.

### 5.2 Quick symptom table

| Symptom | Likely cause | Fix |
|---|---|---|
| `ERR_ADDRESS_INVALID` in browser | Opened `0.0.0.0` | Use `127.0.0.1` (laptop) or `192.168.137.1` (phone) |
| Phone browser times out, laptop `curl` works | Firewall Block rule on `php.exe` | Step 4 |
| Phone browser times out, laptop `curl` fails | artisan bound to `127.0.0.1` or not running | Restart with `--host=0.0.0.0` |
| Phone can't find the server at all | Phone not on the laptop's hotspot | Join the hotspot, recheck with `ipconfig` |
| Worked yesterday, fails today | Hotspot IP changed | Re-run `ipconfig`, update `API_BASE_URL`, restart the app |
| App error: `CLEARTEXT communication not permitted` | Missing manifest attribute | Add `usesCleartextTraffic`, rebuild (see 4.4) |
| App still uses the old URL | `--dart-define` is compile-time | Stop the app and run `flutter run` again |
| Login returns 500 | XAMPP MySQL stopped or `.env` DB values wrong | Start MySQL, check `.env`, read `storage/logs/laravel.log` |
| Login returns 404 | Wrong base path | Confirm `API_BASE_URL` ends in `/api/v1` |
| `adb connect` refused | Connect port changed | Re-read the port from the phone, re-pair if needed |
| `adb pair` fails or code rejected | Pairing code expired | Reopen "Pair device with pairing code" for fresh values |
| `& $adb` throws BadExpression | `$adb` empty in this session | Define `$adb` (see 4.5) |
| `flutter devices` doesn't list the phone | Wireless debugging off or not connected | Toggle it on, run `adb connect`, then `adb devices` |

---

## 6. What's wired up

- **Auth**: `POST /auth/login` → Sanctum token in Flutter Secure Storage →
  role-based routing. `must_change_password` gates every dashboard route,
  same as the web app.
- **401 handling**: any API call returning 401 clears the local session and
  bounces back to login, app-wide, not just on login itself.
- **Role shells**: bottom-nav scaffolding for Student / Officer / Admin with
  the nav items from `10-Mobile-Deployment.md`. Every tab is currently a
  `PlaceholderTab`. Swap the `builder` in each `*_home_screen.dart` for a
  real screen as it's built; the shell itself doesn't change.

## 7. Not wired up yet (next slices)

- **QR display** (`qr_flutter`): render from `GET /api/v1/student/qr/current`.
  The payload is `"<user_id>:<token>"`, matching the scan-station fix on the
  Laravel side.
- **Officer camera scan** (`mobile_scanner`) and offline scan queue
  (Flutter Secure Storage, `scan-batch` sync).
- **Events, Fines, Announcements, Calendar** screens (all three roles).
- **Firebase push**: `firebase_messaging` is in `pubspec.yaml`, but
  `Firebase.initializeApp()` is **not** called in `main.dart` yet. Add
  `google-services.json` / `GoogleService-Info.plist` from your Firebase
  console first, then wire init in `main.dart`.
- **Admin screens** (Users, Officer Appointment, Reports, Logs) are
  placeholders.

---

## 8. Project layout

```
lib/
  core/            Dio client, secure storage, API base URL config
  models/          AppUser
  providers/       Riverpod AuthController (login/logout/change-password)
  screens/
    auth/          Login, forced change-password
    student/       officer/       admin/    (nav shells)
  widgets/         RoleShell (shared bottom-nav scaffold)
```