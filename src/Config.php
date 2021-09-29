<?php

namespace Neosmic\ArangoPhpOgm;

use Dotenv\Dotenv;
use ArangoDBClient\ConnectionOptions as ArangoConnectionOptions;
use ArangoDBClient\UpdatePolicy as ArangoUpdatePolicy;

class Config
{

    public static function load($options)
    {

        if ($options == []) {
            $config = config('database.connections')['binarydb'];
            $out['config'] = [
                // database name
                ArangoConnectionOptions::OPTION_DATABASE => $config['name'],
                // server endpoint to connect to
                ArangoConnectionOptions::OPTION_ENDPOINT => $config['server'],
                // authorization type to use (currently supported: 'Basic')
                ArangoConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
                // user for basic authorization
                ArangoConnectionOptions::OPTION_AUTH_USER => $config['user'], 
                // password for basic authorization
                ArangoConnectionOptions::OPTION_AUTH_PASSWD => $config['password'], 
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
            $out['mainNode'] =  $config['main']; 
            $out['nodesCollection'] = $config['nodes']; 
            $out['edgesCollection'] = $config['edges']; 
        } else {
            $env = Dotenv::createMutable($options['envDir']);
            $env->load();
            $out['config'] =   [
                // database name
                ArangoConnectionOptions::OPTION_DATABASE => $_ENV['ADB_NAME'],
                // server endpoint to connect to
                ArangoConnectionOptions::OPTION_ENDPOINT => $_ENV['ADB_SERVER'],
                // authorization type to use (currently supported: 'Basic')
                ArangoConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
                // user for basic authorization
                ArangoConnectionOptions::OPTION_AUTH_USER => $_ENV['ADB_USER'],
                // password for basic authorization
                ArangoConnectionOptions::OPTION_AUTH_PASSWD => $_ENV['ADB_USER_PASSWORD'],
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
            $out['mainNode'] = $_ENV['ADB_MAIN'];
            $out['nodesCollection'] = $_ENV['ADB_NODES'];
            $out['edgesCollection'] = $_ENV['ADB_EDGES'];
        }

        return $out;
    }
}
