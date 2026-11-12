<?php
declare(strict_types=1);
$host=getenv('DB_HOST')?:'127.0.0.1';$port=getenv('DB_PORT')?:'3306';$name=getenv('DB_NAME')?:'CAT2';$user=getenv('DB_USER')?:'root';$pass=getenv('DB_PASS')?:'';
if(!preg_match('/^[A-Za-z0-9_]+$/',$name))throw new RuntimeException('DB_NAME contains unsupported characters.');
$db=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(190) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$check=$db->prepare('SELECT 1 FROM schema_migrations WHERE migration=?');$mark=$db->prepare('INSERT INTO schema_migrations(migration) VALUES(?)');
foreach(glob(dirname(__DIR__).'/database/migrations/*.sql') as $file){$migration=basename($file);$check->execute([$migration]);if($check->fetchColumn()){echo "Already applied: $migration\n";continue;}$sql=file_get_contents($file);if($sql===false)throw new RuntimeException("Cannot read $migration");foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement){$statement=trim($statement);if($statement!=='')$db->exec($statement);}$mark->execute([$migration]);echo "Applied: $migration\n";}
