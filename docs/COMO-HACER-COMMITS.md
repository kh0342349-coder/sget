# Cómo hacer commits sin romper el código

## La respuesta corta

El código **NO se dañó solo**. Se dañó por una secuencia concreta de comandos
que se ejecutó en este repositorio:

```
git stash            ← guardó el trabajo
git checkout <rama>  ← cambió de rama con el árbol de trabajo "en el aire"
git checkout main    ← volvió a la rama
git stash pop        ← intentó reintegrar el trabajo → CONFLICTOS
git commit -a        ← ¡commiteó los marcadores de conflicto dentro del código!
```

`git stash pop` escribe los marcadores `<<<<<<< Updated upstream` en los
archivos. Si se commitea sin resolverlos, PHP deja de analizar esos archivos
y **el commit queda inservible**.

## La forma segura

### 1. Nunca commitees sin revisar

```bash
git status                                            # ¿qué cambió?
git diff                                              # ¿cómo cambió, línea a línea?
grep -rn "^<<<<<<< \|^>>>>>>> " --include=*.php .      # ¿hay conflictos?
```

Si el `grep` devuelve algo, **no commitees**: primero resuélvelo.

### 2. Un commit = un cambio con sentido

```bash
git add core/ services/ views/     # rutas concretas, nunca "todo"
git status                          # revisa el staging
git commit -m "Add service layer for routes and trips"
```

### 3. Antes de commitear, ejecuta las pruebas

```bash
php pruebas/smoke.php

touch pruebas/.habilitar
SGET_DEBUG=1 php -S 127.0.0.1:8899 -t .
php pruebas/render.php
php pruebas/api.php
node pruebas/modal-visual.js
node pruebas/transicion-visual.js
node pruebas/cancelacion-visual.js
rm pruebas/.habilitar
```

Si alguna falla, **arregla antes de commitear**. Un commit que rompe la
aplicación es peor que no commitear: deshacerlo cuesta más.

## Reglas de oro

| Regla | Por qué |
|---|---|
| No uses `git add -A` a ciegas | Mete archivos que no deberían (submódulos, `.habilitar`, datos de prueba) |
| No commitees con marcadores de conflicto | Rompe el archivo entero |
| Antes de cambiar de rama: `git status` limpio | Un `checkout` con cambios sueltos puede perderlos |
| Si usas `stash`, confírmalo: `git stash pop` puede fallar | Revisa los conflictos ANTES de commitear |
| `pruebas/.habilitar` nunca se sube | Es el interruptor que permite simular una sesión de admin |
| No commitees contraseñas ni `.env` | Ni aunque "es solo local" |

## Configura esto una vez

```bash
git config --global core.autocrlf input
git config --global merge.conflictstyle diff3
```

- `core.autocrlf input` evita que Git convierta los finales de línea y genere
  diffs con todo el archivo marcado como modificado.
- `merge.conflictstyle diff3` muestra el conflicto con las tres versiones
  (base, nuestra, suya), que es mucho más fácil de resolver bien.

## Si ya metiste marcadores de conflicto en un commit

```bash
# 1. Recuperar la versión buena del stash (si el trabajo está ahí)
git show stash@{0}:Admin/rutas.php > Admin/rutas.php

# O, si la versión buena está en un commit anterior
git checkout HEAD~1 -- Admin/rutas.php

# 2. Verificar
php -l Admin/rutas.php
grep -rn "^<<<<<<< " --include=*.php .

# 3. Commitear la corrección
git add -A && git commit -m "fix: resolve merge conflict markers"
```

## Alternativa más segura que el stash

Si trabajas en una rama, no necesitas `stash` para cambiar de rama:

```bash
git switch -c mi-rama     # trabaja siempre en tu rama
git add -A && git commit -m "WIP: trabajo en curso"
git switch main
```

Commitear antes de cambiar de rama es más seguro que guardar en un stash:
si algo sale mal, el commit siempre se puede deshacer con `git reset`.
