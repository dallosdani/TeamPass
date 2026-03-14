<!-- docs/features/rights.md -->

## Overview

> 🔔 **Administrator ≠ access to items.** The Administrator privilege in Teampass is a purely administrative role: it grants access to configuration pages (users, roles, folders, settings, logs) but **not** to folder contents or items. Item access is always and exclusively controlled through roles, for every account without exception.

In Teampass, access to items is controlled at the **folder** level through a layered system:

```
User account
  └── Roles  (one or more)
        └── Folder permissions  (one permission type per folder per role)
              └── Effective access  (resolved when the user has multiple roles)
```

A user never has a permission directly on a folder. Permissions are always defined on a **Role**, and roles are assigned to users.

---

## Permission types

Each role defines one permission type per folder it covers:

| Type | Label | Create item | Edit item | Delete item |
|------|-------|:-----------:|:---------:|:-----------:|
| `W`    | Write              | ✅ | ✅ | ✅ |
| `ND`   | Write, no delete   | ✅ | ✅ | ❌ |
| `NE`   | Write, no edit     | ✅ | ❌ | ✅ |
| `NDNE` | Write, no edit, no delete | ✅ | ❌ | ❌ |
| `R`    | Read only          | ❌ | ❌ | ❌ |

> 💡 "Create item" means the user can add new items inside the folder. "Edit" and "Delete" refer to existing items.

---

## How roles and permissions combine

### Single role

When a user has exactly one role, their access to a folder is simply the permission type defined on that role for that folder. If the role has no entry for a given folder, the user has no access to it.

### Multiple roles — the "most permissive wins" rule

A user can have several roles simultaneously (manually assigned by an administrator, or inherited from LDAP/AD groups — see [Authentication](authentication.md)).

When two or more roles define a permission on the **same folder**, Teampass applies a deterministic resolution rule:

> **The most permissive permission type always wins.**

The priority order is: `W` > `ND` > `NE` > `NDNE` = `R`

**Special case — `ND` + `NE`:** if one role grants `ND` (no delete) and another grants `NE` (no edit) on the same folder, the result is `NDNE` (no edit, no delete). Both restrictions are combined because neither role alone grants full write access.

#### Examples

| Role A on folder X | Role B on folder X | Effective access |
|--------------------|-------------------|-----------------|
| `W`                | `R`               | `W` — write wins |
| `ND`               | `R`               | `ND` — ND wins |
| `ND`               | `NE`              | `NDNE` — both restrictions apply |
| `R`                | `R`               | `R` — read only |
| `W`                | `ND`              | `W` — write wins |

#### Practical implication

> 🔔 **A read-only role cannot restrict a user who already has a write role on the same folder.** If a user belongs to a broad group that grants `W` and a more specific group that grants `R`, the effective access will be `W`.

To enforce read-only access, you must ensure that **none** of the user's roles grants a more permissive type (`W`, `ND`, or `NE`) on that folder.

---

## Permission sources

A user's roles can come from two sources, which are merged together:

| Source | How it is assigned | Stored as |
|--------|-------------------|-----------|
| **Manual** | Administrator assigns a role directly to the user in the Users page | `source = 'manual'` in `teampass_users_roles` |
| **LDAP / AD groups** | At login, the user's AD group memberships are looked up and mapped to Teampass roles via the LDAP group mapping table | `source = 'ad'` in `teampass_users_roles` |

Both sources are combined before resolving effective permissions. The same "most permissive wins" rule applies regardless of source.

> 💡 LDAP group mapping is configured in **Settings → LDAP** and **Roles → LDAP synchronization**. See [Roles](roles.md) for setup instructions.

---

## Folder visibility

A user only sees folders they have at least one permission type on (through any of their roles). Folders with no matching role entry are invisible.

The folder tree also reflects read-only status: folders where the effective permission is `R` are shown but the "Add item" button is hidden and item edit/delete actions are disabled.

---

## Diagnosing permissions for a user

An administrator can inspect the exact permissions a specific user has on every folder directly from the **Users** page:

1. Open **Users** in the administration menu.
2. Click the action menu next to a user and select **Visible folders**.
3. A modal opens with the full list of folders the user can access, including:
   - The **effective permission type** (resolved from all roles).
   - The **contributing roles** — each role that has an entry for that folder, shown as a badge with its individual type.

This view makes it straightforward to identify which role is granting an unexpected level of access. For example, if a user has `W` access to a sensitive folder, the badge column will show exactly which role is responsible.

> 💡 Use the **filter bar** at the top of the modal to isolate folders by permission type. For instance, filtering on `W` shows only folders where the user has full write access.

---

## Summary

```
User
 ├── Role A  ──► Folder 1: W,  Folder 2: R
 └── Role B  ──► Folder 1: R,  Folder 3: ND
                      │                │
                  Folder 1: W      Folder 3: ND
                  (W beats R)      (only one role)
```

The effective permission on each folder is calculated independently. Adding a restrictive role to a user **cannot reduce** their access on a folder where another role already grants a higher permission.
