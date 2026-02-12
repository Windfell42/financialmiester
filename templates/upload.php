<?php if (!empty($error)): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="index.php" enctype="multipart/form-data" id="upload-form">
    <div class="upload-section">
        <div class="upload-card" id="drop-income">
            <div class="icon">&#128200;</div>
            <h3>Income Statement</h3>
            <p>Upload a PDF of the income statement (P&amp;L). May contain multiple pages.</p>
            <label class="btn" for="income_file">Choose PDF</label>
            <input type="file" name="income_file" id="income_file" accept=".pdf">
            <div class="file-name" id="income-name"></div>
        </div>
        <div class="upload-card" id="drop-balance">
            <div class="icon">&#128202;</div>
            <h3>Balance Sheet / Asset List</h3>
            <p>Upload a PDF of the balance sheet or asset list. May contain multiple pages.</p>
            <label class="btn" for="balance_file">Choose PDF</label>
            <input type="file" name="balance_file" id="balance_file" accept=".pdf">
            <div class="file-name" id="balance-name"></div>
        </div>
    </div>
    <div class="submit-row">
        <button type="submit" class="analyze-btn" id="analyze-btn" disabled>Analyze Financial Data</button>
    </div>
</form>

<script>
(function() {
    const incomeInput  = document.getElementById('income_file');
    const balanceInput = document.getElementById('balance_file');
    const incomeName   = document.getElementById('income-name');
    const balanceName  = document.getElementById('balance-name');
    const btn          = document.getElementById('analyze-btn');

    function updateBtn() {
        btn.disabled = !(incomeInput.files.length && balanceInput.files.length);
    }

    incomeInput.addEventListener('change', function() {
        incomeName.textContent = this.files[0] ? this.files[0].name : '';
        updateBtn();
    });

    balanceInput.addEventListener('change', function() {
        balanceName.textContent = this.files[0] ? this.files[0].name : '';
        updateBtn();
    });

    // Drag-and-drop support
    function setupDrop(dropZone, input, nameDisplay) {
        ['dragenter', 'dragover'].forEach(evt => {
            dropZone.addEventListener(evt, function(e) {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropZone.addEventListener(evt, function() {
                dropZone.classList.remove('dragover');
            });
        });
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                nameDisplay.textContent = e.dataTransfer.files[0].name;
                updateBtn();
            }
        });
    }

    setupDrop(document.getElementById('drop-income'), incomeInput, incomeName);
    setupDrop(document.getElementById('drop-balance'), balanceInput, balanceName);
})();
</script>
