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

function getEnvironmentVariable ( string $variableName, $default, $castingFunctionName = null )
{
    $keyExists = array_key_exists( $variableName, $_ENV );
    $value = null;

    if ( $keyExists ) {
        $value = $_ENV[$variableName];
    } else {
        $dotenv = Dotenv::createImmutable( __DIR__ . "/../" );
        $dotenv->load();

        $value = getenv( $variableName );

        if ( empty( $value ) ) {
            $value = $default;
        }
    }

    if ( !empty( $castingFunctionName ) ) {
        return call_user_func( $castingFunctionName, $value );
    }

    return $value;
}

$dbHost = getEnvironmentVariable( "DB_HOST", null );
$dbUsername = getEnvironmentVariable( "DB_USERNAME", null );
$dbPassword = getEnvironmentVariable( "DB_PASSWORD", null );
$dbPort = getEnvironmentVariable( "DB_PORT", null );
$dbDatabase = getEnvironmentVariable( "DB_DATABASE", null );

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

function getIpAddress () : array
{
    $result = [];
    $result["HTTP_CLIENT_IP"] = empty( $_SERVER["HTTP_CLIENT_IP"] ) ? null : $_SERVER["HTTP_CLIENT_IP"];
    $result["HTTP_X_FORWARDED_FOR"] = empty( $_SERVER["HTTP_X_FORWARDED_FOR"] ) ? null : $_SERVER["HTTP_X_FORWARDED_FOR"];
    $result["REMOTE_ADDR"] = empty( $_SERVER["REMOTE_ADDR"] ) ? null : $_SERVER["REMOTE_ADDR"];

    return $result;
}

try {
    if ( isset( $_GET["skip"] ) ) {
        echo ".";
        exit( 0 );
    }

    $ipInfo = getIpAddress();

    $databaseLoader->load( new Row( [
        "http_client_ip" => $ipInfo["HTTP_CLIENT_IP"],
        "http_x_forwarded_for" => $ipInfo["HTTP_X_FORWARDED_FOR"],
        "remote_addr" => $ipInfo["REMOTE_ADDR"]
    ] ) );

    $query = "SELECT COUNT(id) AS total_visits FROM visit";

    $databaseExtractor = new DatabaseExtractor( $mysqlConnector, $query );

    $result = $databaseExtractor->extract();

    $totalVisits = $result[0]->total_visits;

    $image = imagecreatetruecolor( 350, 20 );

    // Blue text
    $textColor = imagecolorallocate( $image, 0, 0, 255 );

    // Write the string at the top left
    imagestring( $image, 5, 0, 0, "My page has been loaded $totalVisits times!", $textColor );

    imagesavealpha( $image, true );
    $color = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
    imagefill( $image, 0, 0, $color );

    // Output the image
    header( 'Content-type: image/png' );

    $ts = gmdate( "D, d M Y H:i:s" ) . " GMT";
    header( "Expires: $ts" );
    header( "Last-Modified: $ts" );
    header( "Pragma: no-cache" );
    header( "Cache-Control: no-cache, must-revalidate" );

    imagepng( $image );
    imagedestroy( $image );
} catch ( SourceWatcherException $e ) {
    echo $e->getMessage();
}
