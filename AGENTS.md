# Itizan — working notes for AI assistants and contributors

Laravel 13 / PostgreSQL / Blade / Tailwind 4. Read `README.md` (architecture) and `docs/FEATURES.md` (rules) first.

## Conventions
- Business rules go in `app/Actions/<Domain>` (throw `BusinessRuleViolation` with a translated message); controllers stay thin; authorise with policies, validate with Form Requests.
- Roles: `Role` enum. `role:` middleware runs before model binding. Never give admin/support access to clinical data.
- Money is integer halalas (`money()` helper). Times are Asia/Riyadh.
- Health text is encrypted via model casts; never put clinical content in notifications, logs or audit metadata.
- Design: follow `docs/DESIGN.md` (tokens, glass only on floating elements, never behind forms/prices/clinical data).
- UI strings: Arabic in `__('…')`, English in `lang/en.json` (a test enforces this). Use logical Tailwind utilities (`ms-`, `ps-`, `text-start`) so RTL/LTR both work. Every status needs a text label, not just colour.
- Models use `#[Unguarded]` except `User` (explicit `#[Fillable]`); always build attributes from validated data.
- Prefer `with()` eager loading: lazy loading throws outside production.

## Commands
`php artisan test` (PostgreSQL `itizan_test`) · `composer lint` · `npm run build` · `php artisan db:seed --class=DemoSeeder` (local only).
Set `PAO_DISABLE=1` to get normal PHPUnit output instead of JSON.

## Do not
Run `DemoSeeder` in production, use the sandbox payment driver in production, commit `.env`, rotate `APP_KEY` without a re-encryption plan, or add an endpoint that lets a client change a prescription or dose.
