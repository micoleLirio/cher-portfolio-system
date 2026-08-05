<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'db_cher_portfolio';
const DB_USER = 'root';
const DB_PASS = '';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $serverDsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';

        $pdo = new PDO(
            $serverDsn,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        $pdo->exec(
            'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '`
             CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci'
        );

        $pdo->exec('USE `' . DB_NAME . '`');

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS contact_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                subject VARCHAR(190) NOT NULL,
                message TEXT NOT NULL,
                status ENUM('Unread', 'Read') NOT NULL DEFAULT 'Unread',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_contact_status (status),
                INDEX idx_contact_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS admin_users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(80) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );

        $checkAdmin = $pdo->prepare(
            'SELECT id FROM admin_users WHERE username = :username LIMIT 1'
        );
        $checkAdmin->execute(['username' => 'admin']);

        if (!$checkAdmin->fetch()) {
            $insertAdmin = $pdo->prepare(
                'INSERT INTO admin_users (username, password_hash)
                 VALUES (:username, :password_hash)'
            );

            $insertAdmin->execute([
                'username'      => 'admin',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            ]);
        }

        $success = 'Database installation completed successfully.';
    } catch (Throwable $exception) {
        $error = 'Installation failed: ' . $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Database Setup</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #08111f;
            color: #f4f7fb;
            font-family: Arial, Helvetica, sans-serif;
        }

        .setup-card {
            width: min(100%, 620px);
            padding: 36px;
            border: 1px solid rgba(165, 193, 224, 0.16);
            border-radius: 22px;
            background: #111f33;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.28);
        }

        .label {
            color: #6ff0bf;
            font-size: 0.75rem;
            font-weight: 900;
            letter-spacing: 0.14em;
        }

        h1 {
            margin-top: 10px;
            font-size: clamp(2rem, 7vw, 3.7rem);
            line-height: 1;
            letter-spacing: -0.05em;
        }

        p {
            margin-top: 18px;
            color: #9baabd;
            line-height: 1.7;
        }

        .details {
            display: grid;
            gap: 10px;
            margin-top: 24px;
            padding: 18px;
            border: 1px solid rgba(165, 193, 224, 0.14);
            border-radius: 14px;
            background: #0d192a;
        }

        .details div {
            display: flex;
            justify-content: space-between;
            gap: 16px;
        }

        .details span {
            color: #8fa0b7;
        }

        .details strong {
            color: #f4f7fb;
            text-align: right;
        }

        .notice {
            margin-top: 20px;
            padding: 14px 16px;
            border-radius: 12px;
            line-height: 1.5;
        }

        .success {
            border: 1px solid rgba(111, 240, 191, 0.35);
            background: rgba(111, 240, 191, 0.09);
            color: #8ff5d0;
        }

        .error {
            border: 1px solid rgba(255, 127, 141, 0.35);
            background: rgba(255, 127, 141, 0.09);
            color: #ff9aa5;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        button,
        a {
            display: inline-flex;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            padding: 0 18px;
            border: 1px solid transparent;
            border-radius: 12px;
            font: inherit;
            font-size: 0.76rem;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #6ff0bf;
            color: #06110d;
        }

        a {
            border-color: rgba(165, 193, 224, 0.16);
            background: #0d192a;
            color: #f4f7fb;
        }

        code {
            color: #6ff0bf;
        }
    </style>
</head>
<body>
    <main class="setup-card">
        <p class="label">LARAGON DATABASE SETUP</p>
        <h1>Portfolio Installer</h1>

        <p>
            This installer creates the MySQL database, contact message table,
            and administrator account needed by your portfolio.
        </p>

        <div class="details">
            <div>
                <span>Database</span>
                <strong><?= htmlspecialchars(DB_NAME) ?></strong>
            </div>
            <div>
                <span>Admin username</span>
                <strong>admin</strong>
            </div>
            <div>
                <span>Default password</span>
                <strong>admin123</strong>
            </div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="notice success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="notice error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <form method="post">
                <button type="submit">INSTALL DATABASE</button>
            </form>

            <?php if ($success !== ''): ?>
                <a href="index.php">OPEN PORTFOLIO</a>
                <a href="admin_messages.php">OPEN ADMIN</a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
