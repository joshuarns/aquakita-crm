# Preguntas para cerrar antes de producción — Aquakita

El documento de especificación deja varios puntos por confirmar (ver "Nota de
alcance", pág. 13). A continuación se listan agrupados, con **por qué importa**,
una **recomendación por defecto** (ya asumida en el sistema actual) y la decisión
del cliente. Basta confirmar o ajustar cada una.

Leyenda: ✅ = ya implementado con ese valor por defecto · ⬜ = requiere decisión.

---

## 1. Infraestructura y volumen

| # | Pregunta | Por qué importa | Recomendación / estado |
|---|----------|-----------------|------------------------|
| 1.1 | ¿Cuántos usuarios y cuántos leads/mes se esperan? | Define recursos del servidor y si hacen falta colas/índices extra. | ⬜ Estimar (p. ej. <10 usuarios, <2 000 leads/mes → un VPS pequeño basta). |
| 1.2 | ¿Dónde se alojará (hosting propio, Laravel Cloud, VPS)? | Define el proceso de despliegue y respaldos. | ⬜ Ver DESPLIEGUE.md; recomendado VPS/Laravel Cloud con MySQL. |
| 1.3 | ¿Base de datos MySQL o MariaDB? | El sistema usa SQLite en desarrollo; producción en MySQL. | ✅ Preparado para MySQL 8 / MariaDB 10.6+. |
| 1.4 | ¿Zona horaria de operación? | Fechas/horas correctas en captura, tiempos y reportes (§10). | ✅ Configurable con `APP_TIMEZONE` (por defecto `America/Mexico_City`). |
| 1.5 | ¿La interfaz debe ser multi-idioma o solo español? | Hoy la UI está en español; el "idioma" del lead sí es un dato. | ✅ UI en español (el idioma del prospecto se registra aparte). |

## 2. Correo saliente (§4.3)

| # | Pregunta | Por qué importa | Recomendación / estado |
|---|----------|-----------------|------------------------|
| 2.1 | ¿Qué proveedor SMTP se usará (Mailgun, SendGrid, SES, Google Workspace)? | Necesario para enviar los avisos por correo reales. | ⬜ En desarrollo se usa `log`; producción requiere SMTP. |
| 2.2 | ¿Correo y nombre del remitente? | Encabezado "De:" de los avisos. | ⬜ p. ej. `no-reply@aquakita.com`. |
| 2.3 | ¿A qué hora deben salir los avisos diarios de seguimiento? | El comando corre a una hora fija. | ✅ Por defecto 08:00 (ajustable). |

## 3. Captura y duplicados (§3.3)

| # | Pregunta | Por qué importa | Recomendación / estado |
|---|----------|-----------------|------------------------|
| 3.1 | Al detectar un posible duplicado, ¿solo advertir o **bloquear** la captura? | Cambia la experiencia del capturista. | ✅ Hoy **advierte** y permite continuar. |
| 3.2 | ¿Por qué campos se considera duplicado (correo, teléfono, ambos)? | Define la exactitud de la detección. | ✅ Por correo **o** teléfono. |
| 3.3 | ¿Qué campos de captura son obligatorios además del nombre? | Reglas de validación del formulario. | ✅ Solo el nombre es obligatorio; el resto opcional. |

## 4. Ficha y adjuntos (§5)

| # | Pregunta | Por qué importa | Recomendación / estado |
|---|----------|-----------------|------------------------|
| 4.1 | ¿Qué tipos de archivo se permiten adjuntar? | Seguridad y compatibilidad. | ✅ PDF, Word, Excel e imágenes (pdf, doc(x), xls(x), png, jpg, webp). |
| 4.2 | ¿Tamaño máximo por archivo? | Límite de subida y almacenamiento. | ✅ 10 MB por archivo. |
| 4.3 | ¿Los adjuntos deben poder eliminarse o conservarse siempre? | Trazabilidad vs. limpieza. | ✅ Los puede eliminar quien gestiona el lead. |

## 5. Ventas y comercial (§7, §10)

| # | Pregunta | Por qué importa | Recomendación / estado |
|---|----------|-----------------|------------------------|
| 5.1 | ¿En qué moneda(s) se registran los montos de venta? | Reportes y cálculo de resultados. | ⬜ Hoy es un monto simple; ¿una moneda o multi-moneda por país? |
| 5.2 | ¿Reasignar un lead debe notificar también al vendedor anterior? | Comunicación interna. | ✅ Hoy notifica al nuevo vendedor; el anterior no. |
| 5.3 | ¿Los 13 estatus del §7 son definitivos o el cliente querrá editarlos? | Se dejó fuera el CRUD de estatus por seguridad. | ⬜ Confirmar. Si deben editarse, se habilita con cuidado. |

## 6. Exportación (§9)

| # | Pregunta | Por qué importa | Recomendación / estado |
|---|----------|-----------------|------------------------|
| 6.1 | ¿Formato preferido: CSV, Excel o ambos? | Compatibilidad con Mailchimp. | ✅ Ambos disponibles. |
| 6.2 | ¿Integración directa con Mailchimp o solo exportar archivo? | La directa es Etapa 2. | ✅ Etapa 1: exporta archivo (no envía correos). |

## 7. Seguridad, respaldos y soporte (§10)

| # | Pregunta | Por qué importa | Recomendación / estado |
|---|----------|-----------------|------------------------|
| 7.1 | ¿Frecuencia y retención de respaldos de BD y adjuntos? | Continuidad del negocio. | ⬜ Recomendado: BD diaria + adjuntos; retención 30 días. |
| 7.2 | ¿Política de contraseñas (longitud mínima, expiración)? | Seguridad de acceso. | ✅ Mínimo 8 caracteres; sin expiración. |
| 7.3 | ¿Se requiere garantía y soporte posterior? ¿Con qué SLA? | Alcance del contrato. | ⬜ Definir en la cotización. |

---

## Resumen: qué falta decidir (⬜)

1. Volumen esperado y hosting (1.1, 1.2).
2. Proveedor y remitente de correo SMTP (2.1, 2.2).
3. Moneda de las ventas (5.1).
4. ¿Editar estatus comerciales? (5.3).
5. Política de respaldos/retención (7.1).
6. Garantía y soporte posterior (7.3).

Todo lo marcado ✅ ya está funcionando con un valor razonable y puede cambiarse
si el cliente lo prefiere.
