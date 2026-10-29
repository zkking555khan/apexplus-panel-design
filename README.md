# SMM APEX PLUS — Panel Design Repo (Private)

Design-only copy of the live SMM APEX PLUS panel, for the panel owner
to redesign with their own Muse.

## What is in here — and what is NOT

- IN: all customer-facing pages, admin pages, CSS, JS, schema.sql,
  robots.txt, sitemap.xml, Privacy Policy and Terms pages.
- NOT IN (never add these here): `config.php` (API key, database
  credentials), the live `data/` folder (admin hash, settings,
  customer data), or any live database dump. The panel will not run
  fully from this repo alone — it needs the live server config.

## Rules for design work in this repo

- Design changes only: layout, colours, typography, spacing, copy.
- Do NOT change business logic: order flow, payment approval,
  balance handling, provider API calls, or the locked API URL.
- Do NOT add secrets, API keys, passwords, or customer data.
- Do NOT rename the panel or add another brand name to public pages.

## Deploy

Only the panel owner deploys to the live site, after reviewing
changes. Nothing in this repo deploys automatically.
