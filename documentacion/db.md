       "spatie/laravel-medialibrary";
        "spatie/laravel-permission";
        // Aunque yo voy a usar spatie/laravel-permission y medialibrary para los permisos y las imagenes, voy a dejarlo en el documento de aqui. No lo voy a borrar porque voy a usarlo en otro proyecto. 

        Schema::create('sedes', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->boolean('activo')->default(false)->comment('1 ACTIVADO, 0 DESACTIVADO');

            $table->timestamps();
        });

        Schema::create('almacens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sede_id')->constrained()->onDelete('cascade');
            $table->string('nombre');
            $table->string('ubicacion')->nullable();
            $table->boolean('activo')->default(false)->comment('1 ACTIVADO, 0 DESACTIVADO');

            $table->timestamps();
        });

        Schema::create('tipo_documentos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->unique();

            $table->timestamps();
        });

        Schema::create('series', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sede_id')->constrained()->onDelete('cascade');
            $table->foreignId('tipo_documento_id')->constrained()->onDelete('cascade');
            
            $table->string('nombre');
            $table->integer('correlativo')->default(0);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(false)->comment('1 ACTIVADO, 0 DESACTIVADO');

            $table->timestamps();
        });

        Schema::create('categorias', function (Blueprint $table) {
            $table->id();

            $table->string('codigo')->unique();
            $table->string('nombre')->unique();
            $table->string('slug')->unique();
            $table->string('descripcion');
            $table->string('icono')->nullable();
            $table->string('imagen_ruta')->nullable();
            $table->boolean('activo')->default(false)->comment('1 ACTIVADO, 0 DESACTIVADO');
            $table->foreignId('categoria_padre_id')->nullable()->constrained('categorias')->onDelete('cascade');
            $table->integer('orden')->default(0);

            $table->timestamps();
        });

        Schema::create('marcas', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->unique();
            $table->string('slug')->unique();
            $table->string('descripcion');
            $table->boolean('activo')->default(false)->comment('1 ACTIVADO, 0 DESACTIVADO');

            $table->softDeletes(); // Asegura que el campo `deleted_at` se agregue correctamente
            $table->timestamps();
        });

        Schema::create('categoria_marcas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('categoria_id')->constrained('categorias')->onDelete('cascade');
            $table->foreignId('marca_id')->constrained('marcas')->onDelete('cascade');
            
            $table->timestamps();
        });

Schema::create('tallas', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->unique();
            $table->boolean('activo')->default(false)->comment('1 ACTIVADO, 0 DESACTIVADO');

            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->unique();
            $table->string('codigo_color')->unique();
            $table->boolean('activo')->default(false)->comment('1 ACTIVADO, 0 DESACTIVADO');

            $table->timestamps();
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('marca_id');
            $table->unsignedBigInteger('categoria_id');

            $table->string('nombre')->unique();
            $table->string('slug')->unique();
            $table->text('descripcion');
            $table->string('imagen_ruta')->nullable();
            $table->boolean('variacion_talla')->default(false);
            $table->boolean('variacion_color')->default(false);
            $table->boolean('activo')->default(false)->comment('1 ACTIVADO, 0 DESACTIVADO');

            $table->foreign('marca_id')->references('id')->on('marcas')->onDelete('cascade');
            $table->foreign('categoria_id')->references('id')->on('categorias')->onDelete('cascade');

            $table->softDeletes(); // Asegura que el campo `deleted_at` se agregue correctamente
            $table->timestamps();
        });

        Schema::create('variacions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('talla_id')->nullable();
            $table->unsignedBigInteger('color_id')->nullable();

            $table->boolean('activo')->default(false)->comment('1 ACTIVADO, 0 DESACTIVADO');

            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
            $table->foreign('talla_id')->references('id')->on('tallas')->onDelete('cascade');
            $table->foreign('color_id')->references('id')->on('colors')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('almacen_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('variacion_id');

            $table->integer('stock')->default(0);
            $table->integer('stock_minimo')->default(0);

            $table->foreign('variacion_id')->references('id')->on('variacions')->onDelete('cascade');

            $table->timestamps();
        });

         Schema::create('lista_precios', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true)->comment('1 ACTIVADO, 0 DESACTIVADO');

            $table->timestamps();
        });

        Schema::create('producto_lista_precios', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('lista_precio_id');

            $table->decimal('precio', 8, 2)->nullable();
            $table->decimal('precio_antiguo', 8, 2)->nullable();
            $table->string('simbolo')->nullable()->default('S/');

            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
            $table->foreign('lista_precio_id')->references('id')->on('lista_precios')->onDelete('cascade');

            $table->timestamps();
        });

             Schema::create('producto_descuentos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('lista_precio_id');

            $table->integer('porcentaje_descuento')->nullable();
            $table->timestamp('fecha_fin')->nullable();

            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
            $table->foreign('lista_precio_id')->references('id')->on('lista_precios')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('guia_entrada_directos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sede_id')->constrained()->onDelete('cascade');
            $table->foreignId('almacen_id')->constrained()->onDelete('cascade');

            $table->enum('estado', ['Aprobado', 'Rechazado', 'Observado', 'Eliminado']);
            $table->text('observacion')->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha_entrada');
            $table->boolean('completado')->default(false);
            $table->string('serie')->nullable();
            $table->integer('correlativo')->nullable();

            $table->timestamps();
        });

         Schema::create('guia_entrada_directo_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('guia_entrada_directo_id')->constrained()->onDelete('cascade');
            $table->foreignId('variacion_id')->constrained()->onDelete('cascade');
            
            $table->integer('stock');
            $table->integer('stock_minimo')->default(0);

            $table->timestamps();
        });

        Schema::create('transferencia_almacens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sede_origen_id')->constrained('sedes')->onDelete('cascade');
            $table->foreignId('almacen_origen_id')->constrained('almacens')->onDelete('cascade');
            $table->foreignId('sede_destino_id')->constrained('sedes')->onDelete('cascade');
            $table->foreignId('almacen_destino_id')->constrained('almacens')->onDelete('cascade');

            $table->enum('estado', ['Pendiente', 'Aprobado', 'Rechazado', 'Observado', 'Eliminado'])->default('Pendiente');
            $table->text('observacion')->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha_transferencia');
            $table->boolean('completado')->default(false);
            $table->string('serie_origen')->nullable();
            $table->integer('correlativo_origen')->nullable();
            $table->string('serie_destino')->nullable();
            $table->integer('correlativo_destino')->nullable();

            $table->timestamps();
        });

        Schema::create('transferencia_almacen_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transferencia_almacen_id')->constrained('transferencia_almacens')->onDelete('cascade');
            $table->foreignId('variacion_id')->constrained()->onDelete('cascade');
            
            $table->integer('cantidad');

            $table->timestamps();
        });

        Schema::create('guia_salida_directos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sede_id')->constrained()->onDelete('cascade');
            $table->foreignId('almacen_id')->constrained()->onDelete('cascade');

            $table->enum('estado', ['Pendiente', 'Aprobado', 'Rechazado', 'Observado', 'Eliminado'])->default('Pendiente');
            $table->text('observacion')->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha_salida');
            $table->boolean('completado')->default(false);
            $table->string('serie')->nullable();
            $table->integer('correlativo')->nullable();

            $table->timestamps();
        });

        Schema::create('guia_salida_directo_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('guia_salida_directo_id')->constrained()->onDelete('cascade');
            $table->foreignId('variacion_id')->constrained()->onDelete('cascade');

            $table->integer('cantidad');

            $table->timestamps();
        });

        Schema::create('imagens', function (Blueprint $table) {
            $table->id();

            $table->string('path');
            $table->string('url')->nullable();
            $table->string('titulo')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('extension')->nullable();

            $table->timestamps();
        });

         Schema::create('imagenables', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('imagen_id');
            $table->unsignedBigInteger('imagenable_id');
            $table->string('imagenable_type');

            $table->foreign('imagen_id')->references('id')->on('imagens')->onDelete('cascade');

            $table->timestamps();
        });

         Schema::create('roles', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('description')->nullable();  // Descripción del rol

            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('description')->nullable();  // Descripción del permiso

            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
        
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
        
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('carritos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id'); // Puede ser null si el carrito es para un invitado

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('carrito_detalles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('carrito_id');
            $table->unsignedBigInteger('variacion_id');
            $table->integer('cantidad')->default(1);
            $table->decimal('precio', 8, 2); // Precio por unidad

            $table->foreign('carrito_id')->references('id')->on('carritos')->onDelete('cascade');
            $table->foreign('variacion_id')->references('id')->on('variacions')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('compradors', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->unique();
            $table->string('nombre')->nullable();
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('email')->unique();
            $table->string('dni')->unique();
            $table->string('celular')->nullable();
            $table->integer('puntos')->default(0);
            $table->string('rol')->default("comprador");
            $table->string('imagen_ruta')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });

        Schema::create('provincias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedBigInteger('departamento_id');
            $table->foreign('departamento_id')->references('id')->on('departamentos')->onDelete('cascade');
        });

        Schema::create('distritos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedBigInteger('provincia_id');
            $table->foreign('provincia_id')->references('id')->on('provincias')->onDelete('cascade');
        });

          Schema::create('comprador_direccions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('comprador_id'); // Clave foránea a la tabla compradors
            $table->string('recibe_nombres');
            $table->string('recibe_celular');

            // Claves foráneas a departamentos, provincias, y distritos
            $table->unsignedBigInteger('departamento_id');
            $table->unsignedBigInteger('provincia_id');
            $table->unsignedBigInteger('distrito_id');

            $table->string('direccion');
            $table->string('direccion_numero');
            $table->string('opcional')->nullable();
            $table->string('codigo_postal');
            $table->string('instrucciones')->nullable();
            $table->boolean('es_principal')->default(false); // Para marcar si es la dirección principal del comprador

            $table->foreign('comprador_id')->references('id')->on('compradors')->onDelete('cascade');

            // Claves foráneas con las otras tablas
            $table->foreign('departamento_id')->references('id')->on('departamentos')->onDelete('cascade');
            $table->foreign('provincia_id')->references('id')->on('provincias')->onDelete('cascade');
            $table->foreign('distrito_id')->references('id')->on('distritos')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('bancos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');
            
            $table->timestamps();
        });

        Schema::create('tipo_cuentas', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');

            $table->timestamps();
        });

        Schema::create('favoritos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id'); // Puede ser null si el carrito es para un invitado

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });

         Schema::create('favorito_detalles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('favorito_id');
            $table->unsignedBigInteger('producto_id');

            $table->foreign('favorito_id')->references('id')->on('favoritos')->onDelete('cascade');
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('cupons', function (Blueprint $table) {
            $table->id();

            $table->string('codigo')->unique(); // Código único del cupón
            $table->decimal('descuento', 8, 2)->nullable(); // Monto fijo de descuento
            $table->integer('porcentaje_descuento')->nullable(); // Descuento en porcentaje (0-100)
            $table->decimal('monto_minimo', 10, 2)->nullable(); // Monto mínimo de compra para aplicar el cupón
            $table->integer('usos_totales')->default(1); // Número total de usos permitidos
            $table->integer('usos_restantes')->default(1); // Número de usos restantes
            $table->date('fecha_inicio')->nullable(); // Fecha desde la cual el cupón es válido
            $table->date('fecha_expiracion')->nullable(); // Fecha de expiración del cupón
            $table->string('tipo_descuento')->default('general'); // Tipo de descuento (general, primer compra, etc.)
            $table->boolean('activo')->default(true); // Estado del cupón (activo/inactivo)

            // Nuevos campos para asignar a productos o categorías
            $table->unsignedBigInteger('producto_id')->nullable(); // ID del producto específico
            $table->unsignedBigInteger('categoria_id')->nullable(); // ID de la categoría
            $table->enum('aplicacion', ['general', 'producto', 'categoria'])->default('general'); // Tipo de aplicación del cupón

            $table->timestamps(); // Timestamps para created_at y updated_at

            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('set null');
            $table->foreign('categoria_id')->references('id')->on('categorias')->onDelete('set null');
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->enum('estado', ['pendiente', 'observado', 'cancelado', 'despacho', 'enviado', 'entregado', 'conforme'])->default('pendiente');
            $table->enum('tipo_entrega', ['tienda', 'delivery'])->default('delivery');
            $table->decimal('total', 10, 2);
            $table->decimal('costo_envio', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->enum('tipo_pago', ['online', 'contraentrega'])->default('online');
            $table->enum('metodo_pago', ['tarjeta_credito', 'tarjeta_debito', 'transferencia_bancaria', 'efectivo', 'paypal', 'otros'])->nullable();
            $table->timestamp('fecha_venta');
            $table->timestamp('fecha_entrega')->nullable();
            $table->unsignedBigInteger('cupon_id')->nullable();
            $table->unsignedBigInteger('comprador_direccion_id')->nullable();
            $table->text('comentarios')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('comprador_direccion_id')->references('id')->on('comprador_direccions')->onDelete('set null');
            $table->foreign('cupon_id')->references('id')->on('cupons')->onDelete('set null');
        });

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id'); // Asegúrate de que sea unsignedBigInteger
            $table->unsignedBigInteger('variacion_id')->nullable(); // Asegúrate de que sea unsignedBigInteger
            $table->integer('cantidad');
            $table->decimal('precio', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        
            // Definición de claves foráneas
            $table->foreign('venta_id')->references('id')->on('ventas')->onDelete('cascade');
            $table->foreign('variacion_id')->references('id')->on('variacions')->onDelete('set null');
        });

        Schema::create('comprador_reembolsos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('banco_id');
            $table->unsignedBigInteger('tipo_cuenta_id');

            $table->string('cuenta_interbancaria');
            $table->string('cuenta_bancaria');

            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('banco_id')->references('id')->on('bancos')->onDelete('cascade');
            $table->foreign('tipo_cuenta_id')->references('id')->on('tipo_cuentas')->onDelete('cascade');
        });

        