# ArangoDB OGM

Librería en PHP para el mapeo de una base de datos de grafos jerárquica direccionada en ArangoDB

  

[![Build Status](https://app.travis-ci.com/neosmic/arango-php-ogm.svg?token=XpdhS2VXy8REkdNz8g9P&branch=master)](https://app.travis-ci.com/neosmic/arango-php-ogm) [![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=neosmic_arango-php-ogm&metric=alert_status)](https://sonarcloud.io/dashboard?id=neosmic_arango-php-ogm)

## Descripción

Esta librería crea una capa de interacción con la base de datos de grafos de ArangoDB a través de la creación de un objeto que se puede acceder de manera estática. Esta librería está pensada para insertar los datos utilizando únicamente dos colecciones de documentos: una de nodos (Document Collection) y otra de conexiones (Edges Collection)

  

## Requerimientos

  

Se debe crear una base de datos en ArangoDB y crear un archivo de variables llamado .env así:

```text

ADB_SERVER=tcp://127.0.0.1:2589 #Always by tcp protocol
ADB_USER=database_user
ADB_NAME=database_name
ADB_USER_PASSWORD=super_secure_password
ADB_MAIN=main_node_key
ADB_NODES=nodes_collection
ADB_EDGES=edges_collection

```

También puede renombrar el archivo .env.example

  

Debe crearse al menos un documento dentro de la colección de nodos con la propiedad \_tag='main', asimismo se deben asignar las propiedades: *tails*, *tags* y *utc* si puede asignarle el valor de *_key* como 'main'. Ej:

```json

{

"_tag": "main",

"name": "Nodo principal",

"tails":["imagenes","documentos"],

"tags": ["persona","director","estudiante"]

}

```

### En Laravel

  

Se deben agregar las siguientes líneas en el archivo config/database.php como un elemento del array _connections_ :

  

```php

...

'binarydb' => [

'database' => env('ADB_NAME'),

'user' => env('ADB_USER'),

'password' => env('ADB_USER_PASSWORD'),

'server' => env('ADB_SERVER'),

'main' => env('ADB_MAIN'),

'nodes' => env('ADB_NODES'),

'edges' => env('ADB_EDGES')

]

...

```

  

También al iniciar el objeto en Laravel no se require pasar ningún parámetro:

  

```php

  

Neosmic\ArangoPhpOgm\BinaryDb::start(); // Inicia la conexión a la base de datos

$main = Neosmic\ArangoPhpOgm\BinaryDb::main(); //Devuelve el nodo main

...

```

  

## Instalación

````cmd

composer require neosmic/arango-php-ogm

````

  

## Uso

Se debe indicar el directorio del archivo _env_ al momento de inicializar el objeto con la función _Binary::start()_, ej:

```php

// Buscar el archivo .env en la carpeta /src y crear el objeto para manipular la base de datos

$arangoDbOgm = BinaryDb::start([ 'envDir' => realpath(dirname(__FILE__)) .  '/src' ]);

// Devuelve los valores almacenados en el nodo main.

$main = $arangoDbOgm::main();

// Preparar datos para guardar en un nuevo nodo

$data = [

'propiedad' => 'valor',

'propiedad2' => 'valor2'

];

// crea un nuevo nodo almacena los datos y devuelve el nodo creado

$new = $arangoDbOgm::insert($data);

// conecta el nodo creado con el nodo main

$arangoDbOgm::link($new['_key'], $main['_key'], ['_tag' => 'hijo']);

// Eliminar conexiones al nuevo nodo

$arangoDbOgm::unlink($main['_key'], $new['_key']);

// Eliminar el nodo creado

$arangoDbOgm::remove($new['_key']);

```

En las propiedades (campos) de los nodos así como las aristas (conexiones/relaciones), se debe incluir una propiedad _tag, para la búsqueda y filtrado. Esto hace parte del diseño previo de la base de datos, que puede tener una estructura como la que se muestra en el siguiente ejemplo.

Películas y actores

![Grafo de ejemplo](./graph.png)

## Recomendaciones

  

Utilice esta librería sólo en entornos de prueba y bajo su propia responsabilidad.

  

## Contribuciones

  

Todas las contribuciones son bienvenidas. Se recomienda utilizar la librería PHPCS Fixer para mantener el estándar.
