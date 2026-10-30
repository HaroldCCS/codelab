# Pruebas de seguridad del sandbox (ejecución de código no confiable)

El código del estudiante es **no confiable** y siempre debe ejecutarse aislado. Estas pruebas
verifican que el motor de ejecución (Judge0 auto-alojado con la configuración de
`judge0/judge0.conf`) **contiene** el código y aplica los límites de tiempo, memoria, procesos,
red y disco. No sustituyen las pruebas automáticas: se ejecutan **manualmente** contra un motor
real, ya que comprueban aislamiento a nivel de sistema operativo.

## Cómo ejecutar

1. Levanta el motor: `docker compose -f docker-compose.yml -f docker-compose.judge0.yml up -d`.
2. Crea una actividad CodeLab con un caso de prueba cualquiera.
3. Como estudiante, envía cada uno de los códigos maliciosos de abajo.
4. Comprueba el **resultado esperado**: el sistema debe reportar el error/límite correspondiente
   y **seguir funcionando** (el resto de la plataforma no se ve afectada).

> Regla de aceptación: **ninguna** de estas pruebas debe colgar el servidor, agotar sus recursos,
> acceder a la red externa, ni leer/escribir fuera del contenedor efímero. Tras cada envío, el
> contenedor de ejecución se destruye.

---

## SEC-01 — Bucle infinito → debe cortar por tiempo

```python
while True:
    pass
```

**Esperado:** estado `timeout` (time_limit_exceeded). La ejecución se corta al superar el límite
de CPU/tiempo de pared configurado; no queda ningún proceso colgado.

## SEC-02 — Consumo excesivo de memoria → debe cortar por memoria

```python
x = " " * (10 ** 10)   # intenta reservar ~10 GB
```

**Esperado:** estado `error`/`runtime_error` por exceder el límite de memoria (`MEMORY_LIMIT`).
El host no debe quedarse sin memoria.

## SEC-03 — Bomba de procesos (fork bomb) → límite de procesos

```python
import os
while True:
    os.fork()
```

**Esperado:** estado `error`/`runtime_error`. El límite `MAX_PROCESSES_AND_OR_THREADS` impide que
el envío cree procesos sin control.

## SEC-04 — Acceso a la red → debe estar bloqueado

```python
import urllib.request
print(urllib.request.urlopen("https://example.com", timeout=5).status)
```

**Esperado:** error de red / excepción. Con `ENABLE_NETWORK=false` el sandbox no tiene salida a
Internet. **No** debe imprimir `200`.

## SEC-05 — Lectura de archivos del sistema → contenida

```python
print(open("/etc/passwd").read())
```

**Esperado:** en el peor caso solo se ve el `/etc/passwd` **del contenedor efímero** (no del host
de Moodle). No debe existir acceso a datos de Moodle, a `config.php`, ni a la base de datos.

## SEC-06 — Escritura en disco → efímera y destruida

```python
open("/tmp/marca.txt", "w").write("estuve aqui")
```

**Esperado:** la escritura queda dentro del contenedor de ejecución, que se **destruye** al
terminar. Un envío posterior no debe ver `marca.txt`.

## SEC-07 — Salida gigante → límite de tamaño de salida

```python
while True:
    print("A" * 1000000)
```

**Esperado:** la salida se trunca al límite (`MAX_FILE_SIZE`) o corta por tiempo; no debe saturar
la base de datos ni la memoria del servidor.

---

## Registro de resultados

| ID | Fecha | Motor/versión | Resultado | Notas |
|----|-------|---------------|-----------|-------|
| SEC-01 | 2026-10-30 | Judge0 1.13.1, AWS t3.small | ✅ | `Time Limit Exceeded` a los 5,095 s (límite de CPU 5 s) |
| SEC-02 | 2026-10-30 | Judge0 1.13.1, AWS t3.small | ✅ | `Runtime Error`: `MemoryError` con límite de 128 000 KB |
| SEC-03 | 2026-10-30 | Judge0 1.13.1, AWS t3.small | ✅ | `Runtime Error`: `BlockingIOError: [Errno 11]` al superar el límite de procesos |
| SEC-04 | 2026-10-30 | Judge0 1.13.1, AWS t3.small | ✅ | `OSError [Errno 101] Network is unreachable`; sin resolución DNS |
| SEC-05 | 2026-10-30 | Judge0 1.13.1, AWS t3.small | ✅ | Solo ve la raíz de la caja de `isolate` (`bin, box, dev, etc, lib, lib64, proc, tmp, usr`); ni `config.php` ni `judge0.conf` existen |
| SEC-06 | 2026-10-30 | Judge0 1.13.1, AWS t3.small | ✅ | El envío escribe y lee `/tmp/marca.txt`; el siguiente envío no lo ve (`False`) |
| SEC-07 | 2026-10-30 | Judge0 1.13.1, AWS t3.small | ✅ | `Runtime Error`: `OSError: [Errno 27] File too large` (límite de tamaño de salida) |

Ejecutadas con `evidencias-despliegue-aws/pruebas-motor.py` (raíz del proyecto) contra el motor
desplegado en AWS, a través del balanceador y con token. Tras las siete, una ejecución normal
siguió respondiendo `Accepted`: el motor no quedó afectado. Salida completa en
`evidencias-despliegue-aws/logs/09-pruebas-motor.json`.

> Estas pruebas validan la **restricción no negociable** del proyecto: el código no confiable
> nunca se ejecuta sin aislamiento con límites de tiempo, memoria, red y disco.
