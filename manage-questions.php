<?php
require_once 'config.php';
requireStaffAccess(); // Ensure only staff can manage questions

$success = false;
$error = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $question_text = sanitize($_POST['question_text']);
        $order = $db->fetchOne("SELECT COALESCE(MAX(question_order), 0) + 1 as next_order FROM inspection_questions")['next_order'];
        
        if ($db->execute("INSERT INTO inspection_questions (question_text, question_order) VALUES (?, ?)", [$question_text, $order])) {
            $success = true;
        } else {
            $error = 'Failed to add question.';
        }
    } elseif ($action === 'delete') {
        $question_id = intval($_POST['question_id']);
        if ($db->execute("UPDATE inspection_questions SET is_active = FALSE WHERE id = ?", [$question_id])) {
            $success = true;
        }
    } elseif ($action === 'reorder') {
        $order_data = json_decode($_POST['order_data'], true);
        foreach ($order_data as $item) {
            $db->execute("UPDATE inspection_questions SET question_order = ? WHERE id = ?", 
                        [$item['order'], $item['id']]);
        }
        $success = true;
    }
}

$questions = $db->fetchAll("SELECT * FROM inspection_questions WHERE is_active = TRUE ORDER BY question_order");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inspection Questions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .question-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
        }
        .question-item.dragging {
            opacity: 0.5;
        }
        .drag-handle {
            color: #6c757d;
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-hospital me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container py-4">
        <h2 class="mb-4">
            <i class="fas fa-list-check text-primary me-2"></i> Manage Inspection Questions
        </h2>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Operation completed successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add New Question -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i> Add New Question</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="question_text" 
                                   placeholder="Enter question text..." required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-1"></i> Add Question
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Current Questions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i> Current Questions
                    <small class="text-muted ms-2">(Drag to reorder)</small>
                </h5>
            </div>
            <div class="card-body">
                <div id="questionsList">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-item" draggable="true" data-id="<?php echo $question['id']; ?>">
                            <div>
                                <i class="fas fa-grip-vertical drag-handle"></i>
                                <span class="fw-bold me-2"><?php echo $index + 1; ?>.</span>
                                <?php echo htmlspecialchars($question['question_text']); ?>
                            </div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" 
                                        onclick="return confirm('Are you sure you want to delete this question?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Drag and drop functionality
        let draggedElement = null;

        document.querySelectorAll('.question-item').forEach(item => {
            item.addEventListener('dragstart', function(e) {
                draggedElement = this;
                this.classList.add('dragging');
            });

            item.addEventListener('dragend', function(e) {
                this.classList.remove('dragging');
            });

            item.addEventListener('dragover', function(e) {
                e.preventDefault();
                const afterElement = getDragAfterElement(document.getElementById('questionsList'), e.clientY);
                if (afterElement == null) {
                    document.getElementById('questionsList').appendChild(draggedElement);
                } else {
                    document.getElementById('questionsList').insertBefore(draggedElement, afterElement);
                }
            });
        });

        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.question-item:not(.dragging)')];

            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        // Save order when dragging ends
        document.getElementById('questionsList').addEventListener('drop', function(e) {
            e.preventDefault();
            const items = document.querySelectorAll('.question-item');
            const orderData = [];
            items.forEach((item, index) => {
                orderData.push({
                    id: item.dataset.id,
                    order: index + 1
                });
            });

            // Send reorder request
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="reorder">
                <input type="hidden" name="order_data" value='${JSON.stringify(orderData)}'>
            `;
            document.body.appendChild(form);
            form.submit();
        });
    </script>
</body>
</html>