# Itizan — features and acceptance criteria

Itizan is a medically supervised weight-management platform (Laravel 13, PostgreSQL, Blade, Tailwind, Arabic-first/RTL with English).
Every criterion below is enforced by an automated test (the test class is named in brackets).

## 1. Public site
| Feature | Acceptance criteria |
|---|---|
| Home, how it works, services, team, pricing, FAQ, prescription journey, about, contact, policies | All render; Arabic (RTL) by default, English (LTR) via the language switch; mobile and desktop; no accessibility violations (axe, 41 pages) [PublicSiteTest, LocalizationTest] |
| Positioning copy | Hero title, intro text, tagline and the call to action "جرّب اتزان واحصل على استشارة مجانية" with its explanatory text appear on the home page, and **before the packages** on home and pricing [PublicSiteTest] |
| Verified team only | A doctor/dietitian appears publicly (name, specialty, licence) only when active, licence verified and not expired [PublicSiteTest, StaffAndPractitionerTest] |
| Pricing | 999 SAR / 6 months (166.50 per month) and 1,499 SAR / 12 months (124.92 per month); saving 499 SAR ≈ 25 %; instalment 249.75 / 374.75; VAT and "medication and labs not included" notes [PackagePricingTest, PublicSiteTest] |
| BNPL | Tabby/Tamara shown as "after merchant approval" until enabled by config [PublicSiteTest, CheckoutTest] |
| Policies | Privacy, terms, subscription/renewal/cancellation/refund, delivery, patient rights, complaints, medical disclaimer; Markdown, raw HTML stripped [PublicSiteTest] |
| WhatsApp | Floating button on every page opens `wa.me/966551475212` with the support message pre-filled [WhatsappTest, PublicSiteTest] |
| Emergency | "Not an emergency service — Saudi Red Crescent 997" in the footer and on clinical screens [PublicSiteTest, SideEffectsAndContactTest] |

## 2. Accounts and client journey
| Feature | Acceptance criteria |
|---|---|
| Registration | Mobile number (any Saudi format, Arabic digits) + details; terms/privacy consent recorded; marketing consent separate and optional; role/activation cannot be chosen by the visitor [RegistrationTest] |
| Login | Phone or email; one generic error (no account enumeration); throttled; deactivated accounts refused and signed out mid-session; session regenerated [LoginTest] |
| Free consultation | One per client (PostgreSQL partial unique index); scope and FTMS attendance explained and acknowledged before booking; reusable after cancellation [AppointmentBookingTest] |
| Booking | 20-minute slots from doctors' weekly availability (24 h coverage supported); minimum notice; 14-day horizon; no double booking (index); instant requests claimed by the first instant-enabled doctor [AppointmentBookingTest] |
| Open sessions | Confirmed appointments that ended > 15 min ago are listed on the doctor's dashboard and the doctor is reminded once (`itizan:stale-appointments`) until closed [ContinuityOfCareTest] |
| Follow-ups | Require an active subscription and remaining quota; quota returned on cancellation; client cannot cancel < 2 h before [AppointmentBookingTest] |
| Primary doctor | The doctor who approves the assessment becomes the client's follow-up doctor (never overwritten); booking offers that doctor's slots first with a one-click switch to any doctor; an admin can reassign or clear it (audited); a busy primary doctor is refused, never silently swapped [ContinuityOfCareTest] |
| Journey | The ten journey steps are tracked and one clear "next step" is always shown [DashboardsAndJourneyTest] |

## 3. Medical assessment, plan, progress
| Feature | Acceptance criteria |
|---|---|
| Assessment | Height/weight/waist/BMI, history, chronic conditions, medications, allergies, surgeries, pregnancy/breastfeeding, previous treatments, symptoms, labs, goals, safety questions; draft and submit; locked while under review; free text **encrypted at rest** [AssessmentTest] |
| Doctor review | Safety-flagged cases first; only a doctor decides (approve / need information / not suitable, note required when not approved); every opening audited; admin, support, care team and unassigned dietitians cannot open it [AssessmentReviewTest] |
| Plan | Only a verified doctor, only after approval; one active plan per client (index); dietitian edits nutrition guidance only [WeightPlanTest] |
| Progress | Start/current/target weight and waist, progress %, doctor notes and steps; chart has a text table equivalent [ProgressTest, WeightPlanTest] |

## 4. Medication, symptoms, labs
| Feature | Acceptance criteria |
|---|---|
| Prescriptions | Doctor-only, verified, approved assessment required; **no automatic prescriptions and no automatic dose increases; the client has no endpoint to change a dose**; only the issuing doctor can stop one; encrypted at rest [PrescriptionTest] |
| Dose logging | Taken/missed; up to 2 h early; duplicates impossible (unique index); schedule advances by the doctor's interval only; prescription completes at end date [DoseLoggingTest] |
| Pre-dose questionnaire | Doctor-defined; an alarming answer holds the dose and raises an urgent request to the doctor [DoseLoggingTest] |
| Reminders | Scheduled, queued, generic text (never the drug or dose) [ScheduledCommandsTest] |
| Side effects | Severity and time, attachments (private, validated); severe ⇒ escalated + urgent doctor request + emergency guidance; care-team triage [SideEffectsAndContactTest] |
| Contact requests | Doctor or care team; urgent ones page the care team; doctors see only requests addressed to them [SideEffectsAndContactTest] |
| Labs | Doctor requests; client uploads; doctor reviews; files served only through an authorised route [LabTest] |

## 5. Orders, pharmacy, delivery
| Feature | Acceptance criteria |
|---|---|
| Subscription gate | Prescribing and ordering **medication** require an active subscription (supplements do not); dose tracking and reminders continue after expiry [ContinuityOfCareTest] |
| Lapsed prescriptions | 7 days after the last subscription ends, `itizan:lapsed-prescriptions` tells the client, the prescribing doctor and the care team once per lapse (generic text); the prescription is **never stopped automatically** — the doctor decides [ContinuityOfCareTest] |
| Order | Medication needs one of the client's active, matching prescriptions; free delivery per configured cities [OrderFlowTest] |
| Pharmacy | Confirms availability, price and ETA; first pharmacy to quote owns the order; the client **approves the exact total** before preparation; re-quote invalidates stale approval [OrderFlowTest] |
| Fulfilment | approved → preparing → out for delivery → delivered; invalid/skipped transitions refused; client notified each step [OrderFlowTest] |
| Delivery partner | Sees delivery details only — no items or medical data [OrderFlowTest] |

## 6. Subscriptions and payments
| Feature | Acceptance criteria |
|---|---|
| Email at checkout | Clients registered by phone only must add a valid, unique email (stored lowercase) to subscribe; not asked again once known [ContinuityOfCareTest] |
| Checkout | Shows total, VAT and what is actually charged; policy links; separate optional auto-renew consent; BNPL cannot be combined with auto-renew [CheckoutTest] |
| Payment | `PaymentGateway` interface with a sandbox driver (refused in production); success activates the subscription and issues a numbered receipt; idempotent [CheckoutTest, WebhookTest] |
| Webhook | HMAC-signed, CSRF-exempt, rate-limited, idempotent; late success after failure still activates [WebhookTest] |
| Management | Turn auto-renew off/on (consent ledger), change payment method, receipts, refund by admin [SubscriptionManagementTest, ContentAndCatalogTest] |
| Renewal window | New purchase allowed ≤ 30 days before the end and queued after the current period [CheckoutTest] |

## 7. Administration and operations
Dashboard, clients (no clinical data), subscriptions, payments/refunds, packages (price in halalas, snapshot on subscriptions), practitioners and licence verification, staff and roles, pages/FAQ CMS, products, pharmacies, tickets/complaints, reports, operations monitor, audit log [AdminAccessTest, StaffAndPractitionerTest, ContentAndCatalogTest, SupportTicketTest].

## 8. Privacy and safety (cross-cutting)
- Least privilege by role; object-level policies on every protected action; role check runs **before** route-model binding so ids cannot be probed [AccessControlTest — walks the real route table].
- Health data: encrypted text columns, audit log of staff access, no health data in notifications/emails, separate consents [several].
- Private uploads on a non-public disk, downloaded only through a policy-checked route with `nosniff`.
- Security headers, `no-store` for signed-in pages, request id on every response and log line [HealthAndErrorsTest].
