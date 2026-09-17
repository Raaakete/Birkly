<?php
declare(strict_types=1);

/** German admin strings → Spanish (Commerce plugin; phrase-safe, no FR). */
function p85_es_commerce_de_to_es(string $de): string
{
    static $pairs = null;
    if ($pairs !== null) {
        return strtr($de, $pairs);
    }
    $raw = <<<'TXT'
Bestellungen|Pedidos
Bestellung|Pedido
Kunden|Clientes
Kunde|Cliente
Produkte|Productos
Produkt|Producto
Versand|Envío
Lieferung|Entrega
Zahlung|Pago
Zahlungen|Pagos
Rückerstattung|Reembolso
Rechnung|Factura
Beleg|Recibo
Abonnement|Suscripción
Abonnements|Suscripciones
Rabatt|Descuento
Rabatte|Descuentos
Steuer|Impuesto
Steuern|Impuestos
Warenkorb|Carrito
Kasse|Pago
Gutschein|Cupón
Geschenkkarte|Tarjeta regalo
Geschenkkarten|Tarjetas regalo
Katalog|Catálogo
Inventar|Inventario
Lagerbestand|Stock
Lager|Almacén
Versandadresse|Dirección de envío
Rechnungsadresse|Dirección de facturación
Versandmethode|Método de envío
Versandmethoden|Métodos de envío
Zahlungsmethode|Método de pago
Zahlungsmethoden|Métodos de pago
Verbindungen|Conexiones
Verbindung|Conexión
Einstellungen|Configuración
Dokumentation|Documentación
Aktualisieren|Actualizar
Speichern|Guardar
Abbrechen|Cancelar
Löschen|Eliminar
Bearbeiten|Editar
Hinzufügen|Añadir
Zurück|Volver
Weiter|Continuar
Bestätigen|Confirmar
Suchen|Buscar
Exportieren|Exportar
Importieren|Importar
Herunterladen|Descargar
Hochladen|Subir
Status|Estado
Name|Nombre
E-Mail|Correo electrónico
Telefon|Teléfono
Adresse|Dirección
Gesamt|Total
Zwischensumme|Subtotal
Menge|Cantidad
Preis|Precio
Beschreibung|Descripción
Notizen|Notas
Aktionen|Acciones
Details|Detalles
Übersicht|Resumen
Ausstehend|Pendiente
Aktiv|Activo
Inaktiv|Inactivo
Entwurf|Borrador
Veröffentlicht|Publicado
Storniert|Cancelado
Abgeschlossen|Completado
Fehlgeschlagen|Error
Erfolg|Éxito
Warnung|Advertencia
Fehler|Error
Aktiviert|Activado
Deaktiviert|Desactivado
Ja|Sí
Nein|No
Alle|Todo
Keine|Ninguno
Optional|Opcional
Erforderlich|Obligatorio
Standard|Predeterminado
Benutzerdefiniert|Personalizado
Vorlage|Plantilla
Vorlagen|Plantillas
Wird geladen…|Cargando…
Speichern…|Guardando…
Senden…|Enviando…
Bitte warten…|Espere…
Sind Sie sicher|¿Está seguro
Diese Aktion kann nicht rückgängig gemacht werden|Esta acción no se puede deshacer
Klicken Sie|Haga clic
Auswählen|Seleccione
Eingeben|Introduzca
Öffnen|Abrir
Erstellen|Crear
Aktualisieren|Actualizar
Entfernen|Quitar
Wiederherstellen|Restaurar
Duplizieren|Duplicar
Anzeigen|Ver
Ausblenden|Ocultar
Kopieren|Copiar
Einfügen|Pegar
Anwenden|Aplicar
Löschen|Borrar
Filtern|Filtrar
Sortieren|Ordenar
Neu|Nuevo
Alt|Antiguo
Weiter|Siguiente
Zurück|Anterior
Erste|Primero
Letzte|Último
heute|hoy
gestern|ayer
morgen|mañana
Woche|semana
Monat|mes
Jahr|año
Tag|día
Stunde|hora
Minute|minuto
Sekunde|segundo
Administrator|Administrador
Benutzer|Usuario
Bestellung|pedido
Bestellungen|pedidos
Kunde|cliente
Kunden|clientes
Produkt|producto
Produkte|productos
Sammlung|colección
Sammlungen|colecciones
Eintrag|entrada
Einträge|entradas
Zeile|línea
Zeilen|líneas
Artikel|artículo
Artikel|artículos
Betrag|importe
Währung|moneda
Land|país
Region|región
Stadt|ciudad
Postleitzahl|código postal
Bundesland|Estado
Provinz|provincia
Unternehmen|empresa
Gewicht|peso
Abmessungen|dimensiones
Sendungsverfolgung|seguimiento
Spediteur|transportista
Lager|almacén
auf Lager|en stock
nicht auf Lager|sin stock
niedriger Bestand|stock bajo
Neu indexieren|Reindexar
synchronisieren|sincronizar
Synchronisieren|Sincronizar
Test-E-Mail|Correo de prueba
Noch keine|Aún no hay
Keine |Ningún 
Kein |Ningún 
Keine|Ninguna
Fehler beim|Error al
Versandt|Enviado
Teilweise versandt|Enviado parcialmente
Muss versendet werden|Por enviar
Drucken|Imprimir
Lieferschein|Albarán de envío
Als versandt markieren|Marcar como enviado
Wird aktualisiert|Actualizando
Artikel|Artículo
Aktion erforderlich|Acción necesaria
Teilweise|Parcial
Erledigt|Hecho
Digital|Digital
Registrieren|Registrar
Noch kein Admin zugewiesen|Aún sin administrador asignado
Bearbeitet von:|Gestionado por:
Zeilenaktionen|Acciones de línea
Menge|Cant.
versandt|enviado
verbleibend|restante
Sendungsverlauf|Historial de envíos
Spediteur|Transportista
unbekannter Admin|administrador desconocido
Aktivität|Actividad
Interne ID:|ID interno:
Geld|Dinero
Verknüpfte Abonnements:|Suscripciones vinculadas:
Abrechnungsmodus:|Modo de facturación:
Abgebrochen:|Abandonado:
ersetzt durch|reemplazado por
Beleg-E-Mail erneut senden|Reenviar correo de recibo
Support-Notizen & Tags|Notas de soporte y etiquetas
Keine Tags|Sin etiquetas
Tags, kommagetrennt|etiquetas, separadas por comas
Sendungsverfolgung:|Seguimiento:
Lieferscheine drucken|Imprimir albaranes
Angebote und Zahlungslinks vor dem Checkout|Presupuestos y enlaces de pago antes del pago
Unbezahlt|Impagado
Bezahlt|Pagado
Abgebrochen|Abandonado
Archiviert|Archivado
Notiz|Nota
Zeilen|Líneas
Annahmelink:|Enlace de aceptación:
Neu berechnen|Recalcular
In Zahlung umwandeln|Convertir en pago
Verlauf|Historial
Überfällig|Vencido
Liste drucken|Imprimir lista
Abonnement drucken|Imprimir suscripción
Katalog neu aufbauen|Reconstruir catálogo
Bestandsmodus|Modo de stock
Unbegrenzt — Bestand nicht zählen|Ilimitado — no contabilizar stock
Einfach — ein Bestand pro Lagercode|Esimple — un recuento por código de stock
Abgleichen|Conciliar
Lager|Almacén
Umbenennen|Renombrar
Bestand setzen|Establecer stock
Lagercode (SKU)|Código de stock (SKU)
Warnung bei niedrigem Bestand bei|Alerta de stock bajo en
Bestand transferieren|Transferir stock
Transferieren|Transferir
Automatisch|Automático
Affiliate|Afiliado
Prozent Rabatt|Porcentaje de descuento
Kostenloser Versand|Envío gratis
Wert|Valor
Mindestbestellwert|Compra mínima
Haupteinheiten (z. B. 50,00). 0 für kein Minimum.|Unidades principales (p. ej. 50,00). Deje 0 para sin mínimo.
Nutzungslimit|Límite de uso
Unbegrenzt|Ilimitado
Beginnt am|Comienza el
Endet am|Termina el
Ziel (optional)|Objetivo (opcional)
Kombinierbar mit anderen kombinierbaren Regeln|Acumulable con otras reglas acumulables
Geschenkkarte ausstellen|Emitir tarjeta regalo
Anfangsguthaben|Saldo inicial
Code (optional)|Código (opcional)
Leer lassen für automatische Generierung|Dejar en blanco para generar automáticamente
Interne Notiz (optional)|Nota interna (opcional)
Karte ausstellen|Emitir tarjeta
Betrag|Importe
Volles Restguthaben|Saldo restante completo
Grund (optional)|Motivo (opcional)
Rückerstattung verarbeiten|Procesar reembolso
Commerce-Einrichtung|Configuración de Commerce
Allgemein|General
Feldtypen|Tipos de campo
Zuordnungen|Mapeos
Titel|Título
Sammlung|Colección
Verfügbar|Disponible
Lagercode|Código de stock
Links|Enlaces
Ohne Titel|Sin título
Verfügbar|En mano
Reserviert|Reservado
Niedrige Warnung|Alerta baja
Digitale Erfüllung|Entrega digital
Fulfillment|Gestión de pedidos
Erfüllung|gestión de pedidos
Reverse Charge|Inversión del sujeto pasivo
Umkehrung der Steuerschuldnerschaft|Inversión del sujeto pasivo
TXT;
    $pairs = [];
    foreach (explode("\n", trim($raw)) as $line) {
        if ($line === '' || !str_contains($line, '|')) {
            continue;
        }
        [$deW, $esW] = explode('|', $line, 2);
        $pairs[$deW] = $esW;
    }
    uksort($pairs, static fn ($a, $b) => strlen($b) <=> strlen($a));

    return strtr($de, $pairs);
}
