<?php
declare(strict_types=1);

/** @return list<array{0:string,1:string}> */
function p85_es_phrase_pairs(): array
{
    static $pairs = null;
    if ($pairs !== null) {
        return $pairs;
    }
    $raw = <<<'TXT'
Automate your workflows with triggers, conditions, and actions|Automatice sus flujos con disparadores, condiciones y acciones
Ask questions about your content in plain language|Haga preguntas sobre su contenido en lenguaje natural
Complete these steps to get your site running:|Complete estos pasos para poner en marcha su sitio:
Learn Birkly features with guided assistance|Conozca las funciones de Birkly con asistencia guiada
Define what happens when automation runs|Defina qué ocurre cuando se ejecuta la automatización
Upload a profile picture (JPG/PNG/WebP).|Suba una foto de perfil (JPG/PNG/WebP).
Get help writing and editing entries|Obtenga ayuda para escribir y editar entradas
Changing Storage Backend Warning|Advertencia al cambiar el backend de almacenamiento
No abandoned checkout attempts.|No hay intentos de pago abandonados.
No orders match your search.|Ningún pedido coincide con su búsqueda.
Backup Data Before Migrating|Hacer copia de seguridad antes de migrar
No orders need shipping.|No hay pedidos por enviar.
Advanced Javascript API|API JavaScript avanzada
Save activity settings|Guardar configuración de actividad
Activity And Audit Log|Actividad y registro de auditoría
Birkly Running Version|Versión en ejecución de Birkly
This cannot be undone|Esta acción no se puede deshacer
Enter automation name|Introduzca el nombre de la automatización
Checking for updates|Buscando actualizaciones
Admin panel language|Idioma del panel de administración
Birkly Cloud account|Cuenta de Birkly Cloud
Click to upload logo|Haga clic para subir el logotipo
No refunded orders.|No hay pedidos reembolsados.
No orders to export|No hay pedidos para exportar
Digital fulfillment|Entrega digital
No pending orders.|No hay pedidos pendientes.
No unpaid orders.|No hay pedidos impagados.
Partially shipped|Enviado parcialmente
Sidebar attention|Atención en la barra lateral
notification bell|campana de notificaciones
Check for updates|Buscar actualizaciones
Admin Login Email|Correo de inicio de sesión del administrador
API Documentation|Documentación de la API
Brief Description|Descripción breve
Loading catalog…|Cargando catálogo…
Shipping address|Dirección de envío
Shipping methods|Metodos de envío
Account Settings|Configuración de la cuenta
Loading orders…|Cargando pedidos…
Billing address|Dirección de facturación
Shipping method|Metodo de envío
Payment methods|Metodos de pago
Role Management|Gestión de roles
API Information|Información de la API
Permanent (301)|Permanente (301)
No orders yet.|Aún no hay pedidos.
Needs shipping|Por enviar
Reverse charge|Inversión del sujeto pasivo
Payment method|Metodo de pago
Admin activity|Actividad de administración
mint indicator|indicador menta
Add First User|Añadir primer usuario
Approval Queue|Cola de aprobación
Client Library|Biblioteca cliente
Infrastructure|Infraestructura
Subscriptions|Suscripciones
Documentation|Documentation
Unknown error|Error desconocido
need shipping|por enviar
Administrator|Administrador
Open Settings|Abrir configuración
Add Condition|Añadir condición
Add Sub-Entry|Añadir subentrada
Certification|Certificación
MySQL/MariaDB|MySQL/MariaDB
Subscription|Suscripción
Please wait…|Espere…
Are you sure|¿Está seguro
out of stock|sin stock
Save changes|Guardar cambios
Add New Role|Añadir nuevo rol
Add New User|Añadir nuevo usuario
Bulk Actions|Acciones masivas
Restrictions|Restricciones
Fulfillment|Gestión de pedidos
No customer|Ningún clientee
All shipped|Todo enviado
Description|Description
collections|collections
postal code|código postal
Suggestions|Sugerencias
Invitations|Invitaciones
Gift Cards|Tarjetas regalo
No orders.|Ningún pedido.
Promo code|Código promocional
Storefront|Tienda
collection|collection
dimensions|dimensions
test email|correo de prueba
Test email|Correo de prueba
Settings →|Configuración →
Browse Hub|Explorar Hub
Add Action|Añadir acción
PostgreSQL|PostgreSQL
Dashboard|Panel
No orders|Ningún pedido
Gift card|Tarjeta regalo
Gift Card|Tarjeta regalo
Customers|Clientees
Inventory|Inventario
Discounts|Descuentos
Published|Publicado
Cancelled|Cancelado
Completed|Completado
Templates|Plantillas
Tax rates|Tipo impositivo
Notify on|Notificar
Could not|No se puede
Failed to|Error al
duplicate|duplicar
Duplicate|Duplicar
yesterday|ayer
customers|clientees
warehouse|almacen
low stock|stock bajo
Send test|Enviar prueba
All Media|Todos los medios
All Types|Todos los tipos
Read only|Solo lectura
Documents|Documentos
Settings|Configuración
Discount|Descuento
Shipping|Envío
Continue|Continuar
Download|Descargar
Subtotal|Subtotal
Quantity|Cantidad
Overview|Resumen
Inactive|Inactivo
Canceled|Cancelado
Disabled|Desactivado
Optional|Opcional
Required|Obligatorio
Template|Plantilla
Products|Productos
Customer|Cliente
Checkout|Pago
Tax rate|Tipo impositivo
Loading…|Cargando…
Sending…|Enviando…
Working…|Procesando…
previous|anterior
Previous|Anterior
tomorrow|mañana
customer|cliente
products|productos
currency|moneda
province|province
tracking|seguimiento
in stock|en stock
Webhooks|Webhooks
Commerce|Commerce
Refresh|Actualizar
Shipped|Enviado
Catalog|Catálogo
Confirm|Confirmar
Address|Dirección
Actions|Actions
Details|Details
Pending|Pendiente
Success|Éxito
Warning|Advertencia
Enabled|Activado
Default|Predeterminado
Product|Producto
Payment|Pago
Invoice|Factura
Receipt|Recibo
Saving…|Guardando…
restore|restaurar
Restore|Restaurar
product|producto
entries|entradas
country|país
company|empresa
carrier|transportista
reindex|reindexar
Reindex|Reindexar
webhook|webhook
API Key|Clave API
Sources|Fuentes
Console|Consola
Service|Servicio
Orders|Pedidos
Cancel|Cancelar
Delete|Eliminar
Search|Buscar
Export|Exportar
Import|Importar
Upload|Subir
Status|Estado
Active|Activo
Failed|Error
Custom|Personalizado
Refund|Reembolso
Coupon|Coupon
select|Seleccione
Select|Seleccione
choose|elija
Choose|Elija
create|crear
Create|Crear
update|actualizar
Update|Actualizar
remove|quitar
Remove|Quitar
filter|filtrar
Filter|Filtrar
minute|minute
second|segundo
orders|pedidos
amount|importe
region|región
tax ID|número de IVA
weight|peso
Stripe|Stripe
PayPal|PayPal
Stable|Estable
Items|Artículos
Close|Cerrar
Email|Correo electrónico
Phone|Telefono
Total|Total
Price|Precio
Notes|Notes
Draft|Borrador
Error|Error
Order|Pedido
click|haga clic
Click|Haga clic
enter|Introduzca
Enter|Introduzca
paste|pegar
Paste|Pegar
apply|aplicar
Apply|Aplicar
clear|borrar
Clear|Borrar
first|primero
today|hoy
month|mes
Admin|Admin
users|usuarios
order|pedido
entry|entrada
lines|líneas
items|artículos
state|Estado
stock|stock
Panel|Panel
Alpha|Alfa
Hooks|Hooks
Save|Guardar
Edit|Editar
Back|Volver
Type|Type
Name|Nombre
None|Ninguno
Cart|Carrito
open|abra
Open|Abrir
view|ver
View|Ver
show|mostrar
Show|Mostrar
hide|ocultar
Hide|Ocultar
copy|copiar
Copy|Copiar
sort|ordenar
Sort|Ordenar
next|siguiente
Next|Siguiente
last|último
week|semana
year|año
hour|hora
User|Usuario
user|usuario
line|línea
item|artículo
city|ciudad
sync|sincronizar
Sync|Sincronizar
Auto|Auto
Tax|Impuesto
Add|Añadir
SKU|SKU
Yes|Sí
All|Todo
new|nuevo
New|Nuevo
old|antiguo
day|día
ZIP|código postal
VAT|IVA
No|No

TXT;
    $pairs = [];
    foreach (explode("\n", trim($raw)) as $line) {
        if ($line === '' || !str_contains($line, '|')) {
            continue;
        }
        [$en, $es] = explode('|', $line, 2);
        $pairs[] = [$en, $es];
    }
    usort($pairs, static fn ($a, $b) => strlen($b[0]) <=> strlen($a[0]));
    return $pairs;
}

