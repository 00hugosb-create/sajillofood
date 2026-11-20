# Sajillo Food
*En casa, comer bien es cosa de todos.*

Menús semanales, lista de compra, fotos de las comidas y ranking puntuado por los niños. Sin dependencias: solo Node 20+.

## Probar en local
    node server.js     # http://localhost:3000

La primera vez te pide crear el primer perfil (adulto). Después, en **Familia**, añade al resto (adultos y niños, con PIN opcional).

## Subir a GitHub
    git init && git add . && git commit -m "Sajillo Food"
    git branch -M main
    git remote add origin git@github.com:TU_USUARIO/sajillo-food.git
    git push -u origin main

## Publicar en tu servidor
    git clone git@github.com:TU_USUARIO/sajillo-food.git && cd sajillo-food
    docker compose up -d --build

Los datos y fotos quedan en `./data` (haz copia de esa carpeta). Para acceder desde fuera, pon un proxy inverso con HTTPS (Caddy o Nginx) delante del puerto 3000.

## Alojamiento compartido (OVH, Apache + PHP)
No hace falta Node: `public/api.php` hace de backend. Abre `https://TU_DOMINIO/public/` (el `.htaccess` de la raíz redirige `/` a `/public/` y bloquea `.git`, `server.js` y `data/`). PHP necesita permiso de escritura en la carpeta del sitio para crear `data/` y `public/uploads/`.

## Base de datos MySQL de OVH
1. Manager de OVH → Web Cloud → Hosting → pestaña **Bases de datos** → crear una (o usar la incluida) y anotar servidor, nombre, usuario y contraseña.
2. En el servidor, copia `config.sample.php` como `config.php` (en la raíz del sitio, junto a `.htaccess`) y rellena los datos. `config.php` no se sube a git.
3. Abre `/public/`: las tablas se crean solas al primer acceso. Las fotos se guardan dentro de la base de datos.
