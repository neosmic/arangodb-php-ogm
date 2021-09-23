<?php

namespace Neosmic\ArangoPhpOgm;

use Config;
use ArangoDBClient\Collection as ArangoCollection;
use ArangoDBClient\CollectionHandler as ArangoCollectionHandler;
use ArangoDBClient\Connection as ArangoConnection;
use ArangoDBClient\ConnectionOptions as ArangoConnectionOptions;
use ArangoDBClient\DocumentHandler as ArangoDocumentHandler;
use ArangoDBClient\Document as ArangoDocument;
use ArangoDBClient\Exception as ArangoException;
use ArangoDBClient\Export as ArangoExport;
use ArangoDBClient\ConnectException as ArangoConnectException;
use ArangoDBClient\ClientException as ArangoClientException;
use ArangoDBClient\ServerException as ArangoServerException;
use ArangoDBClient\Statement as ArangoStatement;
use ArangoDBClient\UpdatePolicy as ArangoUpdatePolicy;

class BinaryConnection
{
    private static $theInstance = null;
    public $connectionOptions;
    public $connection;
    /********************** DB configuration****************** */
    public static $mainNode = 'MAIN';
    public static $nodesCollection = 'library';
    public static $edgesCollection = 'links';
    public static $labels = [];
    public static $tails = [];
    public static $utc = 0;
    public static $setted = false;

    public function __construct()
    {
        //dd( config('binarydb.db_server'));
        $this->property = 1;


        // set up some basic connection options
        $this->connectionOptions = [
            // database name
            ArangoConnectionOptions::OPTION_DATABASE => config('binarydb.db_name'), // $this->db_name,
            // server endpoint to connect to
            ArangoConnectionOptions::OPTION_ENDPOINT => config('binarydb.db_server'), // $this->db_server,

            // authorization type to use (currently supported: 'Basic')
            ArangoConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
            // user for basic authorization
            ArangoConnectionOptions::OPTION_AUTH_USER => config('binarydb.db_user'), //$this->db_user,
            // password for basic authorization
            ArangoConnectionOptions::OPTION_AUTH_PASSWD => config('binarydb.db_user_password'), // $this->db_user_pass,
            ArangoConnectionOptions::OPTION_CONNECTION => 'Keep-Alive',
            // connect timeout in seconds
            ## ArangoConnectionOptions::OPTION_TIMEOUT => 3,
            // whether or not to reconnect when a keep-alive connection has timed out on server
            ArangoConnectionOptions::OPTION_RECONNECT => true,
            // optionally create new collections when inserting documents
            ArangoConnectionOptions::OPTION_CREATE => true,
            // optionally create new collections when inserting documents
            ArangoConnectionOptions::OPTION_UPDATE_POLICY => ArangoUpdatePolicy::LAST,
        ];

        // turn on exception logging (logs to whatever PHP is configured)
        //Activar para obtener información adicional de los errores
        //ArangoException::enableLogging();
        //Comentarios omitidos
        $this->connection = new ArangoConnection($this->connectionOptions);
    }
    private static function layer($layer)
    {
        if ($layer == 'node') {
            $collection = self::$nodesCollection;
        } elseif ($layer == 'edge') {
            $collection = self::$edgesCollection;
        } else {
            return dd(" $layer layer doesn't exist ");
        }
        return $collection;
    }

    public static function start()
    {
        if (self::$theInstance === null) {
            self::$theInstance = new self();
        } else {
        }
        return self::$theInstance;
    }
    public static function setTags(array $preOut)
    {
        self::$tails = $out['leaves'] = $preOut['leaves'];
        self::$labels = $out['tags'] = $preOut['tags'];
        self::$utc = $out['utc'] = $preOut['utc'];
        //dd($out);
        return $out;
    }
    public function queryAnswer($query)
    {

        $statement = new ArangoStatement(
            $this->connection,
            array(
                'query'     => $query,
                'count'     => true,
                'batchSize' => 1000,
                'sanitize'  => true
            )
        );
        try {
            $cursor = $statement->execute();
        } catch (\Exception $e) {
            dd($query);
            // echo "<script> console.log(\"{$e}\")</script>";
            // //throw $th;
            // echo "<h2>Error en el llamado a la Base datos, por favor contacte al administrador</h2>";
            // print_r("Query_Fallido", $query);
            //$this->messages::show_errors_js();
            die;
        }
        return $cursor->getMetadata()['result'];
    }
    public static function query($query)
    {
        $conn = self::start();
        return $conn->queryAnswer($query);
    }
    public static function all($tag = null)
    {
        if ($tag != null) {
            $filter = " FILTER d._tag == '$tag' ";
        } else {
            $filter = '';
        }

        $query = ' FOR d IN ' . self::$nodesCollection . ' ' . $filter . ' SORT d.dateUpdate RETURN d ';
        //dd($query);
        return self::query($query);
    }
    public static function one(string $id)
    {
        return self::query("RETURN DOCUMENT('$id')")[0];
    }
    public static function main()
    {
        self::$mainNode = config('binarydb.main_key');
        self::$nodesCollection = config('binarydb.nodes_collection');
        self::$edgesCollection = config('binarydb.edges_collection');
        $out = self::one(self::$nodesCollection . '/' . self::$mainNode);
        self::setTags($out);
        return $out;
    }
    public static function parents(string $key)
    {
        $query = " FOR node, edge IN 1..1 INBOUND '"
            . self::$nodesCollection . '/' . $key . "' "
            . self::$edgesCollection
            . ' RETURN {_key:node._key,id:node._id,_tag:node._tag,name:node.name} ';
        return self::query($query);
    }
    public static function insert(array $data, string $layer = 'node')
    {
        $collection = self::layer($layer);
        $query = ' INSERT ' . json_encode($data)
            . " INTO '" . $collection . "' RETURN NEW ";
        return self::query($query);
    }
    public static function update(string $key, array $data)
    {
        $data['dateUpdate'] = '..NOW..';
        $dataStr = json_encode($data);
        $dataStr = str_replace('"..NOW.."', ' DATE_ADD(DATE_NOW(),' . self::$utc . ",'h') ", $dataStr);
        $query = " UPDATE  {_key:'$key'} WITH "
            . $dataStr . ' IN '
            . self::$nodesCollection . ' RETURN NEW ';
        //dd($query);
        return self::query($query);
    }
    public static function children(string $key, $tag = '')
    {
        if ($tag != '') {
            $filter = " FILTER edge._tag == '$tag' ";
        } else {
            $filter = '';
        }
        $query = " FOR node, edge IN 1..1 OUTBOUND '"
            . self::$nodesCollection . '/' . $key . "' "
            . self::$edgesCollection
            . $filter
            . ' RETURN {_key:node._key,id:node._id,_tag:node._tag,name:node.name}  ';
        return self::query($query);
    }
    public static function remove($key, $layer = 'node')
    {
        $collection = self::layer($layer);
        $query = " REMOVE {_key:'$key'} IN " . $collection . '';
        self::query($query);
    }
    public static function unlink(string $fromKey, string $toKey)
    {
        $query = ' FOR d IN ' . self::$edgesCollection
            . " FILTER d._from == '" . self::$nodesCollection . '/' . $fromKey
            . "' && d._to == '" . self::$nodesCollection . '/' . $toKey . "' "
            . ' REMOVE {_key: d._key} IN  ' . self::$edgesCollection . ' ';
        self::query($query);
    }
    public static function link(string $keyFrom, string $keyTo, array $data = [])
    {
        $_from = self::$nodesCollection . '/' . $keyFrom;
        $_to = self::$nodesCollection . '/' . $keyTo;
        $data = array_merge(['_from' => $_from, '_to' => $_to], $data);
        return self::insert($data, 'edge');
    }
    public static function timestamp($key, $property = 'dateUpdate')
    {
        $query = " UPDATE {_key:'$key'} WITH { $property : DATE_ADD(DATE_NOW(), " . self::$utc . ",'h')} "
            . ' IN ' . self::$nodesCollection . ' RETURN NEW';
        return self::query($query);
    }
    public static function isLinked(string $keyFrom, string $keyTo)
    {
        $query = ' FOR d IN ' . self::$edgesCollection
            . " FILTER d._from =='" . self::$nodesCollection . '/' . $keyFrom . "' "
            . " && d._to  =='" . self::$nodesCollection . '/' . $keyTo . "' "
            . ' RETURN d._id ';
        if (self::query($query) != null) {
            return true;
        } else {
            return false;
        }
    }
    public static function param(string $key, string $param, string $layer = 'node')
    {
        $collection = self::layer($layer);
        return self::query("RETURN DOCUMENT('" . $collection . '/' . $key . "').$param");
    }
}
