# ArangoDB OGM
 Librería en PHP para el mapeo de una base de datos de grafos en ArangoDB

[![Build Status](https://app.travis-ci.com/neosmic/arango-php-ogm.svg?token=XpdhS2VXy8REkdNz8g9P&branch=master)](https://app.travis-ci.com/neosmic/arango-php-ogm)    [![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=neosmic_arango-php-ogm&metric=alert_status)](https://sonarcloud.io/dashboard?id=neosmic_arango-php-ogm)
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
    '_tag':'main'
}
```

## Instalación
````cmd
composer require neosmic/arango-php-ogm
````

## Uso
Requiere un archivo .env y se debe indicar el directorio del mismo al momento de inicializar el objeto, ej:
```php
$arangoDbOgm = Neosmic\ArangoPhpOgm\BinaryDb::start('./app/src');
$main = $arangoDbOgm::main(); // Devuelve los valores almacenados en el nodo main.
$data = ['propiedad' => 'valor', 'propiedad2' => 'valor2'];
$new = $arangoDbOgm::insert($data); // crea un nuevo nodo y devuelve el nodo creado
$arangoDbOgm::link($new['_key'], $main['_key'], ['_tag' => 'hijo']); // conecta el nodo creado con el nodo main
```
## Recomendaciones

Utilice esta librería sólo en entornos de prueba y bajo su propia responsabilidad.

## Contribuciones

Todas las contribuciones son bienvenidas, sin embargo por ahora hace falta un constructor de esquemas, o una integración con uno existente, para desarrollos en Laravel.
