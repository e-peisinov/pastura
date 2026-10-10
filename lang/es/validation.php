<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mensajes de validación
    |--------------------------------------------------------------------------
    |
    | Trato de usted, como el resto de los textos de cara al usuario.
    | Los nombres de los campos se traducen en la clave 'attributes' del final.
    |
    */

    'accepted' => 'Debe aceptar :attribute.',
    'accepted_if' => 'Debe aceptar :attribute cuando :other sea :value.',
    'active_url' => ':attribute no es una URL válida.',
    'after' => ':attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => ':attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => ':attribute solo puede contener letras.',
    'alpha_dash' => ':attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => ':attribute solo puede contener letras y números.',
    'any_of' => ':attribute no es válido.',
    'array' => ':attribute debe ser un conjunto de valores.',
    'ascii' => ':attribute solo puede contener caracteres alfanuméricos y símbolos de un byte.',
    'before' => ':attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => ':attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => ':attribute debe tener entre :min y :max elementos.',
        'file' => ':attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => ':attribute debe estar entre :min y :max.',
        'string' => ':attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => ':attribute debe ser verdadero o falso.',
    'can' => ':attribute contiene un valor no autorizado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'contains' => 'A :attribute le falta un valor requerido.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => ':attribute no es una fecha válida.',
    'date_equals' => ':attribute debe ser una fecha igual a :date.',
    'date_format' => ':attribute no coincide con el formato :format.',
    'decimal' => ':attribute debe tener :decimal decimales.',
    'declined' => 'Debe rechazar :attribute.',
    'declined_if' => 'Debe rechazar :attribute cuando :other sea :value.',
    'different' => ':attribute y :other deben ser distintos.',
    'digits' => ':attribute debe tener :digits dígitos.',
    'digits_between' => ':attribute debe tener entre :min y :max dígitos.',
    'dimensions' => 'Las dimensiones de la imagen de :attribute no son válidas.',
    'distinct' => ':attribute tiene un valor duplicado.',
    'doesnt_contain' => ':attribute no puede contener ninguno de los siguientes valores: :values.',
    'doesnt_end_with' => ':attribute no puede terminar con ninguno de los siguientes valores: :values.',
    'doesnt_start_with' => ':attribute no puede empezar con ninguno de los siguientes valores: :values.',
    'email' => ':attribute no es un correo electrónico válido.',
    'encoding' => ':attribute debe estar codificado en :encoding.',
    'ends_with' => ':attribute debe terminar con uno de los siguientes valores: :values.',
    'enum' => ':attribute seleccionado no es válido.',
    'exists' => ':attribute seleccionado no es válido.',
    'extensions' => ':attribute debe tener una de las siguientes extensiones: :values.',
    'file' => ':attribute debe ser un archivo.',
    'filled' => ':attribute no puede estar vacío.',
    'gt' => [
        'array' => ':attribute debe tener más de :value elementos.',
        'file' => ':attribute debe pesar más de :value kilobytes.',
        'numeric' => ':attribute debe ser mayor que :value.',
        'string' => ':attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'array' => ':attribute debe tener :value elementos o más.',
        'file' => ':attribute debe pesar :value kilobytes o más.',
        'numeric' => ':attribute debe ser mayor o igual que :value.',
        'string' => ':attribute debe tener :value caracteres o más.',
    ],
    'hex_color' => ':attribute debe ser un color hexadecimal válido.',
    'image' => ':attribute debe ser una imagen.',
    'in' => ':attribute seleccionado no es válido.',
    'in_array' => ':attribute debe existir en :other.',
    'in_array_keys' => ':attribute debe contener al menos una de las siguientes claves: :values.',
    'integer' => ':attribute debe ser un número entero.',
    'ip' => ':attribute debe ser una dirección IP válida.',
    'ipv4' => ':attribute debe ser una dirección IPv4 válida.',
    'ipv6' => ':attribute debe ser una dirección IPv6 válida.',
    'json' => ':attribute debe ser una cadena JSON válida.',
    'list' => ':attribute debe ser una lista.',
    'lowercase' => ':attribute debe estar en minúsculas.',
    'lt' => [
        'array' => ':attribute debe tener menos de :value elementos.',
        'file' => ':attribute debe pesar menos de :value kilobytes.',
        'numeric' => ':attribute debe ser menor que :value.',
        'string' => ':attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => ':attribute no puede tener más de :value elementos.',
        'file' => ':attribute debe pesar :value kilobytes o menos.',
        'numeric' => ':attribute debe ser menor o igual que :value.',
        'string' => ':attribute debe tener :value caracteres o menos.',
    ],
    'mac_address' => ':attribute debe ser una dirección MAC válida.',
    'max' => [
        'array' => ':attribute no puede tener más de :max elementos.',
        'file' => ':attribute no puede pesar más de :max kilobytes.',
        'numeric' => ':attribute no puede ser mayor que :max.',
        'string' => ':attribute no puede tener más de :max caracteres.',
    ],
    'max_digits' => ':attribute no puede tener más de :max dígitos.',
    'mimes' => ':attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => ':attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => ':attribute debe tener al menos :min elementos.',
        'file' => ':attribute debe pesar al menos :min kilobytes.',
        'numeric' => ':attribute debe ser al menos :min.',
        'string' => ':attribute debe tener al menos :min caracteres.',
    ],
    'min_digits' => ':attribute debe tener al menos :min dígitos.',
    'missing' => ':attribute no debe estar presente.',
    'missing_if' => ':attribute no debe estar presente cuando :other sea :value.',
    'missing_unless' => ':attribute no debe estar presente salvo que :other sea :value.',
    'missing_with' => ':attribute no debe estar presente cuando :values esté presente.',
    'missing_with_all' => ':attribute no debe estar presente cuando :values estén presentes.',
    'multiple_of' => ':attribute debe ser múltiplo de :value.',
    'not_in' => ':attribute seleccionado no es válido.',
    'not_regex' => 'El formato de :attribute no es válido.',
    'numeric' => ':attribute debe ser un número.',
    'password' => [
        'letters' => ':attribute debe contener al menos una letra.',
        'mixed' => ':attribute debe contener al menos una mayúscula y una minúscula.',
        'numbers' => ':attribute debe contener al menos un número.',
        'symbols' => ':attribute debe contener al menos un símbolo.',
        'uncompromised' => ':attribute apareció en una filtración de datos. Elija otra contraseña.',
    ],
    'present' => ':attribute debe estar presente.',
    'present_if' => ':attribute debe estar presente cuando :other sea :value.',
    'present_unless' => ':attribute debe estar presente salvo que :other sea :value.',
    'present_with' => ':attribute debe estar presente cuando :values esté presente.',
    'present_with_all' => ':attribute debe estar presente cuando :values estén presentes.',
    'prohibited' => ':attribute está prohibido.',
    'prohibited_if' => ':attribute está prohibido cuando :other sea :value.',
    'prohibited_if_accepted' => ':attribute está prohibido cuando se acepta :other.',
    'prohibited_if_declined' => ':attribute está prohibido cuando se rechaza :other.',
    'prohibited_unless' => ':attribute está prohibido salvo que :other esté en :values.',
    'prohibits' => ':attribute impide que :other esté presente.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => ':attribute es obligatorio.',
    'required_array_keys' => ':attribute debe contener entradas para: :values.',
    'required_if' => ':attribute es obligatorio cuando :other es :value.',
    'required_if_accepted' => ':attribute es obligatorio cuando se acepta :other.',
    'required_if_declined' => ':attribute es obligatorio cuando se rechaza :other.',
    'required_unless' => ':attribute es obligatorio salvo que :other esté en :values.',
    'required_with' => ':attribute es obligatorio cuando :values está presente.',
    'required_with_all' => ':attribute es obligatorio cuando :values están presentes.',
    'required_without' => ':attribute es obligatorio cuando :values no está presente.',
    'required_without_all' => ':attribute es obligatorio cuando ninguno de :values está presente.',
    'same' => ':attribute debe coincidir con :other.',
    'size' => [
        'array' => ':attribute debe tener :size elementos.',
        'file' => ':attribute debe pesar :size kilobytes.',
        'numeric' => ':attribute debe ser :size.',
        'string' => ':attribute debe tener :size caracteres.',
    ],
    'starts_with' => ':attribute debe empezar con uno de los siguientes valores: :values.',
    'string' => ':attribute debe ser texto.',
    'timezone' => ':attribute debe ser una zona horaria válida.',
    'unique' => ':attribute ya está en uso.',
    'uploaded' => 'No se pudo subir :attribute.',
    'uppercase' => ':attribute debe estar en mayúsculas.',
    'url' => ':attribute debe ser una URL válida.',
    'ulid' => ':attribute debe ser un ULID válido.',
    'uuid' => ':attribute debe ser un UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensajes de validación personalizados
    |--------------------------------------------------------------------------
    |
    | Reglas de negocio con mensaje propio: 'campo.regla' => 'mensaje'.
    |
    */

    'custom' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombres de los campos
    |--------------------------------------------------------------------------
    |
    | Reemplaza :attribute por un nombre legible. Se van agregando a medida
    | que aparecen los formularios.
    |
    */

    'attributes' => [
        'email' => 'el correo electrónico',
        'password' => 'la contraseña',
        'password_confirmation' => 'la confirmación de la contraseña',
        // El formulario de acceso anida sus campos en `form` (LoginForm).
        'form.email' => 'el correo electrónico',
        'form.password' => 'la contraseña',
        'name' => 'el nombre',

        // Campos del dominio.
        'nombre' => 'el nombre',
        'telefono' => 'el teléfono',
        'direccion' => 'la dirección',
        'localidad' => 'la localidad',
        'documento' => 'el documento',
        'observaciones' => 'las observaciones',
        'precio' => 'el precio',
        'codigo' => 'el código',
        'tipo' => 'el tipo',
        'stock' => 'el stock',
        'stock_minimo' => 'el stock mínimo',
        'marca_neumatico_id' => 'la marca del neumático',
        'ancho' => 'el ancho',
        'perfil' => 'el perfil',
        'rodado' => 'el rodado',
        'cliente_id' => 'el cliente',
        'marca_vehiculo_id' => 'la marca',
        'tipo_vehiculo_id' => 'el tipo de vehículo',
        'modelo' => 'el modelo',
        'patente' => 'la patente',
        'anio' => 'el año',
        'kms_ultimo' => 'el kilometraje',
    ],

];
