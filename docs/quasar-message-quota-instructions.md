# Instrucciones Frontend Quasar - Cuota de Mensajes

Esta guia explica como adaptar el frontend (Quasar + Vue 3 + Pinia) para trabajar con la nueva cuota mensual de mensajes del backend.

## 1) Contrato backend disponible

### Variables de entorno backend
- `MESSAGE_MONTHLY_QUOTA` (ej. `1000`, `3000`)
- `MESSAGE_QUOTA_WARNING_PERCENT` (ej. `80`)

### Endpoint para estado inicial
- `GET /api/message-quota` (requiere Bearer token)

Respuesta ejemplo:

```json
{
  "period": "2026-03",
  "monthly_quota": 1000,
  "used": 810,
  "remaining": 190,
  "usage_percent": 81,
  "warning_percent": 80,
  "warning": true,
  "blocked": false,
  "status": "warning"
}
```

### Evento Realtime (Reverb/Echo)
- Canal: `message-quota`
- Evento: `.message.quota.updated`
- Payload: mismo objeto de cuota

### Bloqueo al enviar/guardar
Cuando se agota la cuota, endpoints de mensajes responden `429`:

```json
{
  "status": "blocked",
  "error": "Cupo mensual de mensajes agotado.",
  "quota": { "...": "snapshot completo" }
}
```

## 2) Crear store de cuota (Pinia)

Archivo sugerido: `src/stores/quota.ts`

```ts
import { defineStore } from 'pinia'

export type QuotaStatus = 'ok' | 'warning' | 'blocked' | 'unlimited'

export interface MessageQuota {
  period: string
  monthly_quota: number
  used: number
  remaining: number | null
  usage_percent: number | null
  warning_percent: number
  warning: boolean
  blocked: boolean
  status: QuotaStatus
}

export const useQuotaStore = defineStore('quota', {
  state: () => ({
    data: null as MessageQuota | null,
  }),

  getters: {
    isBlocked: (state) => !!state.data?.blocked,
    isWarning: (state) => !!state.data?.warning,
    remainingText: (state) => {
      if (!state.data) return '--'
      if (state.data.remaining === null) return 'Ilimitado'
      return String(state.data.remaining)
    },
  },

  actions: {
    setQuota(payload: MessageQuota) {
      this.data = payload
    },

    clearQuota() {
      this.data = null
    },
  },
})
```

## 3) Cargar estado inicial en login o boot

En tu flujo de inicio (por ejemplo en `ChatPage.vue` o `boot` de sesion), consulta:

```ts
const quotaStore = useQuotaStore()
const { data } = await api.get('/message-quota')
quotaStore.setQuota(data)
```

Recomendado:
- Ejecutarlo justo despues de `fetchUser()`.
- Si falla por `401`, continuar con flujo normal de login.

## 4) Escuchar cambios realtime de cuota

Puedes integrarlo en tu composable de Reverb o crear uno aparte.

Archivo sugerido: `src/composables/useQuotaRealtime.ts`

```ts
import { onMounted, onUnmounted } from 'vue'
import { useQuotaStore } from 'src/stores/quota'

export function useQuotaRealtime() {
  const quotaStore = useQuotaStore()

  onMounted(() => {
    window.Echo.channel('message-quota')
      .listen('.message.quota.updated', (payload: any) => {
        quotaStore.setQuota(payload)
      })
  })

  onUnmounted(() => {
    window.Echo.leaveChannel('message-quota')
  })
}
```

## 5) Bloquear UI cuando se agote cuota

En `ChatWindow.vue` (o componente que envia), deshabilita input y boton si `isBlocked`.

```vue
<q-input v-model="text" :disable="quotaStore.isBlocked || !activeConversation?.is_human" />
<q-btn label="Enviar" :disable="quotaStore.isBlocked || !activeConversation?.is_human" @click="send" />
```

Mostrar banner:

```vue
<q-banner v-if="quotaStore.isBlocked" class="bg-red-2 text-red-10 q-mb-md">
  Cupo mensual agotado. No se pueden enviar ni registrar nuevos mensajes.
</q-banner>

<q-banner v-else-if="quotaStore.isWarning" class="bg-orange-2 text-orange-10 q-mb-md">
  Advertencia: te acercas al limite mensual de mensajes.
  Restantes: {{ quotaStore.remainingText }}
</q-banner>
```

## 6) Manejar errores 429 en axios o en sendMessage

Si el backend devuelve 429, actualiza store con `quota` y muestra notificacion.

```ts
try {
  await api.post(`/conversations/${id}/send`, { body })
} catch (error: any) {
  const status = error?.response?.status
  const payload = error?.response?.data

  if (status === 429 && payload?.quota) {
    quotaStore.setQuota(payload.quota)
    $q.notify({ type: 'negative', message: payload.error || 'Cupo agotado' })
    return
  }

  throw error
}
```

Opcional recomendado:
- Agregar este manejo en un interceptor global de axios para no repetir codigo.

## 7) Donde engancharlo en la app

Minimo necesario:
- `src/stores/quota.ts`
- `src/composables/useQuotaRealtime.ts`
- `src/pages/ChatPage.vue`:
  - Cargar `GET /message-quota` al entrar
  - Invocar `useQuotaRealtime()`
- `src/components/ChatWindow.vue`:
  - Deshabilitar envio en estado `blocked`
  - Mostrar banners de `warning` y `blocked`

## 8) Checklist rapido

1. Configurar `Echo` y confirmar que ya escuchas eventos de conversacion.
2. Entrar al chat autenticado y validar `GET /message-quota`.
3. Verificar que el banner warning aparece cuando `usage_percent >= warning_percent`.
4. Al llegar a limite, comprobar que:
   - backend responde `429`
   - input/boton quedan bloqueados
   - UI muestra mensaje de cupo agotado
5. Al iniciar nuevo mes, confirmar que vuelve a estado `ok`.
