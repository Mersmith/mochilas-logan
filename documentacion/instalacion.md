# Guía de Instalación y Configuración del Proyecto

Esta guía contiene los pasos y comandos necesarios para inicializar y configurar el proyecto de e-commerce utilizando **Laragon**, **DBeaver**, **Laravel 13** y **Livewire 4** en tu entorno local.

---

## 1. Requisitos Previos en Laragon

Asegúrate de que Laragon esté configurado para soportar los requisitos modernos:
1. **Versión de PHP:** Laravel 13 requiere **PHP 8.3** o superior. En Laragon, ve a `Menú > PHP > Quick Settings` o añade la versión PHP 8.3/8.4 en tu carpeta `laragon/bin/php/` y selecciónala.
2. **Servidor Web y BD:** Inicia los servicios de Apache y MySQL en Laragon.

---

## 2. Creación del Proyecto Laravel 13

Abre tu terminal favorita (o la terminal de Laragon) e ingresa a tu directorio raíz `laragon/www/`. Para crear el proyecto en el directorio actual (`logan`), ejecuta:

```bash
# Crear un nuevo proyecto Laravel
composer create-project laravel/laravel:^13.0 logan

# Entrar al directorio
cd logan
```

---

## 3. Configuración de Base de Datos y DBeaver

### A. Configurar DBeaver
Para conectarte a la base de datos MySQL de Laragon desde DBeaver:
1. Abre DBeaver y crea una nueva conexión seleccionando **MySQL**.
2. **Host:** `localhost`
3. **Puerto:** `3306`
4. **Database:** `logan` (deberás crearla primero en DBeaver o phpMyAdmin).
5. **Usuario:** `root`
6. **Contraseña:** *(dejar en blanco por defecto en Laragon)*

### B. Modificar el archivo `.env` en Laravel
Abre tu archivo `.env` en la raíz de tu proyecto Laravel y configura la conexión de la siguiente manera:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=logan
DB_USERNAME=root
DB_PASSWORD=
```

---

## 4. Instalación de Livewire 4

Instala la última versión de Livewire mediante Composer:

```bash
composer require livewire/livewire:^4.0
```

---

## 5. Configuración de Tailwind CSS con Vite

1. Instala Tailwind CSS y sus dependencias de compilación a través de NPM:
```bash
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init -p
```

2. Configura las rutas de tus componentes en el archivo `tailwind.config.js`:
```javascript
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
    "./app/Livewire/**/*.php", // Incluir componentes Livewire
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

3. Agrega las directivas de Tailwind en tu archivo de estilos principal (`resources/css/app.css`):
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

---

## 6. Comandos del Día a Día en Desarrollo

Una vez configurado todo, utiliza estos comandos para ejecutar el proyecto en tu entorno local:

```bash
# Iniciar el servidor de desarrollo de Vite (compilación de assets CSS/JS)
npm run dev

# Generar la llave única de la aplicación (si no se generó al instalar)
php artisan key:generate

# Correr las migraciones para crear las tablas
php artisan migrate
```
