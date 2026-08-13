# Course Send SMS (`local_coursessms`)

**Author:** Kewayne Davidson  
**License:** GNU GPL v3 or later  
**Requires:** Moodle 4.1+  
**Version:** 1.1.0 (`2026081300`)  

---

## 1. Overview

The **Course Send SMS** plugin (`local_coursessms`) enables teachers, course managers, and authorized roles to send bulk SMS messages to course participants directly from within a Moodle course.

---

## 2. Key Features in v1.1.0

* **Instant Form Submission & Background Queueing:**
  * Submitting an SMS batch redirects **instantly in under 1 second**. All messages are handed over to Moodle's native SMS background worker (`async: true`).
* **SMS Gateway Selection Controls:**
  * **Admin Default Gateway:** Set the default SMS Gateway instance in **Site administration** > **Plugins** > **Local plugins** > **Course Send SMS**.
  * **Sender Gateway Dropdown:** Toggle in Admin settings to allow teachers to select which gateway instance to send from directly on the Send SMS form.
* **Static Gateway History Preservation:**
  * Stores the exact text name of the gateway used at sending time (e.g. `Critico`, `OpenWA`, or `System Default Gateway`).
  * Preserves full history records even if an admin deletes or renames an SMS gateway instance in the future.
* **Real-Time Delivery Status Tracking:**
  * Displays delivery status breakdowns: 🔵 **Queued**, 🟢 **Sent**, 🔴 **Failed**.
  * Includes a manual **Refresh Status** button to pull live delivery updates from Moodle's SMS subsystem.
* **SMS Log Management & Deletion:**
  * View detailed recipient lists, message content, timestamps, and sender signatures.
  * Delete individual log records or clear all course SMS logs (protected by `local/coursessms:deletelog` capability and `sesskey`).
* **Accurate Recipient Validation:**
  * Extracts user `phone1` / `phone2` profiles and records users without phone numbers as failed sends with explicit reasons.
* **100% Moodle Standards Compliant:**
  * Full string localization via `lang/en/local_coursessms.php`.
  * Zero hardcoded domain URLs.

---

## 3. Installation & Setup

1. Place plugin files in `your_moodle_site/local/coursessms/`.
2. Visit **Site administration** > **Notifications** to complete database installation.
3. Configure admin settings at **Site administration** > **Plugins** > **Local plugins** > **Course Send SMS**.
