# Course Send SMS (`local_coursessms`)

**Author:** Kewayne Davidson  
**License:** GNU GPL v3 or later  
**Requires:** Moodle 4.1+  
**Version:** 1.1.0  

---

## Overview

The **Course Send SMS** plugin (`local_coursessms`) allows teachers, course managers, and authorized roles to send bulk SMS messages to course participants directly from within a course.

Features include role and group targeting, dynamic placeholders (`{sender}`, `{coursename}`, `{firstname}`, `{lastname}`), SMS Gateway selection controls, log history tracking, log deletion, and strict compliance with Moodle coding standards and string localization.

---

## Key Features

* **Targeted Recipients:** Send SMS to all enrolled participants, or filter by specific Course Roles or Groups.
* **SMS Gateway Selection:**
  * Admin configurable Default Gateway (`default_gateway`).
  * Toggle in Admin settings (`allow_select_gateway`) to enable/disable sender gateway selection on the form.
  * Direct gateway routing via Moodle's native `core_sms` subsystem.
* **SMS Log Management:**
  * Detailed history of sent messages, timestamp, sender, and recipient counts.
  * Delete individual log entries or clear all course SMS logs (protected by `local/coursessms:deletelog` capability and `sesskey`).
* **Accurate Recipient Validation:**
  * Extracts user `phone1` / `phone2` profiles and correctly records users with missing phone numbers as failed sends with explicit reasons.
* **100% Moodle Standards Compliant:**
  * Full string localization via `lang/en/local_coursessms.php`.
  * Zero hardcoded URLs or external domain links.

---

## Installation & Configuration

1. Place files in `your_moodle_site/local/coursessms`.
2. Visit **Site administration** > **Notifications** to upgrade the database.
3. Configure admin settings at **Site administration** > **Plugins** > **Local plugins** > **Course Send SMS**.
