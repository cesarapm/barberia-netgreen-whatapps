# Instrucciones Frontend Quasar - Contactos y Limite de Agentes

Este documento cubre 2 funciones nuevas del backend:
1. Renombrar contactos desde el front (admin y asesor).
2. Respetar el limite de asesores (2, 4 o ilimitado).

## 1) Contrato backend

### Renombrar contacto
- `PATCH /api/contacts/{contact}/name`
- Requiere `auth:sanctum`
- Roles permitidos: `admin` y `asesor`

Body:

```json
{ "name": "Juan Perez" }
```

Respuesta:

```json
{
  "status": "updated",
  "contact": {
    "id": 10,
    "phone": "+5215512345678",
    "name": "Juan Perez"
  }
}
```

Notas:
- `name` acepta `null` para limpiar el nombre.
- Maximo 255 caracteres.

### Limite de asesores
- Config backend: `AGENTS_MAX`
  - `AGENTS_MAX=2` -> maximo 2 asesores
  - `AGENTS_MAX=4` -> maximo 4 asesores
  - `AGENTS_MAX=*` o `0` -> ilimitado

Endpoint de consulta:
- `GET /api/admin/asesores/limits`
- Solo `admin`

Respuesta ejemplo:

```json
{
  "max_agents": 4,
  "current_agents": 3,
  "remaining_slots": 1,
  "is_unlimited": false,
  "can_create": true
}
```

Cuando el admin intenta crear asesor y ya no hay cupo:
- `POST /api/admin/asesores` responde `422`.

Respuesta ejemplo:

```json
{
  "error": "Se alcanzó el máximo de asesores permitidos.",
  "limits": {
    "max_agents": 4,
    "current_agents": 4,
    "remaining_slots": 0,
    "is_unlimited": false,
    "can_create": false
  }
}
```

## 2) Store recomendado para admin

Archivo sugerido: `src/stores/admin.ts`

Campos minimos extra:
- `limits`
- `canCreateAgent`

Ejemplo de tipos:

```ts
export interface AgentLimits {
  max_agents: number | null
  current_agents: number
  remaining_slots: number | null
  is_unlimited: boolean
  can_create: boolean
}
```

Actions nuevas:
- `fetchAgentLimits()` -> `GET /api/admin/asesores/limits`
- en `createAsesor()`, manejar `422` y actualizar `limits` desde `error.response.data.limits`

## 3) Cambios UI en panel Admin

### Tab Asesores
- Mostrar resumen arriba de la tabla:
  - "Asesores: X / Y" si es limitado.
  - "Asesores: X / Ilimitado" si `is_unlimited`.
- Boton "Nuevo asesor":
  - deshabilitado si `!limits.can_create`.
- Mostrar tooltip al estar deshabilitado:
  - "Se alcanzó el máximo de asesores permitidos".

Ejemplo rapido:

```vue
<q-btn
  label="Nuevo asesor"
  color="primary"
  :disable="limits && !limits.can_create"
  @click="openCreateDialog"
/>
```

## 4) Renombrar contacto en Chat

Opciones de UX:
1. Boton icono lapiz en header del chat.
2. Dialog con `QInput` para editar nombre.

Action sugerida en store de conversaciones:

```ts
async function updateContactName(contactId: number, name: string | null) {
  const { data } = await api.patch(`/contacts/${contactId}/name`, { name })

  // Actualizar lista de conversaciones localmente
  conversations = conversations.map((c) => {
    if (c.contact.id !== contactId) return c
    return {
      ...c,
      contact: {
        ...c.contact,
        name: data.contact.name,
      },
    }
  })

  return data.contact
}
```

## 5) Manejo de errores recomendado

- `403`: usuario sin rol correcto.
- `422` en nombre: validacion (`max:255`).
- `422` en crear asesor: limite alcanzado.

Ejemplo para crear asesor:

```ts
try {
  await api.post('/admin/asesores', payload)
  await fetchAsesores()
  await fetchAgentLimits()
} catch (error: any) {
  if (error?.response?.status === 422 && error?.response?.data?.limits) {
    limits = error.response.data.limits
    $q.notify({ type: 'warning', message: error.response.data.error })
    return
  }
  throw error
}
```

## 6) Checklist

1. Confirmar login admin y carga de `GET /api/admin/asesores/limits`.
2. Probar `AGENTS_MAX=2` y validar bloqueo al tercer asesor.
3. Probar `AGENTS_MAX=*` y validar creacion ilimitada.
4. Renombrar contacto desde chat y validar persistencia en la lista.
5. Limpiar nombre (`name = null`) y validar que vuelva a mostrar telefono.


php artisan reverb:start --host=127.0.0.1 --port=8081 --no-interaction
