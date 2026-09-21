# Especificaciones Funcionales (Specs)

Documento de especificaciones que guían el desarrollo del módulo.
Actualizado al estado real del código (2026-09-21).

## Spec 1: CRUD de Liquidaciones

Sistema completo de gestión de liquidaciones de arriendo.

- Crear liquidación con propiedad, fechas y N items (cargos/descuentos)
- Listar con búsqueda en tiempo real, filtros por estado/período y paginación
- Editar (solo PENDIENTE), ver detalle, PDF
- Cálculo automático: Neto = Cargos - Descuentos, IVA = 19%, Total = Neto + IVA
- Toggle PAGADA/PENDIENTE (`app_settlement_pay`) y pago directo (`app_settlement_pagar`)

Implementación: `src/Controller/SettlementController.php`,
`src/Repository/SettlementRepository.php` (`createFilteredQueryBuilder`, `getKpiData`, `findDuplicate`).

Validaciones:
- Propiedad y fechas obligatorias
- UniqueEntity + chequeo `findDuplicate`: no duplicados (misma propiedad + mismas fechas)

Estados: PENDIENTE, PAGADA, ANULADA.

## Spec 2: Formulario Dinámico de Items

- Botón "Agregar Item" que clona prototipo de Symfony
- Botón "Eliminar" en cada item
- Cálculo en tiempo real de totales
- Buscador en tiempo real para select de propiedades

Validaciones:
- Exactamente 1 línea por clic (no 2)
- Recalcular totales al agregar/eliminar

## Spec 3: Anulación con Auditoría

Modal de confirmación con campo obligatorio "Motivo" (mín 10 caracteres).

Al confirmar, `motivoAnulacion` y `observacion` guardan:
- Motivo escrito por el usuario
- Email del usuario que anuló
- Fecha y hora de la anulación

Formato: `{motivo} | por {email} el {d/m/Y H:i}`.
Construcción centralizada en `SettlementService::buildAudit()`
(valida mínimo 10 caracteres). No se borra de la BD (trazabilidad).
`SettlementService::cancel/anular` bloquea anular liquidaciones ya facturadas
(pivote `invoice_settlement`).

Implementación: `src/Entity/Settlement.php` (`motivoAnulacion TEXT nullable`),
`src/Service/SettlementService.php`, `SettlementController::delete`.

## Spec 4: Generador de Facturación

Doble capa:
- `InvoicePeriodGenerator`: preview/generate por rango de fechas
  (`inicio/fin/propertyId`), 1 factura por propiedad (o 1 global si hay `propertyId`),
  folio `FAC-{Ym}-{propId}-{rand}`, validación duplicado periodo+folio,
  solo liquidaciones PAGADAS.
- `BillingService` (fachada): `preview(YYYY-MM, companyId?)` con neto/IVA/total
  en memoria, `isPeriodoFacturado()`, `createInvoice()` que delega al generador,
  vincula el pivote `invoice_settlement` y genera el archivo plano.

Rutas: `/invoices/actions/generator`, `/preview-by-period`, `/generate-by-period`.
API: `POST /api/invoices {periodo YYYY-MM, emisor, receptor?, companyId?, propertyId?}`.

Validaciones:
- Solo facturar liquidaciones PAGADAS
- No facturar liquidaciones ya asociadas a factura (`i.id IS NULL`)
- No permitir facturar mismo período dos veces (`ya_facturado` / "YA FACTURADO")

## Spec 5: Archivo Plano (facturacion.cl)

Formato TXT (`BillingService::buildArchivoPlano`):
- ->Encabezado<- con datos del owner
- ->Totales<- con neto, IVA, total
- ->Detalle<- con items (LIQ-{id}, descripción, monto)

Reglas:
- Reemplazar saltos de línea y punto y coma por espacios
- Máximo 60 items
- Descuentos con monto negativo, cargos positivo

## Spec 6: Diseño UI/UX Moderno

- Color primario: #6366f1 (indigo)
- Color éxito: #10b981 (verde)
- Color peligro: #dc2626
- Border-radius: 12-16px en cards
- Iconos: Bootstrap Icons
- Cards con gradientes en headers
- Paginador moderno

## Spec 7: Prevención de Duplicados

- `UniqueEntity` en Settlement: `['property', 'fechaInicio', 'fechaTermino']`
  Mensaje: "Ya existe una liquidación para esta propiedad en este período"
- Chequeo en controlador (`new/edit`) vía `SettlementRepository::findDuplicate()`,
  que excluye ANULADAS y acepta `$excludeId` para edición
- `SettlementService::save()` repite el chequeo a nivel servicio

## Spec 8: Adaptación al Esquema Existente

Tabla `invoice` con columnas:
- id, folio, emisor, receptor, periodo, total, estado, observacion, createdAt, createdBy
- NO existen columnas: rutEmisor, neto, iva, fecha
- Neto e IVA se calculan en memoria desde liquidaciones (Spec 4)

## Spec 9: Relación Many-to-Many Invoice ↔ Settlement

- Tabla pivote: `invoice_settlement` (invoice_id, settlement_id)
- Constraint único: (invoice_id, settlement_id)
- Cascade delete en ambos FK
- Entidad `InvoiceSettlement` con repositorio propio
  (`src/Repository/InvoiceSettlementRepository.php`)
- `Invoice::$invoiceSettlements` OneToMany (cascade persist/remove, orphanRemoval)

## Spec 10: Debugging y Corrección de Errores

Errores resueltos:
1. "Field __name__ has already been rendered": capturar prototipo en variable
2. "Expected Literal, got 'is'": renombrar alias DQL a 'invSet'
3. "operator does not exist: date ~~ unknown": usar rangos de fechas
4. "column rut_emisor does not exist": adaptar al esquema real
5. Se agregan 2 items: usar cloneNode + flag isProcessing
6. Form no guarda: asegurar token CSRF en form_end
7. `BillingService` llamaba a `setRutEmisor/setFecha/setNeto/setIva` inexistentes:
   reescrito sobre el esquema real (Spec 8)
8. `ApiInvoiceController` ordenaba por `fecha` y serializaba campos inexistentes:
   adaptado al esquema real
9. `Invoice.inversedBy=invoiceSettlements` sin propiedad: agregada la colección
10. `SettlementService` incompatible con tests y `ApiSettlementController`
    (`markAsPaid/save/cancel` faltantes): API unificada
11. Tests PHPUnit 12: `@dataProvider` ya no existe → atributos `#[DataProvider]`;
    RUTs de fixture inválidos → reemplazados por RUTs con DV correcto
12. API devolvía 500 sin autenticar: faltaban `JWT_*` en `.env` y claves
    `config/jwt/*.pem` (gitignoradas, generar local)

## Spec 11: SettlementService (lógica de dominio)

`src/Service/SettlementService.php` (`__construct(em, ivaRate = 0.19)`):
- `recalculate()`: suma CARGO/DESCUENTO por `abs(monto)`, neto = cargo - desc;
  lanza `InvalidArgumentException` si neto < 0; formatea a 2 decimales
- `save()`: valida propiedad/fechas, chequea duplicado, recalcula, persiste
- `markAsPaid()` / alias `pagar()`: PENDIENTE → PAGADA
  (`InvalidArgumentException` si ANULADA, `LogicException` si ya PAGADA)
- `cancel($motivo?, $user?)` / alias `anular()`: bloquea si facturada, audita motivo
- `recalcular()` alias histórico

Cubierto por `tests/Service/SettlementServiceTest.php`.

## Spec 12: RUT chileno

`src/Service/RutService.php`:
- `validate()`: algoritmo módulo 11, acepta con/sin puntos y guion
- `clean()`, `format()` (estándar `76.123.456-0`)
- `calculateDv($body)`: DV para el cuerpo numérico

Usado en `ApiCompanyController` (validación + normalización al crear/editar).
Cubierto por `tests/Service/RutServiceTest.php`.

## Spec 13: Seguridad API (SqlGuard)

`src/Service/AI/SqlGuard.php`:
- Solo `SELECT`, máx 800 chars, sin `;` intermedio ni comentarios
- Bloquea DDL/DML (`DROP/DELETE/UPDATE/INSERT/...`) y tablas fuera de
  `company/owner/property/settlement/settlement_item/invoice/invoice_settlement`
- Bloquea columnas sensibles (`password/embedding/roles`) y esquemas `pg_*`
- `enforceLimit()`: agrega/topéa `LIMIT 100`

Cubierto por `tests/Service/AI/SqlGuardTest.php`.

## Spec 14: Seguridad y Acceso

`config/packages/security.yaml`:
- Web: `form_login` con CSRF → `app_dashboard`
- API (`^/api`): stateless JWT (Lexik, `lexik_jwt_authentication.yaml`);
  claves en `config/jwt/*.pem` + `JWT_SECRET_KEY/JWT_PUBLIC_KEY/JWT_PASSPHRASE/JWT_TTL`
- `access_control`: `/login`, `/health`, `/api/login_check`, `/api/docs` públicos;
  resto exige `ROLE_USER`

## Spec 15: Seed de Datos Demo

`src/Command/SeedSettlementsCommand.php` (`app:seed:settlements --truncate --count=N`):
- Crea/asegura usuarios `admin@pop.cl` / `user@pop.cl`, 2 companies, 2 owners,
  hasta 80 properties
- N liquidaciones PAGADA dic-2025 con fechas variadas dentro del mes
  (respeta Unique property+fechas), cada una con items CARGO (+DESCUENTO parcial)
  y totales cargo/descuento/neto/iva/total coherentes, `createdBy = admin@pop.cl`
- 1 ANULADA nov-2025 con `motivoAnulacion` auditado
- Opción `--truncate` borra settlements y properties previas

Comando: `docker compose exec app php bin/console app:seed:settlements --truncate`

## Spec 16: Infraestructura

- `docker-compose.yml`: `db` (`pgvector/pgvector:pg16`), `redis`, `mailer`,
  `app` (php-fpm, entrypoint con espera de DB + `migrate` + `schema:update` +
  creación de admin), `nginx` en `:8080`
- `Makefile`: `up/down/down-v/fix/fix-front/logs/sh/ps/db/lint/reset-db`
  (`DB=db`, acorde al servicio compose)
- Tests: `vendor/bin/phpunit` → 43 tests, 65 assertions, sin errores

## Resumen

16 specs implementadas y validadas:
- CRUD Liquidaciones ✅
- Formulario Dinámico ✅
- Anulación con Auditoría ✅
- Generador de Facturación ✅
- Archivo Plano ✅
- Diseño UI/UX ✅
- Prevención de Duplicados ✅
- Adaptación al Esquema ✅
- Relación M:N ✅
- Debugging ✅
- SettlementService ✅
- RUT chileno ✅
- SqlGuard ✅
- Seguridad y Acceso ✅
- Seed Demo ✅
- Infraestructura ✅
