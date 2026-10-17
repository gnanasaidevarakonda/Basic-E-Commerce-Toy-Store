# ToyVerse E-Commerce REST API — Seed Accounts

Universal test passwords:
- **Admin:** `admin123`
- **Managers:** `manager123`
- **Customers:** `customer123`

| ID | Full Name | Email | Password | Role | Status | Purpose |
|:---|:---|:---|:---|:---|:---|:---|
| **1** | Store-Admin | `admin@toystore.com` | `admin123` | `admin` | Active | Super Admin backoffice, manager CRUD |
| **2** | Jane Customer | `customer@toystore.com` | `customer123` | `user` | Active | Primary shopper (cart & checkout testing) |
| **3** | Ruhani Sharma | `ruhani@toystore.com` | `manager123` | `manager` | Active | Inventory management & catalog editing |
| **4** | Vikram Malhotra | `vikram@toystore.com` | `manager123` | `manager` | Active | Secondary manager directory testing |
| **5** | Alex Suspended | `alex.suspended@toystore.com` | `manager123` | `manager` | Suspended | Tests red status dot & 403 login rejection |
| **6** | John Shopper | `john@example.com` | `customer123` | `user` | Active | Tests search filtering (`?q=john`) |
| **7** | Priya Patel | `priya@example.com` | `customer123` | `user` | Active | Tests international phone format (`+91`) |
| **8** | Blocked User | `blocked@example.com` | `customer123` | `user` | Suspended | Tests inactive filter (`?status=inactive`) |