# 📦 **Reset WordPress – Fresh Install Reset Plugin**

A powerful WordPress tool that **resets your entire site to a true fresh install**, just like the moment WordPress was first installed — with the **option to preserve existing users** and/or **delete all uploaded media**.

This plugin is ideal for:

- Development environments  
- Reusable WordPress instances  
- Theme & plugin testing  
- Cleanup of broken or bloated sites  
- Local / staging resets  

---

## ⚠️ **Warning — Extremely Destructive**

Running a reset will:

- Permanently delete **ALL content** (posts, pages, media entries, comments, terms)
- Reset **ALL core options** to default WordPress install values
- Delete **all non-core database tables**
- Reactivate **no plugins** (all plugins are disabled)
- Reset the theme to the **default** WordPress theme
- Delete **uploads** (if selected)
- Destroy **all customizations** (menus, widgets, theme mods, settings)

**Backup your site before running. This operation cannot be undone.**

---

# ✨ Features

### ✔️ True fresh-install reset  
Performs a **full WordPress reinstallation** using internal WP functions like:

- `dbDelta()`
- `wp_install()`
- `wp_install_defaults()`

Ensures an identical state to a brand-new WordPress site.

### ✔️ Option to keep existing users  
If enabled:

- All existing users are backed up
- Site resets completely
- Users are re-created with the **same hashed passwords**
- User meta is restored

### ✔️ Option to delete all uploads  
Deletes all files inside `wp-content/uploads` (but not the folder itself).

### ✔️ Handles all core database tables  
All WordPress tables are dropped and recreated exactly as WP would on a fresh install.

### ✔️ Safe on new WordPress versions  
Because the plugin relies on WordPress core install functions instead of hard-coded table logic.

### ✔️ Multisite protection  
Multisite environments are **blocked automatically** to prevent network-wide damage.

---

# 📥 Installation

1. Download the plugin or clone this repository:

```bash
git clone git@github.com:trinadin/reset-wordpress.git