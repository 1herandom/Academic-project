<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('Teacher');

$user = current_user();
$pdo = db();
$quiz_id = (int)($_GET['id'] ?? 0);

$quizStmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND teacher_user_id = ?");
$quizStmt->execute([$quiz_id, $user['id']]);
$quiz = $quizStmt->fetch();

if (!$quiz) {
    flash_set('error', 'Quiz not found.');
    redirect('/teacher/quizzes.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download_template'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="quiz_questions_template.json"');
    echo json_encode([
        [
            'question_text' => 'What is the capital of France?',
            'options' => ['Paris', 'Berlin', 'Rome', 'Madrid'],
            'correct_option' => 0
        ],
        [
            'question_text' => 'Which number is even?',
            'options' => ['3', '5', '4', '7'],
            'correct_option' => 2
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$editQuestionId = (int)($_GET['edit_question_id'] ?? 0);
$editQuestion = null;
$editOptions = [];
if ($editQuestionId > 0) {
    $editStmt = $pdo->prepare("SELECT * FROM questions WHERE id = ? AND quiz_id = ? LIMIT 1");
    $editStmt->execute([$editQuestionId, $quiz_id]);
    $editQuestion = $editStmt->fetch();
    if ($editQuestion) {
        $optStmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC");
        $optStmt->execute([$editQuestionId]);
        $editOptions = $optStmt->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_questions'])) {
        $contents = '';

        if (!empty($_POST['question_json'])) {
            $contents = trim($_POST['question_json']);
        } elseif (isset($_FILES['question_file']) && $_FILES['question_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['question_file']['tmp_name'];
            $contents = file_get_contents($uploadedFile);
        }

        if ($contents === '') {
            flash_set('error', 'Please select a valid JSON file to upload or load questions via the preview first.');
            redirect('/teacher/quiz_questions.php?id=' . $quiz_id);
        }

        $contents = trim(preg_replace('/\x{FEFF}/u', '', $contents));
        $questionsData = json_decode($contents, true);
        if ($questionsData === null && json_last_error() !== JSON_ERROR_NONE) {
            flash_set('error', 'JSON parse error: ' . json_last_error_msg() . '. Check your file format and try again.');
            redirect('/teacher/quiz_questions.php?id=' . $quiz_id);
        }

        if (isset($questionsData['questions']) && is_array($questionsData['questions'])) {
            $questionsData = $questionsData['questions'];
        }

        if (!is_array($questionsData)) {
            flash_set('error', 'Uploaded file must contain a JSON array of questions.');
            redirect('/teacher/quiz_questions.php?id=' . $quiz_id);
        }

        $inserted = 0;
        try {
            $pdo->beginTransaction();
            $questionStmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text) VALUES (?, ?)");
            $optionStmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");

            foreach ($questionsData as $item) {
                if (!is_array($item)
                    || empty(trim($item['question_text'] ?? ''))
                    || !is_array($item['options'])
                    || count($item['options']) < 2
                    || !isset($item['correct_option'])
                    || !is_int($item['correct_option'])
                    || $item['correct_option'] < 0
                    || $item['correct_option'] >= count($item['options'])) {
                    continue;
                }

                $questionStmt->execute([$quiz_id, trim($item['question_text'])]);
                $questionId = $pdo->lastInsertId();

                foreach ($item['options'] as $index => $optionText) {
                    if (trim($optionText) === '') {
                        continue;
                    }
                    $optionStmt->execute([$questionId, trim($optionText), $index === $item['correct_option'] ? 1 : 0]);
                }
                $inserted++;
            }

            if ($inserted === 0) {
                throw new Exception('No valid questions found in uploaded file.');
            }

            $pdo->commit();
            flash_set('success', "Uploaded $inserted questions successfully.");
        } catch (Exception $e) {
            $pdo->rollBack();
            flash_set('error', 'Failed to upload questions: ' . $e->getMessage());
        }
        redirect('/teacher/quiz_questions.php?id=' . $quiz_id);
    }

    $question_text = trim($_POST['question_text'] ?? '');
    $options = $_POST['options'] ?? [];
    $correct_index = (int)($_POST['correct_option'] ?? -1);
    $edit_question_id = (int)($_POST['edit_question_id'] ?? 0);

    if ($edit_question_id > 0) {
        if ($question_text === '' || count($options) < 2 || $correct_index < 0) {
            flash_set('error', 'Please provide a question, at least 2 options, and select the correct answer.');
            redirect('/teacher/quiz_questions.php?id=' . $quiz_id . '&edit_question_id=' . $edit_question_id);
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE questions SET question_text = ? WHERE id = ? AND quiz_id = ?");
            $stmt->execute([$question_text, $edit_question_id, $quiz_id]);

            $deleteStmt = $pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
            $deleteStmt->execute([$edit_question_id]);

            $optStmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
            foreach ($options as $index => $optText) {
                if (trim($optText) !== '') {
                    $optStmt->execute([$edit_question_id, trim($optText), $index === $correct_index ? 1 : 0]);
                }
            }
            $pdo->commit();
            flash_set('success', 'Question updated.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash_set('error', 'Error updating question.');
        }
    } elseif ($question_text !== '' && count($options) >= 2 && $correct_index >= 0) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text) VALUES (?, ?)");
            $stmt->execute([$quiz_id, $question_text]);
            $question_id = $pdo->lastInsertId();

            $optStmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
            foreach ($options as $index => $optText) {
                if (trim($optText) !== '') {
                    $optStmt->execute([$question_id, trim($optText), $index === $correct_index ? 1 : 0]);
                }
            }
            $pdo->commit();
            flash_set('success', 'Question added.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash_set('error', 'Error adding question.');
        }
    } else {
        flash_set('error', 'Please provide a question, at least 2 options, and select the correct answer.');
    }
    redirect('/teacher/quiz_questions.php?id=' . $quiz_id);
}

// Fetch questions
$questionsStmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$questionsStmt->execute([$quiz_id]);
$questions = $questionsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; gap: 16px; flex-wrap: wrap;">
    <div style="min-width: 240px;">
        <h1 class="page-title">Manage Questions</h1>
        <p class="muted page-subtitle">Quiz: <?= esc($quiz['title']) ?></p>
        <p class="muted" style="margin-top:8px;">Use the upload section to add many questions at once from a JSON file.</p>
    </div>
    <div style="display:flex; gap: 8px; flex-wrap: wrap; align-items:center;">
        <a href="<?= APP_BASE_URL ?>/teacher/quizzes.php" class="btn secondary">Back to Quizzes</a>
        <a href="#upload-panel" class="btn secondary">Jump to Upload</a>
    </div>
</div>

<div class="grid-2">
    <div>
        <?php foreach ($questions as $i => $q): ?>
            <?php
            $optStmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC");
            $optStmt->execute([$q['id']]);
            $options = $optStmt->fetchAll();
            ?>
            <div class="panel" style="margin-bottom: 1rem;">
                <p><strong>Q<?= $i+1 ?>: <?= nl2br(esc($q['question_text'])) ?></strong></p>
                <ul style="list-style: none; padding: 0; margin-top: 10px;">
                    <?php foreach ($options as $opt): ?>
                        <li style="padding: 5px 0; <?= $opt['is_correct'] ? 'color: var(--herald-green); font-weight: bold;' : 'color: var(--text-muted);' ?>">
                            <?= $opt['is_correct'] ? '✓' : '○' ?> <?= esc($opt['option_text']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="<?= APP_BASE_URL ?>/teacher/quiz_questions.php?id=<?= $quiz_id ?>&edit_question_id=<?= $q['id'] ?>#edit-panel" class="btn sm secondary">Edit</a>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($questions)): ?>
            <div class="panel text-center">
                <p class="muted">No questions added yet.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div>
        <div class="panel" id="upload-panel">
            <h3 class="panel-title">Upload Questions from JSON</h3>
            <p class="muted" style="margin-bottom:1rem;">Upload a text or JSON file with question objects. Use the template to format the file correctly.</p>
            <form id="upload-questions-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="upload_questions" value="1">
                <input type="hidden" name="question_json" id="question-json" value="">
                <div class="form-group">
                    <label>Question File (.json or .txt)</label>
                    <input id="question-file-input" type="file" name="question_file" accept=".json,.txt" class="input">
                </div>
                <button type="submit" class="btn full mt-2">Upload Questions</button>
            </form>
            <div id="upload-preview" class="panel" style="margin-top: 1rem; display: none;">
                <h4 style="margin-top:0;">Preview Questions</h4>
                <p id="preview-summary" class="muted" style="margin-bottom: 1rem;"></p>
                <div id="preview-list"></div>
            </div>
            <form method="get" style="margin-top: 1rem;">
                <input type="hidden" name="id" value="<?= $quiz_id ?>">
                <input type="hidden" name="download_template" value="1">
                <button type="submit" class="btn secondary full">Download JSON Template</button>
            </form>
            <div class="mt-3" style="font-size:0.95rem;color:#555;">
                The JSON file should include an array of questions, each with <strong>question_text</strong>, <strong>options</strong>, and <strong>correct_option</strong> fields.
                You can upload either a plain array or an object with a <strong>questions</strong> array.
            </div>
        </div>

        <div class="panel" id="edit-panel" style="margin-top: 1rem;">
            <h3 class="panel-title"><?= $editQuestion ? 'Edit Question' : 'Add New Question' ?></h3>
            <form method="post">
                <?php if ($editQuestion): ?>
                    <input type="hidden" name="edit_question_id" value="<?= (int)$editQuestion['id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>Question Text</label>
                    <textarea name="question_text" class="input" rows="3" required><?= esc($editQuestion['question_text'] ?? '') ?></textarea>
                </div>
                
                <label>Options (Select correct one)</label>
                <?php
                    $editCorrectIndex = 0;
                    $editOptionTexts = array_fill(0, 4, '');
                    if ($editQuestion) {
                        foreach ($editOptions as $index => $opt) {
                            $editOptionTexts[$index] = $opt['option_text'];
                            if ($opt['is_correct']) {
                                $editCorrectIndex = $index;
                            }
                        }
                    }
                ?>
                <?php for($i=0; $i<4; $i++): ?>
                    <div style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                        <input type="radio" name="correct_option" value="<?= $i ?>" <?= $i === $editCorrectIndex ? 'checked' : '' ?> <?= $i===0?'required':'' ?>>
                        <input type="text" name="options[]" class="input" placeholder="Option <?= $i+1 ?>" value="<?= esc($editOptionTexts[$i]) ?>" <?= $i<2?'required':'' ?> />
                    </div>
                <?php endfor; ?>
                
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <button type="submit" class="btn full mt-4"><?= $editQuestion ? 'Update Question' : 'Save Question' ?></button>
                    <?php if ($editQuestion): ?>
                        <a href="<?= APP_BASE_URL ?>/teacher/quiz_questions.php?id=<?= $quiz_id ?>" class="btn secondary mt-4">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
    const fileInput = document.getElementById('question-file-input');
    const previewArea = document.getElementById('upload-preview');
    const previewList = document.getElementById('preview-list');
    const previewSummary = document.getElementById('preview-summary');
    const questionJsonInput = document.getElementById('question-json');
    const uploadForm = document.getElementById('upload-questions-form');
    let questions = [];

    function setError(message) {
        previewArea.style.display = 'block';
        previewSummary.textContent = message;
        previewSummary.style.color = 'var(--herald-red)';
        previewList.innerHTML = '';
        questionJsonInput.value = '';
        questions = [];
    }

    function renderPreview() {
        previewList.innerHTML = '';
        if (!questions.length) {
            previewArea.style.display = 'none';
            questionJsonInput.value = '';
            previewSummary.textContent = '';
            previewSummary.style.color = '';
            return;
        }
        previewArea.style.display = 'block';
        previewSummary.style.color = '';
        previewSummary.textContent = `Previewing ${questions.length} question${questions.length === 1 ? '' : 's'}. Remove any question before uploading.`;
        questionJsonInput.value = JSON.stringify(questions);

        questions.forEach((item, index) => {
            const card = document.createElement('div');
            card.className = 'panel';
            card.style.marginBottom = '10px';
            card.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                    <div style="flex:1;">
                        <strong>Q${index+1}:</strong> ${item.question_text}
                        <ul style="margin:8px 0 0 18px; padding:0;">
                            ${item.options.map((opt, optIndex) => `<li style="margin-bottom:4px;${optIndex === item.correct_option ? 'font-weight:bold;color:#1b6bff;' : ''}">${optIndex === item.correct_option ? '✓ ' : ''}${opt}</li>`).join('')}
                        </ul>
                    </div>
                    <button type="button" class="btn secondary" data-remove-index="${index}" style="height:34px;align-self:flex-start;">Remove</button>
                </div>
            `;
            previewList.appendChild(card);
        });
    }

    fileInput.addEventListener('change', function() {
        const file = fileInput.files[0];
        if (!file) {
            questions = [];
            renderPreview();
            return;
        }

        const reader = new FileReader();
        reader.onload = function(event) {
            let text = event.target.result;
            text = text.replace(/^\uFEFF/, '').trim();
            if (!text) {
                setError('Selected file is empty.');
                return;
            }

            try {
                const raw = JSON.parse(text);
                questions = Array.isArray(raw) ? raw : (Array.isArray(raw.questions) ? raw.questions : []);
            } catch (err) {
                setError('Invalid JSON: ' + err.message);
                return;
            }

            if (!Array.isArray(questions) || questions.length === 0) {
                setError('Uploaded file must contain a JSON array of questions.');
                return;
            }

            questions = questions.filter(item => {
                return item && typeof item === 'object'
                    && typeof item.question_text === 'string'
                    && Array.isArray(item.options)
                    && item.options.length >= 2
                    && Number.isInteger(item.correct_option)
                    && item.correct_option >= 0
                    && item.correct_option < item.options.length;
            });

            if (!questions.length) {
                setError('No valid question objects found in the file.');
                return;
            }

            renderPreview();
        };
        reader.readAsText(file);
    });

    previewList.addEventListener('click', function(event) {
        const button = event.target.closest('button[data-remove-index]');
        if (!button) return;
        const index = Number(button.getAttribute('data-remove-index'));
        if (!Number.isNaN(index) && index >= 0 && index < questions.length) {
            questions.splice(index, 1);
            renderPreview();
        }
    });

    uploadForm.addEventListener('submit', function(event) {
        if (!questionJsonInput.value) {
            if (!fileInput.files.length) {
                event.preventDefault();
                setError('Please select a valid JSON file to upload.');
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
