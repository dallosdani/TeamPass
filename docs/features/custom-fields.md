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

## For users

When you open an item in a folder that has custom fields configured:

- The **Details** tab in the item view shows the category sections with their fields and values.
- In edit mode, the same fields appear as editable inputs.
- **Mandatory** fields show a visual indicator and prevent saving until filled.
- **Masked** fields show a placeholder until you click the reveal icon.

No additional configuration is needed on your side — custom fields appear automatically based on the folder the item is in.
