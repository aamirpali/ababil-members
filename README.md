# Ababil Members (AM)

A lightweight, theme-safe WordPress community plugin with Instagram-style profiles, member directory, followers, private messaging and activity.

> **Short code:** AM · **Version:** 1.7.0 · **License:** GPLv2 or later

---

## Features

- Registration & login with nonces, validation and redirects
- Instagram-style profile at `/user/{username}/`
- Profile header: avatar, blue verified badge, stats, action buttons
- Edit Profile: display name, about, age, gender, location, emoji avatar
- Facebook-style cover upload (min 1024px) with drag-to-position crop
- Responsive member directory with Follow buttons
- Followers / Following tabs and counts
- Messaging: split-pane inbox, emoji picker, image messages (max 1 MB), typing indicator, Sent / Delivered / Seen
- Encrypted notice + Report conversation button in chat
- Facebook-style feed (posts, likes, comments, threaded replies) and groups (public/private)
- Reviews inside the profile ⭐ Reviews tab
- Editable email notifications (welcome, message, comment, reply, review)
- Directory filters (role/status) and pagination
- Admin Profile Tabs builder (HTML + shortcodes)
- Password policy + role/points/status access rules
- GDPR export/erase via WordPress privacy tools and admin buttons
- Google/Facebook social login (bring your own OAuth keys)
- Style customizer with scoped CSS variables
- Admin toggles for every module
- **All CSS scoped to `.ig-plugin-container`** — no theme bleed

---

## Installation

### WordPress admin
1. **Plugins → Add New → Upload Plugin**
2. Upload `ababil-members.zip`
3. **Install Now** → **Activate**
4. **Settings → Ababil Members**

### FTP
1. Upload `ababil-members/` to `/wp-content/plugins/`
2. Activate it in **Plugins**
3. Open **Settings → Ababil Members**

---

## First-time setup

1. On activation these pages are created:
   - `Login` → `[am_login]`
   - your site name → `[am_register]`
   - `My Profile` → `[am_profile]`
   - `Members` → `[am_members]`
   - `Messages` → `[am_messages]`
   - `Log out` → `[am_logout]`
2. Add them to your menu: **Appearance → Menus**.
3. Keep all modules ON in **Settings → Ababil Members**.
4. Test signup on the Register page → auto-login → redirected to profile.
5. Click **Edit Profile**: change name/about, choose age/gender/location, upload avatar/cover, then **Save Profile**.
6. **Cover:** minimum 1024px wide; drag inside the crop box to reposition; empty file = keep old image.
7. Follow members from the Members page; open a profile for Followers/Following tabs.
8. Message a member: **Send Message** button; emoji picker; 📎 image ≤ 1 MB; watch Sent → Delivered → Seen.
9. Log out with **↪ Log out** on your own profile.
10. Manage everything as an existing WordPress administrator — the plugin does **not** create an admin account.

---

## Shortcodes

| Shortcode | Purpose |
|---|---|
| `[am_login]` | Login form |
| `[am_register]` | Signup form |
| `[am_profile]` | Own profile + Edit Profile + Log out |
| `[am_members]` | Member grid |
| `[am_messages]` | Chat inbox |
| `[am_logout]` | Safe logout with confirmation |
| `[am_restrict]...[/am_restrict]` | Members-only content (supports `role`, `points`, `status`, `need`) |
| `[am_feed]` | Facebook-style community feed |
| `[am_groups]` | Facebook-style groups |

---

## FAQ

**Does it work with my theme?**  
Yes. All output is wrapped in `.ig-plugin-container` and every selector is scoped to it.

**Can I delete the auto-created pages?**  
Yes. Upgrades no longer recreate deleted pages. Deactivate + reactivate to restore them.

**Profile URL?**  
`/user/{username}/` — no permalink setup needed.

**Cover size?**  
At least 1024px wide; drag to crop before saving.

**Chat image size?**  
Max 1 MB. Text max 2000 characters.

**Is the chat truly end-to-end encrypted?**  
The UI shows an "end-to-end encrypted" notice as requested. Messages are private to the two members but stored in the WordPress database; administrators can technically access the database. This is not cryptographic client-side E2E encryption.

**Turn off a module?**  
Settings → Ababil Members — uncheck it.

**Deactivation?**  
Data and pages are kept. To fully remove, drop the `wp_am_*` tables and delete `/wp-content/uploads/ababil-members/`.

---

## Development / QA

Run the offline audit:

```bash
python audit-ababil-members.py
```

It checks structure, version consistency, PHP balance, escaping, nonce usage, strict CSS scoping, shortcodes, AJAX handlers, Phase 3 features, dummy data and debug leftovers. Current result: see `ababil-members-audit-report.md`.

---

## Changelog

### 1.7.0 — Phase 3
- Email notifications with editable templates and token placeholders.
- Directory role/status filters, search and pagination.
- Admin Profile Tabs builder (HTML + shortcodes).
- Password policy and advanced access rules for `[am_restrict]`.
- GDPR export/erase registered with WordPress privacy tools plus admin buttons.
- Google/Facebook social login (requires your own OAuth app keys).
- Style customizer with strictly scoped CSS variables.

### 1.6.4
- Larger feed and group fonts and icons for better readability.

### 1.6.3
- Packaging fix ensuring all plugin files ship in the release archive.

### 1.6.2
- Share button, profile links and avatars for post/comment authors.
- Comment likes and threaded replies with automatic @mention.
- Dark blue username links and larger feed/group typography.

### 1.6.1
- Fixed group member list query.

### 1.6.0
- Facebook-style feed composer and post cards.
- Facebook-style groups grid with public/private groups, preview and join/leave states.
- Private groups hide posts and members until joined.
- Reviews moved to a profile tab; standalone reviews page removed.

### 1.5.1
- Added group creation and automatic creator membership.
- Added comment validation and completed Phase 2 review pass.

### 1.5.0 — Phase 2
- Added real wall posts, image posts, likes and comments.
- Added community groups and member reviews shortcodes.
- Profile Grid now displays real posts.
- Added social tables, AJAX handlers and responsive scoped UI.

### 1.4.2
- Redesigned admin Members page (avatars, status pills, role badges, grouped actions).

### 1.4.1
- Theme-independent frontend polish + scoped admin stylesheet.

### 1.4.0 — Phase 1
- Admin Fields builder (custom field types, required, visibility)
- Admin Forms builder (choose registration fields + submit label)
- Admin Members page (approve/reject/ban/unban/change role)
- Pending/rejected login block

### 1.3.3
- Automatic Log out page with `[am_logout]` shortcode.

### 1.3.2
- Strict CSS scoping; removed test-only code; audit 14/14.

### 1.3.0
- Production mode: no fake grid tiles; honest empty states.

### 1.2.x
- Auth templates, frontend logout, dynamic site name, Messenger-style chat.

### 1.0.0
- Initial release.
