# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

PremierSky's own internal charter-brokerage staff (brokers/ops) — a single small trusted team, not external clients or other brokerages. They handle the full lifecycle of a charter sale: intake a request, source and compare operator offers, verify aircraft and airport reference data, cost out the flight, and issue a contract.

## Product Purpose

An internal operations tool for PremierSky, a private-jet charter brokerage, that runs the whole charter sales workflow in one place: client records, airport and aircraft-type reference data, flight time/cost calculation, charter-fleet (tail) tracking, quote intake and comparison, and contract generation. It replaces what would otherwise be manual spreadsheet- and email-based tracking.

## Positioning

Its differentiator is collapsing the normally manual "copy offer details out of email" workflow: it retrieves operator offer emails from an Avinode-connected mailbox via IMAP, parses them into structured, comparable quote records, and carries that same data through to polished client-facing PDF quotations and VAT-compliant, multi-currency contracts — one connected pipeline instead of email, spreadsheet, and document tools stitched together by hand.

## Operating Context

Desk-based daily use by brokers: reviewing incoming charter requests, cross-referencing operator offers pulled automatically from Avinode emails (with search history over past lookups), verifying tail/aircraft and airport data, running flight-time/cost calculations, and issuing contracts denominated in EUR, RON, or USD with VAT percentage handling consistent with Romanian business operations.

## Capabilities and Constraints

- Stack: Laravel 13 + Inertia.js + Vue 3 + Tailwind CSS v4, SQLite datastore.
- Single company, single tenant — no multi-tenancy; not intended for other brokerages to use.
- Flat access model: any authenticated user has full access to all modules. No roles/permissions exist today, and none are planned near-term — this is a deliberate current state, not a gap.
- Avinode offer emails are retrieved via IMAP (webklex/laravel-imap) and parsed into `QuoteRequest`/`QuoteOffer` records.
- Client-facing PDFs (quotations, contracts) are generated via barryvdh/laravel-dompdf and must read as professional documents once they leave the internal tool.

## Brand Commitments

Name "PremierSky" and the logo at `resources/images/LOGO2023.png` are confirmed, final brand assets for this tool.

## Evidence on Hand

Existing modules/screens: Dashboard, Clients, Airports, Aircraft Type references, Flight Calculator, Contracts, Tails, Charter Fleet, Quotes (with search history). No marketing copy, testimonials, or public-facing pages exist — nothing here is customer-facing; do not fabricate any.

## Product Principles

1. Preserve the flat, low-friction access model — this is a small trusted internal team's tool, not a platform needing permission scaffolding.
2. Keep the Avinode-to-contract pipeline the connective spine — new work should reduce manual re-entry across quotes, contracts, and clients rather than add parallel silos.
3. Respect multi-currency (EUR/RON/USD) and Romanian VAT handling wherever money is shown or calculated.
4. Client-facing outputs (quotation PDFs, contracts) carry the PremierSky brand and must read as professional and trustworthy, since real clients see them.
5. This is an Operate-mode admin tool: scanability, consistency, and speed for daily task completion outrank marketing-style visual expression.
