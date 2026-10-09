/**
 * ToyVerse Backoffice — Lightweight Admin Orchestrator
 */

const API_BASE = '/api/v1';
let allProducts = [];
let allCategories = [];
let allManagers = [];

// ============================================================================
// 1. MULTI-CURRENCY ENGINE (DEFAULT: INR)
// ============================================================================
const CURRENCIES = {
    INR: { symbol: '₹', rate: 1.0, name: 'INR (₹)' },
    USD: { symbol: '$', rate: 0.012, name: 'USD ($)' }, // ₹100 ~ $1.20
    EUR: { symbol: '€', rate: 0.011, name: 'EUR (€)' }  // ₹100 ~ €1.10
};
let activeCurrency = 'INR';

function formatPrice(amountInINR) {
    const num = parseFloat(amountInINR) || 0;
    const curr = CURRENCIES[activeCurrency];
    const converted = num * curr.rate;
    return `${curr.symbol}${converted.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function changeCurrency(newCurr) {
    activeCurrency = newCurr;
    document.querySelectorAll('.curr-sym').forEach(el => el.textContent = CURRENCIES[newCurr].symbol);
    renderProducts(allProducts);
    showToast(`Currency switched to ${CURRENCIES[newCurr].name}`, 'success');
}

// ============================================================================
// 2. INITIALIZATION & AUTHENTICATION
// ============================================================================
document.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('toyverse_token');
    if (!token) {
        showLogin();
    } else {
        showDashboard();
    }

    // Live search filter (with smart numeric age bracket matching)
    document.getElementById('search-input')?.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase().trim();
        const numTerm = parseInt(term, 10);
        const isNumeric = !isNaN(numTerm) && String(numTerm) === term;

        const filtered = allProducts.filter(p => {
            const matchesText = p.name.toLowerCase().includes(term) ||
                p.sku.toLowerCase().includes(term) ||
                (p.brand && p.brand.toLowerCase().includes(term));

            let matchesAge = false;
            if (p.age_group) {
                if (p.age_group.toLowerCase().includes(term)) {
                    matchesAge = true;
                } else if (isNumeric) {
                    if (p.age_group.includes('-')) {
                        const [min, max] = p.age_group.split('-').map(Number);
                        matchesAge = numTerm >= min && numTerm <= max;
                    } else if (p.age_group.endsWith('+')) {
                        const min = parseInt(p.age_group, 10);
                        matchesAge = numTerm >= min;
                    }
                }
            }

            return matchesText || matchesAge;
        });
        renderProducts(filtered);
    });
});

// BUG 1 FIX: Cleanly resets the login button and fields upon display
function showLogin() {
    document.getElementById('login-view').style.display = 'flex';
    document.getElementById('dashboard-view').style.display = 'none';

    const btn = document.getElementById('btn-login-submit');
    if (btn) {
        btn.disabled = false;
        btn.textContent = 'Login';
    }
}

function showDashboard() {
    document.getElementById('login-view').style.display = 'none';
    document.getElementById('dashboard-view').style.display = 'block';

    const user = JSON.parse(localStorage.getItem('toyverse_user') || '{}');
    document.getElementById('user-display').textContent = `${user.full_name || 'Staff'} (${(user.role || 'staff').toUpperCase()})`;

    // Hide staff management tab if caller is not an admin
    if (user.role !== 'admin') {
        const tab = document.getElementById('tab-btn-staff');
        if (tab) tab.style.display = 'none';
    }

    loadStats();
    loadCategories();
    loadProducts();
}

// LOGIN FORM SUBMISSION
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    const btn = document.getElementById('btn-login-submit');

    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-inline"></span> Authenticating...`;

    try {
        const res = await fetch(`${API_BASE}/admin/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();

        if (!res.ok) throw new Error(data.message || 'Login failed');

        localStorage.setItem('toyverse_token', data.data.token);
        localStorage.setItem('toyverse_user', JSON.stringify(data.data.user));

        triggerGreeting(data.data.user);
    } catch (err) {
        showToast(err.message, 'error');
        btn.disabled = false;
        btn.textContent = 'Login';
    }
});

// POSH GREETING SPLASH ANIMATION
function triggerGreeting(user) {
    const splash = document.getElementById('greeting-splash');
    const userEl = document.getElementById('greeting-user');
    const progress = document.getElementById('laser-progress');

    userEl.textContent = `Hello ${user.full_name || 'Admin'}! 👋`;
    splash.classList.add('active');

    setTimeout(() => { progress.style.width = '100%'; }, 50);

    setTimeout(() => {
        splash.classList.remove('active');
        showDashboard();
        showToast(`Welcome back, ${user.full_name}!`, 'success');
    }, 1300);
}

// LOGOUT ACTION (WITH BUTTON RESET)
document.getElementById('btn-logout').addEventListener('click', () => {
    localStorage.removeItem('toyverse_token');
    localStorage.removeItem('toyverse_user');
    showToast('Logged out cleanly', 'success');
    showLogin();
});

// TAB SWITCHER
function switchTab(tab) {
    document.querySelectorAll('.nav-tab-btn').forEach(b => b.classList.remove('active'));
    if (tab === 'catalog') {
        document.querySelectorAll('.nav-tab-btn')[0].classList.add('active');
        document.getElementById('section-catalog').style.display = 'block';
        document.getElementById('section-staff').style.display = 'none';
    } else if (tab === 'staff') {
        document.getElementById('tab-btn-staff').classList.add('active');
        document.getElementById('section-catalog').style.display = 'none';
        document.getElementById('section-staff').style.display = 'block';
        loadManagers();
    }
}

// ============================================================================
// 3. BACKEND DATA LOADERS
// ============================================================================
async function loadStats() {
    try {
        const token = localStorage.getItem('toyverse_token');
        const res = await fetch(`${API_BASE}/admin/dashboard`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const json = await res.json();
        if (json.success) {
            const d = json.data;
            document.getElementById('stat-total-products').textContent = d.total_products || 0;
            document.getElementById('stat-active-products').textContent = d.active_products || 0;
            document.getElementById('stat-low-stock').textContent = d.low_stock_alerts || 0;
            document.getElementById('stat-customers').textContent = d.total_customers || 0;
        }
    } catch (err) {
        console.error('Stats error:', err);
    }
}

async function loadCategories() {
    try {
        const res = await fetch(`${API_BASE}/categories`);
        const json = await res.json();
        if (json.success) {
            allCategories = json.data;
            const catSelect = document.getElementById('toy-category');
            const subParentSelect = document.getElementById('sub-parent-category');

            catSelect.innerHTML = '<option value="">Select Category...</option>';
            subParentSelect.innerHTML = '<option value="">Select Parent Category...</option>';

            allCategories.forEach(c => {
                catSelect.innerHTML += `<option value="${c.id}" data-slug="${c.slug}">${c.name}</option>`;
                subParentSelect.innerHTML += `<option value="${c.id}">${c.name}</option>`;
            });
        }
    } catch (err) {
        console.error('Categories error:', err);
    }
}

async function handleCategoryChange() {
    const catSelect = document.getElementById('toy-category');
    const subSelect = document.getElementById('toy-subcategory');
    const selectedOpt = catSelect.options[catSelect.selectedIndex];
    const slug = selectedOpt.getAttribute('data-slug');

    subSelect.innerHTML = '<option value="">Select Subcategory...</option>';
    if (!slug) return;

    try {
        const res = await fetch(`${API_BASE}/categories/${slug}/subcategories`);
        const json = await res.json();
        if (json.success && json.data.subcategories) {
            json.data.subcategories.forEach(s => {
                subSelect.innerHTML += `<option value="${s.id}">${s.name}</option>`;
            });
        }
    } catch (err) {
        console.error('Subcategories fetch error:', err);
    }
}

async function loadProducts(query = '') {
    const tbody = document.getElementById('products-table-body');
    tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 48px;"><div class="cyber-spinner"></div><p style="color: var(--text-secondary);">Connecting to MySQL...</p></td></tr>`;

    try {
        const token = localStorage.getItem('toyverse_token');
        const url = query ? `${API_BASE}/admin/products?${query}` : `${API_BASE}/admin/products`;
        const res = await fetch(url, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const json = await res.json();
        if (json.success) {
            allProducts = json.data.products || [];
            renderProducts(allProducts);
        }
    } catch (err) {
        showToast('Failed to load products from server', 'error');
    }
}

function renderProducts(products) {
    const tbody = document.getElementById('products-table-body');
    if (products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 48px; color: var(--text-muted);">No products found in catalog.</td></tr>`;
        return;
    }

    tbody.innerHTML = products.map(p => {
        const isLive = parseInt(p.is_active) === 1;
        return `
        <tr>
            <td>
                <div class="toy-cell">
                    <img src="${p.primary_image || 'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?w=100'}" class="toy-thumb" alt="Thumb">
                    <div>
                        <span style="font-weight: 700; color: #fff;">${escapeHtml(p.name)}</span>
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-family: 'JetBrains Mono'; display: block;">${p.sku} ${p.brand ? '• ' + p.brand : ''}</span>
                    </div>
                </div>
            </td>
            <td>
                <span class="badge badge-cat">${escapeHtml(p.category_name || 'General')}</span>
                ${p.subcategory_name ? `<span class="badge badge-sub" style="margin-left: 4px;">${escapeHtml(p.subcategory_name)}</span>` : ''}
            </td>
            <td>
                <span class="badge badge-age">${p.age_group}</span>
            </td>
            <td>
                <span class="price-text">${formatPrice(p.price)}</span>
            </td>
            <td>
                <span style="font-weight: 700; color: ${p.stock_quantity <= 5 ? '#f87171' : 'var(--text-primary)'}">
                    ${p.stock_quantity}
                </span>
            </td>
            <td>
                <label class="toggle-3d">
                    <input type="checkbox" ${parseInt(p.is_featured) === 1 ? 'checked' : ''} onchange="toggleFeatured(${p.id}, this)">
                    <span class="toggle-track"><span class="toggle-knob"></span></span>
                </label>
            </td>
            <td>
                <label class="toggle-3d">
                    <input type="checkbox" ${isLive ? 'checked' : ''} onchange="toggleStatus(${p.id}, this)">
                    <span class="toggle-track"><span class="toggle-knob"></span></span>
                </label>
            </td>
            <td style="text-align: right; white-space: nowrap;">
                <button class="btn-3d btn-3d-info" onclick="viewToyDetails(${p.id})">
                    👁️ View
                </button>
                
                <!-- SECURITY RULE: EDIT BUTTON UNLOCKS ONLY WHEN LIVE STATUS IS OFF -->
                ${isLive ? `
                    <button class="btn-3d btn-3d-secondary btn-locked" onclick="showLockedAlert()" title="Turn off Live Status to edit">
                        🔒 Edit
                    </button>
                ` : `
                    <button class="btn-3d btn-3d-primary" style="padding: 6px 12px; font-size: 0.75rem;" onclick="openEditToyModal(${p.id})">
                        ✏️ Edit
                    </button>
                `}

                <button class="btn-3d btn-3d-danger" onclick="deleteToy(${p.id}, '${escapeHtml(p.name)}')">
                    🗑️
                </button>
            </td>
        </tr>
    `}).join('');
}

function showLockedAlert() {
    showToast('⚠️ Product is currently LIVE! Turn off "Live Status" first to unlock editing.', 'error');
}

// ============================================================================
// 4. STRUCTURED DESCRIPTIONS & SPECIFICATIONS (ISSUE 2 RESOLUTION)
// ============================================================================

// Helper to convert form inputs into structured JSON string
function packageStructuredDescription() {
    const summary = document.getElementById('toy-desc-para').value.trim();
    const rawBullets = document.getElementById('toy-desc-bullets').value.trim();
    const rawSpecs = document.getElementById('toy-desc-specs').value.trim();

    const features = rawBullets ? rawBullets.split('\n').map(l => l.replace(/^[•\-\*]\s*/, '').trim()).filter(Boolean) : [];

    const specs = {};
    if (rawSpecs) {
        rawSpecs.split('\n').forEach(line => {
            const parts = line.split(':');
            if (parts.length >= 2) {
                const key = parts[0].trim();
                const val = parts.slice(1).join(':').trim();
                if (key && val) specs[key] = val;
            }
        });
    }

    // Save as clean structured JSON string
    return JSON.stringify({
        summary: summary,
        features: features,
        specs: specs
    });
}

// Helper to parse description (supports structured JSON & legacy plain text)
function parseStructuredDescription(descStr) {
    if (!descStr) return { summary: '', features: [], specs: {} };
    try {
        const parsed = JSON.parse(descStr);
        if (typeof parsed === 'object' && parsed !== null) {
            return {
                summary: parsed.summary || '',
                features: Array.isArray(parsed.features) ? parsed.features : [],
                specs: typeof parsed.specs === 'object' && parsed.specs !== null ? parsed.specs : {}
            };
        }
    } catch (e) {
        // Plain text fallback
    }
    return { summary: descStr, features: [], specs: {} };
}

// VIEW TOY DETAILS MODAL
async function viewToyDetails(id) {
    try {
        const token = localStorage.getItem('toyverse_token');
        const res = await fetch(`${API_BASE}/admin/products/${id}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        const p = json.data;
        document.getElementById('view-toy-sku').textContent = p.sku;
        document.getElementById('view-toy-name').textContent = p.name;
        document.getElementById('view-toy-image').src = (p.images && p.images[0]) ? p.images[0].image_url : 'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?w=400';
        document.getElementById('view-toy-category').textContent = p.category_name || 'General';
        document.getElementById('view-toy-subcategory').textContent = p.subcategory_name || 'None';
        document.getElementById('view-toy-subcategory').style.display = p.subcategory_name ? 'inline-block' : 'none';
        document.getElementById('view-toy-age').textContent = `Age: ${p.age_group}`;
        document.getElementById('view-toy-price').textContent = formatPrice(p.price);
        document.getElementById('view-toy-stock').textContent = `${p.stock_quantity} units available`;

        // Render structured description
        const parsed = parseStructuredDescription(p.description);
        const container = document.getElementById('view-toy-desc-container');

        let html = '';
        if (parsed.summary) {
            html += `<p style="margin-bottom: 12px; color: #e2e8f0; line-height: 1.5;">${escapeHtml(parsed.summary)}</p>`;
        }

        if (parsed.features && parsed.features.length > 0) {
            html += `<div style="font-size: 0.75rem; font-weight: 700; color: var(--mint-400); text-transform: uppercase; margin-top: 14px;">Key Highlights & Features:</div>`;
            html += `<ul class="features-list">` + parsed.features.map(f => `<li>${escapeHtml(f)}</li>`).join('') + `</ul>`;
        }

        const specKeys = Object.keys(parsed.specs || {});
        if (specKeys.length > 0) {
            html += `<div style="font-size: 0.75rem; font-weight: 700; color: var(--mint-400); text-transform: uppercase; margin-top: 14px;">Technical Specifications:</div>`;
            html += `<table class="specs-table"><tbody>` + specKeys.map(k => `
                <tr>
                    <td class="specs-key">${escapeHtml(k)}</td>
                    <td class="specs-val">${escapeHtml(parsed.specs[k])}</td>
                </tr>
            `).join('') + `</tbody></table>`;
        }

        if (!html) {
            html = '<p style="color: var(--text-muted);">No description or specifications recorded.</p>';
        }

        container.innerHTML = html;
        openModal('view-toy-modal');
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// OPEN EDIT MODAL
async function openEditToyModal(id) {
    try {
        const token = localStorage.getItem('toyverse_token');
        const res = await fetch(`${API_BASE}/admin/products/${id}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        const p = json.data;
        document.getElementById('toy-modal-title').textContent = `Edit Toy: ${p.name}`;
        document.getElementById('form-toy-id').value = p.id;
        document.getElementById('toy-name').value = p.name;
        document.getElementById('toy-sku').value = p.sku;
        document.getElementById('toy-brand').value = p.brand || 'ToyVerse';
        document.getElementById('toy-price').value = p.price;
        document.getElementById('toy-stock').value = p.stock_quantity;
        document.getElementById('toy-age').value = p.age_group;
        document.getElementById('toy-featured').checked = parseInt(p.is_featured) === 1;

        // Unpack structured description fields
        const parsed = parseStructuredDescription(p.description);
        document.getElementById('toy-desc-para').value = parsed.summary || '';
        document.getElementById('toy-desc-bullets').value = parsed.features.join('\n');
        document.getElementById('toy-desc-specs').value = Object.entries(parsed.specs || {}).map(([k, v]) => `${k}: ${v}`).join('\n');

        document.getElementById('toy-category').value = p.category_id;
        await handleCategoryChange();
        if (p.subcategory_id) {
            document.getElementById('toy-subcategory').value = p.subcategory_id;
        }

        openModal('toy-form-modal');
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function openAddToyModal() {
    document.getElementById('toy-modal-title').textContent = 'Add New Toy to Inventory';
    document.getElementById('form-toy-id').value = '';
    document.getElementById('toy-form').reset();
    openModal('toy-form-modal');
}

// SAVE TOY (ADD OR UPDATE)
document.getElementById('toy-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = localStorage.getItem('toyverse_token');
    const toyId = document.getElementById('form-toy-id').value;
    const btn = document.getElementById('btn-toy-submit');

    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-inline"></span> Saving to MySQL...`;

    const payload = {
        name: document.getElementById('toy-name').value.trim(),
        category_id: parseInt(document.getElementById('toy-category').value),
        subcategory_id: document.getElementById('toy-subcategory').value ? parseInt(document.getElementById('toy-subcategory').value) : null,
        sku: document.getElementById('toy-sku').value.trim().toUpperCase(),
        brand: document.getElementById('toy-brand').value.trim(),
        price: parseFloat(document.getElementById('toy-price').value),
        stock_quantity: parseInt(document.getElementById('toy-stock').value),
        age_group: document.getElementById('toy-age').value,
        is_featured: document.getElementById('toy-featured').checked ? 1 : 0,
        description: packageStructuredDescription() // Packs structured specs & highlights!
    };

    const url = toyId ? `${API_BASE}/admin/products/${toyId}` : `${API_BASE}/admin/products`;
    const method = toyId ? 'PUT' : 'POST';

    try {
        const res = await fetch(url, {
            method: method,
            headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Operation failed');

        showToast(toyId ? 'Toy updated successfully!' : 'Toy successfully added to catalog!', 'success');
        closeModal('toy-form-modal');
        loadProducts();
        loadStats();
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save to Catalog';
    }
});

// 1-CLICK TOGGLES
async function toggleStatus(id, checkbox) {
    try {
        const token = localStorage.getItem('toyverse_token');
        const res = await fetch(`${API_BASE}/admin/products/${id}/status`, {
            method: 'PATCH',
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message);

        showToast(`Status updated: ${json.data.is_active === 1 ? 'Live on Store' : 'Inactive (Editable)'}`, 'success');
        loadProducts(); // Re-render to update the edit button state!
        loadStats();
    } catch (err) {
        checkbox.checked = !checkbox.checked;
        showToast(err.message, 'error');
    }
}

async function toggleFeatured(id, checkbox) {
    try {
        const token = localStorage.getItem('toyverse_token');
        const res = await fetch(`${API_BASE}/admin/products/${id}/featured`, {
            method: 'PATCH',
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message);
        showToast(`Featured updated: ${json.data.is_featured === 1 ? 'Featured' : 'Standard'}`, 'success');
    } catch (err) {
        checkbox.checked = !checkbox.checked;
        showToast(err.message, 'error');
    }
}

async function deleteToy(id, name) {
    if (!confirm(`Are you sure you want to delete "${name}"?`)) return;

    try {
        const token = localStorage.getItem('toyverse_token');
        const res = await fetch(`${API_BASE}/admin/products/${id}`, {
            method: 'DELETE',
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message);
        showToast('Product deleted from inventory', 'success');
        loadProducts();
        loadStats();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// CREATE MAIN CATEGORY
document.getElementById('add-category-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = localStorage.getItem('toyverse_token');
    const btn = document.getElementById('btn-cat-submit');

    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-inline"></span> Creating...`;

    const payload = {
        name: document.getElementById('cat-name').value.trim(),
        image_url: document.getElementById('cat-image').value.trim(),
        description: document.getElementById('cat-desc').value.trim()
    };

    try {
        const res = await fetch(`${API_BASE}/admin/categories`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Failed to create category');

        showToast(`Category "${payload.name}" created successfully!`, 'success');
        closeModal('add-category-modal');
        e.target.reset();
        loadCategories();
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Create Category';
    }
});

// CREATE SUBCATEGORY
document.getElementById('add-sub-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = localStorage.getItem('toyverse_token');
    const btn = document.getElementById('btn-sub-submit');

    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-inline"></span> Creating...`;

    const payload = {
        category_id: parseInt(document.getElementById('sub-parent-category').value),
        name: document.getElementById('sub-name').value.trim(),
        description: document.getElementById('sub-description').value.trim(),
        is_active: 1
    };

    try {
        const res = await fetch(`${API_BASE}/admin/subcategories`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Failed to create subcategory');

        showToast(`Subcategory "${payload.name}" created!`, 'success');
        closeModal('add-sub-modal');
        e.target.reset();
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Create Subcategory';
    }
});

// ============================================================================
// 5. STAFF / MANAGERS DIRECTORY
// ============================================================================
async function loadManagers() {
    const tbody = document.getElementById('managers-table-body');
    tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 32px;"><div class="cyber-spinner"></div></td></tr>`;

    try {
        const token = localStorage.getItem('toyverse_token');
        const res = await fetch(`${API_BASE}/admin/managers`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const json = await res.json();
        if (json.success) {
            allManagers = json.data.managers || [];
            renderManagers(allManagers);
        }
    } catch (err) {
        showToast('Failed to load team managers', 'error');
    }
}

function renderManagers(managers) {
    const tbody = document.getElementById('managers-table-body');
    if (managers.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">No managers registered.</td></tr>`;
        return;
    }

    tbody.innerHTML = managers.map(m => `
        <tr>
            <td>
                <span style="font-weight: 700; color: #fff;">${escapeHtml(m.full_name)}</span>
                <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">${m.email}</span>
            </td>
            <td>${m.phone || 'N/A'}</td>
            <td><span class="badge badge-sub">${m.role.toUpperCase()}</span></td>
            <td>
                <span style="font-size: 0.8rem; font-weight: 700; color: ${parseInt(m.is_active) === 1 ? 'var(--mint-400)' : '#f87171'};">
                    ${parseInt(m.is_active) === 1 ? 'Active' : 'Suspended'}
                </span>
            </td>
            <td>${m.created_at ? m.created_at.split(' ')[0] : '-'}</td>
            <td style="text-align: right;">
                <button class="btn-3d btn-3d-danger" onclick="deleteManager(${m.id}, '${escapeHtml(m.full_name)}')">
                    Revoke Access
                </button>
            </td>
        </tr>
    `).join('');
}

document.getElementById('add-manager-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = localStorage.getItem('toyverse_token');

    const payload = {
        full_name: document.getElementById('mgr-name').value.trim(),
        email: document.getElementById('mgr-email').value.trim(),
        password: document.getElementById('mgr-pass').value,
        phone: document.getElementById('mgr-phone').value.trim()
    };

    try {
        const res = await fetch(`${API_BASE}/admin/managers`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Failed to onboard manager');

        showToast(`Manager ${payload.full_name} onboarded!`, 'success');
        closeModal('add-manager-modal');
        e.target.reset();
        loadManagers();
    } catch (err) {
        showToast(err.message, 'error');
    }
});

async function deleteManager(id, name) {
    if (!confirm(`Are you sure you want to revoke access for manager "${name}"?`)) return;

    try {
        const token = localStorage.getItem('toyverse_token');
        const res = await fetch(`${API_BASE}/admin/managers/${id}`, {
            method: 'DELETE',
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message);
        showToast('Manager access revoked', 'success');
        loadManagers();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ============================================================================
// 6. UI MODAL & TOAST HELPERS
// ============================================================================
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function filterCatalog(type) {
    if (type === 'low-stock') loadProducts('stock=low');
    else if (type === 'active') renderProducts(allProducts.filter(p => parseInt(p.is_active) === 1));
    else loadProducts();
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${type === 'success' ? '✅' : '⚠️'}</span><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}