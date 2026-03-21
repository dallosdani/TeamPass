<!-- docs/features/renewal.md -->

## Overview

The **Renewal** page helps you identify items whose passwords are approaching or have passed their expiration date, so you can plan and execute password rotations in a timely manner.

> 🔔 Password expiration must be enabled by your administrator (**Settings → Security → Activate item expiration feature**). The expiration period per folder is set in the folder configuration.

---

## How expiration works

Each folder can have a **password renewal period** (in days) defined by its administrator. When an item's password has not been changed for longer than that period, it is considered expired.

Expired items are visually flagged in the main item list (coloured indicator next to the item label).

---

## Using the Renewal page

1. Navigate to **Renewal** in the utilities menu.
2. Use the **date picker** to select a target date.
3. The table updates to show all items that will have expired **by that date**.

The results table includes:

| Column | Content |
|--------|---------|
| **Label** | Item name |
| **Expiration date** | The date on which the item's password expires |
| **Folder** | Folder containing the item |

You can select items using the checkboxes and then act on them (navigate to the item to update the password).

> 💡 Use the date picker to look ahead: setting the date to a month from now lets you plan renewals in advance rather than reacting to expired items.

---

## Renewing a password

The Renewal page itself does not allow editing items directly. To renew a password:

1. Note the item and folder from the results table.
2. Navigate to that folder in the main item list.
3. Edit the item and change the password.
4. Save.

The expiration timer resets from the date of the password change.

---

## Folder-level renewal reminders

In addition to the Renewal page, administrators can configure **renewal reminders** at the folder level. When enabled, users with access to the folder receive an email notification a configurable number of days before items in that folder expire.

Renewal reminder settings are configured per folder in the **Folders** administration page. See [Folders](folders.md) for details.
