<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    header('Location: login.php');
    exit;
}

global $db_prefix; // Declare global here

require_once __DIR__ . '/db.php'; // Adjust according to the actual path

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;

    // Operations for confirmation_codes
    if ($action === 'create' || $action === 'update') {
        $table = $_POST['table'] ?? '';
        $content = $_POST['content'] ?? '';
        $status = $_POST['status'] ?? '';

        if ($table === 'confirmation_codes') {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO {$db_prefix}confirmation_codes (content, status) VALUES (?, ?)");
                $stmt->execute([$content, $status]);
            } elseif ($action === 'update' && $id) {
                $stmt = $pdo->prepare("UPDATE {$db_prefix}confirmation_codes SET content = ?, status = ? WHERE id = ?");
                $stmt->execute([$content, $status, $id]);
            }
        }
    } else if ($action === 'delete' && $id) {
        $table = $_POST['table'] ?? '';
        if ($table === 'confirmation_codes') {
            $stmt = $pdo->prepare("DELETE FROM {$db_prefix}confirmation_codes WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($table === 'chatbot') {
            $stmt = $pdo->prepare("DELETE FROM {$db_prefix}chatbot WHERE id = ?");
            $stmt->execute([$id]);
        }
    } elseif ($action === 'copy' && $id) {
        // Copy Confirmation Code without ID
        $stmt = $pdo->prepare("SELECT * FROM {$db_prefix}confirmation_codes WHERE id = ?");
        $stmt->execute([$id]);
        $code = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($code) {
            // Check for duplicates before copying
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM {$db_prefix}confirmation_codes WHERE content = ?");
            $stmt_check->execute([$code['content']]);
            $count = $stmt_check->fetchColumn();

            if ($count > 0) {
                // Add "_copy" suffix to content to avoid duplicates
                $new_content = $code['content'] . '_copy';
                $stmt_copy = $pdo->prepare("INSERT INTO {$db_prefix}confirmation_codes (content, status) VALUES (?, ?)");
                $stmt_copy->execute([$new_content, $code['status']]);
            } else {
                // If no duplicate, copy normally
                $stmt_copy = $pdo->prepare("INSERT INTO {$db_prefix}confirmation_codes (content, status) VALUES (?, ?)");
                $stmt_copy->execute([$code['content'], $code['status']]);
            }
        }
    }

    // Operations for chatbot (Manage Questions)
    if ($action === 'create' || $action === 'update') {
        $table = $_POST['table'] ?? '';
        $question = $_POST['question'] ?? '';
        $reply = $_POST['reply'] ?? '';

        if ($table === 'chatbot') {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO {$db_prefix}chatbot (queries, replies) VALUES (?, ?)");
                $stmt->execute([$question, $reply]);
            } elseif ($action === 'update' && $id) {
                $stmt = $pdo->prepare("UPDATE {$db_prefix}chatbot SET queries = ?, replies = ? WHERE id = ?");
                $stmt->execute([$question, $reply, $id]);
            }
        }
    } else if ($action === 'delete' && $id) {
        $table = $_POST['table'] ?? '';
        if ($table === 'chatbot') {
            $stmt = $pdo->prepare("DELETE FROM {$db_prefix}chatbot WHERE id = ?");
            $stmt->execute([$id]);
        }
    } elseif ($action === 'copy' && $id) {
        // Copy Question without ID
        $stmt = $pdo->prepare("SELECT * FROM {$db_prefix}chatbot WHERE id = ?");
        $stmt->execute([$id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($question) {
            // Check for duplicates before copying
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM {$db_prefix}chatbot WHERE queries = ?");
            $stmt_check->execute([$question['queries']]);
            $count = $stmt_check->fetchColumn();

            if ($count > 0) {
                // Add "_copy" suffix to question to avoid duplicates
                $new_question = $question['queries'] . '_copy';
                $stmt_copy = $pdo->prepare("INSERT INTO {$db_prefix}chatbot (queries, replies) VALUES (?, ?)");
                $stmt_copy->execute([$new_question, $question['replies']]);
            } else {
                // If no duplicate, copy normally
                $stmt_copy = $pdo->prepare("INSERT INTO {$db_prefix}chatbot (queries, replies) VALUES (?, ?)");
                $stmt_copy->execute([$question['queries'], $question['replies']]);
            }
        }
    }
}

// Query data for manage confirmation_codes
$search_codes = $_GET['search_codes'] ?? '';
$query2 = "SELECT * FROM {$db_prefix}confirmation_codes WHERE content LIKE ? OR status LIKE ?";
$stmt2 = $pdo->prepare($query2);
$stmt2->execute(["%$search_codes%", "%$search_codes%"]);
$codes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Query data for manage chatbot (Manage Questions)
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM {$db_prefix}chatbot WHERE queries LIKE ? OR replies LIKE ?";
$stmt = $pdo->prepare($query);
$stmt->execute(["%$search%", "%$search%"]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="css/admin-style.css">
    <script src="https://kit.fontawesome.com/f3a4cc31b5.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="admin-container">
        <h2>Admin Panel</h2>

        <!-- Tab Section -->
        <div class="tab-section">
            <button class="tab-button" data-tab="question-tab">Manage Questions</button>
            <button class="tab-button" data-tab="confirmation-tab">Manage Confirmation Codes</button>
        </div>

        <!-- Tab Manage Questions -->
        <div id="question-tab" class="tab-content" style="display: block;">
            <h3>Manage Questions</h3>

            <!-- Search Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search questions and replies" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>

            <!-- Create New Form -->
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="table" value="chatbot">
                <input type="text" name="question" placeholder="Question" required>
                <input type="text" name="reply" placeholder="Reply" required>
                <button type="submit"><i class="fas fa-plus"></i> Create</button>
            </form>

            <!-- Data Table -->
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Question</th>
                        <th>Reply</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($question['id']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="table" value="chatbot">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($question['id']); ?>">
                                    <input type="text" name="question" value="<?php echo htmlspecialchars($question['queries']); ?>" required>
                            </td>
                            <td>
                                    <input type="text" name="reply" value="<?php echo htmlspecialchars($question['replies']); ?>" required>
                            </td>
                            <td>
                                    <button type="submit"><i class="fas fa-check"></i> Save</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="table" value="chatbot">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($question['id']); ?>">
                                    <button type="submit" class="delete-button"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="copy">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($question['id']); ?>">
                                    <button type="submit" class="copy-button"><i class="fas fa-copy"></i> Copy</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Tab Manage Confirmation Codes -->
        <div id="confirmation-tab" class="tab-content" style="display: none;">
            <h3>Manage Confirmation Codes</h3>

            <!-- Search Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search_codes" placeholder="Search confirmation codes" value="<?php echo htmlspecialchars($search_codes); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>

            <!-- Create New Form -->
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="table" value="confirmation_codes">
                <input type="text" name="content" placeholder="Content" required>
                <select name="status" required>
                    <option value="active">Active</option>
                    <option value="multi-direction warning">Multi-Direction Warning</option>
                    <option value="disabled">Disabled</option>
                </select>
                <button type="submit"><i class="fas fa-plus"></i> Create</button>
            </form>

            <!-- Data Table -->
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Content</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $code): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($code['id']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="table" value="confirmation_codes">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($code['id']); ?>">
                                    <input type="text" name="content" value="<?php echo htmlspecialchars($code['content']); ?>" required>
                            </td>
                            <td>
                                    <select name="status" required>
                                        <option value="active" <?php echo $code['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="multi-direction warning" <?php echo $code['status'] == 'multi-direction warning' ? 'selected' : ''; ?>>Multi-Direction Warning</option>
                                        <option value="disabled" <?php echo $code['status'] == 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                            </td>
                            <td>
                                    <button type="submit"><i class="fas fa-check"></i> Save</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="table" value="confirmation_codes">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($code['id']); ?>">
                                    <button type="submit" class="delete-button"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="copy">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($code['id']); ?>">
                                    <button type="submit" class="copy-button"><i class="fas fa-copy"></i> Copy</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Store tab state in localStorage
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                localStorage.setItem('activeTab', button.dataset.tab);
                document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
                document.getElementById(button.dataset.tab).style.display = 'block';
            });
        });

        // Restore tab state on page load
        window.addEventListener('load', () => {
            const activeTab = localStorage.getItem('activeTab');
            if (activeTab) {
                document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
                document.getElementById(activeTab).style.display = 'block';
            }
        });

        // Confirmation before delete
        document.querySelectorAll('.delete-button').forEach(button => {
            button.addEventListener('click', (event) => {
                if (!confirm('Are you sure you want to delete this item?')) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>