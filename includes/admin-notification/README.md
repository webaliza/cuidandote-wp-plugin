# Sistema de Notificación a Administradores - Cuidándote Presupuestos

Este módulo añade la funcionalidad de enviar un correo electrónico de notificación a los administradores cuando un usuario solicita un presupuesto.

## 📋 ¿Qué hace?

Cuando un usuario completa el formulario de presupuesto:

1. **Se envía el presupuesto al cliente** (funcionalidad existente)
2. **Se notifica a los administradores** vía email (nueva funcionalidad)

El email a los administradores incluye:
- ✅ Datos completos del cliente (nombre, email, teléfono, código postal)
- ✅ Detalles del servicio solicitado
- ✅ Monto del presupuesto calculado
- ✅ Fecha y hora de la solicitud
- ✅ Información de llamada programada (si aplica)
- ✅ Botón para ver detalles en el panel de admin
- ✅ Diseño profesional responsive

## 📦 Archivos incluidos

```
admin-notification/
├── class-cdp-admin-notification.php          # Clase principal
├── class-cdp-admin-notification-migration.php # Migración de BD
├── loader.php                                  # Cargador del módulo
├── migration-admin-notification.sql            # SQL de migración manual
└── README.md                                   # Este archivo
```

## 🚀 Instalación

### Paso 1: Subir archivos

Sube la carpeta `admin-notification/` a tu plugin:

```
/wp-content/plugins/cuidandote-presupuestos/includes/admin-notification/
```

### Paso 2: Cargar el módulo

En el archivo principal del plugin (`cuidandote-presupuestos.php`), añade esta línea:

```php
// Cargar sistema de notificación a administradores
require_once CDP_PLUGIN_DIR . 'includes/admin-notification/loader.php';
require_once CDP_PLUGIN_DIR . 'includes/admin-notification/class-cdp-admin-notification-migration.php';
```

### Paso 3: Actualizar base de datos

Tienes **dos opciones**:

#### Opción A: Desde el Panel de Admin (Recomendado)

1. Entra al panel de WordPress
2. Verás un aviso en la parte superior
3. Haz clic en **"Ejecutar Migración Ahora"**
4. Listo ✅

#### Opción B: Ejecutar SQL manualmente

Si prefieres hacerlo manualmente, ejecuta este SQL en phpMyAdmin:

```sql
ALTER TABLE kwuf_cdp_presupuestos 
ADD COLUMN admin_notificado TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Si se notificó al admin',
ADD COLUMN admin_notificado_at DATETIME DEFAULT NULL COMMENT 'Fecha de notificación al admin',
ADD INDEX idx_admin_notificado (admin_notificado);
```

### Paso 4: Configurar email de destino (Opcional)

El email por defecto es: `info@cuidandoteserviciosauxiliares.com`

Para cambiarlo:

1. Ve a **Ajustes > Cuidándote** en el panel de WordPress
2. Busca el campo **"Email para Notificaciones"**
3. Cambia el email y guarda

O puedes configurarlo por código:

```php
CDP_Admin_Notification::config_admin_email('nuevo-email@tudominio.com');
```

## 🔌 Integración

El sistema se conecta automáticamente al hook existente:

```php
do_action('cdp_presupuesto_guardado', $presupuesto_id, $data);
```

**No necesitas modificar tu código actual.** Si ya tienes este hook en tu endpoint REST, el email se enviará automáticamente.

### Verificar integración actual

Busca en tu archivo `class-cdp-api.php` algo como esto:

```php
// Guardar en BDD
$presupuesto_id = $wpdb->insert_id;

// Disparar hook
do_action('cdp_presupuesto_guardado', $presupuesto_id, $data);
```

Si lo tienes, **ya está integrado** ✅

Si no lo tienes, añade estas líneas después de guardar el presupuesto en la base de datos.

## 🎨 Personalización del Email

### Cambiar el remitente

Edita en `class-cdp-admin-notification.php`:

```php
'From: Tu Nombre <noreply@tudominio.com>',
```

### Cambiar el asunto

Busca la línea:

```php
$subject = '🔔 Nuevo presupuesto solicitado - ' . $email_data['nombre'];
```

### Personalizar el diseño

El template HTML está en el método `get_email_template()`. Puedes modificar:

- Colores (busca `#667eea` y `#764ba2`)
- Estructura de las tablas
- Texto y mensajes
- Añadir más campos de información

## 📊 Monitoreo

### Ver en logs

Los envíos se registran en el log de WordPress. Para verlos:

```bash
tail -f /wp-content/debug.log | grep "CDP Admin Notification"
```

### Ver en la base de datos

```sql
SELECT 
    id,
    nombre,
    email,
    admin_notificado,
    admin_notificado_at,
    created_at
FROM kwuf_cdp_presupuestos
ORDER BY created_at DESC
LIMIT 10;
```

## 🐛 Solución de Problemas

### El email no se envía

1. **Verifica que el hook esté conectado**:
   ```php
   error_log('Presupuesto guardado ID: ' . $presupuesto_id);
   do_action('cdp_presupuesto_guardado', $presupuesto_id, $data);
   ```

2. **Revisa los logs de WordPress**: Busca mensajes de error del tipo:
   ```
   CDP Admin Notification: ERROR al enviar email
   ```

3. **Verifica la configuración de correo de WordPress**:
   - Instala el plugin "WP Mail SMTP" si tienes problemas de entrega
   - Revisa que tu servidor permita enviar emails

### El formato del email se ve mal

Algunos clientes de correo (como Outlook antiguo) tienen limitaciones. El template está optimizado para la máxima compatibilidad, pero si ves problemas:

1. Prueba en diferentes clientes: Gmail, Outlook, Apple Mail
2. Usa tablas en lugar de divs (ya lo hacemos)
3. Evita CSS complejo (ya evitado)

### Los links del admin no funcionan

Verifica que la URL del admin esté correcta:

```php
$admin_url = admin_url('admin.php?page=cuidandote-presupuestos&presupuesto_id=' . $presupuesto_id);
```

Asegúrate de que la página `cuidandote-presupuestos` exista en tu panel de admin.

## 🔄 Actualizaciones Futuras

Posibles mejoras que puedes implementar:

- [ ] Notificaciones a múltiples emails (CC)
- [ ] Plantillas personalizables desde el admin
- [ ] Estadísticas de envíos en el panel
- [ ] Integración con sistemas de CRM
- [ ] Notificaciones por SMS
- [ ] Slack/Telegram webhooks

## 📧 Ejemplo de Email

El administrador recibirá un email similar a este:

```
🔔 Nuevo Presupuesto Solicitado

👤 Datos del Cliente
Nombre: María García López
Email: maria.garcia@example.com
Teléfono: 911 22 33 44
Código Postal: 28001

📋 Servicio Solicitado
Tipo: Interna fines de semana (día y medio)
Horas Semanales: 16 horas
Pago Mensual: 757,15 €
Fecha: 16/12/2025 18:45

📞 Llamada programada: 20/12/2025 a las 10:00

[Ver Detalles en el Panel Admin]
```

## 📞 Soporte

Si tienes problemas con la instalación o funcionamiento:

1. Revisa los logs de WordPress (`debug.log`)
2. Verifica que la migración de BD se ejecutó correctamente
3. Comprueba que el hook `cdp_presupuesto_guardado` se dispara
4. Revisa la configuración del email en WordPress

## 📄 Licencia

Este código es parte del proyecto Cuidándote Presupuestos v2.0

---

**Última actualización**: 16 de diciembre de 2025  
**Versión**: 1.0  
**Compatible con**: WordPress 5.8+, PHP 7.4+
