<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function createCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function redirectToAdmin(): never
{
    header('Location: admin_messages.php');
    exit;
}

$error = '';
$success = '';

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $exception) {
    $pdo = null;
    $error = 'Database connection failed. Open setup.php first.';
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: admin_messages.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $statement = $pdo->prepare(
            'SELECT id, username, password_hash
             FROM admin_users
             WHERE username = :username
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $admin = $statement->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['portfolio_admin_id'] = (int) $admin['id'];
            $_SESSION['portfolio_admin_username'] = $admin['username'];

            redirectToAdmin();
        }

        $error = 'Incorrect username or password.';
    }

    if (isset($_SESSION['portfolio_admin_id'])) {
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');

        if (!verifyCsrfToken($csrfToken)) {
            $error = 'Invalid request. Refresh the page and try again.';
        } elseif ($action === 'mark_read') {
            $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);

            if ($messageId) {
                $statement = $pdo->prepare(
                    "UPDATE contact_messages
                     SET status = 'Read'
                     WHERE id = :id"
                );
                $statement->execute(['id' => $messageId]);
            }

            redirectToAdmin();
        } elseif ($action === 'mark_unread') {
            $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);

            if ($messageId) {
                $statement = $pdo->prepare(
                    "UPDATE contact_messages
                     SET status = 'Unread'
                     WHERE id = :id"
                );
                $statement->execute(['id' => $messageId]);
            }

            redirectToAdmin();
        } elseif ($action === 'delete') {
            $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);

            if ($messageId) {
                $statement = $pdo->prepare(
                    'DELETE FROM contact_messages WHERE id = :id'
                );
                $statement->execute(['id' => $messageId]);
            }

            redirectToAdmin();
        } elseif ($action === 'change_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (strlen($newPassword) < 8) {
                $error = 'The new password must have at least 8 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'The new passwords do not match.';
            } else {
                $statement = $pdo->prepare(
                    'SELECT password_hash
                     FROM admin_users
                     WHERE id = :id
                     LIMIT 1'
                );
                $statement->execute([
                    'id' => (int) $_SESSION['portfolio_admin_id'],
                ]);
                $admin = $statement->fetch();

                if (!$admin || !password_verify(
                    $currentPassword,
                    $admin['password_hash']
                )) {
                    $error = 'The current password is incorrect.';
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE admin_users
                         SET password_hash = :password_hash
                         WHERE id = :id'
                    );
                    $statement->execute([
                        'password_hash' => password_hash(
                            $newPassword,
                            PASSWORD_DEFAULT
                        ),
                        'id' => (int) $_SESSION['portfolio_admin_id'],
                    ]);

                    $success = 'Password updated successfully.';
                }
            }
        }
    }
}

$isLoggedIn = isset($_SESSION['portfolio_admin_id']);
$messages = [];
$totalMessages = 0;
$unreadMessages = 0;

if ($isLoggedIn && $pdo instanceof PDO) {
    $totalMessages = (int) $pdo
        ->query('SELECT COUNT(*) FROM contact_messages')
        ->fetchColumn();

    $unreadMessages = (int) $pdo
        ->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'Unread'")
        ->fetchColumn();

    $messages = $pdo
        ->query(
            'SELECT id, full_name, email, subject, message, status, created_at
             FROM contact_messages
             ORDER BY created_at DESC'
        )
        ->fetchAll();
}

$csrfToken = createCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Messages | Admin</title>
    <style>
        :root {
            --background: #08111f;
            --background-two: #0d192a;
            --card: #111f33;
            --card-light: #172a43;
            --text: #f4f7fb;
            --muted: #92a1b5;
            --primary: #6ff0bf;
            --danger: #ff7f8d;
            --border: rgba(165, 193, 224, 0.15);
            --shadow: 0 25px 70px rgba(0, 0, 0, 0.28);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            padding: 28px;
            background:
                radial-gradient(circle at 12% 10%, rgba(111, 240, 191, 0.08), transparent 25rem),
                var(--background);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        button,
        input {
            font: inherit;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: min(100%, 1180px);
            margin: 0 auto;
        }

        .login-card {
            width: min(100%, 470px);
            margin: 8vh auto 0;
            padding: 34px;
            border: 1px solid var(--border);
            border-radius: 22px;
            background: var(--card);
            box-shadow: var(--shadow);
        }

        .label {
            color: var(--primary);
            font-size: 0.7rem;
            font-weight: 900;
            letter-spacing: 0.14em;
        }

        h1 {
            margin-top: 8px;
            font-size: clamp(2rem, 6vw, 3.5rem);
            line-height: 1;
            letter-spacing: -0.05em;
        }

        .description {
            margin-top: 15px;
            color: var(--muted);
            line-height: 1.65;
        }

        .form {
            display: grid;
            gap: 15px;
            margin-top: 25px;
        }

        label {
            display: grid;
            gap: 7px;
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.05em;
        }

        input {
            width: 100%;
            min-height: 47px;
            padding: 0 13px;
            border: 1px solid var(--border);
            outline: none;
            border-radius: 11px;
            background: var(--background-two);
            color: var(--text);
        }

        input:focus {
            border-color: var(--primary);
        }

        .button {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            padding: 0 16px;
            border: 1px solid transparent;
            border-radius: 11px;
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.05em;
            cursor: pointer;
        }

        .primary-button {
            background: var(--primary);
            color: #06110d;
        }

        .secondary-button {
            border-color: var(--border);
            background: var(--background-two);
            color: var(--text);
        }

        .danger-button {
            border-color: rgba(255, 127, 141, 0.32);
            background: rgba(255, 127, 141, 0.08);
            color: var(--danger);
        }

        .notice {
            margin-top: 17px;
            padding: 13px 15px;
            border-radius: 11px;
            line-height: 1.5;
        }

        .error {
            border: 1px solid rgba(255, 127, 141, 0.35);
            background: rgba(255, 127, 141, 0.09);
            color: #ff9ba6;
        }

        .success {
            border: 1px solid rgba(111, 240, 191, 0.35);
            background: rgba(111, 240, 191, 0.09);
            color: #8ff5d0;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .topbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 22px;
        }

        .stat {
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: var(--card);
        }

        .stat span {
            color: var(--muted);
            font-size: 0.7rem;
            font-weight: 900;
            letter-spacing: 0.1em;
        }

        .stat strong {
            display: block;
            margin-top: 7px;
            color: var(--primary);
            font-size: 2rem;
        }

        .message-list {
            display: grid;
            gap: 15px;
        }

        .message-card {
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--card);
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.08);
        }

        .message-card.unread {
            border-color: rgba(111, 240, 191, 0.38);
        }

        .message-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
        }

        .message-header h2 {
            font-size: 1rem;
        }

        .message-header a {
            display: inline-block;
            margin-top: 4px;
            color: var(--primary);
            font-size: 0.8rem;
        }

        .status {
            padding: 6px 9px;
            border-radius: 8px;
            background: var(--card-light);
            color: var(--muted);
            font-size: 0.66rem;
            font-weight: 900;
            letter-spacing: 0.06em;
        }

        .unread .status {
            background: rgba(111, 240, 191, 0.1);
            color: var(--primary);
        }

        .subject {
            margin-top: 18px;
            font-weight: 900;
        }

        .message-text {
            margin-top: 10px;
            color: var(--muted);
            line-height: 1.7;
            white-space: pre-wrap;
        }

        .message-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .date {
            color: var(--muted);
            font-size: 0.73rem;
        }

        .message-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .password-card {
            margin-top: 26px;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--card);
        }

        .password-card h2 {
            font-size: 1rem;
        }

        .password-grid {
            display: grid;
            margin-top: 17px;
            grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
            gap: 11px;
            align-items: end;
        }

        .empty {
            padding: 40px 24px;
            border: 1px dashed var(--border);
            border-radius: 18px;
            color: var(--muted);
            text-align: center;
        }

        @media (max-width: 760px) {
            body {
                padding: 18px;
            }

            .topbar,
            .message-header,
            .message-footer {
                align-items: stretch;
                flex-direction: column;
            }

            .stats,
            .password-grid {
                grid-template-columns: 1fr;
            }

            .message-actions {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php if (!$isLoggedIn): ?>
        <main class="login-card">
            <p class="label">PORTFOLIO ADMIN</p>
            <h1>Messages</h1>

            <p class="description">
                Sign in to view the messages submitted through your portfolio.
            </p>

            <?php if ($error !== ''): ?>
                <div class="notice error"><?= escape($error) ?></div>
            <?php endif; ?>

            <form method="post" class="form">
                <input type="hidden" name="action" value="login">

                <label>
                    USERNAME
                    <input
                        type="text"
                        name="username"
                        autocomplete="username"
                        required>
                </label>

                <label>
                    PASSWORD
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required>
                </label>

                <button class="button primary-button" type="submit">
                    SIGN IN
                </button>

                <a class="button secondary-button" href="index.php">
                    BACK TO PORTFOLIO
                </a>
            </form>
        </main>
    <?php else: ?>
        <main class="container">
            <header class="topbar">
                <div>
                    <p class="label">PORTFOLIO ADMIN</p>
                    <h1>Messages</h1>
                </div>

                <div class="topbar-actions">
                    <a class="button secondary-button" href="index.php">
                        VIEW PORTFOLIO
                    </a>
                    <a class="button danger-button" href="?logout=1">
                        LOG OUT
                    </a>
                </div>
            </header>

            <?php if ($error !== ''): ?>
                <div class="notice error"><?= escape($error) ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="notice success"><?= escape($success) ?></div>
            <?php endif; ?>

            <section class="stats">
                <article class="stat">
                    <span>TOTAL MESSAGES</span>
                    <strong><?= $totalMessages ?></strong>
                </article>

                <article class="stat">
                    <span>UNREAD MESSAGES</span>
                    <strong><?= $unreadMessages ?></strong>
                </article>
            </section>

            <section class="message-list">
                <?php if (!$messages): ?>
                    <div class="empty">
                        No contact messages have been submitted yet.
                    </div>
                <?php endif; ?>

                <?php foreach ($messages as $message): ?>
                    <article class="message-card <?= strtolower($message['status']) ?>">
                        <div class="message-header">
                            <div>
                                <h2><?= escape($message['full_name']) ?></h2>
                                <a href="mailto:<?= escape($message['email']) ?>">
                                    <?= escape($message['email']) ?>
                                </a>
                            </div>

                            <span class="status">
                                <?= escape(strtoupper($message['status'])) ?>
                            </span>
                        </div>

                        <p class="subject">
                            <?= escape($message['subject']) ?>
                        </p>

                        <div class="message-text"><?= escape($message['message']) ?></div>

                        <footer class="message-footer">
                            <span class="date">
                                <?= escape(date(
                                    'M d, Y - h:i A',
                                    strtotime($message['created_at'])
                                )) ?>
                            </span>

                            <div class="message-actions">
                                <form method="post">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escape($csrfToken) ?>">
                                    <input
                                        type="hidden"
                                        name="message_id"
                                        value="<?= (int) $message['id'] ?>">

                                    <?php if ($message['status'] === 'Unread'): ?>
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="mark_read">
                                        <button
                                            class="button secondary-button"
                                            type="submit">
                                            MARK READ
                                        </button>
                                    <?php else: ?>
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="mark_unread">
                                        <button
                                            class="button secondary-button"
                                            type="submit">
                                            MARK UNREAD
                                        </button>
                                    <?php endif; ?>
                                </form>

                                <form
                                    method="post"
                                    onsubmit="return confirm('Delete this message?');">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escape($csrfToken) ?>">
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete">
                                    <input
                                        type="hidden"
                                        name="message_id"
                                        value="<?= (int) $message['id'] ?>">

                                    <button
                                        class="button danger-button"
                                        type="submit">
                                        DELETE
                                    </button>
                                </form>
                            </div>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="password-card">
                <h2>Change Admin Password</h2>

                <form method="post" class="password-grid">
                    <input type="hidden" name="action" value="change_password">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape($csrfToken) ?>">

                    <label>
                        CURRENT PASSWORD
                        <input
                            type="password"
                            name="current_password"
                            required>
                    </label>

                    <label>
                        NEW PASSWORD
                        <input
                            type="password"
                            name="new_password"
                            minlength="8"
                            required>
                    </label>

                    <label>
                        CONFIRM PASSWORD
                        <input
                            type="password"
                            name="confirm_password"
                            minlength="8"
                            required>
                    </label>

                    <button class="button primary-button" type="submit">
                        UPDATE
                    </button>
                </form>
            </section>
        </main>
    <?php endif; ?>
</body>
</html>
