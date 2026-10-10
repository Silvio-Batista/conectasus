<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gerenciamento de Pacientes — ConectaSUS</title>
    <meta name="description" content="Sistema de gestão de pacientes vinculados a unidades de saúde.">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%232563EB'/%3E%3Cpath d='M9 17h4l2-5 3 9 2-4h3' fill='none' stroke='%23fff' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
    <header class="app-header">
        <div class="container-xl app-header__inner">
            <a class="brand" href="{{ route('home') }}" aria-label="ConectaSUS — página inicial">
                <span class="brand__mark" aria-hidden="true"><i class="bi bi-heart-pulse"></i></span>
                <span class="brand__text">
                    <span class="brand__name">ConectaSUS</span>
                    <span class="brand__tagline">Gestão de Pacientes</span>
                </span>
            </a>

            <div class="api-status" id="api-status" data-state="loading" role="status" aria-live="polite">
                <span class="api-status__dot" aria-hidden="true"></span>
                <span class="api-status__label" id="api-status-label">Conectando à API…</span>
            </div>
        </div>
    </header>

    <main class="container-xl page">
        <div class="page-header">
            <h1 class="page-title">Gerenciamento de Pacientes</h1>
            <p class="page-subtitle">Cadastre, consulte e gerencie pacientes vinculados às unidades de saúde.</p>
        </div>

        <section class="stats" aria-label="Indicadores">
            <article class="stat-card">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                <div class="stat-card__body">
                    <p class="stat-card__label" id="stat-patients-label">Pacientes cadastrados</p>
                    <p class="stat-card__value" id="stat-patients" aria-labelledby="stat-patients-label">—</p>
                </div>
            </article>
            <article class="stat-card">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-hospital"></i></span>
                <div class="stat-card__body">
                    <p class="stat-card__label" id="stat-units-label">Unidades de saúde</p>
                    <p class="stat-card__value" id="stat-units" aria-labelledby="stat-units-label">—</p>
                </div>
            </article>
        </section>

        <section class="panel" aria-labelledby="patients-heading">
            <div class="panel__toolbar">
                <h2 id="patients-heading" class="visually-hidden">Lista de pacientes</h2>

                <form class="search" id="search-form" role="search">
                    <label for="search-input" class="visually-hidden">Pesquisar paciente pelo nome</label>
                    <i class="bi bi-search search__icon" aria-hidden="true"></i>
                    <input type="search" id="search-input" class="form-control search__input" placeholder="Pesquisar por nome…" autocomplete="off" spellcheck="false">
                    <button type="button" class="search__clear" id="search-clear" aria-label="Limpar pesquisa" hidden>
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </form>

                <button type="button" class="btn btn-primary btn-new" id="btn-new-patient">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    Novo paciente
                </button>
            </div>

            <div class="loading-bar" id="loading-bar" role="progressbar" aria-label="Carregando pacientes" aria-hidden="true"></div>

            <div class="table-wrapper" id="table-wrapper" aria-busy="true">
                <table class="table patients-table" id="patients-table">
                    <caption class="visually-hidden">Pacientes cadastrados com CPF, data de nascimento, telefone e unidade de saúde</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nome</th>
                            <th scope="col">CPF</th>
                            <th scope="col">Nascimento</th>
                            <th scope="col">Telefone</th>
                            <th scope="col">Unidade de saúde</th>
                            <th scope="col" class="cell-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="patients-body"></tbody>
                </table>

                <div class="table-state" id="table-state" hidden>
                    <span class="table-state__icon" id="table-state-icon" aria-hidden="true"></span>
                    <p class="table-state__title" id="table-state-title"></p>
                    <p class="table-state__text" id="table-state-text"></p>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="table-state-action"></button>
                </div>
            </div>

            <footer class="panel__footer">
                <p class="pagination-summary" id="pagination-summary" aria-live="polite">Carregando…</p>
                <nav class="pager" id="pager" aria-label="Paginação de pacientes">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-prev" disabled>
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        Anterior
                    </button>
                    <span class="pager__page" id="pager-page" aria-live="polite">Página 1 de 1</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-next" disabled>
                        Próxima
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </nav>
            </footer>
        </section>
    </main>

    {{-- Modal: cadastro e edição --}}
    <div class="modal fade" id="patient-modal" tabindex="-1" aria-labelledby="patient-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="patient-form" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title" id="patient-modal-title">Novo paciente</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger form-alert" id="form-alert" role="alert" hidden></div>
                        <p class="form-hint">Campos marcados com <span class="required-mark" aria-hidden="true">*</span> são obrigatórios.</p>

                        <fieldset id="patient-fieldset">
                            <div class="form-group">
                                <label for="field-name" class="form-label">Nome completo <span class="required-mark" aria-hidden="true">*</span></label>
                                <input type="text" class="form-control" id="field-name" name="name" maxlength="150" required aria-required="true" aria-describedby="error-name" autocomplete="off">
                                <div class="invalid-feedback" id="error-name"></div>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6 form-group">
                                    <label for="field-cpf" class="form-label">CPF <span class="required-mark" aria-hidden="true">*</span></label>
                                    <input type="text" class="form-control" id="field-cpf" name="cpf" inputmode="numeric" placeholder="000.000.000-00" maxlength="14" required aria-required="true" aria-describedby="error-cpf" autocomplete="off">
                                    <div class="invalid-feedback" id="error-cpf"></div>
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="field-birth-date" class="form-label">Data de nascimento <span class="required-mark" aria-hidden="true">*</span></label>
                                    <input type="date" class="form-control" id="field-birth-date" name="birth_date" required aria-required="true" aria-describedby="error-birth_date">
                                    <div class="invalid-feedback" id="error-birth_date"></div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6 form-group">
                                    <label for="field-phone" class="form-label">Telefone</label>
                                    <input type="tel" class="form-control" id="field-phone" name="phone" inputmode="tel" placeholder="(00) 00000-0000" maxlength="15" aria-describedby="error-phone" autocomplete="off">
                                    <div class="invalid-feedback" id="error-phone"></div>
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="field-health-unit" class="form-label">Unidade de saúde <span class="required-mark" aria-hidden="true">*</span></label>
                                    <select class="form-select" id="field-health-unit" name="health_unit_id" required aria-required="true" aria-describedby="error-health_unit_id">
                                        <option value="">Selecione…</option>
                                    </select>
                                    <div class="invalid-feedback" id="error-health_unit_id"></div>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btn-save">
                            <span class="spinner-border spinner-border-sm" aria-hidden="true" hidden></span>
                            <span class="btn-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal: visualização --}}
    <div class="modal fade" id="view-modal" tabindex="-1" aria-labelledby="view-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="view-modal-title">Dados do paciente</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <dl class="details" id="view-details">
                        <div class="details__row">
                            <dt>Nome</dt>
                            <dd id="view-name">—</dd>
                        </div>
                        <div class="details__row">
                            <dt>CPF</dt>
                            <dd id="view-cpf" class="cell-mono">—</dd>
                        </div>
                        <div class="details__row">
                            <dt>Data de nascimento</dt>
                            <dd id="view-birth-date">—</dd>
                        </div>
                        <div class="details__row">
                            <dt>Telefone</dt>
                            <dd id="view-phone" class="cell-mono">—</dd>
                        </div>
                        <div class="details__row">
                            <dt>Unidade de saúde</dt>
                            <dd id="view-unit">—</dd>
                        </div>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btn-view-edit">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                        Editar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: confirmação de exclusão --}}
    <div class="modal fade" id="delete-modal" tabindex="-1" aria-labelledby="delete-modal-title" aria-describedby="delete-modal-text" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm-confirm">
            <div class="modal-content">
                <div class="modal-body confirm">
                    <span class="confirm__icon" aria-hidden="true"><i class="bi bi-trash3"></i></span>
                    <h2 class="confirm__title" id="delete-modal-title">Excluir paciente</h2>
                    <p class="confirm__text" id="delete-modal-text">
                        Tem certeza de que deseja excluir o paciente <strong id="delete-patient-name"></strong>?
                    </p>
                    <p class="confirm__warning">Esta ação não poderá ser desfeita.</p>
                </div>
                <div class="modal-footer confirm__footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btn-confirm-delete">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true" hidden></span>
                        <span class="btn-label">Excluir paciente</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    {{-- Templates clonados pelo JavaScript (dados sempre inseridos via textContent) --}}
    <template id="patient-row-template">
        <tr>
            <td data-label="Nome"><span class="cell-primary js-name"></span></td>
            <td data-label="CPF" class="cell-mono js-cpf"></td>
            <td data-label="Nascimento">
                <span class="js-birth-date"></span>
                <span class="cell-secondary js-age"></span>
            </td>
            <td data-label="Telefone" class="cell-mono js-phone"></td>
            <td data-label="Unidade de saúde">
                <span class="js-unit"></span>
                <span class="cell-secondary js-unit-city"></span>
            </td>
            <td class="cell-actions">
                <div class="actions">
                    <button type="button" class="icon-btn js-view" data-action="view"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    <button type="button" class="icon-btn js-edit" data-action="edit"><i class="bi bi-pencil" aria-hidden="true"></i></button>
                    <button type="button" class="icon-btn icon-btn--danger js-delete" data-action="delete"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                </div>
            </td>
        </tr>
    </template>

    <template id="skeleton-row-template">
        <tr class="skeleton-row" aria-hidden="true">
            <td data-label="Nome"><span class="skeleton" style="width: 62%"></span></td>
            <td data-label="CPF"><span class="skeleton" style="width: 78%"></span></td>
            <td data-label="Nascimento"><span class="skeleton" style="width: 55%"></span></td>
            <td data-label="Telefone"><span class="skeleton" style="width: 70%"></span></td>
            <td data-label="Unidade de saúde"><span class="skeleton" style="width: 66%"></span></td>
            <td class="cell-actions"><span class="skeleton skeleton--actions"></span></td>
        </tr>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script type="module" src="{{ asset('js/patients.js') }}"></script>
</body>
</html>
