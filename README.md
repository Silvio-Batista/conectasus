# ConectaSUS — Sistema de Gestão de Pacientes

Aplicação web para gerenciar pacientes vinculados a unidades de saúde, desenvolvida como resposta ao desafio técnico para Desenvolvedor PHP Júnior.

O backend expõe uma API REST em Laravel 12 e o frontend é uma página única servida pelo Blade, construída com Bootstrap 5 e JavaScript puro (Fetch API), sem frameworks SPA e sem dependência de Node.js.

Desenvolvido por **Silvio Batista** · [LinkedIn](https://linkedin.com/in/silvio-p-batista) · [GitHub](https://github.com/Silvio-Batista) · [Portfólio](https://portfolio-silvio.vercel.app) — veja [Processo de desenvolvimento e uso de IA](#processo-de-desenvolvimento-e-uso-de-ia).

## Funcionalidades

- Listagem de pacientes ordenada por nome, com a unidade de saúde de cada um
- Busca parcial por nome (debounce de 300 ms, sem condições de corrida)
- Paginação de 10 registros por página
- Cadastro, visualização, edição e exclusão (com confirmação) de pacientes
- Associação do paciente a uma unidade de saúde
- Consulta das unidades de saúde disponíveis
- Validação completa no backend (mensagens em português) e validação de apoio no frontend
- Máscaras visuais de CPF e telefone; os valores são armazenados apenas com dígitos
- Indicadores reais (total de pacientes e de unidades), estados de carregamento, vazio e erro, toasts de feedback
- Interface responsiva (tabela no desktop, cartões no celular) e acessível (labels, foco, `aria-*`)

## Tecnologias

| Camada | Tecnologias |
|---|---|
| Backend | PHP 8.4, Laravel 12, Eloquent, Form Requests, API Resources, PHPUnit 11 |
| Banco | MySQL 8 (SQLite em memória apenas nos testes) |
| Frontend | Blade, HTML5, CSS3, Bootstrap 5.3, Bootstrap Icons, JavaScript ES6+ (módulos, Fetch API) |
| Infra | Docker, Docker Compose, PHP-FPM, Nginx |

## Requisitos

**Com Docker (recomendado):** Docker Desktop ou Docker Engine com Compose v2.

**Sem Docker:** PHP ≥ 8.4 com as extensões `pdo_mysql`, `mbstring`, `intl`, `bcmath` e `zip`; Composer 2; MySQL 8. Para rodar os testes localmente, a extensão `pdo_sqlite` deve estar habilitada.

> O `composer.lock` utiliza componentes Symfony 8.x, que exigem PHP ≥ 8.4.1. Por isso a imagem Docker usa `php:8.4-fpm`.

## Estrutura do projeto

```text
conectasus/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── HealthUnitController.php   # GET /api/health-units
│   │   │   └── PatientController.php      # CRUD de pacientes
│   │   ├── Requests/
│   │   │   ├── StorePatientRequest.php    # validação + normalização (cadastro)
│   │   │   └── UpdatePatientRequest.php   # validação (PUT completo / PATCH parcial)
│   │   └── Resources/
│   │       ├── HealthUnitResource.php
│   │       └── PatientResource.php
│   └── Models/
│       ├── HealthUnit.php                 # hasMany(Patient)
│       └── Patient.php                    # belongsTo(HealthUnit), scope de busca
├── bootstrap/app.php                      # respostas JSON e 404 em português na API
├── database/
│   ├── factories/                         # HealthUnitFactory, PatientFactory (opcional)
│   ├── migrations/                        # health_units e patients
│   └── seeders/HealthUnitSeeder.php       # 3 unidades iniciais (idempotente)
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile (+ conf.d, php-fpm.d)
├── lang/pt_BR*                            # traduções complementares
├── public/
│   ├── css/app.css
│   └── js/api.js, js/patients.js
├── resources/views/patients.blade.php     # única página da aplicação
├── routes/api.php, routes/web.php
├── sql/consultas.sql                      # exercício SQL obrigatório
├── tests/Feature/                         # testes da API e da página
├── compose.yaml
├── .env.example
└── README.md
```

## Instalação com Docker

```bash
# 1. Variáveis de ambiente (ajuste MYSQL_* se desejar; faça isso antes do primeiro "up")
cp .env.example .env            # Windows (PowerShell): Copy-Item .env.example .env

# 2. Build e subida dos serviços (app = PHP-FPM, web = Nginx, db = MySQL 8)
docker compose up -d --build

# 3. Dependências PHP (instaladas em um volume nomeado dentro do container)
docker compose exec app composer install

# 4. Chave da aplicação, migrations e unidades de saúde iniciais
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Acesse **http://localhost:8080** (interface) e **http://localhost:8080/api/patients** (API).

Observações:

- O MySQL do Docker aguarda ficar saudável (healthcheck) antes de o PHP iniciar. O Nginx também possui healthcheck via rota `/up`.
- O compose injeta `DB_HOST=db` e as credenciais `MYSQL_*` no container; os valores `DB_*` do `.env` servem apenas para execução local.
- A porta do MySQL **não** é publicada por padrão. Para acessar com um cliente externo, descomente o bloco `ports` do serviço `db` em `compose.yaml`.
- O `migrate:fresh` nunca é executado automaticamente; o banco fica em um volume persistente (`db_data`).
- Para alterar a porta externa, defina `APP_PORT` no `.env`.

Comandos úteis:

```bash
docker compose ps                                  # status e saúde dos containers
docker compose logs -f web app                     # logs
docker compose exec app php artisan route:list     # rotas
docker compose down                                # para os containers (mantém os dados)
docker compose down -v                             # para e APAGA os volumes (banco e vendor)
```

## Instalação sem Docker

```bash
composer install
cp .env.example .env            # ajuste DB_HOST, DB_DATABASE, DB_USERNAME e DB_PASSWORD
php artisan key:generate
php artisan migrate --seed
php artisan serve               # http://localhost:8000
```

O banco `conectasus` precisa existir no MySQL local (`CREATE DATABASE conectasus;`). Há também um atalho que executa esses passos em sequência: `composer run setup`.

## Configuração do `.env`

| Variável | Uso |
|---|---|
| `APP_URL`, `APP_PORT` | URL/porta externa (Docker publica `APP_PORT`, padrão 8080) |
| `APP_TIMEZONE` | Fuso horário da aplicação (padrão `America/Belem`), usado na regra "data não futura" |
| `DB_*` | Conexão MySQL na execução **local** |
| `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | Criação do MySQL do Docker e credenciais do container `app` |

## Banco de dados

Migrations (Schema Builder):

- `health_units`: `id`, `uuid` (único, gerado automaticamente pelo Laravel), `name` (150), `city` (100), timestamps, `deleted_at`
- `patients`: `id`, `uuid` (único), `name` (150), `cpf` (CHAR 11, único), `birth_date`, `phone` (20, opcional), `health_unit_id` (FK com `restrictOnDelete`), timestamps, `deleted_at`

Relacionamentos: `HealthUnit::patients()` (hasMany) e `Patient::healthUnit()` (belongsTo). A FK impede remover uma unidade que ainda possua pacientes.

```bash
php artisan migrate            # aplica as migrations
php artisan db:seed            # cria as unidades (pode ser repetido sem duplicar)
php artisan migrate:rollback   # desfaz o último lote
```

Dados de desenvolvimento (opcional) com a factory de pacientes:

```bash
php artisan tinker --execute="App\Models\Patient::factory()->count(15)->create(['health_unit_id' => fn () => App\Models\HealthUnit::inRandomOrder()->first()->id]);"
```

Acesso ao banco no Docker:

```bash
docker compose exec db mysql -u conectasus -p conectasus     # senha: MYSQL_PASSWORD do .env
```

## API

Base: `http://localhost:8080/api`. Todas as respostas são JSON, inclusive erros.

| Método | Endpoint | Descrição | Sucesso |
|---|---|---|---|
| GET | `/patients` | Lista pacientes (ordenados por nome, 10 por página) | 200 |
| GET | `/patients?search=Maria&page=2` | Busca parcial por nome, com paginação | 200 |
| GET | `/patients/{id}` | Consulta um paciente | 200 |
| POST | `/patients` | Cadastra um paciente | 201 |
| PUT | `/patients/{id}` | Atualiza um paciente (todos os campos) | 200 |
| PATCH | `/patients/{id}` | Atualiza parcialmente (apenas os campos enviados) | 200 |
| DELETE | `/patients/{id}` | Exclui um paciente | 204 (sem corpo) |
| GET | `/health-units` | Lista unidades de saúde (ordenadas por nome) | 200 |

Códigos de erro: `404` registro/rota inexistente (`{"message": "Registro não encontrado."}`), `422` validação (estrutura padrão do Laravel com `message` e `errors` por campo).

### Validações

| Campo | Regras |
|---|---|
| `name` | obrigatório, texto, máx. 150 |
| `cpf` | obrigatório, exatamente 11 dígitos (máscara removida antes de validar/salvar, zeros à esquerda preservados), único |
| `birth_date` | obrigatório, data válida, não pode ser futura |
| `phone` | opcional, 10 ou 11 dígitos (máscara removida) |
| `health_unit_id` | obrigatório, deve existir em `health_units` (e não estar excluída) |

### Exemplos

Cadastro (as máscaras são aceitas e normalizadas):

```bash
curl -X POST http://localhost:8080/api/patients \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Maria Silva","cpf":"012.345.678-90","birth_date":"1995-05-15","phone":"(91) 99999-9999","health_unit_id":1}'
```

```json
{
  "data": {
    "id": 1,
    "uuid": "01a12764-f185-709a-8cdc-5800152e38b7",
    "name": "Maria Silva",
    "cpf": "01234567890",
    "birth_date": "1995-05-15",
    "phone": "91999999999",
    "health_unit_id": 1,
    "health_unit": { "id": 1, "uuid": "…", "name": "UBS PA", "city": "Belém" }
  }
}
```

Erro de validação (HTTP 422):

```json
{
  "message": "Este CPF já está cadastrado.",
  "errors": { "cpf": ["Este CPF já está cadastrado."] }
}
```

Listagem paginada (HTTP 200): `{"data": [...], "links": {...}, "meta": {"current_page": 1, "last_page": 2, "per_page": 10, "total": 14, ...}}`.

Outras chamadas:

```bash
curl "http://localhost:8080/api/patients?search=mar&page=1"
curl http://localhost:8080/api/patients/1
curl -X PATCH http://localhost:8080/api/patients/1 -H "Content-Type: application/json" -d '{"phone":"9132221111"}'
curl -X DELETE http://localhost:8080/api/patients/1 -i
curl http://localhost:8080/api/health-units
```

## Testes

A suíte usa PHPUnit com `RefreshDatabase` sobre **SQLite em memória** — nunca toca o banco de desenvolvimento. `tests/bootstrap.php` e `tests/TestCase.php` garantem isso mesmo dentro do Docker, onde o processo recebe `DB_CONNECTION=mysql` do compose.

```bash
docker compose exec app php artisan test     # no Docker
php artisan test                              # local
```

Se o PHP local não tiver `pdo_sqlite` habilitado ("could not find driver"), habilite `extension=pdo_sqlite` no `php.ini` ou execute:

```bash
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit
```

Cobertura (35 testes, 177 asserções): listagem ordenada com unidade, lista vazia, cadastro válido, zeros à esquerda no CPF, telefone opcional, nome obrigatório e tamanho máximo, CPF com tamanho inválido, CPF duplicado (cadastro e edição), data futura e inválida, telefone inválido, unidade inexistente e excluída, campos obrigatórios, consulta e 404, PUT (ignorando o próprio CPF), PUT incompleto, PATCH parcial, exclusão (204 + 404 posterior), busca parcial, paginação, links de paginação com o termo de busca, unidades ordenadas/somente leitura, UUID automático, seeder idempotente, FK `restrict`, relacionamentos e renderização da página.

## Exercício SQL

As três consultas obrigatórias estão em [`sql/consultas.sql`](sql/consultas.sql): pacientes com suas unidades (`INNER JOIN`), contagem de pacientes por unidade incluindo unidades vazias (`LEFT JOIN` + `COUNT` + `GROUP BY`) e busca por termo no nome (`LIKE`). Foram executadas e validadas no MySQL 8 do Docker.

## Decisões técnicas

- **MVC do Laravel sem camadas extras**: controllers finos, Form Requests para validar e normalizar, API Resources para o JSON. Sem repositórios/serviços desnecessários.
- **UUID e soft delete** nas duas tabelas: o `id` continua sendo a chave primária e o identificador das rotas; o `uuid` é gerado pelo `HasUuids`. A exclusão é lógica (`deleted_at`), e registros excluídos saem da listagem e retornam 404. Consequência: o CPF de um paciente excluído permanece reservado pelo índice único.
- **Respostas JSON na API mesmo sem `Accept: application/json`** e mensagens de 404 em português (`bootstrap/app.php`).
- **Docker**: `vendor` do container fica em um volume nomeado — em hosts Windows/macOS o bind mount deixava cada requisição com vários segundos; com o volume a latência caiu para ~0,3 s. O código da aplicação continua montado por volume.
- **Frontend sem build**: Bootstrap, ícones e fonte via CDN (com SRI), CSS e JS próprios em `public/`. Dados da API são sempre inseridos com `textContent`.

## Processo de desenvolvimento e uso de IA

Este projeto foi construído com o apoio do **Cursor**, editor com agente de IA integrado. A escrita do código foi feita pela IA, mas o trabalho foi dirigido do início ao fim por mim, **Silvio Batista**: o prompt que orientou todo o desenvolvimento foi desenhado e arquitetado por mim, funcionando como a especificação técnica do projeto.

O que o prompt definiu antes de qualquer linha de código:

- **Contexto e escopo**: o problema a resolver, as funcionalidades obrigatórias e o que não deveria ser implementado (autenticação, permissões, filas, CRUD de unidades, abstrações desnecessárias).
- **Stack e arquitetura**: Laravel 12 em MVC expondo API REST, Eloquent, Form Requests e API Resources; Blade apenas para servir a página; Bootstrap 5 e JavaScript puro com Fetch API; Docker com PHP-FPM, Nginx e MySQL 8; a responsabilidade de cada camada e a estrutura de diretórios.
- **Modelagem e contrato**: tabelas, colunas, índices e chaves estrangeiras; relacionamentos Eloquent; seeders; todos os endpoints com seus códigos HTTP; regras de validação campo a campo com as mensagens em português; o formato exato das respostas JSON.
- **Qualidade**: testes automatizados com os cenários mínimos a cobrir, banco de testes isolado, exercício SQL obrigatório, regras de código limpo e proibição de dados mockados, TODOs ou botões sem função.
- **Experiência do usuário**: direção visual, paleta de cores, tipografia, estados de carregamento, vazio, erro e sucesso, responsividade e acessibilidade.
- **Método de execução**: desenvolvimento em fases (backend validado antes do frontend), etapas ordenadas, verificações obrigatórias em cada etapa, organização dos commits e critérios de aceite para considerar o projeto concluído.

Durante a execução, acompanhei o trabalho do agente, revisei o código gerado, tomei as decisões de modelagem que fugiam ao enunciado (UUID nos pacientes, exclusão lógica e as unidades de saúde iniciais), validei o ambiente Docker e a aplicação na minha máquina e organizei o repositório Git. O resultado reflete, portanto, uma especificação pensada por um desenvolvedor e executada com o apoio de IA, com cada requisito verificado antes de ser dado como pronto.

## Autor

**Silvio Batista**

- LinkedIn: [linkedin.com/in/silvio-p-batista](https://linkedin.com/in/silvio-p-batista)
- GitHub: [github.com/Silvio-Batista](https://github.com/Silvio-Batista)
- Portfólio: [portfolio-silvio.vercel.app](https://portfolio-silvio.vercel.app)
