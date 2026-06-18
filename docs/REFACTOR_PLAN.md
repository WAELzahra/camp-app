# camp-app — SOLID / Architecture Refactor Plan

Goal: move validation out of controllers, slim fat controllers via a service/action
layer, introduce queues & jobs, apply dependency injection, and remove overlapping
controllers/models.

**Rules of engagement**
- One phase per PR/branch. Don't mix concerns.
- Refactor behind tests where possible — no behavior change unless explicitly noted.
- Use `ReservationsCentreController` as the reference implementation, then replicate.

Legend: `[ ]` todo · `[~]` in progress · `[x]` done

---

## Phase 0 — Foundations & safety net  ✅ DONE (2026-06-18)
- [x] Confirm `php artisan test` runs; baseline recorded: **10 passed / 62 failed**
- [x] Isolate the test database — see "Test setup" below (tests must NEVER touch `camp_app`)
- [x] Fix the stale `UserFactory` (matched real schema, added role states) + seed roles centrally
- [x] Add feature tests around the **reservation create/confirm/cancel/show** flows
      (`tests/Feature/Reservation/CentreReservationTest.php`, 11 tests)
- [x] Repair + green the **payment** suite (wallet, manual, admin review, overdue-cancel)
- [x] Remove obsolete Breeze **web** auth/profile tests (routes are disabled — `auth.php`
      is commented out in `routes/web.php`; the SPA uses `/api` + Sanctum)
- [x] Add `pint.json` (laravel preset). Run `vendor/bin/pint` (or `--test` in CI)
- **Result: `php artisan test` → 76 passed (145 assertions), 0 failures.**
- [ ] (Optional follow-up) Add CI workflow that creates `camp_app_test` and runs `pint --test` + `artisan test`
- [ ] (Optional follow-up) API auth feature tests (`/api/login`, register, password reset) to
      replace the deleted web-auth coverage

### Test setup (how to run the safety net)
```bash
# 1. Create the isolated MySQL test database once (SQLite is NOT viable —
#    migrations use MySQL-only spatial/JSON column types):
mysql -u root -e "CREATE DATABASE camp_app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Run the suite (phpunit.xml points DB_DATABASE → camp_app_test):
php artisan test
```
- `phpunit.xml` pins `DB_CONNECTION=mysql` + `DB_DATABASE=camp_app_test` so the dev DB is never wiped.
- Roles are seeded once (committed, outside the per-test transaction) via `$seed`/`$seeder` in
  `tests/TestCase.php` — the `users.role_id` FK depends on them.
- `platform_settings` rows are seeded by migration, so test helpers must **upsert** (not insert).

### Findings surfaced during Phase 0 (review in later phases)
- **Manual-payment confirm does not branch on full vs deposit**: `AdminPaymentReviewController::confirm`
  sends every initial centre payment to `'pending'` regardless of `payment_option`. A deposit
  arguably should move to a balance-pending state. Tests now capture *current* behaviour; revisit
  when extracting the payment service (Phase 3/4).

---

## Phase 1 — Queues & async (fast win, low risk)
- [ ] Switch `QUEUE_CONNECTION` from `sync` → `redis` (predis already installed) or `database`
- [ ] If using `database`: `php artisan queue:table` + migrate
- [ ] Add a queue worker to the deployment (supervisor / `php artisan queue:work`)
- [ ] Make **all 44 mailables** implement `ShouldQueue` (only 3 do today:
      `NotificationMail`, `ReservationConfirmedToFournisseur`, `ReservationRejectedToUser`)
- [ ] Create `app/Jobs/` and move heavy work off the request:
  - [ ] `GenerateInvoicePdfJob` (barryvdh/laravel-dompdf)
  - [ ] `ExportReportJob` (maatwebsite/excel)
  - [ ] `SyncZoneDataJob` — external Foursquare / Nominatim calls (`ZoneSearchService`)
  - [ ] `AutoCancelUnpaidReservationJob` — manual-payment balance-due expiry
- [ ] Add `failed_jobs` handling + alerting

---

## Phase 2 — Form Requests (move validation out of controllers)
For each controller: create `App\Http\Requests\...Request`, move `rules()` + `messages()` +
`authorize()`, replace inline `validate()`/`Validator::make()`, use `$request->validated()`.

**Tier A — complex / high-value (do first, with the service extraction in Phase 3)**
- [ ] Reservation/ReservationsCentreController (store, update, centerModify, reject, partialAccept)
- [ ] Reservation/ReservationEventController
- [ ] Reservation/ReservationMaterielleController
- [ ] Reservation/ManualPaymentController
- [ ] Reservation/WalletRechargeController
- [ ] Admin/AdminPaymentController
- [ ] Admin/AdminPaymentReviewController
- [ ] Admin/AdminReservationsController
- [ ] Admin/AdminEventReservationController
- [ ] PromoCodeController
- [ ] Admin/AdminCustomCommissionController

**Tier B — domain CRUD**
- [ ] Event/EventController, Event/EventServiceController
- [ ] Annonce/AnnonceController, Admin/AdminAnnonceController
- [ ] Materielle/MaterielleController, Admin/AdminMaterielleController
- [ ] Boutique/BoutiqueController
- [ ] Center/CenterEquipmentController, Center/CenterServiceController, Center/ProfileCentreController
- [ ] zonecamping/CampingZonesController, CampingCentresController, PublicCampingController, ZonePolygonController
- [ ] Admin/CampingCentreController, Admin/CampingZoneController
- [ ] Admin/CancellationPolicyController, Admin/ServiceCategoryController, Admin/AdminEquipmentController
- [ ] Admin/AdminExpenseController, ExpenseController
- [ ] CentreClaimController, Organizer/OrganizerSupplierController
- [ ] profile/ProfileController (split — see Phase 3)
- [ ] BankInfoController, Api/CenterServiceApiController

**Tier C — misc / social / auth**
- [ ] Comment/CommentController, Feedback/FeedbackController, Admin/AdminFeedbackController
- [ ] Message/ConversationController, Message/MessageController
- [ ] Notification/NotificationController, Admin/AdminNotificationController
- [ ] Report/ReportController, Contact/ContactController, PopupController
- [ ] Favorites/FavoriteController, Signal/SignalementZoneController, Admin/SignaleZoneController
- [ ] Admin/AdminUserController, Admin/AdminUserTrackController, Admin/AdminSettingsController
- [ ] Auth/* (RegisteredUserController, SocialAuthController, NewPasswordController,
      PasswordController, PasswordResetLinkController, ConfirmablePasswordController,
      VerifyEmailController) — most already partially done

---

## Phase 3 — Service / Action layer (slim the fat controllers, SRP)
Target: no controller method > ~50 lines; business logic lives in services/actions.

- [ ] Define convention: thin controllers call a Service or single-purpose Action
- [ ] **ReservationsCentreController** (1996→thin): extract
  - [ ] `CentreReservationService::create()` (pricing + promo + commission + escrow + payment)
  - [ ] `...::update()`, `::confirm()`, `::reject()`, `::partialAccept()`, `::centerModify()`
- [ ] **Consolidate overlapping reservation controllers** into shared services so
      `UnifiedReservationController` reuses the same logic (Centre/Event/Materielle)
- [ ] **profile/ProfileController (1996 lines)** — split by role/concern
      (ProfileCampeur / ProfileCentre / ProfileFournisseur / ProfileGroupe / Guide)
- [ ] **AdminReservationsController (1436)** + **AdminUserController (1363)** — extract services
- [ ] Move side-effects (multi-email sends) into **Events + queued Listeners**
      (you have app/Events but only 1 listener) — e.g. `ReservationCreated` event
      fans out to camper/owner/fournisseur mail listeners
- [ ] Move the 23 controllers' raw `DB::table/select/raw` calls into models/repositories

---

## Phase 4 — Dependency Injection & abstractions (D + O in SOLID)
- [ ] Convert static services to injectable instances:
  - [ ] CommissionService, ManualPaymentService, MaterielPricingService,
        CancellationPolicyService, PaymentReferenceService, EmailVerificationService,
        PasswordResetService, CentreClaimApprovalService
- [ ] Inject services via constructor in controllers (model after `ZoneSearchService`)
- [ ] Create `app/Contracts/` interfaces for swappable pieces:
  - [ ] `PaymentGateway` contract → `FlouciGateway`, `WalletGateway`, `ManualGateway`
  - [ ] `GeocodingService` contract → `NominatimService` (+ Foursquare)
- [ ] Bind interfaces → implementations in `AppServiceProvider` (currently empty)
- [ ] Expand repository pattern beyond `ZoneCampingRepository` where it adds value
      (reservations, payments) — optional, only where queries are reused

---

## Phase 5 — De-duplication / naming cleanup
- [ ] Merge `Admin/AdminEventController` ↔ `Admin/AdminEventsController`
- [ ] Merge `Favoris` ↔ `Favorites` controllers
- [ ] Reconcile models `Feedback` ↔ `Feedbacks`, `Favoris` ↔ `Favorite`
- [ ] Audit `Reservations_*` vs singular model naming for consistency
- [ ] Remove dead/unused controllers after consolidation

---

## Phase 6 — Hardening
- [ ] Add Policies/Gates; move `authorize()` logic out of controllers into Form Requests/Policies
- [ ] Use API Resources for JSON responses (consistent shape, no model leakage)
- [ ] Standardize error responses (custom exceptions + Handler rendering)
- [ ] Run `pint`, ensure tests green, update CLAUDE.md with the new conventions

---

## Progress tracker
| Phase | Status | PR |
|---|---|---|
| 0 Foundations | [ ] | |
| 1 Queues & Jobs | [ ] | |
| 2 Form Requests | [ ] | |
| 3 Service layer | [ ] | |
| 4 DI & contracts | [ ] | |
| 5 De-duplication | [ ] | |
| 6 Hardening | [ ] | |
