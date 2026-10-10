/**
 * Camada de acesso à API REST do ConectaSUS.
 *
 * Centraliza as chamadas Fetch, os headers, a serialização JSON e a
 * interpretação das respostas HTTP (204, 404, 422, 5xx e falhas de rede).
 */

const BASE_URL = '/api';

/**
 * Erro padronizado para qualquer falha de comunicação com a API.
 */
export class ApiError extends Error {
    /**
     * @param {string} message Mensagem amigável, pronta para exibição.
     * @param {{ status?: number, errors?: Record<string, string[]>, cause?: unknown }} [details]
     */
    constructor(message, { status = 0, errors = {}, cause } = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
        this.cause = cause;
    }

    /** Falha de rede (servidor inacessível, sem conexão). */
    get isNetwork() {
        return this.status === 0;
    }

    /** Registro inexistente. */
    get isNotFound() {
        return this.status === 404;
    }

    /** Erro de validação (HTTP 422) com mensagens por campo em `errors`. */
    get isValidation() {
        return this.status === 422;
    }

    /** Erro interno do servidor. */
    get isServer() {
        return this.status >= 500;
    }
}

/**
 * Monta a URL completa, incluindo apenas os parâmetros com valor.
 */
function buildUrl(path, params = {}) {
    const url = new URL(`${BASE_URL}${path}`, window.location.origin);

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && String(value).trim() !== '') {
            url.searchParams.set(key, String(value));
        }
    });

    return url;
}

/**
 * Converte o corpo da resposta em JSON quando houver conteúdo compatível.
 */
async function parseBody(response) {
    if (response.status === 204) {
        return null;
    }

    const contentType = response.headers.get('content-type') ?? '';

    if (!contentType.includes('application/json')) {
        return null;
    }

    try {
        return await response.json();
    } catch {
        return null;
    }
}

/**
 * Define a mensagem amigável de acordo com o status HTTP.
 */
function messageFor(status, payload) {
    switch (true) {
        case status === 422:
            return 'Verifique os campos destacados e tente novamente.';
        case status === 404:
            return payload?.message || 'Registro não encontrado.';
        case status === 405:
            return 'Operação não permitida.';
        case status >= 500:
            return 'Erro interno do servidor. Tente novamente em instantes.';
        default:
            return payload?.message || `Não foi possível concluir a operação (HTTP ${status}).`;
    }
}

/**
 * Executa uma requisição à API e devolve o JSON de resposta (ou null para 204).
 *
 * @param {string} path Caminho relativo a /api.
 * @param {{ method?: string, body?: unknown, params?: Record<string, unknown>, signal?: AbortSignal }} [options]
 * @throws {ApiError} Para respostas de erro ou falhas de rede. Abortos são repassados como AbortError.
 */
async function request(path, { method = 'GET', body, params, signal } = {}) {
    const headers = { Accept: 'application/json' };
    const init = { method, headers, signal };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(body);
    }

    let response;

    try {
        response = await fetch(buildUrl(path, params), init);
    } catch (error) {
        if (error?.name === 'AbortError') {
            throw error;
        }

        throw new ApiError('Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.', {
            status: 0,
            cause: error,
        });
    }

    const payload = await parseBody(response);

    if (!response.ok) {
        throw new ApiError(messageFor(response.status, payload), {
            status: response.status,
            errors: payload?.errors ?? {},
        });
    }

    return payload;
}

export const api = {
    /**
     * Lista pacientes com busca por nome e paginação (10 por página).
     *
     * @param {{ search?: string, page?: number }} [filters]
     * @param {AbortSignal} [signal]
     */
    listPatients({ search = '', page = 1 } = {}, signal) {
        return request('/patients', { params: { search, page }, signal });
    },

    /** @param {number} id */
    getPatient(id) {
        return request(`/patients/${encodeURIComponent(id)}`);
    },

    /** @param {object} data */
    createPatient(data) {
        return request('/patients', { method: 'POST', body: data });
    },

    /**
     * @param {number} id
     * @param {object} data
     */
    updatePatient(id, data) {
        return request(`/patients/${encodeURIComponent(id)}`, { method: 'PUT', body: data });
    },

    /** @param {number} id */
    deletePatient(id) {
        return request(`/patients/${encodeURIComponent(id)}`, { method: 'DELETE' });
    },

    listHealthUnits() {
        return request('/health-units');
    },
};
