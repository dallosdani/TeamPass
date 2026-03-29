<!-- docs/features/custom-fields.md -->

## Overview

**Custom fields** let administrators add extra data fields to items, beyond the standard label / login / password / URL set. They are organized into **categories**, each of which is linked to one or more folders.

When a user creates or edits an item in a folder that has custom fields, those fields appear in the **Details** tab of the item form.

---

## For administrators

### Enabling custom fields

Before defining fields, enable the feature in **Settings → Items**:

- **Enable extra fields on items** — makes the Fields page active and displays configured fields on items.
- **Enable item creation templates** — allows pre-filling new items with predefined values based on a template.

---

### Categories

A **category** groups related custom fields together and is linked to the folders where it should appear.

#### Creating a category

1. Go to **Fields** in the administration menu.
2. Click **Add Category**.
3. Fill in:
   - **Label** — displayed as the section heading in the item form.
   - **Folders** — the folders where this category (and its fields) will be visible. Select one or more from the tree.
   - **Position** — display order relative to other categories.
4. Click **Save**.

#### Editing or deleting a category

Use the action icons in the categories table. Deleting a category also removes all fields within it and the data stored in those fields on existing items.

---

### Fields

Each field belongs to a category and defines a single input in the item form.

#### Creating a field

1. Click **Add Field**.
2. Fill in:

| Option | Description |
|--------|-------------|
| **Label** | The field's display name in the item form |
| **Type** | `Text` (single line) or `Textarea` (multi-line) |
| **Regex validation** | Optional — a regular expression the entered value must match (e.g. `^\d{4}$` for a 4-digit PIN) |
| **Mandatory** | If checked, the field must be filled in before the item can be saved |
| **Masked text** | If checked, the field value is hidden (like a password) and requires a click to reveal |
| **Encrypted data** | If checked, the field value is stored encrypted (recommended for sensitive data) |
| **Restricted to roles** | Limits field visibility to specific roles. Users not in the listed roles will not see the field |
| **Category** | The category this field belongs to |
| **Position** | Display order within the category |

3. Click **Confirm**.

> 🔔 The **Encrypted data** option uses the same encryption scheme as item passwords. Only users with access to the item can decrypt the value. Enabling this option after data has already been entered does not retroactively encrypt existing values.

---

## Encryption — behaviors and edge cases

This section describes what TeamPass does with encrypted custom field values in specific situations. It is intended for administrators.

### How encrypted field values are stored

When a field has **Encrypted data** enabled, its value is:
1. AES-encrypted with a random object key at save time.
2. The object key is itself RSA-encrypted for each user who has access to the item's folder, and stored in a dedicated sharekeys table.

A user can only read the field value if their personal sharekey exists in that table. If the sharekey is missing, the field shows an error message (see [Troubleshooting](../misc/troubleshooting.md)).

---

### Changing the "Encrypted data" flag on an existing field

Toggling **Encrypted data** on a field that already has values stored **does not retroactively transform existing data**:

| Before change | After change | Effect on stored values |
|---|---|---|
| Unencrypted | Encrypted | Existing values remain in plaintext. Only new values saved after the change are encrypted. |
| Encrypted | Unencrypted | Existing values remain encrypted in the database. They will no longer be decryptable by users. |

**Recommendation:** avoid changing the encryption flag on a field that already holds data. If you must do so, delete all existing values first (by editing each item), then change the flag.

---

### Copying an item

When a user copies an item, each encrypted field value is:
1. Decrypted using the user's current sharekey.
2. Re-encrypted with a fresh object key for the copy.
3. A new set of sharekeys is created for all users with access to the destination folder.

If the user performing the copy does not have a sharekey for a given field (an edge case caused by a past inconsistency), the copied item will have an **empty value** for that field. The original item is not affected.

---

### Moving an item between folders

#### Public folder → public folder (different access scopes)

Sharekeys are redistributed: users who gain access receive new sharekeys, users who lose access have their sharekeys removed. Encrypted field values follow the same logic as the item password.

#### Public folder → personal folder

All field sharekeys held by other users are deleted. Only the moving user retains access to the encrypted field values.

#### Personal folder → public folder

This is the most sensitive case. TeamPass must create sharekeys for all users who will have access to the destination folder. For each encrypted field:

- If the moving user **has a sharekey** for the field → sharekeys are created for all target users normally.
- If the moving user **has no sharekey** for the field (orphaned encrypted value) → the field value is **permanently unrecoverable**. TeamPass deletes the orphaned row and logs an error. The item is moved successfully; only that specific field value is lost.

> ⚠️ An orphaned encrypted field value means the object key is gone: the ciphertext exists in the database but cannot be decrypted by anyone. Deleting it is the only safe option. This situation can only arise from a previous bug or data inconsistency.

---

### Deleting a category or a field

Deleting a **category** removes all fields within it and all stored values for those fields on every item, including encrypted ones. This operation is irreversible.

Deleting an individual **field** similarly removes all stored values for that field across all items.

There is no "soft delete" or archive — plan accordingly before removing a category or field that holds data.

---

## For users

When you open an item in a folder that has custom fields configured:

- The **Details** tab in the item view shows the category sections with their fields and values.
- In edit mode, the same fields appear as editable inputs.
- **Mandatory** fields show a visual indicator and prevent saving until filled.
- **Masked** fields show a placeholder until you click the reveal icon.

No additional configuration is needed on your side — custom fields appear automatically based on the folder the item is in.
