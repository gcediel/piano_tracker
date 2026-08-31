# Auditoría de la metodología pedagógica de Piano Tracker

Fecha: 2026-08-24

Este documento recoge las tres fases de una auditoría de la app: (1) qué metodología de aprendizaje está codificada realmente, (2) qué dice la evidencia científica sobre motor learning y práctica pianística, y (3) el cruce crítico entre ambas, con una lista priorizada de cambios concretos.

---

## FASE 1 — Extracción de la metodología pedagógica implementada

*(Nota: existe una reimplementación en Node/Express bajo `App/` —directorio sin trackear en git— que reproduce el mismo algoritmo de sugerencia de piezas y la misma escalera de BPM línea por línea (`App/server/helpers.js:133-156`, `App/server/routes/sesion.js:55-66`). Se trata como una sola metodología porque no diverge en ningún punto pedagógico; se cita la versión PHP por ser la que está en producción.)*

### 1. Criterios de consolidación de una pieza/ejercicio

**No existe un criterio formal de consolidación o "mastery" codificado.** No hay umbral de repeticiones correctas, ni de sesiones consecutivas, ni de tolerancia a errores que marque una pieza o ejercicio como "aprendido".

- **Repertorio**: una pieza deja de aparecer en las sugerencias solo si un humano la desactiva manualmente (`activa = 0`) desde `repertorio.php:91-101`. No hay lógica automática que la desactive por buen rendimiento sostenido.
- **Técnica (ejercicios)**: no hay estado de "completado" permanente. El campo `bpm` en `ejercicios_tecnica` sube o baja indefinidamente (`sesion.php:60-83`, caso AJAX `ejercicio_valorar`), pero nunca se marca el ejercicio como dominado; solo se puede resetear manualmente a 120 BPM (`tecnica.php:66-75`).
- El campo `grado` (dificultad 1-10, `database/schema.sql:22`) es una etiqueta manual que **no se usa en ningún cálculo** de selección o progresión — no aparece en `obtenerPiezaSugerida()` (`config/database.php:54-126`).

### 2. Secuenciación de dificultad: ¿cómo decide qué practicar?

Hay **un único algoritmo adaptativo**, `obtenerPiezaSugerida()` (`config/database.php:54-126`), que decide qué pieza de repertorio toca a continuación:

```
Score = Σ [ max(0, 10 − fallos_día_i) × (31 − días_desde_i) ]  ×  (1 / ponderación)
```
- Solo pondera fallos registrados en los últimos 30 días, con más peso a los más recientes (peso 30 = ayer, peso 1 = hace 30 días).
- Menor score = mayor prioridad (`config/database.php:121-125`). Cuantos más fallos recientes tiene una pieza, antes se practica.
- `ponderacion` es un multiplicador manual de importancia (editable en `repertorio.php`).
- Una pieza sin fallos registrados en 30 días tiene score 0 → máxima prioridad.

Para **ejercicios de técnica**, la secuenciación es distinta (`sesion.php:391-409`): se ordena por `veces_total ASC, bpm ASC, nombre ASC` — prioriza el ejercicio con menos repeticiones históricas totales, sin relación con el rendimiento reciente ni con fallos.

No hay progresión de dificultad entre piezas; el `grado` es solo informativo.

### 3. Tipo de repetición: bloque, interleaving, random-entry

- **Entre piezas de repertorio dentro de una misma actividad**: interleaving forzado. `completar_pieza` (`sesion.php:85-127`) impide repetir la misma pieza dos veces en la misma actividad (`config/database.php:67-69`) y siempre ofrece la siguiente pieza según el score.
- **Dentro de una pieza**: no hay random-entry. No hay noción de compases o secciones — la pieza se practica como unidad monolítica de principio a fin.
- **Ejercicios de técnica**: también interleaving — cada valoración avanza automáticamente al siguiente ejercicio de la lista ordenada (`sesion.php:874-931`).
- **Entre sesiones**: no hay bloques temáticos programados; cada sesión reconstruye el orden desde cero con el mismo algoritmo de score.

### 4. Detección y respuesta a errores

- **Repertorio**: detección **100% autoinformada y post-hoc**. No hay entrada MIDI que compare notas tocadas contra una partitura de referencia (la capa MIDI existente solo selecciona la voz/tono del instrumento). El usuario introduce manualmente un número de "fallos" al terminar la pieza (`sesion.php:614-621`, `ajax/timer.php:50-92`).
- No hay corrección "sobre la marcha": el conteo se registra una sola vez al final.
- **Técnica**: la señal de error es un juicio subjetivo de 3 niveles (`bien`/`neutro`/`mal`) tras cada intento (`sesion.php:572-574`).
- Única respuesta automática: la escalera de BPM de técnica (`mal` → BPM−1, `bien` → BPM+1, `neutro` → sin cambio, suelo 20 BPM). Para repertorio, los fallos **no disparan ninguna acción inmediata**; solo alimentan el score de la próxima sesión.

### 5. Espaciado temporal (spaced repetition)

- No existe un programador de repetición espaciada explícito.
- El único mecanismo con sabor a espaciado es el decaimiento temporal de `obtenerPiezaSugerida()`: los fallos de 30+ días dejan de contar, y los recientes pesan más (`config/database.php:80-95`).
- El espaciado real entre sesiones depende enteramente de cuándo decide practicar el usuario. No hay recordatorios ni bloqueo de contenido por tiempo transcurrido.
- La "racha" de días consecutivos (`index.php:76-127`) es puramente informativa/motivacional — no se usa en ningún punto de decisión.

### 6. Tracking de progreso: qué se guarda y para qué se usa

Se guarda: `fallos.cantidad` por pieza/actividad con timestamp; `sesion_tecnica_ejercicios.resultado` y `bpm_practicado`; `actividades.tiempo_segundos`; notas de texto libre.

**Uso real en decisiones**: `fallos` alimenta `obtenerPiezaSugerida()`; `sesion_tecnica_ejercicios` alimenta el orden "menos practicado primero" y el ajuste de BPM.

**Uso puramente descriptivo**: `informes.php`, `informe_mensual.php`, `informe_anual.php` calculan medias de fallos y las colorean en un mapa de calor, pero no retroalimentan ningún algoritmo — son solo lectura humana. Lo mismo la racha de días en `index.php`.

### Correcciones aportadas por el usuario tras la Fase 1

1. **Repertorio**: antes de la interpretación "real" (con metrónomo) se hace una prueba sin metrónomo. Al final de cada mes, si la media de fallos en interpretación es ≤1, el usuario sube manualmente el tempo de la pieza entre 4 y 6 BPM. Este criterio **no está codificado**; es un proceso enteramente manual basado en leer `informe_mensual.php`.
2. **Técnica**: los ejercicios provienen de los libros de Natalia Jareño ("Desbloquea tus dedos", actualmente por el tercero) y próximamente de material de Nahre Sol. El usuario los practica secuencialmente **fuera de la app** hasta poder reproducirlos a 120 BPM sin errores, y **solo entonces** los añade al sistema, donde cada repetición se valora en Bien/Neutro/Mal. Es decir: dentro de la app, técnica funciona como **mantenimiento/sobreaprendizaje**, no como adquisición inicial. Se usa tanto para calentamiento como para mantener aptitudes técnicas, con mejoras subjetivas notables frente a métodos previos (Czerny, Beyer).

---

## FASE 2 — Revisión de literatura sobre aprendizaje motor y pianístico

*(Investigación pura sobre evidencia científica, sin comparar todavía con la app.)*

### 1. Práctica deliberada aplicada a habilidades motoras complejas

**Evidencia**: Ericsson, Krampe & Tesch-Römer (1993, *Psychological Review*, 100:363-406) definieron la práctica deliberada como actividad con objetivos específicos y alcanzables, feedback inmediato sobre el resultado, repetición enfocada en corregir debilidades concretas, y ejecución fuera de la zona de confort. El meta-análisis de Macnamara, Hambrick & Oswald (2014, *Psychological Science*, 25(8):1608-1618) encontró que la práctica deliberada explica el **21% de la varianza en desempeño musical** (18% en deporte, 26% en juegos, <1% en profesiones) — significativa pero muy lejos de ser el factor dominante que la formulación original sugería.

**Consenso**: **debatido**. Los componentes estructurales tienen aceptación amplia; la afirmación fuerte de que "la práctica lo explica casi todo" está hoy considerada exagerada. Ericsson respondió duramente al meta-análisis — disputa activa, no zanjada.

**Matiz piano/música**: la música es, junto a los juegos, el dominio donde la práctica deliberada más varianza explica — pero deja ~79% sin explicar (edad de inicio, memoria de trabajo, calidad vs. cantidad, genética).

### 2. Interleaving vs. práctica en bloque (contextual interference)

**Evidencia**: Shea & Morgan (1979, *J. Experimental Psychology: Human Learning and Memory*, 5:179-187) mostraron que la práctica aleatoria (alta interferencia contextual) perjudica el rendimiento durante la adquisición pero mejora retención y transferencia frente a la práctica en bloque. Magill & Hall (1990, *Human Movement Science*) confirmaron el efecto con matices.

**Consenso en tareas motoras simples de laboratorio: alto.** En música el panorama es mixto e incluso se invierte:
- Carter & Grahn (2016, *Frontiers in Psychology*, 7:1251) — 10 clarinetistas: ventaja parcial del interleaving, muestra pequeña.
- **Mathias & Goldman (2025, *Journal of Research in Music Education*, 72(4):357-375)** — 19 violinistas avanzados: **sin diferencias en adquisición, pero la práctica en bloque superó a la aleatoria en retención a 24h** — resultado que invierte el efecto clásico. Los autores lo atribuyen a que la carga cognitivo-motora de tocar un instrumento puede saturar la memoria de trabajo cuando se combina con alta interferencia.

**Matiz piano/música**: uno de los puntos donde la literatura generalista **no se traslada limpiamente** a instrumentos complejos.

### 3. Repetición espaciada aplicada a consolidación motora

**Evidencia**: el spacing effect clásico (memoria declarativa) está sólidamente establecido (Cepeda et al., ~2006-2008). Para memoria motora, Walker et al. (2002, *Nature Neuroscience*) mostraron ganancias "offline" tras una noche de sueño sin práctica adicional.

Pero el único estudio con tareas tipo piano encontró lo contrario: **Wiseheart, D'Souza & Chae (2017, *PLoS ONE*, 12(8):e0182986), "Lack of spacing effects during piano learning"** — 100 participantes, secuencias tipo Hanon y melodías, intervalos de 0-15 min: **ningún efecto de espaciado** en ninguna condición. Los intervalos probablemente fueron demasiado cortos para que el olvido necesario se manifestara.

**Consenso**: **medio, con matiz importante**. Alto para memoria declarativa; para consolidación motora depende de si el intervalo incluye sueño (ahí sí hay ganancia offline) — a escala de minutos/horas dentro de una sesión, la evidencia directa en piano no encontró beneficio.

### 4. Random practice / variabilidad de práctica (Schmidt, schema theory)

**Evidencia**: Schmidt (1975, *Psychological Review*) propuso que variar los parámetros de una misma clase de movimiento (tempo, fuerza) —no alternar entre tareas distintas— facilita un esquema motor generalizable y mejora la transferencia. La revisión de van Rossum (1990, *Human Movement Science*, 63 estudios) encontró **soporte mixto**, más consistente para transferencia que para retención pura.

**Consenso**: **medio**. Es un principio distinto del interleaving (varía parámetros de una misma habilidad, no alterna piezas).

**Matiz piano**: no hay estudio específico a gran escala sobre variar tempo/dinámica/articulación del mismo pasaje — la aplicación a piano es extrapolación, no evidencia directa.

### 5. Feedback inmediato vs. diferido en corrección de errores motores

**Evidencia**: Salmoni, Schmidt & Walter (1984, *Psychological Bulletin*, 95:355-386) formularon la **guidance hypothesis**: el feedback (KR) frecuente ayuda durante la adquisición pero perjudica la retención porque el aprendiz se vuelve dependiente de él en vez de desarrollar detección interna de errores. Winstein & Schmidt (1990, *JEP:LMC*) confirmaron que feedback reducido/decreciente retiene mejor que feedback al 100%.

**Consenso**: **alto** en tareas motoras simples de laboratorio. Matiz: novatos o tareas muy complejas pueden necesitar feedback inicial con desvanecimiento progresivo, no ausencia total.

**Matiz música**: se invoca a menudo en pedagogía instrumental, pero no hay estudio dedicado que replique el efecto en práctica instrumental compleja — extrapolación razonable, no evidencia del dominio.

### 6. Curva de olvido en habilidades motoras vs. memoria verbal

**Evidencia**: la curva de Ebbinghaus describe memoria declarativa/verbal. La memoria procedimental/motora es un sistema neuroanatómicamente distinto (cerebelo-ganglios basales-corteza motora) con resistencia al olvido notablemente mayor una vez automatizada. Casos de amnésicos aprendiendo tareas motoras sin memoria declarativa del entrenamiento evidencian esta disociación.

**Consenso**: **alto** en que son sistemas distintos con curvas de olvido distintas. Matiz: en fases tempranas (no automatizadas), la habilidad depende más de memoria declarativa/estratégica y es más vulnerable al olvido rápido.

**Matiz piano**: el trabajo de Chaffin (ver punto 7) sugiere que una pieza memorizada combina una capa motora automatizada (resistente) y una capa estructural/interpretativa declarativa (frágil) — observación de estudio de caso, no una curva cuantificada con muestra amplia.

### 7. Criterios objetivos de "consolidación"/mastery en estudios de instrumentos

**Evidencia general**: en literatura conductual/ABA el criterio típico es 80-100% de precisión durante 2-3 sesiones consecutivas — pero es literatura de educación especial, no específica de música.

**Evidencia específica de piano**: Chaffin, Imreh & Crawford (2002, libro *Practicing Perfection: Memory and Piano Performance*; artículo asociado en *Psychological Science*) documentaron con estudio de caso (una pianista concertista grabada ~3.5 años, retest 2 años después) que el "dominio" experto no se define por umbral numérico de repeticiones correctas, sino por la capacidad de recuperar la pieza de memoria de forma fluida usando "performance cues" (puntos de referencia estructurales/técnicos/interpretativos), verificado por estabilidad a largo plazo. Jørgensen y Hallam, en su literatura sobre práctica instrumental, tampoco usan criterios de "N repeticiones correctas seguidas".

**Consenso**: **bajo/inexistente un estándar único**. La investigación en instrumentos tiende a usar medidas cualitativas y longitudinales, no umbrales cuantitativos que sí son estándar en otras disciplinas motoras.

**Fuentes citadas**: Ericsson, Krampe & Tesch-Römer (1993); Macnamara, Hambrick & Oswald (2014); Shea & Morgan (1979); Magill & Hall (1990); Carter & Grahn (2016); Mathias & Goldman (2025); Cepeda et al. (~2006-2008); Walker et al. (2002); Wiseheart, D'Souza & Chae (2017); Schmidt (1975); van Rossum (1990); Salmoni, Schmidt & Walter (1984); Winstein & Schmidt (1990); Chaffin, Imreh & Crawford (2002); Jørgensen; Hallam.

---

## FASE 3 — Comparación crítica (gap analysis)

### Matices previos a la clasificación

**Matiz A — repertorio vs. técnica no son comparables 1:1 con la literatura del mismo modo.** Para repertorio, la app participa en la *adquisición y refinamiento* real de la pieza. Para técnica, la app entra en juego **después** de que el ejercicio ya está dominado externamente (120 BPM limpio con los libros de Jareño/Nahre Sol) — dentro de la app funciona como **mantenimiento/sobreaprendizaje**, no como adquisición. Gran parte de la literatura de la Fase 2 (Schmidt, contextual interference, guidance hypothesis) se investigó sobre adquisición de habilidades *nuevas*, no sobre mantenimiento de habilidades ya consolidadas. Esto hace que varias "faltas" respecto a esa literatura sean menos graves de lo que parecerían a primera vista para el módulo de técnica.

**Matiz B — la prueba sin metrónomo previa a la interpretación real no existe como concepto en el código.** El campo `fallos` es un único número por pieza por actividad; no hay forma de distinguir si esos fallos vinieron del pase libre o del pase con metrónomo, que es el que el usuario trata como el que realmente cuenta para su criterio de progresión.

### Clasificación por principio

**1. Criterios de consolidación**

| Aspecto | Categoría | Explicación |
|---|---|---|
| Subida de tempo en repertorio (media mensual de fallos ≤1 → +4-6 BPM) | ❌ Ausente en el código | Es un criterio real y razonable que ya se aplica de forma consistente, pero vive enteramente en la cabeza del usuario + la lectura visual de `informe_mensual.php`. El código no lo calcula, no lo dispara y no lo registra como decisión (punto 7, Fase 2: no hay un estándar numérico único validado en literatura de piano, pero esta heurística de "ventana temporal con rendimiento sostenido" es coherente con el espíritu de los criterios usados en otros dominios motores). |
| Retiro/graduación de piezas de repertorio | ❌ Ausente | `activa=0` es un interruptor manual y arbitrario (`repertorio.php:91-101`), sin relación con el historial de `fallos`. |
| Umbral de entrada de ejercicios de técnica (120 BPM limpio) | ❓ Fuera de alcance del código, por diseño | Ocurre en el proceso de estudio, no en la app — no es un "gap" del software, es una decisión de mantener esa fase fuera del sistema. |
| Techo/graduación de ejercicios de técnica dentro de la app | ❓ Sin respaldo claro en ningún sentido | No hay techo: el BPM sube indefinidamente y el ejercicio compite siempre en la rotación. Como el objetivo declarado es mantenimiento, no hay literatura que diga que esto *deba* tener techo — pero tampoco que no lo necesite. |

**2. Secuenciación de dificultad**

| Aspecto | Categoría | Explicación |
|---|---|---|
| Algoritmo de score de repertorio (`obtenerPiezaSugerida`, `config/database.php:54-126`) | ✅ Alineado | Prioriza sistemáticamente piezas con más fallos recientes — implementación razonable del componente "foco en debilidades específicas" de la práctica deliberada (Ericsson et al. 1993). |
| Orden de ejercicios de técnica ("menos practicado primero", `sesion.php:392-406`) | ⚠️ Variante subóptima | Ignora completamente el último resultado (`bien`/`neutro`/`mal`). Un ejercicio recién calificado "mal" tiene la misma prioridad que uno exitoso — contradice el foco en debilidades de Ericsson et al. |
| Campo `grado` (dificultad 1-10) | ❓ Dato capturado sin función | Ninguno de los 7 puntos de la Fase 2 exige progresión explícita por grado declarado. No es un "gap" respecto a la literatura, pero es ruido en la UI. |

**3. Tipo de repetición (bloque / interleaving / random-entry)**

| Aspecto | Categoría | Explicación |
|---|---|---|
| Interleaving forzado entre piezas de repertorio dentro de una sesión | ⚠️ Variante potencialmente subóptima frente a evidencia reciente y específica de instrumentos | Alineado con Shea & Morgan (1979) clásico, pero el único estudio en instrumentistas avanzados (Mathias & Goldman 2025, violín) encontró que el bloqueo superó al azar en retención a 24h. Un solo estudio (n=19) — señal de precaución, no veredicto. |
| Rotación de ejercicios de técnica (mantenimiento) | ✅ Razonablemente alineado | Al ser mantenimiento de habilidades ya consolidadas, el round-robin funciona como práctica distribuida/recuperación espaciada entre sesiones (días/semanas), donde Walker et al. (2002) sí encuentran beneficio de consolidación motora. |
| Random-entry dentro de una pieza (por compás/sección) | ❌ Ausente pese a respaldo empírico sólido | Ericsson et al. exige entrenamiento focalizado en componentes/debilidades concretas, no solo repetición de la pieza entera. El esquema no permite aislar ni repetir un pasaje problemático. |
| Variar tempo/dinámica/articulación deliberadamente (Schmidt 1975) | ❓ Ausente, evidencia mixta | Van Rossum (1990) da soporte "medio", no sólido, y no hay estudio específico de piano. No es prioridad. |

**4. Detección y respuesta a errores**

| Aspecto | Categoría | Explicación |
|---|---|---|
| Registro post-hoc de fallos (un número al terminar la pieza, sin corrección en tiempo real) | ✅ Alineado, aunque no por diseño explícito | Evita la "guidance dependency" de Salmoni, Schmidt & Walter (1984) — obliga a autodetectar y autocorregir en el momento. |
| Fusión de "pase libre sin metrónomo" + "interpretación real con metrónomo" en un solo número | ⚠️ Variante subóptima (pérdida de granularidad) | El criterio de subida de tempo depende de la interpretación *con metrónomo*; al no distinguirse en el dato, `obtenerPiezaSugerida()` puede sumar ruido de la pasada de calentamiento a la señal relevante. |
| Valoración Bien/Neutro/Mal tras cada intento de técnica (feedback al 100% de frecuencia) | ❓ Sin respaldo claro en ningún sentido | No es "KR" en el sentido de Salmoni et al. (no indica qué corregir); es una escalera adaptativa tipo procedimiento psicofísico. Sin literatura musical que lo valide o invalide para este uso. |

**5. Espaciado temporal**

| Aspecto | Categoría | Explicación |
|---|---|---|
| Ausencia de un scheduler de repetición espaciada explícito (tipo SM-2) | ❓→casi ✅ (ausencia justificada) | El único estudio directo en aprendizaje pianístico (Wiseheart et al. 2017) no encontró efecto de espaciado en intervalos cortos. No construir un algoritmo rígido de repaso espaciado no es un déficit claro. |
| Decaimiento por recencia de 30 días en el score de repertorio (`config/database.php:80-95`) | ⚠️ Variante con efecto secundario no intencionado | Una pieza sin fallos en 30+ días recibe score 0 (máxima prioridad) — igual que una pieza recién añadida sin historial, e igual que una pieza "consolidada" a tempo alto. No distingue "olvidada y necesitada" de "nunca evaluada" de "dominada hace tiempo". |

**6. Tracking de progreso y su uso en decisiones**

| Aspecto | Categoría | Explicación |
|---|---|---|
| `fallos` alimentando el score de repertorio | ✅ Alineado | Cierra el ciclo objetivo-feedback-ajuste de Ericsson et al. de forma automatizada. |
| `sesion_tecnica_ejercicios` alimentando solo el orden (no el enfoque en debilidades) | ⚠️ Ya cubierto en el punto 2 | Se recoge el dato pero se subutiliza. |
| Informes mensuales/anuales, puramente descriptivos | ❓ Sin problema per se, pero es la brecha central | La decisión pedagógica más importante (subir tempo) depende de leer el panel manualmente en vez de estar conectada al sistema de decisión automatizado que sí existe para la selección de piezas. |

### Lista priorizada de cambios concretos

**Alto impacto**

1. **Automatizar el criterio de progresión de tempo.** Añadir una función (junto a `obtenerPiezaSugerida()` en `config/database.php`) que, para cada pieza activa, calcule la media de `fallos.cantidad` en el mes natural (idealmente solo de fallos marcados como "con metrónomo", ver punto 3) y, si es ≤1, incremente `piezas.tempo` en un valor configurable (4-6). No hacerlo silencioso: mostrarlo como notificación en el dashboard ("Sugerencia: subir tempo de X a Y BPM") dejando la confirmación al usuario. Es el cambio de mayor valor porque formaliza una decisión que ya se toma de forma consistente y para la que el sistema tiene todos los datos.

2. **Reponderar el orden de ejercicios de técnica para enfocar debilidades.** En `sesion.php:392-406` (y su equivalente `App/server/routes/sesion.js`), cambiar el `ORDER BY veces_total ASC, bpm ASC` para que un ejercicio con último `resultado='mal'` suba de prioridad, en vez de tratarlo igual que uno que va bien.

3. **Distinguir fallos "con metrónomo" de fallos de pase libre.** Añadir columna `tipo_pasada` (ENUM `'libre'`/`'metronomo'`) a la tabla `fallos`, capturarla en el flujo de `sesion.php`, y hacer que tanto `obtenerPiezaSugerida()` como el nuevo cálculo de progresión de tempo (punto 1) usen solo `'metronomo'`.

**Medio impacto**

4. **Permitir granularidad de sección/pasaje en repertorio.** Añadir una tabla opcional `pasajes` (pieza_id, nombre/compases) y permitir que `fallos.pasaje_id` sea nullable. No forzar su uso — que sea opcional para cuando se detecte un pasaje problemático recurrente. Habilita random-entry real y práctica focalizada.

5. **Corregir el acantilado de score a los 30 días.** En `obtenerPiezaSugerida()`, sustituir el corte binario (`DATEDIFF < 30`) por una función con decaimiento continuo, para no igualar "nunca evaluada" con "dominada y en espera".

6. **Ofrecer sugerencia de graduación de piezas.** En el dashboard o en `repertorio.php`, avisar cuando una pieza lleve, p.ej., 3+ meses consecutivos con media de fallos ≤1 y tempo estable, sugiriendo pasar a "repertorio de mantenimiento" en vez de competir siempre en igualdad con piezas en aprendizaje activo.

**Bajo impacto**

7. **Eliminar o vincular el campo `grado`.** Tal como está, es dato muerto que puede inducir a pensar que el sistema pondera dificultad cuando no lo hace.

8. **Techo/pausa de mantenimiento para ejercicios de técnica muy sobreaprendidos.** Marcar ejercicios que llevan mucho tiempo muy por encima de 120 BPM con menor frecuencia de rotación.

9. **No construir un scheduler de repetición espaciada tipo SM-2.** El único estudio directo en piano no encontró efecto de espaciado a intervalos cortos; el diseño actual (decaimiento por recencia + práctica voluntaria) no debería tocarse en esa dirección sin evidencia específica que lo justifique.
