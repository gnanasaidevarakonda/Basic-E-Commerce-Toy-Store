# AGENTS.md — Master Project Context & AI Guidelines
# ToyVerse E-Commerce Backend (Vanilla PHP 8.2+ REST API)

> **NOTICE FOR ALL AI ASSISTANTS (Antigravity, Gemini, Claude, Copilot, ChatGPT):**
> Read this file completely before assisting in this workspace. It defines the project architecture, file structure, completed milestones, immediate next steps, and strict pedagogical pair-programming rules established with the user.

---

## 1. Pedagogical & Pair-Programming Rules (MANDATORY)

1. **User is Learning by Doing:** The user is building this entire enterprise-grade backend from scratch to deeply understand software engineering, web security, and DevOps.
2. **DO NOT Edit or Write Project Code Automatically:**
   - **Never** modify, overwrite, or create application code files (`src/`, `public/`, `database/`) behind the user's back.
   - Always output the code block clearly in chat and instruct the user to create/edit the file themselves.
   - The user must run syntax checks (`php -l`) and terminal commands with their own hands.
3. **Backend File-by-File & Frontend Bulk Efficiency:**
   - **Backend (`src/`):** Strictly ONE file at a time. Explain line-by-line, verify with `php -l`, and focus on deep architectural learning.
   - **Frontend (`public/`):** Provide bulk, ready-to-paste code blocks directly so the user can quickly update UI without interruption to their backend focus.
4. **Beginner-Friendly, In-Depth Explanations:**
   - Explain line-by-line what every piece of code does.
   - Explain PHP symbols (`$`, `::`, `->`, `=>`, `?array`, `??`, `mixed`).
   - Explain HTTP status codes (200, 201, 400, 401, 403, 404, 409, 422).
   - Explain design patterns and security rationale (e.g. constant-time password verify, defense against account enumeration, IDOR protection).
5. **Strict Zero-Framework Policy:**
   - Pure, modern vanilla **PHP 8.2+ only**.
   - **NO** Laravel, Symfony, Slim, or composer framework bloat.
   - Everything (router, autoloader, request parser, response emitter, JWT service) is hand-crafted from scratch.
6. **Automatic AI & Agent Files Synchronization:**
   - Only ask for permission/guidance regarding application project code (`src/`, `public/`, `database/`) so the user learns by doing.
   - Automatically update and maintain `AGENTS.md` and `.agents/` files as new milestones and architectural changes occur, keeping them 100% in sync for Git and multi-device portability without prompting the user.
7. **Active Git Workflow Mentorship (Enterprise Discipline):**
   - Proactively prompt the user when to create a feature branch (`git switch -c feat/...` or `fix/...`).
   - Prompt atomic commits at key logical milestones once syntax verification (`php -l`) passes.
   - Prompt clean merging into `main` and switching branches when features are stabilized, helping build intuitive professional Git muscle memory.

---

## 2. Infrastructure & Environment Specifications

- **Dual-Environment Architecture (Simultaneous DEV & STAGING):**
  - **DEV (Windows Host / `/mnt/c/`):**
    - `toystore_dev_web` (Nginx Alpine): Port `8085:80` (FastCGI configured with `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`, `absolute_redirect off;` to preserve host port 8085 on directory redirects).
    - `toystore_dev_app` (PHP 8.2-FPM): Custom Dockerfile from `docker/php/Dockerfile` (`pdo`, `pdo_mysql`).
    - `toystore_dev_db` (MySQL 8.0): Host port `3307:3306`.
    - Profile: `COMPOSE_PROJECT_NAME=toystore_dev`, `HOST_WEB_PORT=8085`, `HOST_DB_PORT=3307`.
  - **STAGING (Ubuntu WSL Native ext4 `~/staging/`):**
    - `toystore_staging_web` (Nginx Alpine): Port `8086:80`.
    - `toystore_staging_app` (PHP 8.2-FPM).
    - `toystore_staging_db` (MySQL 8.0): Host port `8088:3306`.
    - Profile: `COMPOSE_PROJECT_NAME=toystore_staging`, `HOST_WEB_PORT=8086`, `HOST_DB_PORT=8088`.
  - **Universal Docker Compose CLI:** Both environments run identical commands without `-p` flags (`docker compose up -d`, `down`, `up -d --build`, `down -v`).
- **Environment File Architecture (Twelve-Factor Single-.env Pattern):**
  - `.env.example`: Git-tracked master blueprint with working local defaults and self-documenting environment templates (Dev, Staging, Production).
  - `.env`: The ONLY active configuration file on any machine or server (strictly gitignored). Created locally via `cp .env.example .env`.
  - Zero clutter: `.env.development` and `.env.staging` are removed in favor of standard per-machine `.env` configuration.
- **Database Credentials (Internal):**
  - DB Host: `db`, DB Name: `toystore_db`, User: `toystore_user`, Password: `toystore_password`, Root Password: `root_password`.
- **Automated Database Auto-Loader:**
  - Mounted via `docker-compose.yml`: `./database/init.sql:/docker-entrypoint-initdb.d/01_init.sql:ro`.
  - Bootstraps all schemas and seed data automatically on `docker compose up -d --build`.
- **Tunneling & Staging Exposure:**
  - Automated Work-PC Script: `./scripts/tunnel.sh [8085|8086]` (zero datagram/ICMP warnings, auto-copies URL to Windows clipboard).
  - High-reliability HTTP/2 Cloudflare Tunnel: `cloudflared tunnel --protocol http2 --edge-ip-version auto --url http://localhost:8086`.
  - Kernel UDP buffer calibration: `net.core.rmem_max=2500000`, `net.core.wmem_max=2500000` (eliminates QUIC datagram drop errors).
  - Kernel unprivileged ping calibration: `net.ipv4.ping_group_range = 0 2147483647` (eliminates ICMP socket permission warnings).
- **Git & Remote Access:**
  - Secure SSH authentication via Ed25519 keypair (`id_ed25519`).
  - Remote Origin URL: `git@github.com:gnanasaidevarakonda/Basic-E-Commerce-Toy-Store.git`.
  - Development Asset Caching: Nginx sends `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` in `docker/nginx/default.conf` to prevent browser asset caching.

---

## 3. Seed Accounts & Credentials

Universal passwords across seed accounts:
- **Admin:** `admin123`
- **Managers:** `manager123`
- **Customers:** `customer123`

| ID | Name | Email | Password | Role | Status (`is_active`) | Testing Purpose |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **1** | `Store-Admin` | `admin@toystore.com` | `admin123` | `admin` | Active (`1`) | Super Admin backoffice access, manager creation/revocation, hard delete |
| **2** | `Jane Customer` | `customer@toystore.com` | `customer123` | `user` | Active (`1`) | Primary shopper, cart checkout, profile inspection |
| **3** | `Ruhani Sharma` | `ruhani@toystore.com` | `manager123` | `manager` | Active (`1`) | Operational inventory manager: catalog CRUD, stock/featured toggles |
| **4** | `Vikram Malhotra` | `vikram@toystore.com` | `manager123` | `manager` | Active (`1`) | Secondary manager: multi-manager directory inspection modal |
| **5** | `Alex Suspended` | `alex.suspended@toystore.com` | `manager123` | `manager` | Deactivated (`0`) | Tests red/inactive status dot and 403 login rejection for suspended staff |
| **6** | `John Shopper` | `john@example.com` | `customer123` | `user` | Active (`1`) | Secondary shopper: tests directory search filtering (`?q=john`) |
| **7** | `Priya Patel` | `priya@example.com` | `customer123` | `user` | Active (`1`) | International shopper format (`+91` phone): tests directory formatting |
| **8** | `Blocked User` | `blocked@example.com` | `customer123` | `user` | Deactivated (`0`) | Tests Customer Directory filter (`?status=inactive`) and auth rejection |

---

## 4. Current File Structure

```
basic_e-commerce/
├── .devcontainer/
│   └── devcontainer.json          # GitHub Codespaces cloud configuration
├── .agents/
│   └── rules/
│       ├── privacy.md             # Never read/print .env or private secrets
│       └── learning_rules.md      # One-file-at-a-time, pedagogical guidelines
├── database/
│   ├── init.sql                   # Master auto-loader SQL script (Schemas + Seeds)
│   ├── schemas/                   # Individual table schemas (users, categories, subcategories, products...)
│   └── seeds/                     # toys_seed.sql
├── docker/
│   ├── nginx/
│   │   └── default.conf           # Nginx server block with HTTP_AUTHORIZATION pass-through
│   └── php/
│       └── Dockerfile             # PHP 8.2-FPM with pdo_mysql
├── public/
│   ├── admin/
│   │   ├── index.html         # Responsive, modular HTML5 Backoffice Dashboard
│   │   ├── admin.css          # Posh futuristic 3D dark-mode UI with tactile controls
│   │   └── admin.js           # Multi-currency engine, structured specs parser, auth lifecycle
│   └── index.php              # Application entry point & route dispatcher
├── src/
│   ├── Controllers/
│   │   ├── AdminAuthController.php# Backoffice staff login with RBAC role checks
│   │   ├── AdminController.php    # Catalog CRUD, subcategory CRUD, smart deletion, dashboard metrics, staff CRUD
│   │   ├── AuthController.php     # Public register, login, and guarded profile
│   │   ├── CategoryController.php # List categories, show by slug, subcategories by category
│   │   └── ProductController.php  # Paginated/filtered catalog, single toy view
│   ├── Core/
│   │   ├── Autoloader.php         # PSR-4 autoloader mapping App\ to src/
│   │   ├── Database.php           # PDO Singleton connection wrapper
│   │   ├── Request.php            # HTTP method, URI, JSON body, query, Bearer token
│   │   ├── Response.php           # Standardized JSON envelopes (success, error, json)
│   │   └── Router.php             # GET/POST/PUT/DELETE, regex params, middleware execution
│   ├── Middlewares/
│   │   ├── AdminMiddleware.php    # Super Admin RBAC guard enforcing role === 'admin' (403)
│   │   ├── AuthMiddleware.php     # Cryptographic JWT bearer guard & user caching
│   │   ├── RateLimitMiddleware.php# Token-bucket sliding window rate limiter (429 Too Many Requests)
│   │   ├── SecurityMiddleware.php # Security headers & bot/scanner probe protection
│   │   └── StaffMiddleware.php    # Operational staff tier guard (manager + admin)
│   ├── Models/
│   │   ├── Category.php           # Categories queries
│   │   ├── Subcategory.php        # Subcategories queries by parent category and slug
│   │   ├── Product.php            # Filtered/paginated products with subcategories & age groups
│   │   └── User.php               # Bcrypt password hashing, findByEmail, findById, create
│   └── Services/
│       └── JWTService.php         # Stateless HMAC-SHA256 token generator & validator
├── scripts/
│   └── tunnel.sh              # Automated zero-error HTTP/2 Cloudflare Tunnel with clipboard sync
├── .env.example                   # Environment configuration template
├── .gitignore                     # Git ignore rules
├── AGENTS.md                      # THIS FILE — Universal project memory & rules
├── docker-compose.yml             # Docker multi-container stack
└── README.md                      # Project documentation
```

---

## 5. Live Active API Endpoints

| Method | Endpoint | Guard / Middleware | Purpose |
| :--- | :--- | :--- | :--- |
| `GET` | `/` | Public | Store welcome message and API directory |
| `GET` | `/api/v1/health` | Public | Uptime, PHP version, and database check |
| `GET` | `/api/v1/categories` | Public | List all active toy categories |
| `GET` | `/api/v1/categories/{slug}` | Public | Single category details |
| `GET` | `/api/v1/categories/{slug}/subcategories` | Public | List subcategories belonging to parent category |
| `GET` | `/api/v1/subcategories` | Public | List all active subcategories across store |
| `GET` | `/api/v1/subcategories/{slug}` | Public | Single subcategory details by slug |
| `GET` | `/api/v1/products` | Public | Paginated toys with filters (`?category=&subcategory=&age=&search=`) |
| `GET` | `/api/v1/products/{slug}` | Public | Single toy details with full image gallery |
| `POST`| `/api/v1/auth/register` | Public | Register new customer (`role: 'user'`, returns JWT) |
| `POST`| `/api/v1/auth/login` | Public | Authenticate customer credentials (returns JWT) |
| `GET` | `/api/v1/auth/profile` | `[AuthMiddleware]` | View authenticated user profile (Requires Bearer token) |
| `GET` | `/api/v1/auth/user` | `[AuthMiddleware]` | Alias for profile endpoint |
| `POST`| `/api/v1/admin/auth/login` | Public | Staff backoffice login (Managers & Super Admins) |
| `POST`| `/api/v1/admin/auth/logout` | `[AuthMiddleware]` | Staff backoffice logout (sets `is_online = 0`) |
| `GET` | `/api/v1/admin/dashboard` | `[AuthMiddleware, StaffMiddleware]` | Executive stats: products, active, low-stock, categories, customers & managers |
| `GET` | `/api/v1/admin/products` | `[AuthMiddleware, StaffMiddleware]` | Full inventory inspection (`?stock=low`) |
| `GET` | `/api/v1/admin/products/{id}`| `[AuthMiddleware, StaffMiddleware]` | Single product complete details (even if inactive) |
| `POST`| `/api/v1/admin/products` | `[AuthMiddleware, StaffMiddleware]` | Add new toy (auto-slug, SKU uniqueness, category & subcategory validation) |
| `PUT` | `/api/v1/admin/products/{id}`| `[AuthMiddleware, StaffMiddleware]` | Update toy prices, stock quantity, subcategory, and details |
| `PATCH`| `/api/v1/admin/products/{id}/status`| `[AuthMiddleware, StaffMiddleware]` | Staff 1-click toggle between Active and Inactive |
| `PATCH`| `/api/v1/admin/products/{id}/featured`| `[AuthMiddleware, StaffMiddleware]` | Staff 1-click toggle for Featured status |
| `GET` | `/api/v1/admin/subcategories`| `[AuthMiddleware, StaffMiddleware]` | Staff list all subcategories with inventory counts |
| `POST`| `/api/v1/admin/subcategories`| `[AuthMiddleware, StaffMiddleware]` | Staff create new subcategory under a category |
| `DELETE`| `/api/v1/admin/subcategories/{id}`| `[AuthMiddleware, AdminMiddleware]`| Super Admin delete subcategory (safely unlinks toys) |
| `DELETE`| `/api/v1/admin/products/{id}`| `[AuthMiddleware, AdminMiddleware]` | Super Admin smart delete (hard-delete or soft-delete) |
| `GET` | `/api/v1/admin/managers` | `[AuthMiddleware, AdminMiddleware]` | Super Admin lists all active inventory managers (with `is_online` status) |
| `GET` | `/api/v1/admin/managers/{id}` | `[AuthMiddleware, AdminMiddleware]` | Super Admin inspects single manager profile details |
| `POST`| `/api/v1/admin/managers` | `[AuthMiddleware, AdminMiddleware]` | Super Admin creates new manager account |
| `DELETE`| `/api/v1/admin/managers/{id}`| `[AuthMiddleware, AdminMiddleware]` | Super Admin removes/revokes a manager account |
| `PATCH`| `/api/v1/admin/managers/{id}/status`| `[AuthMiddleware, AdminMiddleware]` | Super Admin 1-click dynamic Grant / Revoke access toggle |
| `GET` | `/api/v1/admin/users` | `[AuthMiddleware, StaffMiddleware]` | Dedicated Customer Directory (`role = 'user'`, `?status=&q=`) |
| `GET` | `/api/v1/admin/users/{id}`| `[AuthMiddleware, StaffMiddleware]` | Single customer profile (Managers restricted to customers; 403 on staff/admins) |
| `POST`| `/api/v1/admin/categories` | `[AuthMiddleware, StaffMiddleware]` | Staff create main category (auto-slug, description, cover image) |
| `POST`| `/api/v1/admin/upload` | `[AuthMiddleware, StaffMiddleware]` | Secure multipart image upload for catalog & categories |
| `PATCH`| `/api/v1/admin/users/{id}/status`| `[AuthMiddleware, StaffMiddleware]` | Staff 1-click toggle between Active and Suspended for customer accounts |

---

## 6. Complete Project Roadmap & Milestone Tracker

### [DONE] Phase 1: Environment & Containerization
- Multi-container Docker Compose with Nginx, PHP 8.2-FPM, MySQL 8.0.
- Custom PSR-4 autoloader without Composer.
- Self-bootstrapping database auto-loader (`database/init.sql`).

### [DONE] Phase 2: Database Modeling & Seeding
- Full schema: `users`, `categories`, `products`, `product_images`, `orders`, `order_items`, `payments`.
- Realistic toy store seed data: 6 categories, 12 toys with photos, admin and customer accounts.

### [DONE] Phase 3: Core Engine & Public Catalog API
- Hand-crafted `Request`, `Response`, `Router` with regex path parsing (`{slug}`, `{id}`).
- Public category and product catalog endpoints with pagination, filters, and fulltext search.

### [DONE] Phase 4: Customer Authentication & Authorization
- `User.php` with Bcrypt password hashing (`PASSWORD_BCRYPT`).
- `JWTService.php` with HMAC-SHA256 signature verification.
- `AuthController.php` (`register`, `login`, `profile`).
- `AuthMiddleware.php` with `getBearerToken()` support across FastCGI/Nginx.

### [DONE] Phase 5: Admin Panel Authentication & Backoffice API (Tiered RBAC)
- Tiered RBAC schema in MySQL: `users.role` ENUM (`user`, `manager`, `admin`).
- `AdminAuthController.php`: Staff authentication gateway for managers & admins (blocks customers with 403).
- `StaffMiddleware.php`: Operational access tier allowing both `manager` and `admin`.
- `AdminMiddleware.php`: Executive access tier enforcing strictly `admin`.
- `AdminController.php`:
  - Executive dashboard metrics (`/api/v1/admin/dashboard`) with `total_customers` count
  - Inventory inspection with low-stock filtering (`/api/v1/admin/products`)
  - Staff toy creation (`POST`) and updates (`PUT`) with automated slugification and SKU validation
  - Super Admin smart product deletion (`DELETE`) with automatic soft-delete preservation for historical orders
  - Super Admin team management: list, create, and delete managers
  - Tiered customer directory (`/api/v1/admin/users`): Operational staff restricted strictly to customers (`role === 'user'`), Super Admin has global role filters (`?role=manager|user|admin`)
  - Single customer inspection (`/api/v1/admin/users/{id}`) with 403 guard blocking staff from inspecting peer staff/admins
- `postman/ToyVerse_API.postman_collection.json`: Comprehensive Postman collection with Bearer Auth, dynamic tokens, and operational vs executive folder segregation.
- `public/index.php`: Layered route registration with strict middleware sequences.

### [DONE] Phase 5.5: Production Security, Admin Panel Frontend & Smart Catalog Search
- `RateLimitMiddleware.php`: Token-bucket sliding window rate limiter (dynamic anti-brute-force on auth endpoints, 429 Too Many Requests).
- `SecurityMiddleware.php`: Security headers (X-Frame-Options, X-Content-Type-Options) & bot probe defense.
- Modular Backoffice Frontend (`public/admin/`):
  - `index.html`: Clean semantic markup, greeting splash screen, live status edit lock, structured e-commerce description forms.
  - `admin.css`: Posh 3D dark-mode aesthetics, tactile buttons, 3D switches, specs tables, laser loaders.
  - `admin.js`: Multi-currency engine (₹ INR, $ USD, € EUR), clean logout state reset, structured JSON specs serializer/parser.
- Smart Age Filter & Bracket Refactor:
  - Supports both bracket queries (`?age=9-12`) and single numeric ages (`?age=10`, `?age=11` mapped into containing bracket).
  - Age brackets normalized: `0-2`, `3-5`, `6-8`, `9-12`, `13-17` (teens), `18+` (adults/collectors).
  - Database schema & seed data migrated from `13-18` to `13-17`.
- Separation of Concerns for Staff vs Customers:
  - `/api/v1/admin/managers` & `/api/v1/admin/managers/{id}`: Strictly Super Admin only.
  - `/api/v1/admin/users`: Dedicated customer directory (`role = 'user'`), eliminating manager query overlap.
  - Updated Postman collection (`ToyVerse_API.postman_collection.json`) with `Get Single Manager Details`.
- Dual-Environment Architecture & Web Server Fixes:
  - Nginx directory canonical redirect fix (`absolute_redirect off;` in `docker/nginx/default.conf` preventing port-stripping on `/admin`).
  - Isolated multi-environment Docker orchestration: Windows DEV (`HOST_WEB_PORT=8085`, `HOST_DB_PORT=3307`, `toystore_dev`) and Ubuntu WSL STAGING (`HOST_WEB_PORT=8086`, `HOST_DB_PORT=8088`, `toystore_staging`).
  - Safe zero-leak environment configuration: Twelve-Factor pattern with Git-tracked `.env.example` blueprint with defaults, gitignored local `.env` per machine.
  - Tunneling calibration: High-performance HTTP/2 Cloudflare Tunnel with Linux kernel UDP buffer optimization (`net.core.rmem_max=2500000`).
  - Development Cache-Busting: Nginx `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` in `docker/nginx/default.conf` preventing stale browser asset caching.
- Catalog & Staff Operations Overhaul:
  - Category creation API (`POST /api/v1/admin/categories`) with automated slugification and database uniqueness validation.
  - Multipart image upload API (`POST /api/v1/admin/upload`) with strict `finfo` magic-byte MIME validation (JPG, PNG, WEBP, GIF) and unguessable random filenames.
  - Inline category cover image upload button in Backoffice Modal.
  - Dynamic Manager Access Toggle (`PATCH /api/v1/admin/managers/{id}/status`): Super Admin 1-click Grant (green) / Revoke (red) with real-time `is_online` reset.
  - Customer Status Toggle (`PATCH /api/v1/admin/users/{id}/status`): Staff 1-click active/suspended switch.
  - Fixed modal hierarchy and null-safe DOM rendering for Staff and Customer Inspect modals.
  - High-contrast Light Theme styles for product inspector, specifications table, and 3D hover scale animations.

---

### [CURRENT FOCUS] Phase 6: Cart & Atomic Checkout
- In-memory/session or database cart (`cart_items`).
- `Order.php`, `OrderItem.php`, `OrderController.php`.
- **Atomic MySQL Transactions:** Safe concurrency locking (`SELECT ... FOR UPDATE`) to prevent overselling inventory during simultaneous checkouts.

---

### [UPCOMING] Phase 7: Third-Party Integrations
- **Payment Gateway:** Stripe Sandbox API integration (`PaymentController.php`, webhooks).
- **Email Receipts:** Mailtrap / Resend / SMTP order confirmation emails.
- **SMS & Messaging:** Twilio SMS / WhatsApp order dispatch alerts.

---

### [UPCOMING] Phase 8 & 9: Production Security & Workflows
- Rate limiting middleware (prevent brute-force attacks on login/register).
- Bot protection & CORS hardening.
- Magic-byte validation for product image uploads.
- Background cron jobs (cleanup abandoned pending orders, daily stock audit).
