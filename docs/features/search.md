<!-- docs/features/search.md -->

## Overview

The **Find** page lets you search across all items you have access to, regardless of which folder they belong to. Results are displayed in a table and support mass operations.

---

## Running a search

1. Click **Find** in the left navigation menu.
2. Type your search terms in the search box at the top of the results table.
3. Results update as you type.

The search covers the following item fields:

| Field searched | Notes |
|----------------|-------|
| **Label** | Item name |
| **Login** | Username / account identifier |
| **Description** | Free-text notes attached to the item |
| **Tags** | Comma-separated keywords |
| **URL** | Web address |

> 🔔 Passwords are **never** returned in search results for security reasons. Searching only matches metadata, not the encrypted credential.

---

## Results table

Each row in the results shows:

| Column | Content |
|--------|---------|
| **Label** | Item name — click to open the item |
| **Login** | Account identifier |
| **Description** | Item description (truncated) |
| **Tags** | Tags attached to the item |
| **URL** | Link to the associated service |
| **Group** | Folder the item belongs to |

---

## Scope of search results

Search results are filtered by your permissions:

- Only items in folders you have at least read access to are returned.
- Items in folders you do not have access to are never shown.
- Items restricted to specific users (see [Items — Restricted items](items.md#restricted-items)) are only shown if you are in the allowed list.

If the administrator has enabled the **Restricted search by default** option, the initial search may be limited to a subset of folders. You can toggle this restriction from the search interface.

---

## Mass operations

You can select multiple items from the results and apply an action to all of them at once:

1. Check the boxes next to the items you want to act on (or use the header checkbox to select all).
2. A **mass operations** menu appears.
3. Choose the action to apply (available actions depend on your permissions).

> 🔔 Mass operations follow the same permission rules as individual item actions. You can only apply an operation to items on which you have the required permission level.
