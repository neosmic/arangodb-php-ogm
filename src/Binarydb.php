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
//use Controller\Globals;
//use Controller\SysMessages;

class BinaryConnection
{
    private static $theInstance = null;
    public $connectionOptions;
    public $connection;
    /********************** DB configuration****************** */
    public static $main_node = "MAIN";
    public static $nodesCollection = "library";
    public static $edgesCollection = "links";
    public static $_tags = [];
    public static $_leaves = [];
    public static $_utc = 0;
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
            // connection persistence on server. can use either 'Close' (one-time connections) or 'Keep-Alive' (re-used connections)
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
        if ($layer == "node") {
            $collection = SELF::$nodesCollection;
        } elseif ($layer == "edge") {
            $collection = SELF::$edgesCollection;
        } else {
            return dd(" $layer layer doesn't exist ");
        }
        return $collection;
    }

    public static function start()
    {
        if (SELF::$theInstance === null) {
            SELF::$theInstance = new self();
        } else {
        }
        return SELF::$theInstance;
    }
    public static function setTags(array $pre_out)
    {
        SELF::$_leaves = $out["leaves"] = $pre_out["leaves"];
        SELF::$_tags = $out["tags"] = $pre_out["tags"];
        SELF::$_utc = $out["utc"] = $pre_out["utc"];
        //dd($out);
        return $out;
    }
    public function queryAnswer($query)
    {

        $statement = new ArangoStatement(
            $this->connection,
            array(
                "query"     => $query,
                "count"     => true,
                "batchSize" => 1000,
                "sanitize"  => true
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
        $conn = SELF::start();
        return $conn->queryAnswer($query);
    }
    public static function all($_tag = null)
    {
        if ($_tag != null) {
            $filter = " FILTER d._tag == '$_tag' ";
        } else {
            $filter = "";
        }

        $query = " FOR d IN " . SELF::$nodesCollection . " " . $filter . " SORT d.dateUpdate RETURN d ";
        //dd($query);
        return SELF::query($query);
    }
    public static function one(string $_id)
    {
        return SELF::query("RETURN DOCUMENT('$_id')")[0];
    }
    public static function main()
    {
        SELF::$main_node = config('binarydb.main_key');
        SELF::$nodesCollection = config('binarydb.nodes_collection');
        SELF::$edgesCollection = config('binarydb.edges_collection');
        $out = SELF::one(SELF::$nodesCollection . "/" . SELF::$main_node);
        SELF::setTags($out);
        return $out;
    }
    public static function parents(string $_key)
    {
        $query = " FOR node, edge IN 1..1 INBOUND '"
            . SELF::$nodesCollection . "/" . $_key . "' "
            . SELF::$edgesCollection
            . " RETURN {_key:node._key,_id:node._id,_tag:node._tag,name:node.name} ";
        return SELF::query($query);
    }
    public static function insert(array $data, string $layer = "node")
    {
        $collection = SELF::layer($layer);
        $query = " INSERT " . json_encode($data)
            . " INTO '" . $collection . "' RETURN NEW ";
        return SELF::query($query);
    }
    public static function update(string $_key, array $data)
    {
        $data["dateUpdate"] = "..NOW..";
        $data_str = json_encode($data);
        $data_str = str_replace('"..NOW.."', " DATE_ADD(DATE_NOW()," . SELF::$_utc . ",'h') ", $data_str);
        $query = " UPDATE  {_key:'$_key'} WITH "
            . $data_str . " IN "
            . SELF::$nodesCollection . " RETURN NEW ";
        //dd($query);
        return SELF::query($query);
    }
    public static function children(string $_key, $_tag = "")
    {
        if ($_tag != "") {
            $filter = " FILTER edge._tag == '$_tag' ";
        } else {
            $filter = "";
        }
        $query = " FOR node, edge IN 1..1 OUTBOUND '"
            . SELF::$nodesCollection . "/" . $_key . "' "
            . SELF::$edgesCollection
            . $filter
            . " RETURN {_key:node._key,_id:node._id,_tag:node._tag,name:node.name}  ";
        return SELF::query($query);
    }
    public static function remove($_key, $layer = "node")
    {
        $collection = SELF::layer($layer);
        $query = " REMOVE {_key:'$_key'} IN " . $collection . "";
        SELF::query($query);
    }
    public static function unlink(string $_from_key, string $_to_key)
    {
        $query = " FOR d IN " . SELF::$edgesCollection
            . " FILTER d._from == '" . SELF::$nodesCollection . "/" . $_from_key
            . "' && d._to == '" . SELF::$nodesCollection . "/" . $_to_key . "' "
            . " REMOVE {_key: d._key} IN  " . SELF::$edgesCollection . " ";
        SELF::query($query);
    }
    public static function link(string $_key_from, string $_key_to, array $data = [])
    {
        $_from = SELF::$nodesCollection . "/" . $_key_from;
        $_to = SELF::$nodesCollection . "/" . $_key_to;
        $data = array_merge(["_from" => $_from, "_to" => $_to], $data);
        return SELF::insert($data, "edge");
    }
    public static function timestamp($_key, $property = "dateUpdate")
    {
        $query = " UPDATE {_key:'$_key'} WITH { $property : DATE_ADD(DATE_NOW(), " . SELF::$_utc . ",'h')} "
            . " IN " . SELF::$nodesCollection . " RETURN NEW";
        return SELF::query($query);
    }
    public static function is_linked(string $_key_from, string $_key_to)
    {
        $query = " FOR d IN " . SELF::$edgesCollection
            . " FILTER d._from =='" . SELF::$nodesCollection . "/" . $_key_from . "' "
            . " && d._to  =='" . SELF::$nodesCollection . "/" . $_key_to . "' "
            . " RETURN d._id ";
        if (SELF::query($query) != null) {
            return true;
        } else {
            return false;
        }
    }
    public static function param(string $_key, string $param, string $layer = "node")
    {
        $collection = SELF::layer($layer);
        return SELF::query("RETURN DOCUMENT('" . $collection . "/" . $_key . "').$param");
    }
}
