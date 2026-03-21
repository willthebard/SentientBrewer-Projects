<?php
/**
 * BuildItPro Database Migration
 * Run: php migrate.php
 */

$config = require __DIR__ . '/config.php';
$db = $config['db'];

try {
    // Connect without database to create it
    $pdo = new PDO(
        "mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}",
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$db['name']}`");

    echo "Database '{$db['name']}' ready.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    echo "Table 'users' created.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            goal TEXT NOT NULL,
            status ENUM('pending','planning','building','reviewing','testing','complete','failed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    echo "Table 'projects' created.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agent_runs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            agent_type ENUM('orchestrator','architect','coder','reviewer','tester','documenter','compiler') NOT NULL,
            iteration INT DEFAULT 1,
            prompt_sent LONGTEXT,
            response_received LONGTEXT,
            tokens_used INT,
            status ENUM('running','success','error') DEFAULT 'running',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    echo "Table 'agent_runs' created.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            filename VARCHAR(500) NOT NULL,
            content LONGTEXT,
            file_type VARCHAR(50),
            version INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    echo "Table 'project_files' created.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            task_order INT NOT NULL,
            task_description LONGTEXT NOT NULL,
            assigned_agent ENUM('architect','coder','reviewer','tester','documenter','compiler'),
            status ENUM('pending','in_progress','complete','failed') DEFAULT 'pending',
            output LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    echo "Table 'project_tasks' created.\n";

    echo "\nMigration complete!\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
