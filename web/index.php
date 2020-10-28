<?php
error_reporting( E_ALL );
ini_set( 'display_errors', 'on' );

require_once __DIR__ . "/../vendor/autoload.php";

use Coco\SourceWatcher\Core\Database\Connections\MySqlConnector;
use Coco\SourceWatcher\Core\Extractors\DatabaseExtractor;
use Coco\SourceWatcher\Core\IO\Outputs\DatabaseOutput;
use Coco\SourceWatcher\Core\Loaders\DatabaseLoader;
use Coco\SourceWatcher\Core\Row;
use Coco\SourceWatcher\Core\SourceWatcherException;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable( __DIR__ . "/../" );
$dotenv->load();

$dbHost = getenv( "DB_HOST" );
$dbUsername = getenv( "DB_USERNAME" );
$dbPassword = getenv( "DB_PASSWORD" );
$dbPort = getenv( "DB_PORT" );
$dbDatabase = getenv( "DB_DATABASE" );

$mysqlConnector = new MySqlConnector();
$mysqlConnector->setHost( $dbHost );
$mysqlConnector->setUser( $dbUsername );
$mysqlConnector->setPassword( $dbPassword );
$mysqlConnector->setPort( intval( $dbPort ) );
$mysqlConnector->setDbName( $dbDatabase );
$mysqlConnector->setTableName( "visit" );

$databaseOutput = new DatabaseOutput( $mysqlConnector );

$databaseLoader = new DatabaseLoader();
$databaseLoader->setOutput( $databaseOutput );

function getIpAddress () : string
{
    if ( !empty( $_SERVER["HTTP_CLIENT_IP"] ) ) {
        return $_SERVER["HTTP_CLIENT_IP"];
    } elseif ( !empty( $_SERVER["HTTP_X_FORWARDED_FOR"] ) ) {
        return $_SERVER["HTTP_X_FORWARDED_FOR"];
    } else {
        return $_SERVER["REMOTE_ADDR"];
    }
}

try {
    $databaseLoader->load( new Row( [ "ip_address" => getIpAddress() ] ) );

    $query = "SELECT COUNT(id) AS total_visits FROM visit";

    $databaseExtractor = new DatabaseExtractor( $mysqlConnector, $query );

    $result = $databaseExtractor->extract();

    $totalVisits = $result[0]->total_visits;

    $image = imagecreate( 350, 20 );

    // White background and blue text
    $background = imagecolorallocate( $image, 255, 255, 255 );
    $textColor = imagecolorallocate( $image, 0, 0, 255 );

    // Write the string at the top left
    imagestring( $image, 5, 0, 0, "My page has been loaded $totalVisits times!", $textColor );

    // Output the image
    header( 'Content-type: image/png' );

    imagepng( $image );
    imagedestroy( $image );
} catch ( SourceWatcherException $e ) {
}
