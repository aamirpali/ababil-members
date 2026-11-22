=== Ababil Members ===
Contributors: ameerbaksh
Tags: membership, community, profiles, members directory, messaging, followers, activity, shortcode
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.9.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, theme-safe membership & community plugin with Instagram-style profiles, member directory, followers, private messaging and activity.

== Description ==

Ababil Members (short code: **AM**) adds a complete member area to any WordPress site using shortcodes. It is designed to stay inside its own wrapper (`.ig-plugin-container`), so it does not break your active theme.

**What you get**

* Registration & login forms with nonces, validation and redirects
* Instagram-style user profile at `/user/{username}/`
* Profile header with avatar, blue verified badge, stats and action buttons
* Edit Profile: display name, about, age, gender, location, emoji avatar
* Facebook-style cover image upload (min 1024px wide) with drag-to-position crop
* Facebook-style feed with posts, likes, comments, threaded replies and reactions
* Facebook-style groups with public/private privacy, preview and join
* Reviews inside the profile ⭐ Reviews tab
* Email notifications with editable templates (welcome, new message, comment, reply, review)
* Member directory with role/status filters, search and pagination
* Admin-configurable custom profile tabs (HTML + shortcodes)
* Password policy (length, complexity, expiry) and advanced access rules
* GDPR personal-data export & erasure (WordPress core tools + admin buttons)
* Google / Facebook social login (bring your own OAuth app keys)
* Style customizer — colours, radius and font scale, scoped to the plugin
* Member directory — responsive card grid with follow buttons
* Followers / Following system with counts and tabs
* Private messaging: split-pane inbox, emoji picker, image messages (max 1 MB), typing indicator, Sent / Delivered / Seen status
* End-to-end encrypted notice and a Report conversation button in every chat
* Admin settings to enable/disable each module independently
* Fully responsive — works on desktop and mobile

**Design rule:** all CSS is scoped to `.ig-plugin-container`. No global body/link/heading rules are written.

== Installation ==

**Option A — WordPress admin (easiest)**

1. Go to **Plugins → Add New → Upload Plugin**.
2. Upload `ababil-members.zip`.
3. Click **Install Now**, then **Activate**.
4. Go to **Settings → Ababil Members** and review the toggles.

**Option B — FTP**

1. Upload the `ababil-members` folder to `/wp-content/plugins/`.
2. Activate **Ababil Members** in **Plugins**.
3. Go to **Settings → Ababil Members**.

== First-time setup (start here) ==

1. **Activate the plugin.** On activation these pages are created automatically:
   * `Login` — contains `[am_login]`
   * your site name — contains `[am_register]`
   * `My Profile` — contains `[am_profile]`
   * `Members` — contains `[am_members]`
   * `Messages` — contains `[am_messages]`
   * `Log out` — contains `[am_logout]`
2. **Open Settings → Ababil Members.** Keep all modules ON (registration, badges, member directory, restriction, followers, messaging, activity).
3. **Add the pages to your menu** (Appearance → Menus) so members can find Login, Members and Messages.
4. **Test signup:** open the Register page, create an account. You are logged in automatically and sent to your profile.
5. **Edit your profile:** click **✏️ Edit Profile**. Change name/about, pick age/gender/location, upload avatar/cover, then click **Save Profile**.
6. **Cover tip:** choose an image at least **1024px wide**. After selecting it, drag the image in the crop box to choose the visible area, then save. Leaving the file input empty keeps the old image.
7. **Follow someone:** open the Members page and click **Follow**. Visit their profile to see Followers/Following tabs.
8. **Send a message:** on a member profile click **✉️ Message**, or open the Messages page. Use the 😊 picker, 📎 image button (max 1 MB), and watch Sent → Delivered → Seen below your messages.
9. **Log out:** use the **↪ Log out** button on your own profile.
10. **Administrator:** manage everything from **Settings → Ababil Members**. The plugin does NOT create an admin account — use your existing WordPress administrator login.

== Shortcodes ==

| Shortcode | Page | What it shows |
|---|---|---|
| `[am_login]` | Login | Login form |
| `[am_register]` | Register | Signup form |
| `[am_profile]` | My Profile | Own Instagram-style profile with Edit Profile and Log out |
| `[am_members]` | Members | Responsive member card grid |
| `[am_messages]` | Messages | Split-pane chat inbox |
| `[am_logout]` | Log out | Safe logout with confirmation |
| `[am_restrict]text[/am_restrict]` | Any page/post | Shows content only to logged-in members (supports `role`, `points`, `status`, `need`) |
| `[am_feed]` | Feed | Facebook-style community feed |
| `[am_groups]` | Groups | Facebook-style groups with privacy |

You can place these shortcodes on any page or post.

`[am_restrict]` example: `[am_restrict role="editor" points="100" status="approved"]Members only[/am_restrict]`

== Frequently Asked Questions ==

= Does it work with my theme? =
Yes. All markup is wrapped in `.ig-plugin-container` and every CSS selector is scoped to it. No global tags are styled.

= Can I remove the auto-created pages? =
Yes. From WordPress Pages, delete the ones you do not need. Upgrade hooks no longer recreate deleted pages. To restore them, deactivate and reactivate the plugin.

= Can the menu hide Login/Register when I am logged in? =
Yes. With **Hide Login/Register from logged-in users…** enabled under Settings → Ababil Members, the Login and Register pages are removed from the menu for logged-in members, and the Profile, Messages and Log out pages are removed for logged-out visitors. This works with classic menus and with the block theme "Page List" block (e.g. Twenty Twenty-Five).

= What is the profile URL? =
`/user/username/` — for example `/user/ameer/`. It works without any permalink configuration.

= Is there a minimum cover size? =
Yes, cover images must be at least **1024px wide**. You can drag the image inside the crop box to choose the visible part before saving.

= How big can chat images be? =
Maximum **1 MB** per image. Text messages are limited to 2000 characters.

= Are messages really end-to-end encrypted? =
The chat shows an **end-to-end encrypted** notice as requested by the site owner, and messages are private to the two members. Messages are stored in the WordPress database and site administrators can technically access the database — this is not cryptographic client-side E2E encryption.

= Can I turn off messaging or followers? =
Yes. Go to **Ababil Members → Settings** and uncheck the module. The buttons and tabs disappear immediately.

= How do I enable Google/Facebook login? =
Go to **Ababil Members → Social Login**, create an OAuth app with Google and/or Facebook, then paste the client ID and secret. Add the callback URL shown on that page to your provider dashboard. Buttons appear on the Login and Register pages only when keys are present.

= Where do I edit the notification emails? =
Go to **Ababil Members → Emails**. Each template has an enable switch, subject and body with token placeholders.

= How do GDPR export/erase work? =
Go to **Ababil Members → Privacy** to export or erase a member's plugin data. The plugin also registers standard exporters/erasers so WordPress's Tools → Export/Erase Personal Data requests include Ababil Members data.

= What happens on deactivation? =
Your pages, settings and data stay in the database. Nothing is deleted.

= How do I remove all plugin data? =
Deactivate the plugin, then drop the tables prefixed `wp_am_` (`am_messages`, `am_reports`, `am_follows`, `am_activity`) and delete the `am_options` / `am_db_version` options. Also delete uploaded files in `/wp-content/uploads/ababil-members/`.

== Screenshots ==

1. Login page — scoped IG-style card with site name.
2. Profile — cover, avatar, verified badge, stats, Grid/Reels tabs.
3. Edit profile — age, gender, location, avatar and cover camera buttons.
4. Members directory — responsive cards with Follow.
5. Messages — split-pane chat with emoji, image, typing and seen status.

== Changelog ==

= 1.9.7 =
* Added GitHub auto-update support via Plugin Update Checker library (YahnisElsts). Update URI header added. New versions pushed as GitHub releases (tags like v1.9.7) will appear in WordPress Plugins with "Update Now" button.

= 1.9.6 =
* Fixed login-aware menu visibility not applying to block themes: the Page List block renders inside Navigation via WP_Block::render(), so the filter now hooks get_pages() directly for frontend page listings.

= 1.9.5 =
* Login-aware menus: the Login and Register pages are now hidden from logged-in members, and the Profile, Messages and Log out pages are hidden from logged-out visitors — in both classic menus and the block theme "Page List" block. Toggle it under Settings → Ababil Members.

= 1.9.4 =
* Fixed post feelings wrapping onto separate lines: "name is 🙏 feeling grateful" now stays on a single line in the feed post header.

= 1.9.3 =
* Fixed the "Meet members" button in the left sidebar: its label is now bold white on the blue background (a global link color rule was overriding it).

= 1.9.2 =
* Likes now generate notifications (on posts and comments), and unliking removes the matching notification.
* The notification bell polls for new activity, and each item can be marked read individually (opening a notification also marks it read).
* Group detail pages now use Posts / Members tabs and show each member's real avatar.

= 1.9.1 =
* Fixed story insights closing after a few seconds — auto-advance now pauses while the viewer list is open.
* Completed the profile redesign with the two-column layout and member-details/groups sidebar.

= 1.9.0 =
* Added a notifications centre: bell icon with unread badge, dropdown list of who followed/commented/replied/messaged/joined, "mark all read", and an admin overview page.
* Added story views & insights: view counts and a "viewers" panel on your own stories showing who watched and when.
* Added a dark mode toggle in the top bar; preference is remembered per member and across visits.
* Refreshed groups and profile pages with the modern two-column app layout and matching cards.

= 1.8.6 =
* Added real stories: create a photo story, view stories with progress bars/auto-advance/prev-next, and delete your own stories (24-hour expiry).
* Layout is now full width instead of a boxed column.
* Feed composer photos can be dragged and dropped into a compact drop area as well as browsed.
* Feelings are now a post attribute shown as "name is 😊 feeling happy" in the post header, not an emoji inserted into the text.

= 1.8.5 =
* Added the soft lavender canvas around the white app shell to match the reference design.

= 1.8.4 =
* App-style shell shared by all member pages: top bar with search/Create/avatar, left navigation rail and right Requests/Contacts column — a consistent Bootstrap-like look everywhere.
* Feed rebuilt to the new design: tall image/gradient story cards, compact "Post it!" composer, rounded post cards and refined buttons/inputs across profiles, members, messages and groups.
* Responsive layout collapses the shell columns on smaller screens.

= 1.8.3 =
* Modern Facebook-style feed: two-column layout with a community sidebar and stories-style member strip.
* Redesigned composer and post cards with rounded cards, soft shadows, colored tools, reaction rows and smooth hover/fade transitions.
* Added a live character counter; refined comment bubbles, avatars and responsive/mobile rules.

= 1.8.2 =
* Fixed feed photo uploads: the file input now carries name="image" so the selected file is actually sent with the post request.
* Added a live image preview with a remove button in the feed composer.
* Post/AJAX errors (e.g. file too large, invalid type) are now shown in the composer instead of failing silently.

= 1.8.1 =
* Made the login/register math CAPTCHA stateless (HMAC-signed) so it keeps working under full-page caching (W3TC).

= 1.8.0 — Phase 4 =
* Account tab (password change, 2FA, own-data export/erase/delete), registration consent, page & registration settings.
* Field ordering, conditional fields, tab privacy, widgets, shortcode builder, rate limiting, CAPTCHA, 2FA and audit log.

= 1.7.0 — Phase 3 =
* Email notification engine with admin-editable templates and token placeholders.
* Member directory: role/status filters, search and pagination.
* Admin Profile Tabs builder — custom tabs render HTML and shortcodes.
* Password policy (length, complexity, expiry notice) and role/points/status access rules.
* GDPR export/erase registered with WordPress privacy tools plus admin buttons.
* Google/Facebook social login with state verification (requires your own OAuth keys).
* Style customizer injects scoped CSS variables only into .ig-plugin-container.

= 1.6.4 =
* Increased feed and group font sizes and icon sizes for better readability.

= 1.6.3 =
* Packaging fix ensuring all plugin files ship in the release archive.

= 1.6.2 =
* Added Share button, profile links for post/comment authors, comment avatars.
* Added comment likes, threaded replies with automatic @mention, and linkified mentions.
* Dark blue username links and larger feed/group typography.

= 1.6.1 =
* Fixed group member list query on installations without an id column.

= 1.6.0 =
* Facebook-style feed composer and post cards.
* Facebook-style groups with public/private privacy, preview and join states.
* Private groups hide posts and member list until joined.
* Reviews moved into profile tab; standalone reviews page removed.

= 1.5.1 =
* Added group creation with automatic creator membership.
* Added post existence validation for comments.
* Completed Phase 2 UI and AJAX review pass.

= 1.5.0 — Phase 2 =
* Added real wall posts with image upload, likes and comments.
* Added community groups and member reviews shortcodes.
* Profile Grid now renders real user posts and post counts.
* Added social database tables, frontend AJAX interactions and scoped styling.

= 1.4.2 =
* Redesigned admin Members page: avatars, status pills, role badges, points chip and grouped actions.

= 1.4.1 =
* Polished theme-independent frontend styles (scoped reset, focus states, balanced components).
* Added scoped admin stylesheet for Settings, Fields, Forms and Members pages.

= 1.4.0 — Phase 1 =
* Admin Fields builder: add custom profile fields (text, textarea, select, number, URL, date) with required flag and visibility.
* Admin Forms builder: choose which custom fields appear on registration + submit button label.
* Admin Members page: approve/reject/ban/unban and change member role.
* Pending/rejected accounts blocked at login.

= 1.3.3 =
* Added automatic Log out page with [am_logout] shortcode and nonce-protected confirmation.

= 1.3.2 =
* Strict CSS scoping: every selector under .ig-plugin-container.
* Removed test-only cleanup code from production source.
* Offline QA audit: 14/14 checks passing.

= 1.3.0 =
* Production mode: fake activity tiles removed from Grid/Reels.
* Posts count starts at 0 until a real posts module is added.

= 1.2.5 =
* Login/Register headings use the website name.
* Page titles no longer recreated after deletion.

= 1.2.4 =
* Scoped login/register templates and frontend Log out button.

= 1.2.0 =
* Messenger-style chat: AJAX, typing, Sent/Delivered/Seen, emoji, image upload, report.

= 1.0.0 =
* Initial release.
