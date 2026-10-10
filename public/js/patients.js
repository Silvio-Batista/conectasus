/**
 * Página de gerenciamento de pacientes.
 *
 * Responsável pela listagem, busca, paginação, cadastro, edição, visualização,
 * exclusão, modais, estados de carregamento e feedback visual. Toda a
 * comunicação com o backend passa por api.js. Dados vindos da API são sempre
 * inseridos no DOM via textContent (nunca como HTML).
 */

import { api, ApiError } from './api.js';

const SEARCH_DEBOUNCE_MS = 300;
const SKELETON_ROWS = 5;

const FIELD_NAMES = ['name', 'cpf', 'birth_date', 'phone', 'health_unit_id'];

const API_STATUS_LABELS = {
    loading: 'Conectando à API…',
    ok: 'API conectada',
    error: 'Falha na comunicação com a API',
};

const state = {
    page: 1,
    search: '',
    patients: [],
    meta: null,
    units: [],
    unitsLoaded: false,
    editingId: null,
    deletingPatient: null,
    pendingEdit: null,
    listController: null,
    isSubmitting: false,
    isDeleting: false,
    lastFocused: null,
    pendingRowFocus: null,
    viewingPatient: null,
    searchTimer: null,
};

const byId = (id) => document.getElementById(id);

const el = {
    apiStatus: byId('api-status'),
    apiStatusLabel: byId('api-status-label'),
    statPatients: byId('stat-patients'),
    statPatientsLabel: byId('stat-patients-label'),
    statUnits: byId('stat-units'),
    searchForm: byId('search-form'),
    searchInput: byId('search-input'),
    searchClear: byId('search-clear'),
    btnNew: byId('btn-new-patient'),
    loadingBar: byId('loading-bar'),
    tableWrapper: byId('table-wrapper'),
    table: byId('patients-table'),
    tbody: byId('patients-body'),
    tableState: byId('table-state'),
    tableStateIcon: byId('table-state-icon'),
    tableStateTitle: byId('table-state-title'),
    tableStateText: byId('table-state-text'),
    tableStateAction: byId('table-state-action'),
    paginationSummary: byId('pagination-summary'),
    pager: byId('pager'),
    pagerPage: byId('pager-page'),
    btnPrev: byId('btn-prev'),
    btnNext: byId('btn-next'),
    patientModal: byId('patient-modal'),
    patientModalTitle: byId('patient-modal-title'),
    form: byId('patient-form'),
    formAlert: byId('form-alert'),
    btnSave: byId('btn-save'),
    fields: {
        name: byId('field-name'),
        cpf: byId('field-cpf'),
        birth_date: byId('field-birth-date'),
        phone: byId('field-phone'),
        health_unit_id: byId('field-health-unit'),
    },
    viewModal: byId('view-modal'),
    viewName: byId('view-name'),
    viewCpf: byId('view-cpf'),
    viewBirthDate: byId('view-birth-date'),
    viewPhone: byId('view-phone'),
    viewUnit: byId('view-unit'),
    btnViewEdit: byId('btn-view-edit'),
    deleteModal: byId('delete-modal'),
    deletePatientName: byId('delete-patient-name'),
    btnConfirmDelete: byId('btn-confirm-delete'),
    toastContainer: byId('toast-container'),
    rowTemplate: byId('patient-row-template'),
    skeletonTemplate: byId('skeleton-row-template'),
};

const modals = {
    patient: bootstrap.Modal.getOrCreateInstance(el.patientModal),
    view: bootstrap.Modal.getOrCreateInstance(el.viewModal),
    delete: bootstrap.Modal.getOrCreateInstance(el.deleteModal),
};

// ---------------------------------------------------------------------------
// Utilitários de formatação
// ---------------------------------------------------------------------------

const digitsOnly = (value) => String(value ?? '').replace(/\D+/g, '');

const formatNumber = (value) => Number(value ?? 0).toLocaleString('pt-BR');

function maskCpf(value) {
    const digits = digitsOnly(value).slice(0, 11);
    let output = digits.slice(0, 3);

    if (digits.length > 3) output += `.${digits.slice(3, 6)}`;
    if (digits.length > 6) output += `.${digits.slice(6, 9)}`;
    if (digits.length > 9) output += `-${digits.slice(9, 11)}`;

    return output;
}

function maskPhone(value) {
    const digits = digitsOnly(value).slice(0, 11);

    if (digits.length === 0) return '';
    if (digits.length <= 2) return `(${digits}`;
    if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

const formatCpf = (cpf) => (digitsOnly(cpf).length === 11 ? maskCpf(cpf) : (cpf || '—'));

const formatPhone = (phone) => (phone ? maskPhone(phone) : '—');

function parseIsoDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value ?? '');

    if (!match) return null;

    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));

    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDate(value) {
    const date = parseIsoDate(value);

    return date ? date.toLocaleDateString('pt-BR') : '—';
}

function ageFrom(value) {
    const birth = parseIsoDate(value);

    if (!birth) return '';

    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const hadBirthday = today.getMonth() > birth.getMonth()
        || (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate());

    if (!hadBirthday) age -= 1;

    if (age < 1) return 'menos de 1 ano';

    return `${age} ${age === 1 ? 'ano' : 'anos'}`;
}

function todayIso() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

const unitLabel = (unit) => (unit ? `${unit.name} — ${unit.city}` : '—');

// ---------------------------------------------------------------------------
// Inicialização
// ---------------------------------------------------------------------------

function init() {
    bindEvents();
    el.fields.birth_date.max = todayIso();
    renderSkeleton();
    loadHealthUnits();
    loadPatients();
}

function bindEvents() {
    el.searchInput.addEventListener('input', onSearchInput);
    el.searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && el.searchInput.value !== '') {
            event.preventDefault();
            clearSearch();
        }
    });
    el.searchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        applySearch({ force: true });
    });
    el.searchClear.addEventListener('click', clearSearch);

    el.btnNew.addEventListener('click', () => openCreateModal(el.btnNew));
    el.tableStateAction.addEventListener('click', onTableStateAction);
    el.tbody.addEventListener('click', onRowAction);

    el.btnPrev.addEventListener('click', () => changePage(-1));
    el.btnNext.addEventListener('click', () => changePage(1));

    el.form.addEventListener('submit', handleSubmit);
    el.fields.cpf.addEventListener('input', () => { el.fields.cpf.value = maskCpf(el.fields.cpf.value); });
    el.fields.phone.addEventListener('input', () => { el.fields.phone.value = maskPhone(el.fields.phone.value); });

    FIELD_NAMES.forEach((field) => {
        const eventName = el.fields[field].tagName === 'SELECT' ? 'change' : 'input';
        el.fields[field].addEventListener(eventName, () => clearFieldError(field));
    });

    el.patientModal.addEventListener('shown.bs.modal', () => el.fields.name.focus());
    el.patientModal.addEventListener('hidden.bs.modal', () => {
        resetForm();
        state.editingId = null;
        restoreFocus();
    });

    el.btnViewEdit.addEventListener('click', () => {
        state.pendingEdit = state.viewingPatient;
        modals.view.hide();
    });
    el.viewModal.addEventListener('hidden.bs.modal', () => {
        restoreFocus();

        if (state.pendingEdit) {
            const patient = state.pendingEdit;
            state.pendingEdit = null;
            openEditForm(patient, document.activeElement);
        }
    });

    el.deleteModal.addEventListener('shown.bs.modal', () => {
        el.deleteModal.querySelector('[data-bs-dismiss="modal"]')?.focus();
    });
    el.deleteModal.addEventListener('hidden.bs.modal', () => {
        state.deletingPatient = null;
        restoreFocus();
    });
    el.btnConfirmDelete.addEventListener('click', confirmDelete);
}

// ---------------------------------------------------------------------------
// Carregamento de dados
// ---------------------------------------------------------------------------

async function loadHealthUnits() {
    try {
        const payload = await api.listHealthUnits();

        state.units = payload.data;
        state.unitsLoaded = true;
        populateUnitSelect();
        el.statUnits.textContent = formatNumber(state.units.length);
    } catch (error) {
        state.unitsLoaded = false;
        el.statUnits.textContent = '—';
        reportConnectivity(error);
    }
}

async function loadPatients() {
    state.listController?.abort();

    const controller = new AbortController();
    state.listController = controller;
    setBusy(true);

    try {
        const payload = await api.listPatients({ search: state.search, page: state.page }, controller.signal);

        if (controller.signal.aborted) return;

        const { data, meta } = payload;

        // A página solicitada deixou de existir (ex.: último registro da página foi excluído).
        if (data.length === 0 && meta.total > 0 && state.page > meta.last_page) {
            state.page = meta.last_page;
            return loadPatients();
        }

        state.patients = data;
        state.meta = meta;

        renderTable();
        renderPagination();
        renderStats();
        setApiStatus('ok');
    } catch (error) {
        if (error?.name === 'AbortError') return;

        state.patients = [];
        state.meta = null;

        renderError(error);
        renderPagination();
        setApiStatus('error');
    } finally {
        if (state.listController === controller) {
            state.listController = null;
            setBusy(false);
        }
    }
}

// ---------------------------------------------------------------------------
// Renderização
// ---------------------------------------------------------------------------

function setBusy(busy) {
    el.tableWrapper.setAttribute('aria-busy', String(busy));
    el.loadingBar.classList.toggle('is-active', busy);
    el.loadingBar.setAttribute('aria-hidden', String(!busy));

    if (busy) {
        el.btnPrev.disabled = true;
        el.btnNext.disabled = true;
    }
}

function setApiStatus(status) {
    el.apiStatus.dataset.state = status;
    el.apiStatusLabel.textContent = API_STATUS_LABELS[status] ?? API_STATUS_LABELS.loading;
}

function reportConnectivity(error) {
    if (error instanceof ApiError && (error.isNetwork || error.isServer)) {
        setApiStatus('error');
    }
}

function renderSkeleton() {
    hideTableState();
    el.tbody.replaceChildren();

    for (let i = 0; i < SKELETON_ROWS; i += 1) {
        el.tbody.appendChild(el.skeletonTemplate.content.cloneNode(true));
    }
}

function renderTable() {
    el.tbody.replaceChildren();

    if (state.patients.length === 0) {
        if (state.search) {
            showTableState({
                variant: 'empty',
                icon: 'bi-search',
                title: 'Nenhum paciente encontrado para esta pesquisa.',
                text: `Não há pacientes com "${state.search}" no nome. Revise o termo ou limpe a pesquisa.`,
                action: { label: 'Limpar pesquisa', name: 'clear-search' },
            });
        } else {
            showTableState({
                variant: 'empty',
                icon: 'bi-people',
                title: 'Nenhum paciente cadastrado.',
                text: 'Cadastre o primeiro paciente para começar a gerenciar os atendimentos das unidades de saúde.',
                action: { label: 'Cadastrar paciente', name: 'create' },
            });
        }

        // A linha que tinha o foco deixou de existir: leva o foco para a ação do estado vazio.
        if (state.pendingRowFocus) {
            el.tableStateAction.focus();
            state.pendingRowFocus = null;
        }

        return;
    }

    hideTableState();

    const fragment = document.createDocumentFragment();
    state.patients.forEach((patient) => fragment.appendChild(buildRow(patient)));
    el.tbody.appendChild(fragment);

    applyPendingRowFocus();
    state.pendingRowFocus = null;
}

function buildRow(patient) {
    const fragment = el.rowTemplate.content.cloneNode(true);
    const row = fragment.querySelector('tr');

    row.dataset.id = String(patient.id);

    row.querySelector('.js-name').textContent = patient.name;
    row.querySelector('.js-cpf').textContent = formatCpf(patient.cpf);
    row.querySelector('.js-birth-date').textContent = formatDate(patient.birth_date);
    row.querySelector('.js-age').textContent = ageFrom(patient.birth_date);

    const phoneCell = row.querySelector('.js-phone');
    phoneCell.textContent = formatPhone(patient.phone);
    phoneCell.classList.toggle('cell-empty', !patient.phone);

    const unitCell = row.querySelector('.js-unit');
    unitCell.textContent = patient.health_unit?.name ?? '—';
    unitCell.classList.toggle('cell-empty', !patient.health_unit);
    row.querySelector('.js-unit-city').textContent = patient.health_unit?.city ?? '';

    const labels = {
        view: `Visualizar ${patient.name}`,
        edit: `Editar ${patient.name}`,
        delete: `Excluir ${patient.name}`,
    };

    row.querySelectorAll('button[data-action]').forEach((button) => {
        const label = labels[button.dataset.action];
        button.setAttribute('aria-label', label);
        button.title = label;
    });

    return fragment;
}

function renderError(error) {
    el.tbody.replaceChildren();

    showTableState({
        variant: 'error',
        icon: 'bi-exclamation-triangle',
        title: 'Não foi possível carregar os pacientes.',
        text: error instanceof ApiError ? error.message : 'Ocorreu um erro inesperado. Tente novamente.',
        action: { label: 'Tentar novamente', name: 'retry' },
    });
}

function showTableState({ variant, icon, title, text, action }) {
    el.table.hidden = true;
    el.tableState.hidden = false;
    el.tableState.dataset.variant = variant;
    el.tableStateIcon.className = `table-state__icon bi ${icon}`;
    el.tableStateTitle.textContent = title;
    el.tableStateText.textContent = text;
    el.tableStateAction.textContent = action.label;
    el.tableStateAction.dataset.action = action.name;
}

function hideTableState() {
    el.table.hidden = false;
    el.tableState.hidden = true;
}

function renderPagination() {
    const { meta } = state;

    if (!meta) {
        el.paginationSummary.textContent = '';
        el.pager.hidden = true;
        return;
    }

    if (meta.total === 0) {
        el.paginationSummary.textContent = state.search ? 'Nenhum resultado' : 'Nenhum paciente cadastrado';
        el.pager.hidden = true;
        return;
    }

    const noun = meta.total === 1 ? 'paciente' : 'pacientes';
    el.paginationSummary.textContent = `Mostrando ${formatNumber(meta.from)}–${formatNumber(meta.to)} de ${formatNumber(meta.total)} ${noun}`;

    el.pager.hidden = meta.last_page <= 1;
    el.pagerPage.textContent = `Página ${formatNumber(meta.current_page)} de ${formatNumber(meta.last_page)}`;
    el.btnPrev.disabled = meta.current_page <= 1;
    el.btnNext.disabled = meta.current_page >= meta.last_page;
}

function renderStats() {
    if (!state.meta) return;

    el.statPatientsLabel.textContent = state.search ? 'Pacientes encontrados' : 'Pacientes cadastrados';
    el.statPatients.textContent = formatNumber(state.meta.total);
}

function populateUnitSelect() {
    const select = el.fields.health_unit_id;
    const current = select.value;
    const placeholder = select.options[0];

    select.replaceChildren(placeholder);

    state.units.forEach((unit) => {
        const option = document.createElement('option');
        option.value = String(unit.id);
        option.textContent = unitLabel(unit);
        select.appendChild(option);
    });

    select.value = current;
}

// ---------------------------------------------------------------------------
// Busca e paginação
// ---------------------------------------------------------------------------

function onSearchInput() {
    toggleClearButton();
    clearTimeout(state.searchTimer);
    state.searchTimer = setTimeout(() => applySearch(), SEARCH_DEBOUNCE_MS);
}

function applySearch({ force = false } = {}) {
    clearTimeout(state.searchTimer);
    state.searchTimer = null;

    const term = el.searchInput.value.trim();

    if (term === state.search && !force) return;

    state.search = term;
    state.page = 1;
    loadPatients();
}

function clearSearch() {
    el.searchInput.value = '';
    toggleClearButton();
    el.searchInput.focus();
    applySearch();
}

function toggleClearButton() {
    el.searchClear.hidden = el.searchInput.value === '';
}

function changePage(delta) {
    const target = state.page + delta;

    if (target < 1 || (state.meta && target > state.meta.last_page)) return;

    state.page = target;
    loadPatients();

    const panel = el.tableWrapper.closest('.panel');
    if (panel && panel.getBoundingClientRect().top < 0) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function onTableStateAction() {
    switch (el.tableStateAction.dataset.action) {
        case 'create':
            openCreateModal(el.tableStateAction);
            break;
        case 'clear-search':
            clearSearch();
            break;
        case 'retry':
            renderSkeleton();
            loadPatients();
            if (!state.unitsLoaded) loadHealthUnits();
            break;
        default:
            break;
    }
}

// ---------------------------------------------------------------------------
// Ações da tabela
// ---------------------------------------------------------------------------

function onRowAction(event) {
    const button = event.target.closest('button[data-action]');

    if (!button || button.disabled) return;

    const id = Number(button.closest('tr')?.dataset.id);
    const patient = state.patients.find((item) => item.id === id);

    if (!patient) return;

    switch (button.dataset.action) {
        case 'view':
            openViewModal(id, button);
            break;
        case 'edit':
            openEditModal(id, button);
            break;
        case 'delete':
            openDeleteModal(patient, button);
            break;
        default:
            break;
    }
}

function setRowBusy(trigger, busy) {
    const row = trigger?.closest('tr');

    if (!row) return;

    row.setAttribute('aria-busy', String(busy));
    row.querySelectorAll('button').forEach((button) => { button.disabled = busy; });
}

/**
 * Busca os dados atualizados do paciente antes de abrir visualização/edição.
 * Retorna null (após exibir o feedback adequado) quando não for possível.
 */
async function fetchPatient(id, trigger) {
    setRowBusy(trigger, true);

    try {
        const payload = await api.getPatient(id);

        return payload.data;
    } catch (error) {
        handleOperationError(error, 'Não foi possível carregar os dados do paciente.');

        return null;
    } finally {
        setRowBusy(trigger, false);
    }
}

function handleOperationError(error, fallbackMessage) {
    if (error instanceof ApiError && error.isNotFound) {
        showToast('Este paciente não existe mais. A lista foi atualizada.', 'error');
        loadPatients();
        return;
    }

    reportConnectivity(error);
    showToast(error instanceof ApiError ? error.message : fallbackMessage, 'error');
}

// ---------------------------------------------------------------------------
// Visualização
// ---------------------------------------------------------------------------

async function openViewModal(id, trigger) {
    const patient = await fetchPatient(id, trigger);

    if (!patient) return;

    state.viewingPatient = patient;

    el.viewName.textContent = patient.name;
    el.viewCpf.textContent = formatCpf(patient.cpf);

    const age = ageFrom(patient.birth_date);
    el.viewBirthDate.textContent = age ? `${formatDate(patient.birth_date)} (${age})` : formatDate(patient.birth_date);

    el.viewPhone.textContent = formatPhone(patient.phone);
    el.viewUnit.textContent = unitLabel(patient.health_unit);

    rememberFocus(trigger);
    modals.view.show();
}

// ---------------------------------------------------------------------------
// Cadastro e edição
// ---------------------------------------------------------------------------

async function openCreateModal(trigger) {
    state.editingId = null;
    resetForm();
    el.patientModalTitle.textContent = 'Novo paciente';
    setSaveLabel('Cadastrar');

    rememberFocus(trigger);
    modals.patient.show();

    await ensureUnitsLoaded();
}

async function openEditModal(id, trigger) {
    const patient = await fetchPatient(id, trigger);

    if (!patient) return;

    openEditForm(patient, trigger);
}

async function openEditForm(patient, trigger) {
    state.editingId = patient.id;
    resetForm();
    el.patientModalTitle.textContent = 'Editar paciente';
    setSaveLabel('Salvar alterações');

    rememberFocus(trigger);
    modals.patient.show();

    await ensureUnitsLoaded();
    fillForm(patient);
}

async function ensureUnitsLoaded() {
    if (!state.unitsLoaded) {
        await loadHealthUnits();
    }

    if (!state.unitsLoaded) {
        showFormAlert('Não foi possível carregar as unidades de saúde. Feche e abra o formulário para tentar novamente.');
    }
}

function fillForm(patient) {
    el.fields.name.value = patient.name ?? '';
    el.fields.cpf.value = maskCpf(patient.cpf);
    el.fields.birth_date.value = patient.birth_date ?? '';
    el.fields.phone.value = patient.phone ? maskPhone(patient.phone) : '';
    el.fields.health_unit_id.value = patient.health_unit_id ? String(patient.health_unit_id) : '';
}

function resetForm() {
    el.form.reset();
    el.fields.health_unit_id.value = '';
    el.fields.birth_date.max = todayIso();
    clearFormErrors();
}

function setSaveLabel(label) {
    el.btnSave.querySelector('.btn-label').textContent = label;
}

function collectPayload() {
    return {
        name: el.fields.name.value.trim(),
        cpf: digitsOnly(el.fields.cpf.value),
        birth_date: el.fields.birth_date.value,
        phone: digitsOnly(el.fields.phone.value) || null,
        health_unit_id: el.fields.health_unit_id.value ? Number(el.fields.health_unit_id.value) : null,
    };
}

/**
 * Validação de frontend para feedback imediato. A validação definitiva é do backend.
 */
function validatePayload(payload) {
    const errors = {};

    if (payload.name === '') {
        errors.name = 'O nome é obrigatório.';
    } else if (payload.name.length > 150) {
        errors.name = 'O nome deve ter no máximo 150 caracteres.';
    }

    if (payload.cpf === '') {
        errors.cpf = 'O CPF é obrigatório.';
    } else if (payload.cpf.length !== 11) {
        errors.cpf = 'O CPF deve conter exatamente 11 dígitos.';
    }

    if (payload.birth_date === '') {
        errors.birth_date = 'A data de nascimento é obrigatória.';
    } else if (!parseIsoDate(payload.birth_date)) {
        errors.birth_date = 'Informe uma data de nascimento válida.';
    } else if (payload.birth_date > todayIso()) {
        errors.birth_date = 'A data de nascimento não pode ser futura.';
    }

    if (payload.phone !== null && ![10, 11].includes(payload.phone.length)) {
        errors.phone = 'O telefone deve conter 10 ou 11 dígitos.';
    }

    if (payload.health_unit_id === null) {
        errors.health_unit_id = 'Selecione uma unidade de saúde.';
    }

    return errors;
}

async function handleSubmit(event) {
    event.preventDefault();

    if (state.isSubmitting) return;

    clearFormErrors();

    const payload = collectPayload();
    const errors = validatePayload(payload);

    if (Object.keys(errors).length > 0) {
        showFieldErrors(errors);
        return;
    }

    const isEdit = state.editingId !== null;
    setSubmitting(true);

    try {
        if (isEdit) {
            await api.updatePatient(state.editingId, payload);
        } else {
            await api.createPatient(payload);
        }

        modals.patient.hide();
        showToast(isEdit ? 'Paciente atualizado com sucesso.' : 'Paciente cadastrado com sucesso.', 'success');
        await loadPatients();
    } catch (error) {
        if (error instanceof ApiError && error.isValidation) {
            showFieldErrors(error.errors);
        } else if (error instanceof ApiError && error.isNotFound) {
            modals.patient.hide();
            handleOperationError(error);
        } else {
            reportConnectivity(error);
            showFormAlert(error instanceof ApiError ? error.message : 'Não foi possível salvar o paciente. Tente novamente.');
        }
    } finally {
        setSubmitting(false);
    }
}

function setSubmitting(submitting) {
    state.isSubmitting = submitting;
    el.btnSave.disabled = submitting;
    el.btnSave.setAttribute('aria-busy', String(submitting));
    el.btnSave.querySelector('.spinner-border').hidden = !submitting;
}

function showFieldErrors(errors) {
    const unknown = [];

    Object.entries(errors).forEach(([field, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : messages;

        if (FIELD_NAMES.includes(field)) {
            setFieldError(field, message);
        } else {
            unknown.push(message);
        }
    });

    showFormAlert(unknown.length > 0 ? unknown.join(' ') : 'Verifique os campos destacados.');
    focusFirstInvalidField();
}

function setFieldError(field, message) {
    const input = el.fields[field];

    input.classList.add('is-invalid');
    input.setAttribute('aria-invalid', 'true');
    byId(`error-${field}`).textContent = message;
}

function clearFieldError(field) {
    const input = el.fields[field];

    input.classList.remove('is-invalid');
    input.removeAttribute('aria-invalid');
    byId(`error-${field}`).textContent = '';

    if (!el.form.querySelector('.is-invalid')) {
        hideFormAlert();
    }
}

function clearFormErrors() {
    FIELD_NAMES.forEach(clearFieldError);
    hideFormAlert();
}

function focusFirstInvalidField() {
    el.form.querySelector('.is-invalid')?.focus();
}

function showFormAlert(message) {
    el.formAlert.textContent = message;
    el.formAlert.hidden = false;
}

function hideFormAlert() {
    el.formAlert.hidden = true;
    el.formAlert.textContent = '';
}

// ---------------------------------------------------------------------------
// Exclusão
// ---------------------------------------------------------------------------

function openDeleteModal(patient, trigger) {
    state.deletingPatient = patient;
    el.deletePatientName.textContent = patient.name;

    rememberFocus(trigger);
    modals.delete.show();
}

async function confirmDelete() {
    const patient = state.deletingPatient;

    if (!patient || state.isDeleting) return;

    setDeleting(true);

    try {
        await api.deletePatient(patient.id);

        modals.delete.hide();
        showToast('Paciente excluído com sucesso.', 'success');
        await loadPatients();
    } catch (error) {
        modals.delete.hide();

        if (error instanceof ApiError && error.isNotFound) {
            showToast('Este paciente já havia sido excluído. A lista foi atualizada.', 'error');
            loadPatients();
        } else {
            reportConnectivity(error);
            showToast(error instanceof ApiError ? error.message : 'Não foi possível excluir o paciente.', 'error');
        }
    } finally {
        setDeleting(false);
    }
}

function setDeleting(deleting) {
    state.isDeleting = deleting;
    el.btnConfirmDelete.disabled = deleting;
    el.btnConfirmDelete.setAttribute('aria-busy', String(deleting));
    el.btnConfirmDelete.querySelector('.spinner-border').hidden = !deleting;
}

// ---------------------------------------------------------------------------
// Feedback e foco
// ---------------------------------------------------------------------------

function showToast(message, type = 'success') {
    const isError = type === 'error';

    const toast = document.createElement('div');
    toast.className = `toast cs-toast cs-toast--${type}`;
    toast.setAttribute('role', isError ? 'alert' : 'status');
    toast.setAttribute('aria-live', isError ? 'assertive' : 'polite');
    toast.setAttribute('aria-atomic', 'true');

    const body = document.createElement('div');
    body.className = 'cs-toast__body';

    const icon = document.createElement('i');
    icon.className = `bi ${isError ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'} cs-toast__icon`;
    icon.setAttribute('aria-hidden', 'true');

    const text = document.createElement('span');
    text.className = 'cs-toast__message';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close';
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', 'Fechar');

    body.append(icon, text, close);
    toast.append(body);
    el.toastContainer.append(toast);

    toast.addEventListener('hidden.bs.toast', () => toast.remove());

    bootstrap.Toast.getOrCreateInstance(toast, { delay: isError ? 6000 : 4000 }).show();
}

function rememberFocus(trigger) {
    state.lastFocused = trigger instanceof HTMLElement ? trigger : document.activeElement;
}

/**
 * Devolve o foco ao elemento que abriu o modal.
 *
 * Quando o gatilho é um botão de uma linha da tabela, a listagem pode estar sendo
 * recarregada neste exato momento (após salvar/excluir). Nesse caso o alvo fica
 * pendente e é reaplicado por renderTable() assim que as novas linhas existirem.
 */
function restoreFocus() {
    const target = state.lastFocused;
    state.lastFocused = null;

    if (!target) return;

    const rowId = target.closest?.('tr')?.dataset.id;
    const action = target.dataset?.action;

    if (rowId && action) {
        state.pendingRowFocus = { rowId, action };
        applyPendingRowFocus();

        if (!state.listController) {
            state.pendingRowFocus = null;
        }

        return;
    }

    if (target.isConnected) {
        target.focus();
    } else {
        el.btnNew.focus();
    }
}

function applyPendingRowFocus() {
    const pending = state.pendingRowFocus;

    if (!pending) return;

    const active = document.activeElement;
    const focusIsFree = !active || active === document.body || el.tbody.contains(active);

    if (!focusIsFree) return;

    const button = el.tbody.querySelector(`tr[data-id="${pending.rowId}"] button[data-action="${pending.action}"]`);

    if (button) {
        button.focus();
    } else if (!state.listController) {
        // A linha deixou de existir (ex.: paciente excluído).
        el.btnNew.focus();
    }
}

init();
