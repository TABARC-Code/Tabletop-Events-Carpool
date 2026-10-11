# Tabletop Events Calendar — Carpool

A "need a lift / have spare seats" board for [Tabletop Events Calendar](https://github.com/TABARC-Code/tabletop-gaming-events-calendar) events — anchored to a specific event, so an admin never has to broker it by hand.

Requires [Tabletop Events Calendar](https://github.com/TABARC-Code/tabletop-gaming-events-calendar) — this plugin does nothing without it.

## What it does

- `[tabletop_event_carpool event="123"]` — one event's lift offers and requests, plus a "post a listing" form.
- A listing is either "offering a lift" or "looking for one" — a display name, a rough departure area, a seat count, and optional notes. No exact address needed.
- Every poster gets a magic link by email to edit their own listing or take it down once a lift's sorted — no account, no password.
- A "get in touch" button relays a message to the poster's real email without ever showing it publicly.
- New listings for an event that's already happened are rejected outright; existing listings quietly come down about a week after the event's date via a daily cleanup, so wp-admin doesn't slowly fill up with stale posts.

## Why anchor on the event rather than build a wider ride-share network

Because a lift to a specific game night is a specific, short-lived need — not a standing carpool profile someone maintains forever. Anchoring on the event ID keeps the whole thing lightweight: no user accounts, no "your rides" dashboard, no separate identity system. Same "anchor on data that already exists" approach as the Venues, LFG, Reviews, and Discord RSVP plugins in this family.

## Installing

Install and activate the core calendar first. Then either upload a zip via Plugins ▸ Add New, or drop the `tabletop-events-carpool` folder into `wp-content/plugins/`. GitHub's "Download ZIP" names the folder `...-main`, which WordPress won't treat as the same plugin on update — run `scripts/pack-plugin.sh` to build a correctly named zip instead.

## Other pieces of the set

Every plugin here stands alone and only ever needs the [core calendar](https://github.com/TABARC-Code/Tabletop-Gaming-Events-Calendar) — none depend on each other. Take what's useful:

- [Tabletop-Events-Discord-rsvp-Repo](https://github.com/TABARC-Code/Tabletop-Events-Discord-rsvp-Repo) — RSVP from a Discord button
- [Tabletop-Events-Reviews](https://github.com/TABARC-Code/Tabletop-Events-Reviews) — RSVP-gated event and organiser reviews
- [tabletop-events-resources](https://github.com/TABARC-Code/tabletop-events-resources) — Terrain, tables and dice to borrow
- [tabletop-events-tournaments](https://github.com/TABARC-Code/tabletop-events-tournaments) — Swiss pairings and standings

## Licence

GPL v2 or later.
