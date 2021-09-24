<?php

namespace Neosmic\ArangoPhpOgm;

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

class BinaryDb
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


    public function __construct($dir = __DIR__)
    {
        $this->property = 1;
        $config = Config::load($dir);
        $this->connectionOptions = $config['config'];
        self::$mainNode = $config['mainNode'];
        self::$nodesCollection = $config['nodesCollection'];
        self::$edgesCollection = $config['edgesCollection'];
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

    public static function start($dir = __DIR__)
    {
        if (self::$theInstance === null) {
            self::$theInstance = new self($dir);
        } else {
        }
        return self::$theInstance;
    }
    public static function setTags(array $preOut)
    {
        self::$tails = $out['tails'] = $preOut['tails'];
        self::$labels = $out['tags'] = $preOut['tags'];
        self::$utc = $out['utc'] = $preOut['utc'];
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
            print($query);
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
        return self::query($query);
    }
    public static function one(string $id)
    {
        return self::query("RETURN DOCUMENT('$id')")[0];
    }
    public static function main()
    {
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
        return self::query($query) != null ? true : false;
    }
    public static function param(string $key, string $param, string $layer = 'node')
    {
        $collection = self::layer($layer);
        return self::query("RETURN DOCUMENT('" . $collection . '/' . $key . "').$param");
    }
}
