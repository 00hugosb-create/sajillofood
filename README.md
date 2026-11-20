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
