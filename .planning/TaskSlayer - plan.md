Excelente evolución. Integrar un sistema de **"Economía de Tiempo"** transforma la app de un simple juego a una herramienta de disciplina real. Al usar puntos para "comprar" tiempo de ocio, creas un sistema de refuerzo positivo inmediato.

Dado que prefieres un backend en **Express** o **Laravel**, y considerando que buscas algo local pero robusto, aquí tienes el planteamiento actualizado con el enfoque de recompensas y el brief técnico detallado.

---

## 1. El Concepto: "The Grind & The Reward"

El núcleo de la experiencia ahora se divide en dos ciclos:

- **Ciclo de Batalla:** Creas una tarea $\rightarrow$ El LLM (vía LM Studio) le asigna una **Clase de Dificultad (CD)**, un nombre épico y una recompensa en oro/puntos.
- **Ciclo de Canje:** Los puntos acumulados se gastan en una "Tienda de Habilidades" (Premios de tiempo). Esto actúa como un _pay-to-play_ donde la moneda es tu propia productividad.

### Escala de Complejidad (Propuesta)

| Nivel de Tarea          | Puntos (Oro) | Equivalente en Ocio           |
| :---------------------- | :----------- | :---------------------------- |
| **Escaramuza (Fácil)**  | 10 pts       | 5 min de Social Media         |
| **Duelo (Media)**       | 30 pts       | 15 min de Videojuegos         |
| **Boss Raid (Difícil)** | 60 pts       | 30 min de Videojuegos / Serie |
| **World Boss (Épica)**  | 120 pts      | 60 min de Ocio libre          |

---

## 2. Brief Técnico: Stack Local-First

Para asegurar que todo corra en tu máquina sin depender de la nube:

- **Frontend:** **SvelteKit** (por su velocidad de renderizado y manejo de estados simple).
- **Backend:** **Laravel** (recomendado si prefieres una estructura "out of the box" con migraciones, autenticación y manejo de bases de datos muy pulido).
- **Base de Datos:** **PostgreSQL** (una base de datos relacional para mejor manejo de reportes).
- **LLM Interface:** **LM Studio** corriendo en modo `Local Server` (expone una API compatible con OpenAI en el puerto 1234).
- **Comunicación:** **Axios** o el `fetch` nativo para consultar al backend y al servidor de IA.

---

## 3. Arquitectura de la IA (The Transformation Engine)

Cuando envíes una tarea al backend, Laravel despachará una petición a LM Studio con un prompt similar a este:

> "Actúa como un diseñador de mecánicas de RPG. Recibe una tarea técnica y devuelve un JSON con:
>
> 1. Un nombre épico (Boss Name).
> 2. Una descripción narrativa breve.
> 3. Un Nivel (1-4) basado en la complejidad percibida.
> 4. Puntos de recompensa proporcionales."

---

## 4. Roadmap de Desarrollo

### Fase 1: El Gremio (Foundation)

- Configurar entorno de **Laravel + PostgreSQL**.
- Diseñar el esquema de base de datos: `tasks` (nombre_original, nombre_rpg, xp, puntos, estado) y `rewards` (nombre, coste_puntos).
- Crear la UI básica en **Svelte** para listar y crear tareas.

### Fase 2: El Oráculo (IA Integration)

- Configurar el servicio en Laravel para conectar con la API de **LM Studio**.
- Implementar la lógica de "Transformación": al guardar una tarea, el sistema debe esperar la respuesta de la IA antes de mostrarla en el tablero.
- Validar que la IA asigne correctamente los puntos según la complejidad.

### Fase 3: La Tesorería (Rewards System)

- Crear la vista de "Tienda de Recompensas".
- Implementar el contador de puntos global del usuario.
- Lógica de canje: al hacer clic en "30 min de juego", se restan los puntos y se inicia un temporizador (opcional) o simplemente se registra el uso.

### Fase 4: Pulido Estético (The Tavern)

- Agregar sonidos de victoria al completar tareas.
- Diseñar las "Tarjetas de Jefe" en el frontend con animaciones de Svelte (transiciones de entrada/salida).

---

## 5. Diagrama de Flujo del Sistema

### ¿Por qué Laravel para esto?

Aunque Express es genial, **Laravel** te ofrece **Eloquent ORM**, que hará que manejar tus puntos y relaciones de tareas sea extremadamente sencillo con muy poco código. Además, si luego quieres agregarle notificaciones o un sistema de colas para que la IA trabaje en segundo plano sin bloquear la UI, Laravel lo tiene integrado por defecto.

## Diseño de la capa de persistencia (enfocado a analítica)

## 1. Entidades principales

### `users`

```sql
id
name
email
created_at
```

---

### `tasks`

Representa la tarea “lógica” (no su ejecución).

```sql
id
user_id (FK)
title_original
title_rpg
description
difficulty_level        -- 1-4
reward_points
status                  -- pending, completed, archived
created_at
updated_at
```

⚠️ Error común: guardar duración aquí → **incorrecto**.
La duración pertenece a ejecuciones, no a la definición.

---

### `task_executions` (CLAVE DEL SISTEMA)

Cada vez que trabajas en una tarea, generas un evento.

```sql
id
task_id (FK)
user_id (FK)
started_at
ended_at
duration_seconds        -- redundante pero útil para queries rápidas
was_completed           -- boolean
created_at
```

✔ Esto te permite:

- Medir tiempo real trabajado
- Tener múltiples sesiones por tarea
- Analizar interrupciones

---

### `task_events` (opcional pero potente)

Si quieres gamificación + trazabilidad real:

```sql
id
task_id (FK)
execution_id (FK nullable)
type            -- start, pause, resume, complete
timestamp
metadata JSONB
```

✔ Útil para:

- Debug de comportamiento
- Métricas avanzadas (ej: cuántas pausas)

---

### `rewards`

```sql
id
name
cost_points
reward_type        -- time, custom
duration_minutes
created_at
```

---

### `reward_redemptions`

```sql
id
user_id (FK)
reward_id (FK)
points_spent
redeemed_at
```

---

### `user_points_ledger` (NO uses un simple contador)

Esto es crítico.

```sql
id
user_id (FK)
points            -- positivo o negativo
source_type       -- task_completed, reward_redeemed
source_id         -- id de task o reward
created_at
```

✔ Ventajas:

- Auditoría completa
- Recalcular saldo si algo falla
- Evitar inconsistencias

---

# 2. Relaciones

- users 1:N tasks
- tasks 1:N task_executions
- task_executions 1:N task_events
- users 1:N user_points_ledger
- rewards 1:N reward_redemptions

---

# 3. Índices (obligatorio para reportes)

```sql
CREATE INDEX idx_task_user ON tasks(user_id);
CREATE INDEX idx_exec_task ON task_executions(task_id);
CREATE INDEX idx_exec_user ON task_executions(user_id);
CREATE INDEX idx_exec_started ON task_executions(started_at);
CREATE INDEX idx_ledger_user ON user_points_ledger(user_id);
```

---

# 4. Métricas que podrás obtener (gracias a este diseño)

### Tiempo total trabajado

```sql
SELECT SUM(duration_seconds)
FROM task_executions
WHERE user_id = ?;
```

---

### Tiempo por tarea

```sql
SELECT task_id, SUM(duration_seconds)
FROM task_executions
GROUP BY task_id;
```

---

### Productividad diaria

```sql
SELECT DATE(started_at), SUM(duration_seconds)
FROM task_executions
GROUP BY DATE(started_at)
ORDER BY DATE(started_at);
```

---

### Tasa de finalización

```sql
SELECT
  COUNT(*) FILTER (WHERE was_completed = true) * 1.0 /
  COUNT(*) AS completion_rate
FROM task_executions;
```

---

### Balance de puntos

```sql
SELECT SUM(points)
FROM user_points_ledger
WHERE user_id = ?;
```

---

# 5. Mejora avanzada (recomendada)

## Materialized View para dashboards

```sql
CREATE MATERIALIZED VIEW user_daily_stats AS
SELECT
  user_id,
  DATE(started_at) as day,
  SUM(duration_seconds) as total_time,
  COUNT(*) as sessions
FROM task_executions
GROUP BY user_id, DATE(started_at);
```

✔ Esto evita recalcular todo cada vez que dibujas gráficos.

---

# 6. Conclusión (lo importante)

Tu sistema no es de tareas. Es un sistema de **tracking de sesiones**.

Si solo guardas tareas → tendrás una app básica.
Si guardas ejecuciones → tendrás analítica real.

---
