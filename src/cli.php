<?php
use Emoji\Manifest;
use Garden\Cli\Cli;
use Garden\Cli\Schema;

error_reporting(E_ALL); //E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);

date_default_timezone_set('America/Montreal');

require_once __DIR__.'/../vendor/autoload.php';

$cli = new Cli();

$cli->command('build')
    ->description('Build an emoji manifest.')
    ->opt('format:f', 'The format of the manifest file. Either json or php')
    ->arg('path', 'The path to to the folder of emoji images.');

$args = $cli->parse($argv);

try {
    $manifest = new Manifest;
    $manifest->setImagePath($args->getArgs()[0]);

    switch ($args->getCommand()) {
        case 'build';
            $manifest->setFormat($args->getOpt('format', 'json'));

            echo "Building emoji manifest";
            $data = $manifest->build($args->getOpt('format', 'json'));
            echo ' ('.count($data['emoji']).')'.PHP_EOL;
            echo 'Writing '.$manifest->getManifestPath().PHP_EOL;
            $manifest->save($data);
            echo 'Writing preview file.'.PHP_EOL;
            $manifest->buildPreview($data);
            break;
    }
} catch (\Exception $ex) {
    echo $cli->red($Ex->getMessage());
    return $ex->getCode();
}
