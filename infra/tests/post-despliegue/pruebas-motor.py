#!/usr/bin/env python3
"""
Pruebas del motor de ejecución desplegado (Judge0 auto-hospedado en AWS).

Uso:
    JUDGE0_URL=http://<alb> JUDGE0_TOKEN=<token> python3 pruebas-motor.py > logs/salida.json

Cubre:
  - AUTH: sin token -> 401; token falso -> 401; token correcto -> 200/201.
  - FUNC: un mismo ejercicio («suma de dos enteros») en cinco lenguajes.
  - SEC-01..SEC-07: los casos de mod/codelab/tests/SECURITY_TESTS.md.

No imprime el token. Cada resultado guarda el estado, tiempos y memoria que devuelve el
motor, tal cual, para que la tesis cite datos medidos y no supuestos.
"""
import json
import os
import sys
import time
import urllib.error
import urllib.request

URL = os.environ["JUDGE0_URL"].rstrip("/")
TOKEN = os.environ["JUDGE0_TOKEN"]

# IDs de lenguaje de Judge0 1.13.1.
PY, JS, JAVA, C, CPP = 71, 63, 62, 50, 54


def peticion(metodo, ruta, cuerpo=None, token=TOKEN, timeout=120):
    datos = json.dumps(cuerpo).encode() if cuerpo is not None else None
    req = urllib.request.Request(URL + ruta, data=datos, method=metodo)
    req.add_header("Content-Type", "application/json")
    if token is not None:
        req.add_header("X-Auth-Token", token)
    t0 = time.time()
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.status, json.loads(r.read() or b"null"), round(time.time() - t0, 3)
    except urllib.error.HTTPError as e:
        cuerpo = e.read()
        try:
            cuerpo = json.loads(cuerpo or b"null")
        except ValueError:
            cuerpo = cuerpo.decode(errors="replace")[:300]
        return e.code, cuerpo, round(time.time() - t0, 3)


def ejecutar(codigo, lenguaje, stdin="", esperado=None, **limites):
    cuerpo = {"source_code": codigo, "language_id": lenguaje, "stdin": stdin}
    if esperado is not None:
        cuerpo["expected_output"] = esperado
    cuerpo.update(limites)
    estado, r, segundos = peticion("POST", "/submissions?base64_encoded=false&wait=true", cuerpo)
    if not isinstance(r, dict):
        return {"http": estado, "respuesta": r, "segundos_ida_y_vuelta": segundos}
    return {
        "http": estado,
        "estado": (r.get("status") or {}).get("description"),
        "stdout": (r.get("stdout") or "")[:300],
        "stderr": (r.get("stderr") or "")[:300],
        "mensaje": r.get("message"),
        "tiempo_s": r.get("time"),
        "memoria_kb": r.get("memory"),
        "segundos_ida_y_vuelta": segundos,
    }


resultados = {"motor": URL, "fecha_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())}

# --- Autenticación ---------------------------------------------------------
resultados["AUTH"] = {
    "sin_token": peticion("GET", "/languages", token=None)[0],
    "token_falso": peticion("GET", "/languages", token="token-falso")[0],
    "token_correcto": peticion("GET", "/languages")[0],
}
estado, about, _ = peticion("GET", "/about")
resultados["about"] = about

# --- Funcional: el mismo ejercicio en cinco lenguajes -----------------------
suma = {
    "python": (PY, "a, b = map(int, input().split())\nprint(a + b)\n"),
    "javascript": (JS, "const [a,b]=require('fs').readFileSync(0,'utf8').trim().split(/\\s+/).map(Number);console.log(a+b);\n"),
    "java": (JAVA, "import java.util.*;public class Main{public static void main(String[] x){Scanner s=new Scanner(System.in);System.out.println(s.nextInt()+s.nextInt());}}\n"),
    "c": (C, "#include <stdio.h>\nint main(){int a,b;scanf(\"%d %d\",&a,&b);printf(\"%d\\n\",a+b);return 0;}\n"),
    "cpp": (CPP, "#include <iostream>\nint main(){int a,b;std::cin>>a>>b;std::cout<<a+b<<std::endl;}\n"),
}
resultados["FUNC"] = {n: ejecutar(c, l, "3 5", "8") for n, (l, c) in suma.items()}
resultados["FUNC"]["python_respuesta_incorrecta"] = ejecutar("print(9)", PY, "3 5", "8")

# --- Seguridad (SECURITY_TESTS.md) -----------------------------------------
lim = {"cpu_time_limit": 5, "wall_time_limit": 10, "memory_limit": 128000}
resultados["SEC-01_bucle_infinito"] = ejecutar("while True:\n    pass\n", PY, **lim)
resultados["SEC-02_memoria"] = ejecutar('x = " " * (10 ** 10)\nprint(len(x))\n', PY, **lim)
resultados["SEC-03_fork_bomb"] = ejecutar("import os\nwhile True:\n    os.fork()\n", PY, **lim)
resultados["SEC-04_red"] = ejecutar(
    'import urllib.request\nprint(urllib.request.urlopen("https://example.com", timeout=5).status)\n', PY, **lim)
resultados["SEC-05_lectura_sistema"] = ejecutar(
    'import os, socket\nprint(socket.gethostname())\nprint(sorted(os.listdir("/"))[:20])\n'
    'print(os.path.exists("/var/www/html/config.php"), os.path.exists("/judge0.conf"))\n', PY, **lim)
resultados["SEC-06a_escritura"] = ejecutar(
    'open("/tmp/marca.txt", "w").write("estuve aqui")\nprint(open("/tmp/marca.txt").read())\n', PY, **lim)
resultados["SEC-06b_persistencia"] = ejecutar('import os\nprint(os.path.exists("/tmp/marca.txt"))\n', PY, **lim)
resultados["SEC-07_salida_gigante"] = ejecutar('while True:\n    print("A" * 1000000)\n', PY, **lim)

# El motor sigue vivo tras todo lo anterior.
resultados["salud_final"] = ejecutar("print(42)", PY, "", "42")

json.dump(resultados, sys.stdout, ensure_ascii=False, indent=2)
print()
