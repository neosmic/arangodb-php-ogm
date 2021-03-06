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
    private static $options = [];


    public function __construct($options = [])
    {
        /**
         * For Laravel, create file binarydb.php
         */
        $this->property = 1;
        self::$options = $options;

        $config = Config::load($options);

        $this->connectionOptions = $config['config'];
        self::$mainNode = $config['mainNode'];
        self::$nodesCollection = $config['nodesCollection'];
        self::$edgesCollection = $config['edgesCollection'];
        $this->connection = new ArangoConnection($this->connectionOptions);
    }
    private static function layer($layer)
    {
        if ($layer == 'node' || null == $layer || 'nodes' == $layer) {
            $collection = self::$nodesCollection;
        } elseif ($layer == 'edge') {
            $collection = self::$edgesCollection;
        } else {
            $collection = self::$nodesCollection;
        }
        return $collection;
    }

    public static function start($options = [])
    {
        if (self::$theInstance === null) {
            self::$theInstance = new self($options);
            self::setTags(self::main());
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
    public static function all(
        $options = [
            'tags' => [],
            'pagination' => null,
            'posfilter' => '',
            'return' => ' d ',
            'layer' => 'nodes'
        ]
    ): array {
        $inputs = [
            'tags' => [],
            'posfilter' => '',
            'pagination' => null,
            'return' => ' d ',
            'layer' => 'nodes'
        ];
        foreach ($inputs as $key => $value) {
            $options[$key] = array_key_exists($key, $options) ? $options[$key]  : $value;
            # code...
        }
        $layer = self::layer($options['layer']);
        $filter =  PreProcess::filterOr('_tag', $options['tags']);
        if ($options['pagination'] != null && is_array($options['pagination'])) {
            $pagination = PreProcess::addPagination($options['pagination'][0], $options['pagination'][1]);
        } else {
            $pagination = '';
        }
        $query = ' FOR d IN ' . $layer . ' '
            . $filter . ' SORT d.dateUpdate '
            . $options['posfilter'] . ' '
            . $pagination . ' RETURN ' . $options['return'] . ' ';
        return self::query($query);
    }
    public static function one(
        string $key,
        $options = [
            'layer' => 'node',
            'return' => null
        ]
    ): array {
        $inputs = [
            'return' => null,
            'layer' => 'node'
        ];
        foreach ($inputs as $skey => $value) {
            $options[$skey] = array_key_exists($skey, $options) ? $options[$skey]  : $value;
            # code...
        }

        $collection = self::layer($options['layer']);
        if (null == $options['return']) {
            return self::query("RETURN DOCUMENT('" . $collection . '/' . $key . "')")[0];
        } else {
            $query = ' FOR d IN ' . $collection
                . ' FILTER d._key == \'' . $key . '\''
                . ' RETURN ' . $options['return'];
            return self::query($query);
        }
    }
    public static function main()
    {
        $out = self::one(self::$mainNode);
        return $out;
    }
    public static function parents(string $key, $options = [])
    {
        $inputs = [
            'tags' => [],
            'return' => ' {_key:node._key,_id:node._id,_tag:node._tag,name:node.name,_outtag:edge._tag} '
        ];
        foreach ($inputs as $skey => $value) {
            $options[$skey] = array_key_exists($skey, $options) ? $options[$skey]  : $value;
            # code...
        }
        $filter =  PreProcess::filterOr('_tag', $options['tags'], 'edge');
        $query = " FOR node, edge IN 1..1 INBOUND '"
            . self::$nodesCollection . '/' . $key . "' "
            . self::$edgesCollection
            . $filter
            . ' RETURN' . $options['return'];
        return self::query($query);
    }
    public static function insert(array $data, string $layer = 'node'): array
    {
        $collection = self::layer($layer);
        $query = ' INSERT ' . json_encode($data)
            . " INTO '" . $collection . "' RETURN NEW ";
        $key = self::query($query)[0]['_key'];
        return self::timestamp($key, $layer);
    }
    public static function update(string $key, array $data, string $layer = 'node', string $property = 'dateUpdate')
    {
        $collection = self::layer($layer);
        self::timestamp($key, $layer, $property);
        $query = " UPDATE  {_key:'$key'} WITH "
            . json_encode($data) . ' IN '
            . $collection . ' RETURN NEW ';
        return self::query($query)[0];
    }
    public static function children(
        string $key,
        $options = []
    ): array {
        $inputs = [
            'tags' => '',
            'return' => '{'
                . '_key:node._key,'
                . '_id:node._id,'
                . '_tag:node._tag,'
                . 'name:node.name,'
                . 'content:node.content,'
                . '_outtag:edge._tag'
                . '}',
            'posfilter' => ''
        ];
        foreach ($inputs as $skey => $value) {
            $options[$skey] = array_key_exists($skey, $options) ? $options[$skey]  : $value;
            # code...
        }
        if (null == $options['tags'] || '' == $options['tags']) {
            $filter = '';
        } else {
            $filter = PreProcess::filterOr('_tag', $options['tags'], 'edge'); // " FILTER edge._tag == '$tag' ";
        }
        $query = " FOR node, edge IN 1..1 OUTBOUND '"
            . self::$nodesCollection . '/' . $key . "' "
            . self::$edgesCollection
            . $filter . '  '
            . $options['posfilter'] . ' '
            . ' RETURN '
            . $options['return'];
        return self::query($query);
    }
    public static function descendant(string $startnode, $options = [])
    {
        $inputs = [
            'tags' => [],
            'return' => ' node ',
            'deep' => 10,
            'prefilter' => '',
            'posfilter' => ''
        ];
        foreach ($inputs as $skey => $value) {
            $options[$skey] = array_key_exists($skey, $options) ? $options[$skey]  : $value;
            # code...
        }
        if (null == $options['tags'] || '' == $options['tags']) {
            $filter = '';
        } else {
            $filter = PreProcess::filterOr('_tag', $options['tags'], 'edge');
        }
        $query = "FOR node, edge, path IN 1.." . $options['deep'] . " OUTBOUND "
            . " '" . self::$nodesCollection . '/' . $startnode . "' " . self::$edgesCollection . " "
            . $options['prefilter'] . ' '
            . $filter . ' '
            . $options['posfilter'] . ' '
            . " RETURN " . $options['return'];
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
    public static function timestamp($key, string $layer = 'node', string $property = 'dateUpdate')
    {
        $collection = self::layer($layer);
        $query = " UPDATE {_key:'$key'} WITH { $property : DATE_ADD(DATE_NOW(), " . self::$utc . ",'h')} "
            . ' IN ' . $collection . ' RETURN NEW';
        return self::query($query)[0];
    }
    public static function isLinked(string $keyFrom, string $keyTo): bool
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
        return self::query("RETURN DOCUMENT('" . $collection . '/' . $key . "').$param")[0];
    }
    public static function unique(
        string $key,
        string $tag,
        array $combination = [
            //'property' => 'value',
            //'property_n' => 'value2'
        ],
        string $postfilter = ''
    ): bool {
        $filter = function ($combination): string {
            $out = ' FILTER ';
            $and = '';
            foreach ($combination as $skey => $svalue) {
                $out = $out . $and . 'd.' . $skey . ' == \'' . $svalue . '\' ';
                $and = ' && ';
            }
            return $out;
        };

        $filterOut = $filter($combination) . " && d._key !='$key' ";
        $query = ' FOR d IN ' . self::$nodesCollection . '  '
            . ' FILTER d._tag == \'' . $tag . '\' '
            . $filterOut . ' RETURN true ';
        if (true == self::query($query)) {
            return false;
        } else {
            return true;
        }
    }
    public static function uniqueChild(
        string $parentKey,
        string $sonKey,
        string $tag,
        array $combination = [
            //'property' => 'value',
            //'property_n' => 'value2'
        ],
        string $posfilter = ''
    ): bool {
        $filter = function ($combination): string {
            $out = ' FILTER ';
            $and = '';
            foreach ($combination as $skey => $svalue) {
                $out = $out . $and . 'node.' . $skey . ' == \'' . $svalue . '\' ';
                $and = ' && ';
            }
            return $out;
        };
        $query = 'FOR node IN 1..1 OUTBOUND \''
            . self::$nodesCollection . '/' . $parentKey . '\' '
            . self::$edgesCollection . ' '
            . ' FILTER node._tag == \'' . $tag . '\' '
            . $filter($combination) . " && node._key !='$sonKey' "
            . $posfilter
            . ' RETURN true ';
        if (true == self::query($query)) {
            return false;
        } else {
            return true;
        }
    }
}
