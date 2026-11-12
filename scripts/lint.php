<?php
declare(strict_types=1);
$root=dirname(__DIR__);$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));$failed=false;
foreach($iterator as $file){if(!$file->isFile()||$file->getExtension()!=='php'||str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR))continue;$command=escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()).' 2>&1';exec($command,$output,$status);echo implode(PHP_EOL,$output).PHP_EOL;$output=[];if($status!==0)$failed=true;}
exit($failed?1:0);
