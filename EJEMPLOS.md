# 📚 Ejemplos de actividades CodeLab por lenguaje

Este documento muestra cómo configurar una actividad CodeLab desde el punto de vista del **profesor** y qué debe escribir el **estudiante**, para cada lenguaje disponible.

Se usan dos ejercicios de referencia:
- **Ejercicio A — Suma**: dado dos números, imprimir su suma. *(modo clásico con `input`)*
- **Ejercicio B — Fibonacci**: dada una cantidad `n`, imprimir los primeros `n` números de Fibonacci. *(con Runner automático para que el estudiante solo escriba la función)*

---

## Índice

1. [Python](#1-python)
2. [JavaScript (Node.js)](#2-javascript-nodejs)
3. [Java](#3-java)
4. [C](#4-c)
5. [C++](#5-c-1)
6. [PHP](#6-php)
7. [C# (C Sharp)](#7-c-c-sharp)
8. [Ruby](#8-ruby)
9. [Go](#9-go)
10. [Kotlin](#10-kotlin)

---

## 1. Python

### 🧑‍🏫 Configuración del profesor — Ejercicio A (Suma)

**Lenguaje:** `python`

**Código inicial:**
```python
# Lee dos números e imprime su suma
a, b = map(int, input().split())
# Escribe aquí tu lógica:
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| Suma positivos | `3 7` | `10` | 40 |
| Suma negativos | `-5 3` | `-2` | 30 |
| Suma cero | `0 0` | `0` | 30 |

---

### 🧑‍🏫 Configuración del profesor — Ejercicio B (Fibonacci con Runner)

**Código inicial:**
```python
def fibonacci(n):
    # Retorna una lista con los primeros n números de Fibonacci
    pass
```

**Código envolvente (runner):**
```python
n = int(input())
{{CODE}}
print(fibonacci(n))
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| n=1 | `1` | `[0]` | 25 |
| n=5 | `5` | `[0, 1, 1, 2, 3]` | 25 |
| n=8 | `8` | `[0, 1, 1, 2, 3, 5, 8, 13]` | 25 |
| n=0 | `0` | `[]` | 25 |

---

### 🧑‍💻 Código del estudiante — Ejercicio A
```python
a, b = map(int, input().split())
print(a + b)
```

### 🧑‍💻 Código del estudiante — Ejercicio B (con runner activo)
```python
def fibonacci(n):
    if n <= 0:
        return []
    serie = [0, 1]
    for i in range(2, n):
        serie.append(serie[i-1] + serie[i-2])
    return serie[:n]
```

---

## 2. JavaScript (Node.js)

### 🧑‍🏫 Configuración del profesor — Ejercicio A (Suma)

**Lenguaje:** `javascript`

**Código inicial:**
```javascript
const [a, b] = require('fs')
    .readFileSync('/dev/stdin', 'utf8')
    .trim().split(' ').map(Number);
// Escribe aquí tu lógica:
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| Suma básica | `4 6` | `10` | 50 |
| Suma con negativo | `-3 8` | `5` | 50 |

---

### 🧑‍🏫 Configuración del profesor — Ejercicio B (Fibonacci con Runner)

**Código inicial:**
```javascript
function fibonacci(n) {
    // Retorna un array con los primeros n números de Fibonacci
}
```

**Código envolvente (runner):**
```javascript
const n = parseInt(require('fs').readFileSync('/dev/stdin','utf8').trim());
{{CODE}}
console.log(JSON.stringify(fibonacci(n)));
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| n=5 | `5` | `[0,1,1,2,3]` | 50 |
| n=1 | `1` | `[0]` | 50 |

---

### 🧑‍💻 Código del estudiante — Ejercicio A
```javascript
const [a, b] = require('fs')
    .readFileSync('/dev/stdin', 'utf8')
    .trim().split(' ').map(Number);
console.log(a + b);
```

### 🧑‍💻 Código del estudiante — Ejercicio B (con runner activo)
```javascript
function fibonacci(n) {
    if (n <= 0) return [];
    const serie = [0, 1];
    for (let i = 2; i < n; i++) {
        serie.push(serie[i-1] + serie[i-2]);
    }
    return serie.slice(0, n);
}
```

---

## 3. Java

> ⚠️ **Regla obligatoria:** La clase pública **siempre debe llamarse `Main`**. Judge0 guarda el archivo como `Main.java`.

### 🧑‍🏫 Configuración del profesor — Ejercicio A (Suma)

**Lenguaje:** `java`

**Código inicial:**
```java
import java.util.Scanner;

public class Main {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        // Escribe aquí tu lógica:
    }
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| Suma básica | `10 20` | `30` | 50 |
| Suma negativa | `-4 9` | `5` | 50 |

---

### 🧑‍🏫 Configuración del profesor — Ejercicio B (Fibonacci)

> En Java el runner no aplica de la misma manera. El estudiante escribe la clase completa con `Main`.

**Código inicial:**
```java
import java.util.Scanner;

public class Main {

    public static void serieFibonacci(int n) {
        // Escribe aquí tu implementación
    }

    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        int n = sc.nextInt();
        serieFibonacci(n);
    }
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| n=5 | `5` | `0 1 1 2 3` | 50 |
| n=1 | `1` | `0` | 50 |

---

### 🧑‍💻 Código del estudiante — Ejercicio A
```java
import java.util.Scanner;

public class Main {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        int a = sc.nextInt();
        int b = sc.nextInt();
        System.out.println(a + b);
    }
}
```

### 🧑‍💻 Código del estudiante — Ejercicio B
```java
import java.util.Scanner;

public class Main {

    public static void serieFibonacci(int n) {
        int a = 0, b = 1;
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < n; i++) {
            if (i > 0) sb.append(" ");
            sb.append(a);
            int sig = a + b;
            a = b;
            b = sig;
        }
        System.out.println(sb.toString());
    }

    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        int n = sc.nextInt();
        serieFibonacci(n);
    }
}
```

---

## 4. C

### 🧑‍🏫 Configuración del profesor — Ejercicio A (Suma)

**Lenguaje:** `c`

**Código inicial:**
```c
#include <stdio.h>

int main() {
    int a, b;
    scanf("%d %d", &a, &b);
    // Escribe aquí tu lógica:
    return 0;
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| Suma básica | `5 3` | `8` | 50 |
| Suma negativa | `-2 10` | `8` | 50 |

---

### 🧑‍🏫 Configuración del profesor — Ejercicio B (Fibonacci con Runner)

**Código inicial:**
```c
#include <stdio.h>

void fibonacci(int n) {
    // Imprime los primeros n números de Fibonacci separados por espacio
}
```

**Código envolvente (runner):**
```c
#include <stdio.h>
{{CODE}}
int main() {
    int n;
    scanf("%d", &n);
    fibonacci(n);
    printf("\n");
    return 0;
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| n=5 | `5` | `0 1 1 2 3` | 50 |
| n=1 | `1` | `0` | 50 |

---

### 🧑‍💻 Código del estudiante — Ejercicio A
```c
#include <stdio.h>

int main() {
    int a, b;
    scanf("%d %d", &a, &b);
    printf("%d\n", a + b);
    return 0;
}
```

### 🧑‍💻 Código del estudiante — Ejercicio B (con runner activo)
```c
void fibonacci(int n) {
    int a = 0, b = 1;
    for (int i = 0; i < n; i++) {
        if (i > 0) printf(" ");
        printf("%d", a);
        int sig = a + b;
        a = b;
        b = sig;
    }
}
```

---

## 5. C++

### 🧑‍🏫 Configuración del profesor — Ejercicio A (Suma)

**Lenguaje:** `cpp`

> ⚠️ **Importante:** Usa `#include <cstdio>` con `scanf`/`printf`. **Evita `#include <iostream>`** — en algunos servidores tarda demasiado en compilar y genera error de tiempo.

**Código inicial:**
```cpp
#include <cstdio>

int main() {
    int a, b;
    scanf("%d %d", &a, &b);
    // Escribe aquí tu lógica:
    return 0;
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| Suma básica | `12 8` | `20` | 50 |
| Suma negativa | `-6 4` | `-2` | 50 |

---

### 🧑‍🏫 Configuración del profesor — Ejercicio B (Fibonacci con Runner)

**Código inicial:**
```cpp
#include <cstdio>

void fibonacci(int n) {
    // Imprime los primeros n números de Fibonacci separados por coma y espacio
}
```

**Código envolvente (runner):**
```cpp
#include <cstdio>
{{CODE}}
int main() {
    int n;
    scanf("%d", &n);
    printf("[");
    fibonacci(n);
    printf("]\n");
    return 0;
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| n=5 | `5` | `[0, 1, 1, 2, 3]` | 50 |
| n=1 | `1` | `[0]` | 50 |

---

### 🧑‍💻 Código del estudiante — Ejercicio A
```cpp
#include <cstdio>

int main() {
    int a, b;
    scanf("%d %d", &a, &b);
    printf("%d\n", a + b);
    return 0;
}
```

### 🧑‍💻 Código del estudiante — Ejercicio B (con runner activo)
```cpp
#include <cstdio>

void fibonacci(int n) {
    int a = 0, b = 1;
    for (int i = 0; i < n; i++) {
        if (i > 0) printf(", ");
        printf("%d", a);
        int sig = a + b;
        a = b;
        b = sig;
    }
}
```

---

## 6. PHP

### 🧑‍🏫 Configuración del profesor — Ejercicio A (Suma)

**Lenguaje:** `php`

**Código inicial:**
```php
<?php
$linea = trim(fgets(STDIN));
[$a, $b] = array_map('intval', explode(' ', $linea));
// Escribe aquí tu lógica:
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| Suma básica | `7 3` | `10` | 50 |
| Suma negativa | `-1 5` | `4` | 50 |

---

### 🧑‍🏫 Configuración del profesor — Ejercicio B (Fibonacci con Runner)

**Código inicial:**
```php
<?php
function fibonacci(int $n): array {
    // Retorna un array con los primeros n números de Fibonacci
}
```

**Código envolvente (runner):**
```php
<?php
$n = intval(trim(fgets(STDIN)));
{{CODE}}
echo '[' . implode(', ', fibonacci($n)) . ']' . PHP_EOL;
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| n=5 | `5` | `[0, 1, 1, 2, 3]` | 50 |
| n=1 | `1` | `[0]` | 50 |

---

### 🧑‍💻 Código del estudiante — Ejercicio A
```php
<?php
$linea = trim(fgets(STDIN));
[$a, $b] = array_map('intval', explode(' ', $linea));
echo $a + $b . PHP_EOL;
```

### 🧑‍💻 Código del estudiante — Ejercicio B (con runner activo)
```php
<?php
function fibonacci(int $n): array {
    if ($n <= 0) return [];
    $serie = [0, 1];
    for ($i = 2; $i < $n; $i++) {
        $serie[] = $serie[$i-1] + $serie[$i-2];
    }
    return array_slice($serie, 0, $n);
}
```

---

## 7. C# (C Sharp)

### 🧑‍🏫 Configuración del profesor — Ejercicio A (Suma)

**Lenguaje:** `csharp`

**Código inicial:**
```csharp
using System;

class Program {
    static void Main() {
        var partes = Console.ReadLine().Split(' ');
        int a = int.Parse(partes[0]);
        int b = int.Parse(partes[1]);
        // Escribe aquí tu lógica:
    }
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| Suma básica | `15 5` | `20` | 50 |
| Suma negativa | `-3 7` | `4` | 50 |

---

### 🧑‍🏫 Configuración del profesor — Ejercicio B (Fibonacci con Runner)

**Código inicial:**
```csharp
using System.Collections.Generic;

class Solution {
    public static List<int> Fibonacci(int n) {
        // Retorna una lista con los primeros n números de Fibonacci
        return new List<int>();
    }
}
```

**Código envolvente (runner):**
```csharp
using System;
using System.Collections.Generic;
{{CODE}}
class Program {
    static void Main() {
        int n = int.Parse(Console.ReadLine().Trim());
        var result = Solution.Fibonacci(n);
        Console.WriteLine("[" + string.Join(", ", result) + "]");
    }
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| n=5 | `5` | `[0, 1, 1, 2, 3]` | 50 |
| n=1 | `1` | `[0]` | 50 |

---

### 🧑‍💻 Código del estudiante — Ejercicio A
```csharp
using System;

class Program {
    static void Main() {
        var partes = Console.ReadLine().Split(' ');
        int a = int.Parse(partes[0]);
        int b = int.Parse(partes[1]);
        Console.WriteLine(a + b);
    }
}
```

### 🧑‍💻 Código del estudiante — Ejercicio B (con runner activo)
```csharp
using System.Collections.Generic;

class Solution {
    public static List<int> Fibonacci(int n) {
        var serie = new List<int>();
        if (n <= 0) return serie;
        int a = 0, b = 1;
        for (int i = 0; i < n; i++) {
            serie.Add(a);
            int sig = a + b;
            a = b;
            b = sig;
        }
        return serie;
    }
}
```

---

## 8. Ruby

### 🧑‍🏫 Configuración del profesor — Ejercicio A (Suma)

**Lenguaje:** `ruby`

**Código inicial:**
```ruby
a, b = gets.split.map(&:to_i)
# Escribe aquí tu lógica:
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| Suma básica | `9 1` | `10` | 50 |
| Suma negativa | `-4 6` | `2` | 50 |

---

### 🧑‍🏫 Configuración del profesor — Ejercicio B (Fibonacci con Runner)

**Código inicial:**
```ruby
def fibonacci(n)
  # Retorna un array con los primeros n números de Fibonacci
end
```

**Código envolvente (runner):**
```ruby
n = gets.to_i
{{CODE}}
puts fibonacci(n).inspect
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| n=5 | `5` | `[0, 1, 1, 2, 3]` | 50 |
| n=1 | `1` | `[0]` | 50 |

---

### 🧑‍💻 Código del estudiante — Ejercicio A
```ruby
a, b = gets.split.map(&:to_i)
puts a + b
```

### 🧑‍💻 Código del estudiante — Ejercicio B (con runner activo)
```ruby
def fibonacci(n)
  return [] if n <= 0
  serie = [0, 1]
  (2...n).each { |i| serie << serie[i-1] + serie[i-2] }
  serie.first(n)
end
```

---

## 9. Go

### 🧑‍🏫 Configuración del profesor — Ejercicio A (Suma)

**Lenguaje:** `go`

**Código inicial:**
```go
package main

import "fmt"

func main() {
    var a, b int
    fmt.Scan(&a, &b)
    // Escribe aquí tu lógica:
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| Suma básica | `6 4` | `10` | 50 |
| Suma negativa | `-2 8` | `6` | 50 |

---

### 🧑‍🏫 Configuración del profesor — Ejercicio B (Fibonacci con Runner)

**Código inicial:**
```go
package main

func fibonacci(n int) []int {
    // Retorna un slice con los primeros n números de Fibonacci
    return []int{}
}
```

**Código envolvente (runner):**
```go
package main

import (
    "fmt"
    "strings"
    "strconv"
)
{{CODE}}
func main() {
    var n int
    fmt.Scan(&n)
    result := fibonacci(n)
    parts := make([]string, len(result))
    for i, v := range result {
        parts[i] = strconv.Itoa(v)
    }
    fmt.Printf("[%s]\n", strings.Join(parts, ", "))
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| n=5 | `5` | `[0, 1, 1, 2, 3]` | 50 |
| n=1 | `1` | `[0]` | 50 |

---

### 🧑‍💻 Código del estudiante — Ejercicio A
```go
package main

import "fmt"

func main() {
    var a, b int
    fmt.Scan(&a, &b)
    fmt.Println(a + b)
}
```

### 🧑‍💻 Código del estudiante — Ejercicio B (con runner activo)
```go
package main

func fibonacci(n int) []int {
    if n <= 0 {
        return []int{}
    }
    serie := []int{0, 1}
    for i := 2; i < n; i++ {
        serie = append(serie, serie[i-1]+serie[i-2])
    }
    return serie[:n]
}
```

---

## 10. Kotlin

### 🧑‍🏫 Configuración del profesor — Ejercicio A (Suma)

**Lenguaje:** `kotlin`

**Código inicial:**
```kotlin
fun main() {
    val (a, b) = readLine()!!.split(" ").map { it.toInt() }
    // Escribe aquí tu lógica:
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| Suma básica | `11 9` | `20` | 50 |
| Suma negativa | `-5 12` | `7` | 50 |

---

### 🧑‍🏫 Configuración del profesor — Ejercicio B (Fibonacci con Runner)

**Código inicial:**
```kotlin
fun fibonacci(n: Int): List<Int> {
    // Retorna una lista con los primeros n números de Fibonacci
    return emptyList()
}
```

**Código envolvente (runner):**
```kotlin
{{CODE}}
fun main() {
    val n = readLine()!!.trim().toInt()
    val result = fibonacci(n)
    println("[${result.joinToString(", ")}]")
}
```

**Casos de prueba:**

| Nombre | Entrada | Salida esperada | Puntos |
|---|---|---|---|
| n=5 | `5` | `[0, 1, 1, 2, 3]` | 50 |
| n=1 | `1` | `[0]` | 50 |

---

### 🧑‍💻 Código del estudiante — Ejercicio A
```kotlin
fun main() {
    val (a, b) = readLine()!!.split(" ").map { it.toInt() }
    println(a + b)
}
```

### 🧑‍💻 Código del estudiante — Ejercicio B (con runner activo)
```kotlin
fun fibonacci(n: Int): List<Int> {
    if (n <= 0) return emptyList()
    val serie = mutableListOf(0, 1)
    for (i in 2 until n) {
        serie.add(serie[i-1] + serie[i-2])
    }
    return serie.take(n)
}
```

---

## 📋 Resumen rápido — Reglas por lenguaje

| Lenguaje | Regla clave |
|---|---|
| **Python** | Usa `input()` o runner con `{{CODE}}` |
| **JavaScript** | Lee stdin con `fs.readFileSync('/dev/stdin')` |
| **Java** | La clase **siempre debe llamarse `Main`** |
| **C** | Usa `scanf` para leer, `printf` para imprimir |
| **C++** | Usa `#include <cstdio>` con `scanf`/`printf` — **NO uses `<iostream>`** (muy lento en compilar) |
| **PHP** | Lee con `fgets(STDIN)` |
| **C#** | Lee con `Console.ReadLine()` |
| **Ruby** | Lee con `gets` |
| **Go** | Lee con `fmt.Scan(&var)` — el `package main` es obligatorio |
| **Kotlin** | Lee con `readLine()!!` |

> 💡 **Tip para el profesor:** Cuando uses el **Runner automático** (`{{CODE}}`), coloca en el código inicial solo la firma de la función. El estudiante escribe la implementación sin preocuparse por `input` ni `print`.
