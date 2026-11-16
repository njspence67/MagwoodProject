# Magwood Herd Management

This repository is the starting point for a capstone herd-management demo. The current target is a small, reviewable prototype by the end of Sprint 2, then a technical stack decision based on the actual IONOS plan.

## Start here

- Read `docs/PROJECT_START.md` for the recommended scope, team split, and provisional answers to unresolved questions.
- Open `prototype/index.html` for the phone-first clickable demo. All displayed records are illustrative.
- Review the [GitHub repository](https://github.com/njspence67/MagwoodProject) and [CAPSTONE TRELLO board](https://trello.com/b/s1IEApC3/capstone-trello) for the shared team plan.

The prototype uses plain HTML, CSS, and JavaScript so the team can review the field workflows before committing to React/Node or a PHP-based deployment path.

## Run the prototype

    npm start

Then open http://localhost:4173.

## Run the tests

    npm test

The tests cover tag search, approximate event creation, date validation, and local date formatting using Node’s built-in test runner.

## Current limitations

There is no backend, persistence, authentication, offline synchronization, medical protocol, or client-verified roster in this starting build. Do not use the sample records for ranch decisions.
