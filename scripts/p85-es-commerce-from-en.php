#!/usr/bin/env php
<?php
/**
 * P85 — Agent EN→ES for commerce plugin catalog plugin (no DE source, no external MT).
 * Preserves {placeholders}, HTML, @i18n refs.
 */
declare(strict_types=1);

function flatten_plugin(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $key => $value) {
        if ($key === '_meta') {
            continue;
        }
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $out += flatten_plugin($value, $path);
        } else {
            $out[$path] = (string) $value;
        }
    }
    return $out;
}

function unflatten_plugin(array $flat): array
{
    $tree = [];
    foreach ($flat as $path => $value) {
        $parts = explode('.', $path);
        $cur = &$tree;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $cur[$part] = $value;
            } else {
                if (!isset($cur[$part]) || !is_array($cur[$part])) {
                    $cur[$part] = [];
                }
                $cur = &$cur[$part];
            }
        }
    }
    return $tree;
}

/** @return list<array{0:string,1:string}> */
function commerce_es_phrase_pairs(): array
{
    static $pairs = null;
    if ($pairs !== null) {
        return $pairs;
    }
    $raw = <<<'TXT'
Sidebar attention for shop activity|Atención en la barra lateral para actividad de la tienda
Enter the provider secret key before saving.|Introduzca la clave secreta del proveedor antes de guardar.
Dashboard|Panel
Fulfillment|Gestión de pedidos
Gift Cards|Tarjetas regalo
Subscriptions|Suscripciones
Settings|Configuración
Documentation|Documentación
Refresh|Actualizar
Loading orders…|Cargando pedidos…
Loading catalog…|Cargando catálogo…
No orders yet.|Aún no hay pedidos.
No orders|Ningún pedido
No orders.|Ningún pedido.
No orders match your search.|Ningún pedido coincide con su búsqueda.
No abandoned checkout attempts.|No hay intentos de pago abandonados.
No orders need shipping.|No hay pedidos por enviar.
No unpaid orders.|No hay pedidos impagados.
No refunded orders.|No hay pedidos reembolsados.
No pending orders.|No hay pedidos pendientes.
No orders to export|No hay pedidos para exportar
Unknown error|Error desconocido
No customer|Ningún cliente
Partially shipped|Enviado parcialmente
Shipped|Enviado
All shipped|Todo enviado
Needs shipping|Por enviar
need shipping|por enviar
Digital fulfillment|Entrega digital
Items|Artículos
Discount|Descuento
Shipping|Envío
Tax|Impuesto
Reverse charge|Inversión del sujeto pasivo
Gift card|Tarjeta regalo
Gift Card|Tarjeta regalo
Orders|Pedidos
Customers|Clientes
Catalog|Catálogo
Inventory|Inventario
Discounts|Descuentos
Close|Cerrar
Cancel|Cancelar
Save|Guardar
Delete|Eliminar
Edit|Editar
Add|Añadir
Back|Volver
Continue|Continuar
Confirm|Confirmar
Search|Buscar
Export|Exportar
Import|Importar
Download|Descargar
Upload|Subir
Status|Estado
Type|Type
Name|Nombre
Email|Correo electrónico
Phone|Teléfono
Address|Dirección
Total|Total
Subtotal|Subtotal
Quantity|Cantidad
Price|Precio
SKU|SKU
Description|Descripción
Notes|Notes
Actions|Actions
Details|Details
Overview|Resumen
Pending|Pendiente
Active|Activo
Inactive|Inactivo
Draft|Borrador
Published|Publicado
Cancelled|Cancelarado
Canceled|Cancelarado
Completed|Completado
Failed|Error
Success|Éxito
Warning|Advertencia
Error|Error
Enabled|Activado
Disabled|Desactivado
Yes|Sí
No|No
All|Todo
None|Ninguno
Optional|Opcional
Required|Obligatorio
Default|Predeterminado
Custom|Personalizado
Template|Plantilla
Templates|Plantillas
Product|Producto
Products|Productoos
Customer|Cliente
Order|Pedido
Subscription|Suscripción
Payment|Pago
Refund|Reembolso
Invoice|Factura
Receipt|Recibo
Checkout|Pago
Cart|Carrito
Coupon|Cupón
Promo code|Código promocional
Shipping address|Dirección de envío
Billing address|Dirección de facturación
Tax rate|Tipo impositivo
Tax rates|Tipos impositivos
Shipping method|Método de envío
Shipping methods|Métodos de envío
Payment method|Método de pago
Payment methods|Métodos de pago
Storefront|Tienda
Admin activity|Actividad de administración
Save activity settings|Guardar configuración de actividad
Notify on|Notificar en
Sidebar attention|Atención en la barra lateral
mint indicator|indicador menta
notification bell|campana de notificaciones
Loading…|Cargando…
Saving…|Guardando…
Sending…|Enviando…
Working…|Procesando…
Please wait…|Espere…
Could not|No se pudo
Failed to|Error al
Are you sure|¿Está seguro
This cannot be undone|Esta acción no se puede deshacer
click|haga clic
Click|Haga clic
select|seleccione
Select|Seleccione
enter|introduzca
Enter|Introduzca
choose|elija
Choose|Elija
open|abra
Open|Abrir
create|crear
Create|Crear
update|actualizar
Update|Actualizar
remove|quitar
Remove|Quitar
restore|restaurar
Restore|Restaurar
duplicate|duplicar
Duplicate|Duplicar
view|ver
View|Ver
show|mostrar
Show|Mostrar
hide|ocultar
Hide|Ocultar
copy|copiar
Copy|Copiar
paste|pegar
Paste|Pegar
apply|aplicar
Apply|Aplicar
clear|borrar
Clear|Borrar
filter|filtrar
Filter|Filtrar
sort|ordenar
Sort|Ordenar
new|nuevo
New|Nuevo
old|antiguo
next|siguiente
Next|Siguiente
previous|anterior
Previous|Anterior
first|primero
last|último
today|hoy
yesterday|ayer
tomorrow|mañana
week|semana
month|mes
year|año
day|día
hour|hora
minute|minuto
second|segundo
Administrator|Administrador
Admin|Admin
User|Usuario
users|usuarios
user|usuario
order|pedido
orders|pedidos
customer|cliente
customers|clientes
product|producto
products|productoos
collection|colección
collections|colecciones
entry|entrada
entries|entradas
line|línea
lines|líneas
item|artículo
items|artículos
amount|importe
currency|moneda
country|país
region|región
city|ciudad
postal code|código postal
ZIP|ZIP
state|Estado
province|provincia
company|empresa
VAT|IVA
tax ID|número de IVA
weight|peso
dimensions|dimensiones
tracking|seguimiento
carrier|transportista
warehouse|almacén
stock|stock
in stock|en stock
out of stock|sen stock
low stock|stock bajo
reindex|reindexar
Reindex|Reindexar
sync|sincronizar
Sync|Sincronizar
webhook|webhook
Webhooks|Webhooks
Stripe|Stripe
PayPal|PayPal
test email|correo de prueba
Test email|Correo de prueba
Send test|Enviar prueba
Settings →|Configuración →
Open Settings|Abrir configuración
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

function protect_tokens(string $s, array &$store): string
{
    $i = 0;
    return preg_replace_callback(
        '/(\{\{[^{}]+\}\}|\{[a-zA-Z0-9_.]+\}|%[sd]|@[a-zA-Z0-9_.:-]+|<[^>]+>)/',
        static function ($m) use (&$store, &$i) {
            $key = "\x00T" . $i++ . "\x00";
            $store[$key] = $m[0];
            return $key;
        },
        $s
    ) ?? $s;
}

function restore_tokens(string $s, array $store): string
{
    return strtr($s, $store);
}

function translate_commerce_en_to_es(string $en): string
{
    if ($en === '' || preg_match('/^[\d\s\W]+$/u', $en)) {
        return $en;
    }
    $store = [];
    $s = protect_tokens($en, $store);
    foreach (commerce_es_phrase_pairs() as [$from, $to]) {
        if ($from !== '' && str_contains($s, $from)) {
            $s = str_replace($from, $to, $s);
        }
    }
    // Pattern fixes
    $s = preg_replace('/\bLoading ([^…]+)…/u', 'Cargando $1…', $s) ?? $s;
    $s = preg_replace('/\bNo ([^.\n]+) yet\./u', 'Aún no hay $1.', $s) ?? $s;
    $s = preg_replace('/\bFailed to ([^.\n]+)\./u', 'Error al $1.', $s) ?? $s;
    $s = preg_replace('/\bSave ([^.\n]+)/u', 'Guardar $1', $s) ?? $s;
    return restore_tokens($s, $store);
}

$root = '/agent/repos/Birkly-Plugin-Commerce';
$enData = json_decode((string) file_get_contents($root . '/lang/en.json'), true);
$enFlat = flatten_plugin($enData['plugins']['commerce'] ?? []);
$esFlat = [];
foreach ($enFlat as $k => $v) {
    $esFlat[$k] = translate_commerce_en_to_es($v);
}

$commerceEsCurated = [
    'settings_emails_help' => 'Plantillas de recibo, envío, reembolso, fallo de pago de suscripción (dunning) y seguimiento retrasado. Active o desactive cada disparador en la lista. Pedido pagado es el recibo enviado tras el pago para todos los pedidos (incluidas donaciones). El agradecimiento de donación opcional es un disparador aparte para automatización o reenvío manual. Activar una plantilla solo prepara el mensaje; no envía correo. Elija una conexión predeterminada abajo y sobrescriba por plantilla si necesita remitentes distintos.',
];
foreach ($commerceEsCurated as $path => $esVal) {
    if (isset($esFlat[$path])) {
        $esFlat[$path] = $esVal;
    }
}

$esData = [
    '_meta' => ['name' => 'Spanish', 'native_name' => 'Español', 'flag' => '🇪🇸'],
    'plugins' => ['commerce' => unflatten_plugin($esFlat)],
];
file_put_contents($root . '/lang/es.json', json_encode($esData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
echo 'Wrote ' . count($esFlat) . " keys\n";
