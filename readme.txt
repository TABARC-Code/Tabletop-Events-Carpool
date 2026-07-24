=== Tabletop Events Calendar — Carpool ===
Contributors: tabarccode
Tags: events, calendar, tabletop, carpool, lift-share
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lift-share board for Tabletop Events Calendar events — offer a seat or ask for one, anchored to a specific event. Requires the Tabletop Events Calendar plugin.

== Description ==

Getting there is half the battle for a lot of the rural and grassroots gaming scenes this calendar already serves — "I'd come if I could get a lift" is a real barrier, not a small one. This plugin puts a lightweight lift-share board on any event page.

Post a listing either way round: offering a seat, or looking for one. Each listing gets a magic-link email so the poster can edit it or take it down themselves once a lift's sorted — no account needed. Nobody's real email address is ever shown publicly; a "get in touch" button relays a message through to them instead.

Listings are anchored to one event ID at a time, same pattern as `[tabletop_organiser_events]` elsewhere in this family.

Two moving parts:

* `[tabletop_event_carpool event="123"]` — the board and submission form for one event.
* A daily cleanup that quietly trashes listings for events that happened a while ago, so wp-admin doesn't slowly fill up with stale posts nobody comes back to remove.

== Installation ==

1. Install and activate **Tabletop Events Calendar** first.
2. Upload the `tabletop-events-carpool` folder to `/wp-content/plugins/` and activate it.
3. Add `[tabletop_event_carpool event="123"]` to an event's page.
4. New listings land in **Events Calendar ▸ Carpool** for approval.

== Frequently Asked Questions ==

= Can I see who posted a listing? =

No — not directly. Names shown on the board are whatever the poster typed in; their real email stays private, used only for the manage link and the "get in touch" relay.

= What happens once the event's happened? =

New listings for a past event are rejected outright. Existing listings quietly come down a week after the event's date, whether or not the poster ever comes back to remove them.

== Changelog ==

= 1.0.0 =
* Initial release: tcar_listing CPT, event-anchored carpool board, magic-link self-service management, get-in-touch relay, daily stale-listing cleanup.
