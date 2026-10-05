// estado da tela
const state = {
    user: null,
    page: 'home',
    categories: [],
    users: [],
    tickets: [],
    notificationTimer: null,
    reportsTimer: null,
};

// menus do portal
const navItems = [
    ['home', 'Início', 'home'],
    ['tickets', 'Chamados', 'ticket'],
    ['mine', 'Meus Chamados', 'headset'],
    ['dashboard', 'Dashboard', 'chart'],
    ['knowledge', 'Base de Conhecimento', 'book'],
    ['notices', 'Avisos', 'bell'],
    ['content', 'Gerenciar Conteúdo', 'content'],
    ['reports', 'Relatórios', 'report'],
    ['settings', 'Configurações', 'settings'],
];

const $ = (selector) => document.querySelector(selector);

const icons = {
    home: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>',
    ticket: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 7 2 2 3-3M5 13l2 2 3-3M5 19l2 2 3-3M13 8h7M13 14h7M13 20h7"/></svg>',
    headset: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13v-2a8 8 0 0 1 16 0v2"/><path d="M4 13h3v6H5a1 1 0 0 1-1-1zM20 13h-3v6h2a1 1 0 0 0 1-1z"/></svg>',
    chart: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V5M4 20h16M8 16v-5M12 16V7M16 16V4M20 16v-3"/></svg>',
    book: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h6a4 4 0 0 1 4 4v12H9a4 4 0 0 0-4 4zM19 4h-4v16h4a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Z"/></svg>',
    bell: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 17H6l1.5-2v-4a4.5 4.5 0 0 1 9 0v4z"/><path d="M10 20h4"/></svg>',
    announcement: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 9 16-5-1 11-15 5zM4 9l5 5M9 14l2 5"/></svg>',
    content: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h6a4 4 0 0 1 4 4v12H9a4 4 0 0 0-4 4zM19 4h-4v16h4a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Z"/></svg>',
    edit: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5v14h16M7 8h6M7 12h5M7 16h8"/><path d="m15 9 3-3 2 2-3 3z"/></svg>',
    report: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v5h4M9 17v-4M12 17v-7M15 17v-2"/></svg>',
    settings: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.4 3.5h5.2l.6 2a6.9 6.9 0 0 1 1.7 1l2-.7 2.6 4.5-1.5 1.4a7.2 7.2 0 0 1 0 2.1l1.5 1.4-2.6 4.5-2-.7a6.9 6.9 0 0 1-1.7 1l-.6 2H9.4l-.6-2a6.9 6.9 0 0 1-1.7-1l-2 .7-2.6-4.5L4 13.8a7.2 7.2 0 0 1 0-2.1L2.5 10.3 5.1 5.8l2 .7a6.9 6.9 0 0 1 1.7-1z"/><circle cx="12" cy="12" r="3"/></svg>',
    user: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>',
    search: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>',
    arrow: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>',
    trash: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M9 7l1-3h4l1 3M6 7l1 14h10l1-14"/></svg>',
    download: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M8 11l4 4 4-4M5 20h14"/></svg>',
};

function icon(name) {
    return icons[name] || '';
}

function escapeHtml(value = '') {
    return String(value).replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[char]));
}

function avatar(user, className = '') {
    if (user?.avatar_path && user?.id) {
        const version = encodeURIComponent(String(user.avatar_path).split('?v=')[1] || '1');
        return `<span class="avatar ${className}"><img src="/api/users/${Number(user.id)}/avatar?v=${version}" alt="Foto de ${escapeHtml(user.name || 'usuário')}"></span>`;
    }
    const initial = (user?.name || '?').trim().charAt(0).toUpperCase();
    return `<span class="avatar ${className}">${escapeHtml(initial)}</span>`;
}

function statusBadge(status) {
    const className = status === 'Aberto' ? 'badge-open' : status === 'Em Atendimento' ? 'badge-work' : 'badge-done';
    return `<span class="badge ${className}">${escapeHtml(status)}</span>`;
}

function formatDate(value, withTime = false) {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T'));
    return withTime ? date.toLocaleString('pt-BR') : date.toLocaleDateString('pt-BR');
}

// chamadas da API
const apiCache = new Map();
const apiPending = new Map();
const GET_CACHE_TTL = 8000;

async function api(url, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const useCache = method === 'GET' && options.cache !== false;
    const cacheKey = url;

    if (useCache) {
        const cached = apiCache.get(cacheKey);
        if (cached && (Date.now() - cached.time) < GET_CACHE_TTL) {
            return cached.data;
        }
        if (apiPending.has(cacheKey)) {
            return apiPending.get(cacheKey);
        }
    }

    const request = (async () => {
        const headers = options.body instanceof FormData
            ? { ...(options.headers || {}) }
            : { 'Content-Type': 'application/json', ...(options.headers || {}) };

        let response;
        try {
            response = await fetch(url, { ...options, headers });
        } catch {
            throw new Error('Não foi possível conectar ao ConectaDesk. Verifique se o servidor está em execução.');
        }

        let data = {};
        try {
            data = await response.json();
        } catch {
            data = {};
        }

        if (!response.ok) {
            throw new Error(data.error || 'Não foi possível concluir a operação.');
        }

        if (useCache) {
            apiCache.set(cacheKey, { data, time: Date.now() });
        } else if (method !== 'GET') {
            apiCache.clear();
        }

        return data;
    })();

    if (useCache) {
        apiPending.set(cacheKey, request);
    }

    try {
        return await request;
    } finally {
        if (useCache) {
            apiPending.delete(cacheKey);
        }
    }
}

// sessão
async function init() {
    try {
        const response = await api('/api/auth/me');
        if (response.user) {
            state.user = response.user;
            showApp();
        } else {
            showLogin();
        }
    } catch {
        showLogin();
    }
}

function showLogin() {
    $('#loginView').classList.remove('hidden');
    $('#app').classList.add('hidden');
}

function showApp() {
    $('#loginView').classList.add('hidden');
    $('#app').classList.remove('hidden');
    updateUserChrome();
    $('#notifIcon').innerHTML = icon('bell');
    renderNavigation();
    go('home');
    refreshNotificationDot();
    startRealtimeUpdates();
}

function updateUserChrome() {
    const user = state.user;
    $('#miniAvatar').innerHTML = avatar(user);
    $('#miniUser').textContent = user.name;
    $('#profileTopBtn').innerHTML = `${avatar(user, 'top-avatar')}<span>${escapeHtml(user.name.split(' ')[0])}</span>`;
}

// menu lateral
function renderNavigation() {
    const isAdmin = state.user?.role === 'admin';
    const visibleItems = navItems.filter((item) => {
        const page = item[0];
        return isAdmin || !['dashboard', 'content', 'reports'].includes(page);
    });

    const nav = $('#nav');
    if (!nav) return;

    nav.innerHTML = visibleItems.map((item) => {
        const page = item[0];
        const label = item[1];
        const iconName = item[2];

        return `
            <button type="button" data-page="${page}" aria-label="${label}">
                <span class="nav-icon">${icon(iconName)}</span>
                <span>${label}</span>
            </button>
        `;
    }).join('');

    nav.querySelectorAll('button').forEach((button) => {
        button.addEventListener('click', () => go(button.dataset.page));
    });
}

function go(page) {
    if (state.reportsTimer && page !== 'reports') {
        clearInterval(state.reportsTimer);
        state.reportsTimer = null;
    }

    state.page = page;
    document.querySelectorAll('#nav button').forEach((button) => {
        button.classList.toggle('active', button.dataset.page === page);
    });
    $('#pageTitle').textContent = navItems.find(([key]) => key === page)?.[1] || 'Início';

    const pages = { home, tickets, mine, dashboard, knowledge, notices, content, reports, settings };
    Promise.resolve((pages[page] || home)()).catch((error) => {
        $('#content').innerHTML = `<div class="card page-error"><strong>Não foi possível carregar esta área.</strong><p>${escapeHtml(error.message)}</p><button class="secondary" onclick="go('${page}')">Tentar novamente</button></div>`;
    });
}

function openModal() {
    $('#modal').classList.remove('hidden');
}

function closeModal() {
    $('#modal').classList.add('hidden');
}

function openRegister() {
    $('#modalContent').innerHTML = `
        <div class="modal-title">
            <span class="eyebrow">Acesso</span>
            <h2>Criar conta</h2>
            <p class="muted">Cadastre seus dados para acessar o ConectaDesk.</p>
        </div>
        <form id="registerForm" class="form-grid">
            <div class="field full"><label>Nome completo<input name="name" required maxlength="120" autocomplete="name"></label></div>
            <div class="field"><label>Usuário<input name="username" required maxlength="80" autocomplete="username"></label></div>
            <div class="field"><label>E-mail<input name="email" type="email" required maxlength="180" autocomplete="email"></label></div>
            <div class="field"><label>E-mail secundário<input name="secondary_email" type="email" maxlength="180"></label></div>
            <div class="field"><label>Telefone<input name="phone" maxlength="30" autocomplete="tel"></label></div>
            <div class="field full"><label>Departamento<input name="department" maxlength="100"></label></div>
            <div class="field"><label>Senha<input name="password" type="password" required minlength="8" autocomplete="new-password"></label></div>
            <div class="field"><label>Confirmar senha<input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"></label></div>
            <div id="registerError" class="error full" role="alert"></div>
            <div class="actions full">
                <button class="secondary" type="button" onclick="closeModal()">Cancelar</button>
                <button class="primary" type="submit">Criar conta</button>
            </div>
        </form>`;

    openModal();
    $('#registerForm').onsubmit = async (event) => {
        event.preventDefault();
        $('#registerError').textContent = '';
        const form = event.target;

        if (form.password.value !== form.password_confirmation.value) {
            $('#registerError').textContent = 'As senhas não conferem.';
            return;
        }

        try {
            const response = await api('/api/auth/register', {
                method: 'POST',
                body: JSON.stringify({
                    name: form.name.value.trim(),
                    username: form.username.value.trim(),
                    email: form.email.value.trim(),
                    secondary_email: form.secondary_email.value.trim(),
                    phone: form.phone.value.trim(),
                    department: form.department.value.trim(),
                    password: form.password.value,
                }),
            });

            state.user = response.user;
            closeModal();
            showApp();
        } catch (exception) {
            $('#registerError').textContent = exception.message;
        }
    };
}

// perfil
function openProfile() {
    const user = state.user;
    $('#modalContent').innerHTML = `
        <div class="profile-modal">
            <div class="profile-modal-head">
                ${avatar(user, 'profile-modal-avatar')}
                <div>
                    <span class="eyebrow">Meu perfil</span>
                    <h2>${escapeHtml(user.name)}</h2>
                    <p>${escapeHtml(user.department || 'Departamento não informado')}</p>
                </div>
            </div>
            <form id="profileForm" class="form-grid">
                <div class="field"><label>Nome<input name="name" value="${escapeHtml(user.name)}" required></label></div>
                <div class="field"><label>E-mail<input name="email" type="email" value="${escapeHtml(user.email)}" required></label></div>
                <div class="field"><label>E-mail secundário<input name="secondary_email" type="email" value="${escapeHtml(user.secondary_email || '')}"></label></div>
                <div class="field"><label>Telefone<input name="phone" value="${escapeHtml(user.phone || '')}"></label></div>
                <div class="field full"><label>Departamento<input name="department" value="${escapeHtml(user.department || '')}"></label></div>
                <div class="field full"><label class="avatar-upload">Alterar foto<input id="profileAvatarInput" type="file" accept="image/png,image/jpeg,image/webp" hidden></label></div>
                <div id="profileError" class="error full"></div>
                <div class="actions full"><button type="button" class="secondary" onclick="closeModal()">Cancelar</button><button class="primary">Salvar alterações</button></div>
            </form>
        </div>`;

    openModal();

    $('#profileForm').onsubmit = async (event) => {
        event.preventDefault();
        const error = $('#profileError');
        error.textContent = '';
        try {
            const result = await api('/api/profile', {
                method: 'PUT',
                body: JSON.stringify(Object.fromEntries(new FormData(event.target))),
            });
            state.user = result.user;
            updateUserChrome();
            closeModal();
        } catch (exception) {
            error.textContent = exception.message;
        }
    };

    $('#profileAvatarInput').onchange = async (event) => {
        const file = event.target.files[0];
        if (!file) return;
        try {
            const formData = new FormData();
            formData.append('avatar', file);
            const result = await api('/api/profile/avatar', { method: 'POST', body: formData });
            state.user = result.user;
            updateUserChrome();
            openProfile();
        } catch (exception) {
            $('#profileError').textContent = exception.message;
        }
    };
}

async function loadCategories() {
    if (!state.categories.length) {
        state.categories = (await api('/api/categories')).items;
    }
}

async function loadUsers() {
    state.users = (await api('/api/users')).items;
}

function renderUserHome() {
    return `
        <section class="learning-hero">
            <div class="learning-copy">
                <span class="eyebrow light">Portal de suporte</span>
                <h3>Olá, ${escapeHtml(state.user.name.split(' ')[0])}!</h3>
                <p>Encontre respostas, acompanhe seus chamados e tenha mais autonomia para resolver situações recorrentes de TI.</p>
                <button class="primary light-button" onclick="go('knowledge')">Explorar materiais ${icon('arrow')}</button>
            </div>
            <div class="learning-visual" aria-hidden="true">
                <div class="visual-glow"></div>
                <div class="visual-window"><span>CD</span><div></div><div></div><div></div></div>
                <div class="visual-person">◕</div>
            </div>
        </section>

        <div class="section-title page-section-title">
            <div><span class="eyebrow">Navegação rápida</span><h3>O que você precisa hoje?</h3></div>
        </div>
        <section class="quick-grid">
            <button class="quick-card" onclick="go('tickets')"><span class="quick-icon">${icon('ticket')}</span><b>Chamados</b><span>Consulte a fila e acompanhe as movimentações das solicitações.</span></button>
            <button class="quick-card" onclick="go('mine')"><span class="quick-icon">${icon('headset')}</span><b>Meus Chamados</b><span>Veja suas solicitações e adicione novas informações ao atendimento.</span></button>
            <button class="quick-card" onclick="go('knowledge')"><span class="quick-icon">${icon('book')}</span><b>Materiais de apoio</b><span>Leia tutoriais preparados para resolver dúvidas recorrentes.</span></button>
            <button class="quick-card" onclick="go('notices')"><span class="quick-icon">${icon('bell')}</span><b>Avisos</b><span>Confira comunicados publicados pela equipe de atendimento.</span></button>
        </section>

        <section class="benefit-grid">
            <article><strong>Autonomia</strong><p>Conteúdos preparados para orientar o usuário antes de abrir uma nova solicitação.</p></article>
            <article><strong>Histórico</strong><p>Chamados e interações ficam organizados para facilitar o acompanhamento.</p></article>
            <article><strong>Comunicação</strong><p>Notificações internas destacam novas movimentações, materiais e avisos.</p></article>
        </section>`;
}

async function home() {
    if (state.user.role !== 'admin') {
        $('#content').innerHTML = renderUserHome();
        return;
    }

    const data = await api('/api/dashboard');
    const max = Math.max(...data.categories.map((item) => Number(item.total)), 1);

    $('#content').innerHTML = `
        <section class="admin-hero">
            <div><span class="eyebrow light">Visão geral</span><h3>Central de atendimento</h3><p>Transforme o movimento dos chamados em uma visão clara do Service Desk.</p></div>
            <button class="primary light-button" onclick="go('dashboard')">Abrir dashboard ${icon('arrow')}</button>
        </section>
        <section class="grid cards">
            ${metricCard('Total de chamados', data.total, 'Solicitações registradas')}
            ${metricCard('Abertos', data.status['Aberto'] || 0, 'Aguardando atendimento')}
            ${metricCard('Em atendimento', data.status['Em Atendimento'] || 0, 'Em acompanhamento')}
            ${metricCard('Concluídos', data.status['Concluído'] || 0, 'Soluções finalizadas')}
        </section>
        <section class="grid two home-dashboard-grid">
            <article class="card"><div class="section-title"><div><span class="eyebrow">Categorias</span><h3>Demandas de TI</h3></div><button class="link-btn" onclick="go('dashboard')">Detalhar</button></div><div class="bars">${data.categories.slice(0, 8).map((item) => `<div class="bar-row"><span>${escapeHtml(item.name)}</span><div><i style="width:${Math.max(3, Number(item.total) / max * 100)}%"></i></div><b>${item.total}</b></div>`).join('')}</div></article>
            <article class="card"><div class="section-title"><div><span class="eyebrow">Conhecimento</span><h3>Interações</h3></div></div><div class="mini-stat-grid"><div><strong>${data.content?.opened || 0}</strong><span>aberturas</span></div><div><strong>${data.content?.liked || 0}</strong><span>curtidas</span></div><div><strong>${data.average_rating || 0}</strong><span>média</span></div></div></article>
        </section>`;
}

function metricCard(label, value, helper) {
    return `<article class="metric-card"><span>${label}</span><strong>${value}</strong><small>${helper}</small></article>`;
}

function ticketTable(items, showRequester = true) {
    return `
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Código</th><th>Título</th><th>Categoria</th>${showRequester ? '<th>Solicitante</th>' : ''}<th>Abertura</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    ${items.map((ticket) => `
                        <tr>
                            <td><b>${escapeHtml(ticket.code)}</b></td>
                            <td><b>${escapeHtml(ticket.title)}</b><small class="muted">Severidade ${'●'.repeat(Number(ticket.severity))}</small></td>
                            <td>${escapeHtml(ticket.category)}</td>
                            ${showRequester ? `<td>${escapeHtml(ticket.requester)}</td>` : ''}
                            <td>${formatDate(ticket.created_at)}</td>
                            <td>${statusBadge(ticket.status)}</td>
                            <td><button class="link-btn" onclick="openTicket(${ticket.id})">Detalhes ${icon('arrow')}</button></td>
                        </tr>`).join('') || `<tr><td colspan="${showRequester ? 7 : 6}"><div class="empty">Nenhum chamado encontrado.</div></td></tr>`}
                </tbody>
            </table>
        </div>`;
}

// chamados
async function tickets() {
    const [_, data] = await Promise.all([loadCategories(), api('/api/tickets')]);
    state.tickets = data.items;

    $('#content').innerHTML = `
        <div class="page-intro">
            <div><span class="eyebrow">Atendimento</span><h3>Chamados</h3><p>Consulte a fila, filtre as solicitações e abra os detalhes de cada atendimento.</p></div>
            <button class="primary" onclick="openTicketModal()">+ Novo chamado</button>
        </div>
        <div class="filter-panel card">
            <div class="filter-heading"><div><b>Filtrar chamados</b><span>Use categoria, status, período ou texto livre.</span></div></div>
            <div class="toolbar">
                <label class="filter-input">${icon('search')}<input id="search" placeholder="Título, código ou solicitante"></label>
                <select id="statusF"><option value="">Todos os status</option><option>Aberto</option><option>Em Atendimento</option><option>Concluído</option></select>
                <select id="catF"><option value="">Todas as categorias</option>${state.categories.map((category) => `<option value="${category.id}">${escapeHtml(category.name)}</option>`).join('')}</select>
                <input id="fromF" type="date" aria-label="A partir de" title="A partir de">
                <button class="secondary" onclick="applyFilters()">Aplicar filtros</button>
                <button class="secondary" onclick="clearTicketFilters()">Limpar</button>
            </div>
        </div>
        <div id="ticketTableArea">${ticketTable(state.tickets, true)}</div>`;
}

async function applyFilters() {
    const params = new URLSearchParams();
    const fields = {
        q: $('#search').value.trim(),
        status: $('#statusF').value,
        category_id: $('#catF').value,
        from: $('#fromF').value,
    };
    Object.entries(fields).forEach(([key, value]) => { if (value) params.set(key, value); });

    const data = await api(`/api/tickets?${params.toString()}`);
    state.tickets = data.items;
    $('#ticketTableArea').innerHTML = ticketTable(state.tickets, true);
}

function clearTicketFilters() {
    ['search', 'statusF', 'catF', 'fromF'].forEach((id) => { if ($('#' + id)) $('#' + id).value = ''; });
    applyFilters();
}

// meus chamados
async function mine() {
    const [_, data] = await Promise.all([loadCategories(), api('/api/tickets')]);
    const items = data.items.filter((ticket) => Number(ticket.requester_id) === Number(state.user.id));

    $('#content').innerHTML = `
        <div class="page-intro">
            <div><span class="eyebrow">Pessoal</span><h3>Meus Chamados</h3><p>Acompanhe suas solicitações e adicione informações quando necessário.</p></div>
            <button class="primary" onclick="openTicketModal()">+ Novo chamado</button>
        </div>
        <div id="ticketTableArea">${ticketTable(items, false)}</div>`;
}

function openTicketModal() {
    loadCategories().then(() => {
        $('#modalContent').innerHTML = `
            <div class="modal-title"><span class="eyebrow">Nova solicitação</span><h2>Abrir chamado</h2><p>Descreva o problema e selecione a categoria de TI mais adequada.</p></div>
            <form id="ticketForm" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="field full"><label>Título<input name="title" required maxlength="180" placeholder="Ex.: Computador sem acesso à rede"></label></div>
                    <div class="field full"><label>Descrição<textarea name="description" required placeholder="Explique o que aconteceu e o que você precisa."></textarea></label></div>
                    <div class="field"><label>Categoria<select name="category_id" required>${state.categories.map((category) => `<option value="${category.id}">${escapeHtml(category.name)} · nível ${category.severity}</option>`).join('')}</select></label></div>
                    <div class="field"><label>Foto/anexo<input name="attachment" type="file" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG ou WEBP até 5 MB.</small></label></div>
                </div>
                <div id="ticketError" class="error"></div>
                <div class="actions"><button type="button" class="secondary" onclick="closeModal()">Cancelar</button><button class="primary">Criar chamado</button></div>
            </form>`;
        openModal();

        $('#ticketForm').onsubmit = async (event) => {
            event.preventDefault();
            $('#ticketError').textContent = '';
            try {
                await api('/api/tickets', { method: 'POST', body: new FormData(event.target) });
                closeModal();
                go(state.user.role === 'admin' ? 'tickets' : 'mine');
            } catch (exception) {
                $('#ticketError').textContent = exception.message;
            }
        };
    });
}

async function openTicket(id) {
    const ticket = await api(`/api/tickets/${id}`);
    const canComment = state.user.role === 'admin' || Number(ticket.requester_id) === Number(state.user.id);
    const canRate = state.user.role === 'user' && Number(ticket.requester_id) === Number(state.user.id) && ticket.status === 'Concluído';

    $('#modalContent').innerHTML = `
        <article class="ticket-detail">
            <div class="ticket-detail-head"><div><span class="eyebrow">${escapeHtml(ticket.code)}</span><h2>${escapeHtml(ticket.title)}</h2></div>${statusBadge(ticket.status)}</div>
            <div class="detail-meta"><span>${escapeHtml(ticket.category)}</span><span>Solicitante: ${escapeHtml(ticket.requester)}</span><span>Aberto em ${formatDate(ticket.created_at, true)}</span></div>
            <div class="detail-description">${escapeHtml(ticket.description).replace(/\n/g, '<br>')}</div>
            ${Number(ticket.requester_id) === Number(state.user.id) && ticket.status === 'Aberto' ? `<div class="actions"><button class="secondary" type="button" onclick="editTicket(${id})">Editar chamado</button><button class="secondary" type="button" onclick="deleteTicket(${id})">Excluir chamado</button></div>` : ''}
            ${ticket.attachments?.length ? `<div class="attachments"><h4>Fotos anexadas</h4>${ticket.attachments.map((attachment) => { const attachmentUrl = `/api/tickets/attachments/${Number(attachment.id)}`; return `<a href="${attachmentUrl}" target="_blank"><img src="${attachmentUrl}" alt="${escapeHtml(attachment.original_name)}"><span>${escapeHtml(attachment.user_name)}</span></a>`; }).join('')}</div>` : ''}
            <div class="detail-section"><div class="section-title"><h4>Interações</h4><span class="muted">Histórico do atendimento</span></div><div class="comments">${ticket.comments?.map((comment) => `<div class="comment"><div class="comment-head"><b>${escapeHtml(comment.user_name)}</b><small>${formatDate(comment.created_at, true)}</small></div><p>${escapeHtml(comment.body)}</p></div>`).join('') || '<div class="empty">Nenhuma interação registrada.</div>'}</div></div>
            ${canComment ? `<form id="commentForm" class="comment-form" enctype="multipart/form-data"><textarea name="body" placeholder="Adicionar uma nova informação..." required></textarea><input type="file" name="attachment" accept="image/png,image/jpeg,image/webp"><button class="secondary">Adicionar informação</button></form>` : ''}
            ${state.user.role === 'admin' ? `<div class="admin-ticket-actions"><label>Status<select id="ticketStatus"><option ${ticket.status === 'Aberto' ? 'selected' : ''}>Aberto</option><option ${ticket.status === 'Em Atendimento' ? 'selected' : ''}>Em Atendimento</option><option ${ticket.status === 'Concluído' ? 'selected' : ''}>Concluído</option></select></label><label>Categoria<select id="ticketCategory">${state.categories.map((category) => `<option value="${category.id}" ${Number(category.id) === Number(ticket.category_id) ? 'selected' : ''}>${escapeHtml(category.name)}</option>`).join('')}</select></label><button class="primary" onclick="updateTicket(${id})">Salvar atendimento</button></div>` : ''}
            ${canRate && !ticket.rating ? `<div class="rating-box"><h4>Avalie o atendimento</h4><select id="ticketRating"><option value="5">5 — Excelente</option><option value="4">4 — Muito bom</option><option value="3">3 — Bom</option><option value="2">2 — Regular</option><option value="1">1 — Precisa melhorar</option></select><button class="secondary" onclick="rateTicket(${id})">Enviar avaliação</button></div>` : ''}
        </article>`;

    openModal();

    if ($('#commentForm')) {
        $('#commentForm').onsubmit = async (event) => {
            event.preventDefault();
            try {
                await api(`/api/tickets/${id}/comments`, { method: 'POST', body: new FormData(event.target) });
                await openTicket(id);
            } catch (exception) {
                alert(exception.message);
            }
        };
    }
}

async function editTicket(id) {
    const ticket = await api(`/api/tickets/${id}`);
    if (ticket.status !== 'Aberto' || Number(ticket.requester_id) !== Number(state.user.id)) return;

    $('#modalContent').innerHTML = `
        <div class="modal-title"><span class="eyebrow">Solicitação</span><h2>Editar chamado</h2><p>Altere os dados enquanto o chamado estiver aberto.</p></div>
        <form id="editTicketForm">
            <div class="form-grid">
                <div class="field full"><label>Título<input name="title" required maxlength="180" value="${escapeHtml(ticket.title)}"></label></div>
                <div class="field full"><label>Descrição<textarea name="description" required>${escapeHtml(ticket.description)}</textarea></label></div>
                <div class="field"><label>Categoria<select name="category_id" required>${state.categories.map((category) => `<option value="${category.id}" ${Number(category.id) === Number(ticket.category_id) ? 'selected' : ''}>${escapeHtml(category.name)}</option>`).join('')}</select></label></div>
            </div>
            <div id="editTicketError" class="error"></div>
            <div class="actions"><button type="button" class="secondary" onclick="openTicket(${id})">Cancelar</button><button class="primary">Salvar alterações</button></div>
        </form>`;

    $('#editTicketForm').onsubmit = async (event) => {
        event.preventDefault();
        const form = new FormData(event.target);
        try {
            await api(`/api/tickets/${id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    title: form.get('title'),
                    description: form.get('description'),
                    category_id: Number(form.get('category_id')),
                }),
            });
            await openTicket(id);
        } catch (exception) {
            $('#editTicketError').textContent = exception.message;
        }
    };
}

async function deleteTicket(id) {
    if (!confirm('Excluir este chamado aberto?')) return;
    try {
        await api(`/api/tickets/${id}`, { method: 'DELETE' });
        closeModal();
        go('mine');
    } catch (exception) {
        alert(exception.message);
    }
}

async function updateTicket(id) {
    try {
        await api(`/api/tickets/${id}`, {
            method: 'PUT',
            body: JSON.stringify({
                status: $('#ticketStatus').value,
                category_id: Number($('#ticketCategory').value),
            }),
        });
        await openTicket(id);
        await refreshTicketList();
    } catch (exception) {
        alert(exception.message);
    }
}

async function refreshTicketList() {
    if (!['tickets', 'mine'].includes(state.page)) return;
    const data = await api('/api/tickets');
    state.tickets = data.items;
    const area = $('#ticketTableArea');
    if (!area) return;
    const items = state.page === 'mine'
        ? data.items.filter((ticket) => Number(ticket.requester_id) === Number(state.user.id))
        : data.items;
    area.innerHTML = ticketTable(items, state.page === 'tickets');
}

async function rateTicket(id) {
    try {
        await api(`/api/tickets/${id}/rate`, {
            method: 'POST',
            body: JSON.stringify({ rating: Number($('#ticketRating').value) }),
        });
        await openTicket(id);
    } catch (exception) {
        alert(exception.message);
    }
}

// indicadores
async function dashboard() {
    if (state.user.role !== 'admin') return home();

    const data = await api('/api/dashboard');
    const maxCategory = Math.max(...data.categories.map((item) => Number(item.total)), 1);
    const maxDaily = Math.max(...data.daily.map((item) => Number(item.total)), 1);

    $('#content').innerHTML = `
        <div class="page-intro">
            <div><span class="eyebrow">Dados</span><h3>Dashboard</h3><p>Uma visão analítica dos chamados, categorias, usuários e conhecimento.</p></div>
            <div class="export-actions"><button class="secondary" type="button" onclick="exportDashboard('pdf')">${icon('download')} PDF</button><button class="secondary" type="button" onclick="exportDashboard('excel')">${icon('download')} Excel</button></div>
        </div>
        <section class="grid cards dashboard-kpis">
            ${metricCard('Total', data.total, 'Chamados registrados')}
            ${metricCard('Abertos', data.status['Aberto'] || 0, 'Aguardando atendimento')}
            ${metricCard('Em atendimento', data.status['Em Atendimento'] || 0, 'Em acompanhamento')}
            ${metricCard('Concluídos', data.status['Concluído'] || 0, 'Soluções finalizadas')}
        </section>
        <section class="grid two dashboard-main-charts">
            <article class="card chart-card"><div class="section-title"><div><span class="eyebrow">Distribuição</span><h3>Chamados por categoria</h3></div></div><div class="bars big-bars">${data.categories.map((item) => `<div class="bar-row"><span>${escapeHtml(item.name)}</span><div><i style="width:${Math.max(2, Number(item.total) / maxCategory * 100)}%"></i></div><b>${item.total}</b></div>`).join('')}</div></article>
            <article class="card"><div class="section-title"><div><span class="eyebrow">Situação</span><h3>Status dos chamados</h3></div></div><div class="status-chart"><div class="donut donut-status" style="--open:${data.total ? data.status['Aberto'] / data.total * 100 : 0};--work:${data.total ? (data.status['Aberto'] + data.status['Em Atendimento']) / data.total * 100 : 0}"><span>${data.total}</span></div><div class="legend"><span><i class="dot open"></i>Aberto <b>${data.status['Aberto'] || 0}</b></span><span><i class="dot work"></i>Em atendimento <b>${data.status['Em Atendimento'] || 0}</b></span><span><i class="dot done"></i>Concluído <b>${data.status['Concluído'] || 0}</b></span></div></div></article>
        </section>
        <section class="grid two">
            <article class="card"><div class="section-title"><div><span class="eyebrow">Últimos 7 dias</span><h3>Volume de abertura</h3></div></div><div class="daily-chart">${data.daily.map((item) => `<div class="daily-column"><span>${item.total}</span><i style="height:${Math.max(8, Number(item.total) / maxDaily * 100)}%"></i><small>${new Date(item.day + 'T00:00:00').toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '')}</small></div>`).join('')}</div></article>
            <article class="card"><div class="section-title"><div><span class="eyebrow">Conhecimento</span><h3>Interações</h3></div></div><div class="mini-stat-grid large"><div><strong>${data.content?.opened || 0}</strong><span>aberturas</span></div><div><strong>${data.content?.liked || 0}</strong><span>curtidas</span></div><div><strong>${data.average_rating || 0}</strong><span>média dos chamados</span></div></div></article>
        </section>
        <section class="card"><div class="section-title"><div><span class="eyebrow">Usuários</span><h3>Relação de chamados por usuário</h3></div></div><div class="table-wrap"><table class="table compact"><thead><tr><th>Usuário</th><th>Chamados</th><th>Concluídos</th><th>Conclusão</th></tr></thead><tbody>${data.users.map((user) => { const percent = Number(user.total) ? Math.round(Number(user.solved) / Number(user.total) * 100) : 0; return `<tr><td>${escapeHtml(user.name)}</td><td>${user.total}</td><td>${user.solved}</td><td><div class="progress"><i style="width:${percent}%"></i></div><small>${percent}%</small></td></tr>`; }).join('')}</tbody></table></div></section>`;
}

// materiais
async function knowledge() {
    const data = await api('/api/contents');
    const materials = data.items.filter((item) => item.type === 'material');

    $('#content').innerHTML = `
        <div class="page-intro"><div><span class="eyebrow">Aprendizado</span><h3>Base de Conhecimento</h3><p>Materiais preparados para ajudar você a resolver situações recorrentes de TI.</p></div></div>
        <section class="knowledge-grid">${materials.map((item) => `
            <article class="knowledge-card">
                <div class="knowledge-cover"><span>${icon('book')}</span><small>${escapeHtml(item.category || 'Geral')}</small></div>
                <div class="knowledge-body"><span class="badge badge-work">Material de apoio</span><h3>${escapeHtml(item.title)}</h3><p>${escapeHtml(item.body).slice(0, 160)}${item.body.length > 160 ? '…' : ''}</p><div class="knowledge-meta"><span>${item.opened ? 'Lido' : 'Não lido'}</span><span>${item.liked ? 'Curtido' : 'Ainda não curtido'}</span></div><div class="actions"><button class="secondary" onclick="readContent(${item.id})">Ler</button>${state.user.role === 'user' ? `<button class="secondary" onclick="likeContent(${item.id})">${item.liked ? '♥ Curtido' : '♡ Curtir'}</button>` : ''}</div></div>
            </article>`).join('') || '<div class="card empty">Nenhum material disponível.</div>'}</section>`;
}

async function readContent(id) {
    const content = await api(`/api/contents/${id}/detail`);
    if (state.user.role === 'user') {
        await api(`/api/contents/${id}/interact`, { method: 'POST', body: JSON.stringify({ action: 'read' }) });
    }

    $('#modalContent').innerHTML = `
        <article class="content-reader"><span class="badge badge-work">Material de apoio</span><h2>${escapeHtml(content.title)}</h2><small class="muted">${escapeHtml(content.category || 'Geral')} · por ${escapeHtml(content.author)}</small><div class="reader-body">${escapeHtml(content.body).replace(/\n/g, '<br>')}</div><div class="reader-actions"><a class="secondary" href="/api/contents/${id}/pdf">${icon('download')} Baixar em PDF</a>${state.user.role === 'user' ? `<button class="secondary" onclick="likeContent(${id})">♡ Curtir</button>` : ''}</div>
        <section class="content-comments">
            <div class="section-title"><div><span class="eyebrow">Interações</span><h4>Comentários</h4></div></div>
            <div class="comments">${content.comments?.map((comment) => `<article class="comment"><div class="comment-head"><div><b>${escapeHtml(comment.user_name)}</b><small>Comentário no material</small></div><time>${formatDate(comment.created_at, true)}</time></div><p>${escapeHtml(comment.body)}</p></article>`).join('') || '<div class="empty">Nenhum comentário ainda.</div>'}</div>
            ${state.user.role === 'user' ? `<form id="contentComment"><textarea name="body" required placeholder="Escreva um comentário sobre este material..."></textarea><div class="actions"><button class="primary">Comentar</button></div></form>` : ''}
        </section>
        </article>`;

    openModal();
    if (!$('#contentComment')) return;
    $('#contentComment').onsubmit = async (event) => {
        event.preventDefault();
        try {
            await api(`/api/contents/${id}/comments`, { method: 'POST', body: JSON.stringify({ body: event.target.body.value }) });
            await readContent(id);
        } catch (exception) {
            alert(exception.message);
        }
    };
}

async function likeContent(id) {
    try {
        await api(`/api/contents/${id}/interact`, { method: 'POST', body: JSON.stringify({ action: 'like' }) });
        closeModal();
        await knowledge();
    } catch (exception) {
        alert(exception.message);
    }
}

// notificacoes
async function loadNotifications() {
    const data = await api('/api/notifications', { cache: false });
    return data.items || [];
}

async function refreshNotificationDot() {
    try {
        const items = await loadNotifications();
        const unread = items.some((item) => !item.read_at);
        $('#notifDot').classList.toggle('hidden', !unread);
    } catch {
        $('#notifDot').classList.add('hidden');
    }
}

function closeNotifications() {
    $('#notificationDrawer').classList.add('hidden');
    $('#drawerOverlay').classList.add('hidden');
}

async function openNotifications() {
    try {
        const items = await loadNotifications();
        const list = $('#notificationList');
        if (!items.length) {
            list.innerHTML = '<div class="empty">Nenhuma notificação disponível.</div>';
        } else {
            list.innerHTML = items.map((item) => `
                <button class="notification-item ${item.read_at ? 'read' : ''}" type="button" data-notification-id="${item.id}">
                    <span class="notification-icon">${icon('bell')}</span>
                    <span><b>${escapeHtml(item.title)}</b><small>${escapeHtml(item.body)}</small><time>${formatDate(item.created_at, true)}</time></span>
                </button>`).join('');

            list.querySelectorAll('[data-notification-id]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const id = Number(button.dataset.notificationId);
                    if (!button.classList.contains('read')) {
                        try {
                            await api(`/api/notifications/${id}/read`, { method: 'POST' });
                            button.classList.add('read');
                            await refreshNotificationDot();
                        } catch {
                            return;
                        }
                    }
                });
            });
        }

        $('#notificationDrawer').classList.remove('hidden');
        $('#drawerOverlay').classList.remove('hidden');
    } catch (exception) {
        $('#notificationList').innerHTML = `<div class="empty">${escapeHtml(exception.message)}</div>`;
        $('#notificationDrawer').classList.remove('hidden');
        $('#drawerOverlay').classList.remove('hidden');
    }
}

function startRealtimeUpdates() {
    if (state.notificationTimer) {
        clearInterval(state.notificationTimer);
    }
    state.notificationTimer = window.setInterval(refreshNotificationDot, 30000);
}

// avisos
async function notices() {
    const data = await api('/api/contents');
    const noticesList = data.items.filter((item) => item.type === 'notice');

    $('#content').innerHTML = `
        <div class="page-intro"><div><span class="eyebrow">Comunicação</span><h3>Avisos</h3><p>Comunicados publicados pela equipe de atendimento.</p></div></div>
        <section class="notice-list">${noticesList.map((item) => `<article class="notice-card"><div class="notice-icon">${icon('bell')}</div><div><span class="badge badge-open">Aviso</span><h3>${escapeHtml(item.title)}</h3><p>${escapeHtml(item.body)}</p><small>${escapeHtml(item.category || 'Geral')}</small></div></article>`).join('') || '<div class="card empty">Nenhum aviso disponível.</div>'}</section>`;
}

// conteúdo
async function content() {
    if (state.user.role !== 'admin') return home();
    const [_, data] = await Promise.all([loadUsers(), api('/api/contents')]);

    $('#content').innerHTML = `
        <div class="page-intro">
            <div>
                <span class="eyebrow">Administração</span>
                <h3>Gerenciar Conteúdo</h3>
                <p>Crie materiais e avisos e escolha os usuários que receberão cada publicação.</p>
            </div>
        </div>
        <div class="card">
            <form id="contentForm">
                <div class="form-grid">
                    <div class="field"><label>Título<input name="title" required placeholder="Ex.: Como acessar o Wi-Fi corporativo"></label></div>
                    <div class="field"><label>Tipo<select name="type"><option value="material">Material de Apoio</option><option value="notice">Aviso</option></select></label></div>
                    <div class="field"><label>Categoria<input name="category" placeholder="Ex.: Rede, Segurança, Sistemas"></label></div>
                    <div class="field"><label>Frequência<select name="frequency"><option value="once">Uma vez</option><option value="daily">Diariamente</option></select></label></div>
                    <div class="field full"><label>Destinatários<select name="user_ids" multiple size="6">${state.users.map((user) => `<option value="${user.id}">${escapeHtml(user.name)} · ${escapeHtml(user.department || 'Sem departamento')}</option>`).join('')}</select></label><small>Use Ctrl para selecionar mais de uma pessoa.</small></div>
                    <div class="field full"><label>Conteúdo<textarea name="body" required placeholder="Escreva manualmente o material ou aviso."></textarea></label></div>
                </div>
                <div id="contentError" class="error"></div>
                <div class="actions"><button class="primary">Publicar</button></div>
            </form>
        </div>

        <section class="card content-management-card">
            <div class="section-title">
                <div><span class="eyebrow">Publicações</span><h3>Materiais e Avisos publicados</h3></div>
            </div>
            <div class="content-management-list">
                ${data.items.map((item) => `
                    <article class="content-management-row">
                        <div>
                            <span class="badge ${item.type === 'notice' ? 'badge-open' : 'badge-work'}">${item.type === 'notice' ? 'Aviso' : 'Material'}</span>
                            <h4>${escapeHtml(item.title)}</h4>
                            <small>${escapeHtml(item.category || 'Geral')} · ${formatDate(item.created_at)} · ${Number(item.recipient_count || 0)} destinatário(s)</small>
                        </div>
                        <div class="actions">
                            <button class="secondary" type="button" onclick="openContentDelete(${item.id})">${icon('trash')} Excluir</button>
                        </div>
                    </article>
                `).join('') || '<div class="empty">Nenhum conteúdo publicado.</div>'}
            </div>
        </section>`;

    $('#contentForm').onsubmit = async (event) => {
        event.preventDefault();
        const form = event.target;
        const data = {
            title: form.title.value,
            type: form.type.value,
            category: form.category.value,
            frequency: form.frequency.value,
            body: form.body.value,
            user_ids: [...form.user_ids.selectedOptions].map((option) => Number(option.value)),
        };

        if (!data.user_ids.length) {
            $('#contentError').textContent = 'Selecione pelo menos um destinatário.';
            return;
        }

        const confirmed = confirm(`Confirma a publicação deste ${data.type === 'notice' ? 'aviso' : 'material'}?`);
        if (!confirmed) return;

        try {
            await api('/api/contents', { method: 'POST', body: JSON.stringify(data) });
            await content();
        } catch (exception) {
            $('#contentError').textContent = exception.message;
        }
    };
}

async function openContentDelete(contentId) {
    if (state.user.role !== 'admin') return;

    const data = await api('/api/contents');
    const item = data.items.find((contentItem) => Number(contentItem.id) === Number(contentId));
    if (!item) return;

    $('#modalContent').innerHTML = `
        <div class="modal-title">
            <span class="eyebrow">Administração</span>
            <h2>Excluir conteúdo</h2>
            <p class="muted">${escapeHtml(item.title)}</p>
        </div>
        <div class="field">
            <label>Excluir para
                <select id="deleteContentScope">
                    <option value="all">Todos os usuários</option>
                    ${state.users.map((user) => `<option value="${user.id}">${escapeHtml(user.name)}</option>`).join('')}
                </select>
            </label>
        </div>
        <div id="deleteContentError" class="error"></div>
        <div class="actions">
            <button class="secondary" type="button" onclick="closeModal()">Cancelar</button>
            <button class="primary" type="button" onclick="deleteContent(${contentId})">${icon('trash')} Excluir</button>
        </div>`;
    openModal();
}

async function deleteContent(contentId) {
    const scope = $('#deleteContentScope').value;
    const body = scope === 'all'
        ? { scope: 'all' }
        : { scope: 'user', user_id: Number(scope) };

    try {
        await api(`/api/contents/${contentId}`, {
            method: 'DELETE',
            body: JSON.stringify(body),
        });
        closeModal();
        await content();
    } catch (exception) {
        $('#deleteContentError').textContent = exception.message;
    }
}

// relatórios
function renderReports(data) {
    const totalEvaluations = data.ratings.reduce((sum, item) => sum + Number(item.evaluations), 0);
    const totalMaterialsReceived = data.ratings.reduce((sum, item) => sum + Number(item.materials_received), 0);
    const average = totalMaterialsReceived ? ((totalEvaluations / totalMaterialsReceived) * 100).toFixed(2) : '0.00';
    const updatedAt = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    $('#content').innerHTML = `
        <div class="page-intro"><div><span class="eyebrow">Análise</span><h3>Relatórios</h3><p>Resultados do atendimento e desempenho dos materiais de conhecimento.</p></div><div class="export-actions"><span class="report-live-status"><i></i> Atualização automática · ${updatedAt}</span><button class="secondary" type="button" onclick="exportReports('pdf')">${icon('download')} PDF</button><button class="secondary" type="button" onclick="exportReports('excel')">${icon('download')} Excel</button></div></div>
        <section class="grid cards report-kpis">
            ${metricCard('Avaliações', totalEvaluations, 'Materiais curtidos')}
            ${metricCard('Média geral', `${average}%`, 'Curtidas em relação aos materiais recebidos')}
            ${metricCard('Materiais', data.knowledge.length, 'Conteúdos analisados')}
            ${metricCard('Status', data.tickets.length, 'Situações acompanhadas')}
        </section>
        <section class="grid two report-grid">
            <article class="card"><div class="section-title"><div><span class="eyebrow">Chamados</span><h3>Resultado por status</h3></div></div><div class="report-status-list">${data.tickets.map((item) => `<div><span>${statusBadge(item.status)}</span><strong>${item.total}</strong></div>`).join('')}</div></article>
            <article class="card"><div class="section-title"><div><span class="eyebrow">Satisfação</span><h3>Avaliações por usuário</h3></div></div><div class="table-wrap"><table class="table compact"><thead><tr><th>Usuário</th><th>Avaliações</th><th>Média</th><th>Materiais recebidos</th></tr></thead><tbody>${data.ratings.map((item) => `<tr><td>${escapeHtml(item.name)}</td><td>${item.evaluations}</td><td>${item.average_rating}%</td><td>${item.materials_received}</td></tr>`).join('') || '<tr><td colspan="4"><div class="empty">Nenhum usuário disponível.</div></td></tr>'}</tbody></table></div></article>
        </section>
        <article class="card report-knowledge-card"><div class="section-title"><div><span class="eyebrow">Conhecimento</span><h3>Resultados da Base de Conhecimento</h3></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Material</th><th>Aberturas</th><th>Curtidas</th><th>Comentários</th></tr></thead><tbody>${data.knowledge.map((item) => `<tr><td><b>${escapeHtml(item.title)}</b></td><td>${item.opens}</td><td>${item.likes}</td><td>${item.comments}</td></tr>`).join('') || '<tr><td colspan="4"><div class="empty">Nenhum material avaliado.</div></td></tr>'}</tbody></table></div></article>`;
}

async function refreshReports() {
    if (state.page !== 'reports' || state.user?.role !== 'admin') return;
    try {
        const data = await api('/api/reports', { cache: false });
        renderReports(data);
    } catch (exception) {
        if (state.page === 'reports') {
            console.warn('Não foi possível atualizar os relatórios:', exception.message);
        }
    }
}

async function reports() {
    if (state.user.role !== 'admin') return home();

    if (state.reportsTimer) {
        clearInterval(state.reportsTimer);
        state.reportsTimer = null;
    }

    const data = await api('/api/reports', { cache: false });
    renderReports(data);
    state.reportsTimer = window.setInterval(refreshReports, 10000);
}

// configurações
async function settings() {
    const admin = state.user.role === 'admin';

    $('#content').innerHTML = `
        <div class="page-intro"><div><span class="eyebrow">Preferências</span><h3>Configurações</h3><p>Personalize a aparência e, para administradores, gerencie permissões.</p></div></div>
        <article class="card settings-card">
            <div class="setting-row"><div><b>Aparência</b><p>Alterne entre o modo claro e escuro sem perder legibilidade.</p></div><button class="secondary" onclick="toggleTheme()">Alternar tema</button></div>
            ${admin ? '<div class="setting-row"><div><b>Permissões</b><p>Conceda ou retire o perfil administrativo de usuários.</p></div><button class="secondary" onclick="manageUsers()">Gerenciar usuários</button></div>' : ''}
        </article>`;
}

async function manageUsers() {
    if (state.user.role !== 'admin') return;
    await loadUsers();

    $('#modalContent').innerHTML = `
        <div class="modal-title"><span class="eyebrow">Administração</span><h2>Gerenciar permissões</h2><p class="muted">Somente administradores autorizados podem alterar perfis.</p></div>
        <div class="permission-list">${state.users.map((user) => `<div class="permission-row"><div class="permission-person">${avatar(user)}<div><b>${escapeHtml(user.name)}</b><small>${escapeHtml(user.department || 'Sem departamento')}</small></div></div><div><button class="secondary ${user.role === 'user' ? 'selected' : ''}" onclick="setRole(${user.id}, 'user')">Usuário</button><button class="primary ${user.role === 'admin' ? 'selected' : ''}" onclick="setRole(${user.id}, 'admin')">Administrador</button></div></div>`).join('')}</div>`;
    openModal();
}

async function setRole(id, role) {
    try {
        await api('/api/users/role', { method: 'POST', body: JSON.stringify({ user_id: id, role }) });
        await manageUsers();
    } catch (exception) {
        alert(exception.message);
    }
}


// exportação
function exportDashboard(format) {
    window.location.href = `/api/export/dashboard?format=${encodeURIComponent(format)}`;
}

function exportReports(format) {
    window.location.href = `/api/export/reports?format=${encodeURIComponent(format)}`;
}

function toggleTheme() {
    document.body.classList.toggle('dark');
    localStorage.setItem('conectadesk-theme', document.body.classList.contains('dark') ? 'dark' : 'light');
}

if (localStorage.getItem('conectadesk-theme') === 'dark') {
    document.body.classList.add('dark');
}

$('#loginForm').onsubmit = async (event) => {
    event.preventDefault();
    $('#loginError').textContent = '';

    try {
        const response = await api('/api/auth/login', {
            method: 'POST',
            body: JSON.stringify({
                username: $('#loginUser').value.trim(),
                password: $('#loginPass').value,
            }),
        });
        state.user = response.user;
        showApp();
    } catch (exception) {
        $('#loginError').textContent = exception.message;
    }
};

$('#logoutBtn').onclick = async () => {
    await api('/api/auth/logout', { method: 'POST' });
    location.reload();
};

$('#profileTopBtn').onclick = openProfile;
$('#notifBtn').onclick = openNotifications;

init();
