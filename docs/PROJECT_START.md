# Magwood Herd App Project Start

## Current target

The repository now contains a phone-first frontend prototype and a PHP/Laravel 13 backend, rewritten from the initial Python/FastAPI implementation. The backend developer owns the API and business logic; a different teammate owns the database, with adapter/query ownership still to be agreed. IONOS shared hosting is the selected hosting direction; the exact package capabilities and database engine still need confirmation. Deadlines are not a constraint for the current backend work.

See `backend/README.md` for the implemented API, local setup, limitations, and database integration contract. The remaining recommendations below preserve the earlier project context and are subject to team/client confirmation.

This document is a working recommendation. It does not represent client approval of the unresolved rules below.

## What the team workspace adds

- The current Trello plan has two active Sprint 1 tasks: selecting a database/hosting/delivery path and deciding how to divide the next research/build work. Keep that decision and a reviewable Sprint 2 demo as the near-term goal.
- The Trello backlog contains inventory and admin user stories, plus broader ideas for voice, weather, local farm information, butcher prices, feed advice, and AI. These support the later product direction; they should not block the first demo.
- The Teams “data from client” folder contains the birthing workbook and a ZIP with four cattle photos. The ZIP does not contain the earlier eight-tab app proposal.
- The accessible OneNote tab currently shows an empty Loop paragraph rather than project notes. Trello is the clearer source for sprint planning until the notebook is populated.

## Product order

1. Establish and search the herd baseline.
2. Open an animal record that preserves identity when tags change or get reused.
3. Record a birth, weight, treatment, tag change, or paddock move with minimal typing.
4. Review the history and upcoming work from the dashboard.
5. Add feedlot, finishing cost, butcher, and sales workflows after the core record path works.
6. Treat voice, offline synchronization, weather, and AI as later slices. For real ranch use, security, backup, and offline behavior must be completed before launch.

## Recommended work split

| Person | Ownership | Sprint 2 deliverable |
|---|---|---|
| Noah, front end | Phone-first interaction design and prototype; align screens to a shared event and animal shape. | Roster, animal detail, quick event capture, overview, grazing view. |
| Brendan | Data model and backend/hosting research; report what the IONOS plan supports. | One-page stack recommendation and a draft animal/event API shape. |
| Mason | Client-data staging and domain rule review; keep raw notes and identify ambiguous matches. | Import map and a short confirmation list for Carlos and Charleene. |
| Nicholas | Operations flows, integration scenarios, and handoff planning. | Grazing/feedlot/booking acceptance scenarios and outline of the walkthrough. |

This table is the earlier suggested split, not current ownership. The current backend assignment excludes database development; the database teammate's exact responsibilities remain to be agreed.

## Recommended answers to open questions

### 1. IONOS plan and runtime

PHP with Laravel 13 is the selected backend stack following confirmation that the IONOS plan is shared hosting. The Laravel rewrite supersedes the earlier FastAPI/Cloud Server assumption. Confirm PHP 8.3+ with Laravel's required extensions, Composer/deployment access, the ability to serve only the `public` directory, and the available MySQL/MariaDB service on the exact package. Local development uses a replaceable file-backed demo repository so work can proceed before database selection; the default adapter refuses production mode. Production authentication, database integration, and hosting verification remain required. The frontend remains plain HTML/CSS/JavaScript; no frontend framework change is required.

### 2. App name and earlier tab proposal

Use “Herd management” as a temporary label; do not invent a brand. Ask the client or team to locate the referenced eight-tab proposal. For the demo, group navigation into five destinations: Overview, Herd, Calendar, Grazing, and Operations. Put vaccines, breeding, feedlot, butcher, and finance inside the relevant destination instead of making eight equally prominent tabs.

Treat “App Store app” as a future packaging idea. Start with a responsive web prototype; if the production hosting and field testing support it, make that web app installable as a PWA before considering native iPhone/Android store releases.

### 3. Breeding and calving dates

The workbook uses a 45-day step and then nine calendar months. Use that as the first compatibility default because it matches the client’s existing sheet. Store the two dates separately: “eligible to breed” and “projected calving.” Show a window and label it an estimate. Do not use the 90-day figure as a rule until the client explains it. Keep the interval configurable; a 283-day gestation calculation can be offered later if the client prefers it.

### 4. Culling flags

Make flags prompts for human review, never automatic removal. Start with configurable “no calf in two years” and “age review at 10 years” flags because those reflect the client’s notes. Show the underlying records and the reason. Treat a pregnancy check deadline, injury, teeth, or veterinary recommendation as recorded evidence only after the client confirms the rule.

### 5. Medicines and schedules

Import the listed product names and history, but leave future schedules unset until Carlos and Charleene provide veterinarian-approved protocols. The supplied medicine list is not a complete treatment plan. The UI can show “schedule not configured” and record product, date, dose as entered, and withdrawal information when supplied.

### 6. Paddocks and rotation

Start with Paddock 1 through Paddock 8 as editable placeholder names. Record group, from/to paddock, date, and notes. Show elapsed time since a move, but do not call a move overdue or give forage advice until the client supplies target graze/rest days and group rules. Include group identity from the beginning because the client is forming a separate herd.

### 7. Feed recipes and finishing costs

Start with actual feed logs: date, animal/group, number of buckets, bucket weight, ingredients, and cost where known. Allow a recipe to be entered without preloading the example 90% corn mix as an approved ration. Calculate costs only when the bucket weight, ingredient weights, and prices have confirmed units. Do not present feeding amounts as animal-care advice.

### 8. Current herd and tag history

Use the November 8 roster as an import candidate, not as the active truth. Stage every row, preserve the original note and source sheet/row, and ask a person to resolve uncertain matches. Give each animal an internal immutable ID; tags are dated aliases that can change or be reused. Keep #1347 historical because the source says the herd bull died; do not assume the replacement bull’s identity.

### 9. Users, roles, and devices

Design mobile web first and support installation as a PWA if the hosting choice allows it. Propose three roles: owner/admin, field editor, and read-only helper. Provisionally, Charleene manages records and finances, Carlos records field events, and helpers get the least access they need. Confirm role assignments and whether both iPhone and Android must be supported before launch.

### 10. Notifications

For the demo, use in-app due lists and badges only. For a real release, begin with optional email reminders and browser push after the client chooses timing and recipients. Avoid paid SMS until the team has a budget and the client requests it.

Local weather and butcher-price feeds are useful research items, but defer integrations until the team confirms a reliable Coffee County data source, processor coverage, and any service costs. Display source and retrieval date when those features are eventually added.

## Questions to take back to the client

- Verify the November roster, current living animals, and new bull identity.
- Confirm the 45-day / nine-month projection and explain the 90-day reference.
- Supply approved vaccine/deworm schedules and withdrawal periods.
- Name the paddocks, confirm target rotation ranges, and identify separate groups.
- Confirm feed bucket weight, recipe quantities, and ingredient costs.
- Confirm the exact IONOS shared-hosting package capabilities, user devices, roles, preferred notifications, and any existing eight-tab proposal.

## Team working agreement

- Treat imported data as proposed until reviewed; preserve the original text and source.
- Use internal IDs for animal identity and tag history for visible numbers.
- Label estimated, approximate, and uncertain values in the interface.
- Keep care, ration, and culling suggestions advisory and traceable to confirmed rules.
- Integrate once each week against the same animal/event examples.
- Do not add AI or weather integrations until the roster, event history, and ownership of data are reliable.

## Sprint 2 demo script

Use one short, connected scenario:

1. Search the roster using a former tag and open the animal record.
2. Show the current tag and former tag together, with the identity marked for review.
3. Add a dated field note or weight record and show it in the timeline.
4. Record a herd move from one placeholder paddock to another.
5. Point out where estimated dates are labeled and where client confirmation is still needed.

Keep the demo data visibly labeled. The goal is to show the intended workflow, not to imply the November roster is verified.

## Local prototype

The prototype in the prototype folder is plain HTML, CSS, and JavaScript. It uses illustrative records and performs no network requests. Open prototype/index.html in a browser, or serve the prototype folder with:

    php -S 127.0.0.1:4173 -t prototype

The prototype is for workflow review, not a production app. Do not treat its sample counts, dates, paddock timing, or animal statuses as client-confirmed data.
