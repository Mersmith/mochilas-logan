# Nueva Estructura de Base de Datos - Mochilas Logan

Este documento describe la propuesta estructurada para la base de datos de **Mochilas Logan**. Resuelve la gestión de múltiples almacenes, movimientos mediante guías (entradas, salidas, transferencias), variaciones flexibles de productos, múltiples listas de precios, y el control de empaques (unidades, cajas, costales) con conversión de stock.

---

## 1. Mantenimiento y Configuración Base

### Sedes
Sucursales o puntos de operación de la empresa.
```php
Schema::create('sedes', function (Blueprint $table) {
    $table->id();
    $table->string('nombre');
    $table->string('direccion')->nullable();
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

### Almacenes
Espacios físicos de almacenamiento asociados a una sede.
```php
Schema::create('almacens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sede_id')->constrained()->onDelete('cascade');
    $table->string('nombre');
    $table->string('ubicacion')->nullable();
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

### Tipos de Documentos y Series
Define comprobantes y documentos de control interno (ej. Boleta, Factura, Guía de Remisión, Guía Interna).
```php
Schema::create('tipo_documentos', function (Blueprint $table) {
    $table->id();
    $table->string('nombre')->unique(); // Boleta, Factura, Guía de Entrada, Guía de Salida, etc.
    $table->string('codigo_sunat', 10)->nullable();
    $table->boolean('activo')->default(true);
    $table->timestamps();
});

Schema::create('series', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sede_id')->constrained()->onDelete('cascade');
    $table->foreignId('tipo_documento_id')->constrained()->onDelete('cascade');
    $table->string('serie', 10); // Ej. F001, G001
    $table->integer('correlativo')->default(0);
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

---

## 2. Catálogo de Productos y Variaciones Dinámicas

### Marcas y Categorías
Las categorías son recursivas para admitir categorías y subcategorías ilimitadas.
```php
Schema::create('marcas', function (Blueprint $table) {
    $table->id();
    $table->string('nombre')->unique();
    $table->string('slug')->unique();
    $table->text('descripcion')->nullable();
    $table->boolean('activo')->default(true);
    $table->softDeletes();
    $table->timestamps();
});

Schema::create('categorias', function (Blueprint $table) {
    $table->id();
    $table->foreignId('categoria_padre_id')->nullable()->constrained('categorias')->onDelete('cascade');
    $table->string('codigo')->unique()->nullable();
    $table->string('nombre')->unique();
    $table->string('slug')->unique();
    $table->text('descripcion')->nullable();
    $table->integer('orden')->default(0);
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

### Productos
Definición general del producto.
```php
Schema::create('tipo_productos', function (Blueprint $table) {
    $table->id();
    $table->string('nombre')->unique(); // Ej. Mochila, Cartera, Maletín, Cartuchera, Cangurera
    $table->string('slug')->unique();
    $table->boolean('activo')->default(true);
    $table->timestamps();
});

Schema::create('productos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tipo_producto_id')->constrained('tipo_productos')->onDelete('cascade');
    $table->foreignId('marca_id')->constrained()->onDelete('cascade');
    $table->foreignId('categoria_id')->constrained()->onDelete('cascade'); // Subcategoría o Categoría final
    $table->string('nombre')->unique();
    $table->string('slug')->unique();
    $table->text('descripcion')->nullable();
    $table->boolean('activo')->default(true);
    $table->softDeletes();
    $table->timestamps();
});
```

### Atributos Dinámicos de Variación
Para evitar crear tablas separadas para cada atributo (Talla, Color, Material, etc.), usamos una estructura EAV (Entity-Attribute-Value) flexible.
```php
Schema::create('atributos', function (Blueprint $table) {
    $table->id();
    $table->string('nombre')->unique(); // Ej. "Color", "Talla", "Material"
    $table->timestamps();
});

Schema::create('atributo_valores', function (Blueprint $table) {
    $table->id();
    $table->foreignId('atributo_id')->constrained()->onDelete('cascade');
    $table->string('valor'); // Ej. "Azul", "M", "Cuero Oxford"
    $table->string('codigo_color_hex')->nullable(); // Util si el atributo es Color
    $table->timestamps();
});
```

### Variaciones de Producto (SKUs)
Una variación representa la combinación exacta de atributos de un producto que tiene su propio stock e identificador único.
```php
Schema::create('variacions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('producto_id')->constrained()->onDelete('cascade');
    $table->string('sku')->unique(); // Código único de inventario
    $table->string('codigo_barras')->nullable()->unique();
    $table->boolean('activo')->default(true);
    $table->timestamps();
});

// Pivote para asociar múltiples atributos a una sola variación
Schema::create('variacion_valores', function (Blueprint $table) {
    $table->id();
    $table->foreignId('variacion_id')->constrained()->onDelete('cascade');
    $table->foreignId('atributo_valor_id')->constrained()->onDelete('cascade');
    $table->timestamps();
});
```

---

## 3. Unidades de Medida y Empaques (Costales, Cajas, Unidades)

Para permitir el movimiento y almacenamiento en diferentes presentaciones (por unidad, cajas de 100 unidades, costales de 50 unidades, etc.).

### Unidades de Medida / Empaques
```php
Schema::create('unidades_medida', function (Blueprint $table) {
    $table->id();
    $table->string('nombre'); // Ej: Unidad, Caja, Costal, Paquete
    $table->string('abreviacion', 10); // Ej: UND, CJ, CST, PQT
    $table->timestamps();
});
```

### Equivalencias por Producto
Especifica cuántas unidades base (UND) contiene cada presentación/empaque para un producto.
```php
Schema::create('producto_empaques', function (Blueprint $table) {
    $table->id();
    $table->foreignId('producto_id')->constrained()->onDelete('cascade');
    $table->foreignId('unidad_medida_id')->constrained('unidades_medida')->onDelete('cascade');
    $table->integer('factor_conversion')->default(1); // Ej. Si es Caja, factor = 100. Si es Costal, factor = 50.
    $table->timestamps();
    
    $table->unique(['producto_id', 'unidad_medida_id']);
});
```

---

## 4. Control de Inventario Base

El inventario físico siempre se almacena en la **unidad base (unidades)** para evitar inconsistencias de redondeo y facilitar reportes.
```php
Schema::create('inventarios', function (Blueprint $table) {
    $table->id();
    $table->foreignId('almacen_id')->constrained()->onDelete('cascade');
    $table->foreignId('variacion_id')->constrained()->onDelete('cascade');
    $table->integer('stock_base')->default(0); // Cantidad en unidades individuales
    $table->integer('stock_minimo')->default(0);
    $table->timestamps();
    
    $table->unique(['almacen_id', 'variacion_id']);
});
```

---

## 5. Tarifas, Precios y Descuentos

### Listas de Precios
Permite tener múltiples precios asignados al mismo producto (Mayorista, Minorista, etc.).
```php
Schema::create('lista_precios', function (Blueprint $table) {
    $table->id();
    $table->string('nombre')->unique(); // Ej: "Precio Mayor", "Precio Menor", "Distribuidor"
    $table->boolean('activo')->default(true);
    $table->timestamps();
});

Schema::create('variacion_precios', function (Blueprint $table) {
    $table->id();
    $table->foreignId('variacion_id')->constrained()->onDelete('cascade');
    $table->foreignId('lista_precio_id')->constrained('lista_precios')->onDelete('cascade');
    $table->decimal('precio', 10, 2);
    $table->decimal('precio_antiguo', 10, 2)->nullable();
    $table->string('simbolo')->default('S/');
    $table->timestamps();
    
    $table->unique(['variacion_id', 'lista_precio_id']);
});
```

### Descuentos y Cupones
```php
Schema::create('descuentos', function (Blueprint $table) {
    $table->id();
    $table->string('nombre');
    $table->integer('porcentaje_descuento'); // Ej. 15 para 15%
    $table->timestamp('fecha_inicio')->nullable();
    $table->timestamp('fecha_fin')->nullable();
    $table->boolean('activo')->default(true);
    $table->timestamps();
});

Schema::create('producto_descuentos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('producto_id')->constrained()->onDelete('cascade');
    $table->foreignId('descuento_id')->constrained()->onDelete('cascade');
    $table->timestamps();
});

Schema::create('cupons', function (Blueprint $table) {
    $table->id();
    $table->string('codigo')->unique();
    $table->enum('tipo_descuento', ['fijo', 'porcentaje']);
    $table->decimal('valor_descuento', 10, 2);
    $table->decimal('monto_minimo_compra', 10, 2)->default(0);
    $table->integer('usos_totales')->default(1);
    $table->integer('usos_restantes')->default(1);
    $table->date('fecha_inicio')->nullable();
    $table->date('fecha_expiracion')->nullable();
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

---

## 6. Movimientos de Inventario (Guías Unificadas)

En lugar de tener tres tablas separadas e inconsistentes, unificamos los movimientos de inventario bajo un modelo centralizado de **Guías de Inventario**. Esto simplifica las consultas y auditorías de stock.

### Proveedores
```php
Schema::create('proveedores', function (Blueprint $table) {
    $table->id();
    $table->string('razon_social');
    $table->string('ruc', 11)->unique()->nullable();
    $table->string('direccion')->nullable();
    $table->string('contacto_nombre')->nullable();
    $table->string('contacto_celular')->nullable();
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

### Guía de Inventario (Cabecera)
```php
Schema::create('guias_inventario', function (Blueprint $table) {
    $table->id();
    $table->enum('tipo_movimiento', ['Entrada', 'Salida', 'Transferencia']);
    
    // Proveedor (asociado si es entrada por compra)
    $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->onDelete('restrict');
    
    // Origen (null si es una entrada directa desde proveedor/producción externa)
    $table->foreignId('sede_origen_id')->nullable()->constrained('sedes')->onDelete('restrict');
    $table->foreignId('almacen_origen_id')->nullable()->constrained('almacens')->onDelete('restrict');
    
    // Destino (null si es una salida directa por venta, merma o descarte)
    $table->foreignId('sede_destino_id')->nullable()->constrained('sedes')->onDelete('restrict');
    $table->foreignId('almacen_destino_id')->nullable()->constrained('almacens')->onDelete('restrict');
    
    $table->foreignId('tipo_documento_id')->constrained()->onDelete('restrict');
    $table->string('serie', 10);
    $table->integer('correlativo');
    
    $table->date('fecha_movimiento');
    $table->enum('estado', ['Borrador', 'En Tránsito', 'Procesado', 'Anulado'])->default('Borrador'); // 'En Tránsito' es útil para transferencias inter-almacén
    $table->text('motivo')->nullable(); // Ej. Venta, Compra, Merma, Reajuste, Traslado entre almacenes
    
    // Relación opcional con documento de venta comercial
    $table->unsignedBigInteger('venta_id')->nullable(); // Relación débil con ventas
    
    $table->foreignId('creado_por_usuario_id')->constrained('users')->onDelete('restrict');
    $table->timestamps();
    
    $table->unique(['tipo_documento_id', 'serie', 'correlativo']);
});
```

### Detalle de Guía de Inventario
Permite registrar en qué presentación o empaque se movió el producto (ej. 2 costales) y calcula automáticamente la conversión al stock base.
```php
Schema::create('guia_inventario_detalles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('guia_inventario_id')->constrained('guias_inventario')->onDelete('cascade');
    $table->foreignId('variacion_id')->constrained()->onDelete('restrict');
    
    // Empaque utilizado en el movimiento
    $table->foreignId('unidad_medida_id')->constrained('unidades_medida')->onDelete('restrict');
    $table->integer('cantidad'); // Cantidad de empaques (Ej. 2 cajas o 2 costales)
    
    // Datos de conversión al momento de registrar el movimiento (histórico para auditoría)
    $table->integer('factor_conversion')->default(1); // Si se mueve por unidad, es 1. Si es caja de 100, es 100.
    $table->integer('cantidad_base'); // Cantidad real en unidades (cantidad * factor_conversion)
    
    // Kardex Valorizado: Costos de adquisición/producción
    $table->decimal('costo_unitario', 10, 2)->nullable(); // Costo de una unidad base
    $table->decimal('costo_total', 10, 2)->nullable();    // costo_unitario * cantidad_base
    
    $table->timestamps();
});
```

---

## 7. Historial de Transacciones de Inventario (KARDEX)

Para llevar una trazabilidad absoluta de cada producto en cada almacén y evitar descuadres, cada movimiento físico de stock debe registrarse en un Kardex. Esto permite reconstruir la historia del inventario en cualquier momento y saber exactamente quién, cuándo, por qué y cómo cambió el stock de un producto.

### Kardex (Movimientos Físicos de Stock)
```php
Schema::create('kardex', function (Blueprint $table) {
    $table->id();
    
    // Almacén y variación específica a la que pertenece el movimiento
    $table->foreignId('almacen_id')->constrained('almacens')->onDelete('restrict');
    $table->foreignId('variacion_id')->constrained('variacions')->onDelete('restrict');
    
    // Tipo de transacción a nivel de stock base
    $table->enum('tipo_transaccion', ['Entrada', 'Salida']);
    
    // Concepto detallado del movimiento
    $table->string('concepto'); // Ej: "Compra", "Venta Virtual", "Venta Física", "Transferencia - Salida", "Transferencia - Entrada", "Ajuste de Inventario", "Merma"
    
    // Cantidades convertidas en unidades base (la unidad indivisible del producto)
    $table->integer('cantidad');       // La cantidad movida en unidades base
    $table->integer('stock_anterior'); // Stock que había en el almacén antes de la operación
    $table->integer('stock_posterior');// Stock resultante en el almacén después de la operación
    
    // Kardex Valorizado: Registro de costos monetarios
    $table->decimal('costo_unitario', 10, 2)->nullable(); // Costo promedio ponderado o costo de adquisición
    $table->decimal('costo_total', 10, 2)->nullable();    // cantidad * costo_unitario
    $table->decimal('valor_total_almacen', 12, 2)->nullable(); // stock_posterior * costo_unitario (valor de la cuenta en almacén)
    
    // Origen documental del movimiento (Polimorfismo para vincularlo a una Guía o directamente a una Venta si corresponde)
    $table->nullableMorphs('origen_documento'); // Crea: origen_documento_id y origen_documento_type (Ej. App\Models\GuiaInventario, App\Models\Venta, etc.)
    
    $table->foreignId('creado_por_usuario_id')->constrained('users')->onDelete('restrict');
    $table->timestamps();
});
```

#### Flujo de Operación del Kardex según el Motivo:
1. **Compra / Entrada Directa**:
   - Se genera una `guia_inventario` de tipo `Entrada`.
   - Se inserta un registro en `kardex` de tipo `Entrada`, donde `stock_posterior = stock_anterior + cantidad`.
2. **Venta Virtual (E-commerce)**:
   - El cliente compra online. Se registra la `venta`.
   - Automáticamente se procesa la salida física del stock: se genera una `guia_inventario` (tipo `Salida`, motivo `Venta Virtual`).
   - Se genera un registro en `kardex` de tipo `Salida` en el almacén asignado al despacho web.
3. **Venta Física (Tienda)**:
   - El vendedor registra la venta directamente en caja. Se registra la `venta`.
   - Se genera una `guia_inventario` (tipo `Salida`, motivo `Venta Física`).
   - Se genera un registro en `kardex` de tipo `Salida` del almacén asignado a la tienda física en la que se vendió.
4. **Transferencia entre Almacenes**:
   - Se genera una única `guia_inventario` de tipo `Transferencia` con `almacen_origen_id` y `almacen_destino_id`.
   - Cuando se procesa y completa la transferencia, se insertan **dos registros** en el `kardex`:
     - **Registro 1 (Salida)**: En el `almacen_origen_id` de tipo `Salida` con concepto `"Transferencia - Salida"`.
     - **Registro 2 (Entrada)**: En el `almacen_destino_id` de tipo `Entrada` con concepto `"Transferencia - Entrada"`.

---

## 8. Ventas y E-Commerce

### Ventas (Cabecera)
```php
Schema::create('ventas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('restrict');
    
    // Serie y correlativo comercial (factura o boleta de venta)
    $table->foreignId('tipo_documento_id')->constrained()->onDelete('restrict');
    $table->string('serie', 10);
    $table->integer('correlativo');
    
    $table->enum('estado_pago', ['pendiente', 'pagado', 'reembolsado', 'cancelado'])->default('pendiente');
    $table->enum('estado_despacho', ['pendiente', 'preparado', 'despachado', 'entregado'])->default('pendiente');
    
    $table->decimal('subtotal', 10, 2);
    $table->decimal('descuento', 10, 2)->default(0);
    $table->decimal('costo_envio', 10, 2)->default(0);
    $table->decimal('total', 10, 2);
    
    $table->enum('tipo_pago', ['online', 'contraentrega']);
    $table->string('metodo_pago')->nullable(); // Ej. tarjeta, yape, transferencia
    
    $table->foreignId('cupon_id')->nullable()->constrained('cupons')->onDelete('set null');
    $table->text('comentarios')->nullable();
    $table->timestamps();
    
    $table->unique(['tipo_documento_id', 'serie', 'correlativo']);
});
```

### Detalle de Ventas
```php
Schema::create('venta_detalles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('venta_id')->constrained()->onDelete('cascade');
    $table->foreignId('variacion_id')->constrained()->onDelete('restrict');
    
    // Unidad en la que compra el cliente (puede comprar por cajas o por unidades)
    $table->foreignId('unidad_medida_id')->constrained('unidades_medida')->onDelete('restrict');
    $table->integer('cantidad');
    $table->integer('factor_conversion')->default(1);
    $table->integer('cantidad_base'); // cantidad * factor_conversion
    
    $table->decimal('precio_unitario', 10, 2); // Precio por la unidad_medida comprada
    $table->decimal('descuento_aplicado', 10, 2)->default(0);
    $table->decimal('subtotal', 10, 2);
    $table->timestamps();
});
```
