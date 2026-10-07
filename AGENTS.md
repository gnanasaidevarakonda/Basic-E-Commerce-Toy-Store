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
3. **Strictly ONE File at a Time:**
   - Never dump multiple files in a single turn. Proceed file-by-file.
   - Ensure the current file is saved, verified with `php -l`, and explained before introducing the next file.
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

---

## 2. Infrastructure & Environment Specifications

- **Docker Compose Architecture (3 Containers):**
  - `toystore_web` (Nginx Alpine): Port `8080:80`. Live volume mount `./:/var/www/html`. FastCGI configured with `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`.
  - `toystore_app` (PHP 8.2-FPM): Custom Dockerfile from `docker/php/Dockerfile`. Contains `pdo`, `pdo_mysql`.
  - `toystore_db` (MySQL 8.0): Host port `3307:3306`.
- **Database Credentials:**
  - DB Name: `toystore_db`
  - User: `toystore_user`
  - Password: `toystore_password`
  - Root Password: `root_password`
- **Automated Database Auto-Loader:**
  - Mounted via `docker-compose.yml`: `./database/init.sql:/docker-entrypoint-initdb.d/01_init.sql:ro`.
  - When cloned on ANY new computer, running `docker compose up -d --build` automatically creates all tables and seeds all data with **zero manual SQL commands**.
- **Tunneling & Testing:**
  - WSL configured with SSH tunnel alias: `ssh -o StrictHostKeyChecking=accept-new -R 80:localhost:8080 nokey@localhost.run`.

---

## 3. Seed Accounts & Credentials

- **Admin Account:**
  - Email: `admin@toystore.com`
  - Password: `admin123`
  - Role: `admin` (Has full administrative backoffice access)
- **Customer Account:**
  - Email: `customer@toystore.com`
  - Password: `customer123`
  - Role: `user` (Standard customer)

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
│   ├── schemas/                   # Individual table schemas
│   └── seeds/                     # toys_seed.sql
├── docker/
│   ├── nginx/
│   │   └── default.conf           # Nginx server block with HTTP_AUTHORIZATION pass-through
│   └── php/
│       └── Dockerfile             # PHP 8.2-FPM with pdo_mysql
├── public/
│   └── index.php                  # Application entry point & route dispatcher
├── src/
│   ├── Controllers/
│   │   ├── AdminAuthController.php# Backoffice staff login with RBAC role checks
│   │   ├── AdminController.php    # Catalog CRUD, smart deletion, dashboard metrics, staff CRUD
│   │   ├── AuthController.php     # Public register, login, and guarded profile
│   │   ├── CategoryController.php # List categories, show by slug
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
│   │   ├── Product.php            # Filtered/paginated products with fulltext search
│   │   └── User.php               # Bcrypt password hashing, findByEmail, findById, create
│   └── Services/
│       └── JWTService.php         # Stateless HMAC-SHA256 token generator & validator
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
| `GET` | `/api/v1/products` | Public | Paginated toys with filters (`?category=&age=&search=`) |
| `GET` | `/api/v1/products/{slug}` | Public | Single toy details with full image gallery |
| `POST`| `/api/v1/auth/register` | Public | Register new customer (`role: 'user'`, returns JWT) |
| `POST`| `/api/v1/auth/login` | Public | Authenticate customer credentials (returns JWT) |
| `GET` | `/api/v1/auth/profile` | `[AuthMiddleware]` | View authenticated user profile (Requires Bearer token) |
| `GET` | `/api/v1/auth/user` | `[AuthMiddleware]` | Alias for profile endpoint |
| `POST`| `/api/v1/admin/auth/login` | Public | Staff backoffice login (Managers & Super Admins) |
| `GET` | `/api/v1/admin/dashboard` | `[AuthMiddleware, StaffMiddleware]` | Executive stats, inventory & low-stock alerts |
| `GET` | `/api/v1/admin/products` | `[AuthMiddleware, StaffMiddleware]` | Full inventory inspection (`?stock=low`) |
| `GET` | `/api/v1/admin/products/{id}`| `[AuthMiddleware, StaffMiddleware]` | Single product complete details (even if inactive) |
| `POST`| `/api/v1/admin/products` | `[AuthMiddleware, StaffMiddleware]` | Add new toy (auto-slug, SKU uniqueness, category check) |
| `PUT` | `/api/v1/admin/products/{id}`| `[AuthMiddleware, StaffMiddleware]` | Update toy prices, stock quantity, and details |
| `PATCH`| `/api/v1/admin/products/{id}/status`| `[AuthMiddleware, StaffMiddleware]` | Staff 1-click toggle between Active and Inactive |
| `PATCH`| `/api/v1/admin/products/{id}/featured`| `[AuthMiddleware, StaffMiddleware]` | Staff 1-click toggle for Featured status |
| `DELETE`| `/api/v1/admin/products/{id}`| `[AuthMiddleware, AdminMiddleware]` | Super Admin smart delete (hard-delete or soft-delete) |
| `GET` | `/api/v1/admin/managers` | `[AuthMiddleware, AdminMiddleware]` | Super Admin lists all active inventory managers |
| `POST`| `/api/v1/admin/managers` | `[AuthMiddleware, AdminMiddleware]` | Super Admin creates new manager account |
| `DELETE`| `/api/v1/admin/managers/{id}`| `[AuthMiddleware, AdminMiddleware]` | Super Admin removes/revokes a manager account |
| `GET` | `/api/v1/admin/users` | `[AuthMiddleware, StaffMiddleware]` | Staff & Admins inspect all registered users/customers (`?role=&status=&q=`) |
| `GET` | `/api/v1/admin/users/{id}`| `[AuthMiddleware, StaffMiddleware]` | Staff & Admins inspect single user/customer details |

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
  - Executive dashboard metrics (`/api/v1/admin/dashboard`)
  - Inventory inspection with low-stock filtering (`/api/v1/admin/products`)
  - Staff toy creation (`POST`) and updates (`PUT`) with automated slugification and SKU validation
  - Super Admin smart product deletion (`DELETE`) with automatic soft-delete preservation for historical orders
  - Super Admin team management: list, create, and delete managers
- `public/index.php`: Layered route registration with strict middleware sequences.

### [CURRENT FOCUS] Phase 5.5: Production Security, Rate Limiting & Admin Panel Frontend
- `RateLimitMiddleware.php`: Token-bucket sliding window rate limiter (dynamic anti-brute-force on auth endpoints, 429 Too Many Requests).
- `SecurityMiddleware.php`: Security headers (X-Frame-Options, X-Content-Type-Options) & bot probe defense.
- `public/admin/index.html`: Responsive, vanilla HTML5/CSS/JS Backoffice Dashboard for Managers and Super Admins.

---

### [UPCOMING] Phase 6: Cart & Atomic Checkout
- In-memory/session or database cart.
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
