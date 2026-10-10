-- =============================================================================
-- ConectaSUS — Exercício SQL obrigatório
-- Compatível com MySQL 8. Tabelas e colunas conforme as migrations do projeto:
--   health_units (id, uuid, name, city, created_at, updated_at, deleted_at)
--   patients     (id, uuid, name, cpf, birth_date, phone, health_unit_id,
--                 created_at, updated_at, deleted_at)
--
-- Observação: as duas tabelas usam soft delete (coluna deleted_at). Registros
-- excluídos logicamente são ignorados com a condição "deleted_at IS NULL".
-- =============================================================================


-- -----------------------------------------------------------------------------
-- Consulta 1
-- Lista os pacientes com o nome da respectiva unidade de saúde.
-- INNER JOIN: retorna apenas pacientes que possuem unidade vinculada
-- (a FK é obrigatória, portanto todos os pacientes ativos aparecem).
-- -----------------------------------------------------------------------------
SELECT
    p.id            AS paciente_id,
    p.name          AS paciente,
    p.cpf,
    hu.name         AS unidade_de_saude,
    hu.city         AS cidade
FROM patients AS p
INNER JOIN health_units AS hu
    ON hu.id = p.health_unit_id
WHERE p.deleted_at IS NULL
  AND hu.deleted_at IS NULL
ORDER BY p.name;


-- -----------------------------------------------------------------------------
-- Consulta 2
-- Conta os pacientes por unidade de saúde, incluindo unidades sem pacientes.
-- LEFT JOIN garante que toda unidade apareça; COUNT(p.id) conta apenas as
-- linhas em que existe paciente (NULL não é contado), resultando em 0 para
-- unidades vazias. O filtro de pacientes excluídos fica no ON, e não no WHERE,
-- para não eliminar as unidades sem pacientes do resultado.
-- -----------------------------------------------------------------------------
SELECT
    hu.id           AS unidade_id,
    hu.name         AS unidade_de_saude,
    hu.city         AS cidade,
    COUNT(p.id)     AS total_pacientes
FROM health_units AS hu
LEFT JOIN patients AS p
    ON p.health_unit_id = hu.id
   AND p.deleted_at IS NULL
WHERE hu.deleted_at IS NULL
GROUP BY hu.id, hu.name, hu.city
ORDER BY total_pacientes DESC, hu.name;


-- -----------------------------------------------------------------------------
-- Consulta 3
-- Lista os pacientes cujo nome contenha um termo informado (busca parcial).
-- LIKE com curingas (%) antes e depois do termo. Em MySQL, com a collation
-- padrão utf8mb4_unicode_ci, a comparação não diferencia maiúsculas de
-- minúsculas. Substitua 'Maria' pelo termo desejado (ou use um parâmetro
-- preparado, como faz a aplicação).
-- -----------------------------------------------------------------------------
SELECT
    p.id            AS paciente_id,
    p.name          AS paciente,
    p.cpf,
    p.birth_date    AS data_nascimento,
    hu.name         AS unidade_de_saude
FROM patients AS p
INNER JOIN health_units AS hu
    ON hu.id = p.health_unit_id
WHERE p.name LIKE CONCAT('%', 'Maria', '%')
  AND p.deleted_at IS NULL
ORDER BY p.name;
