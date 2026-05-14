<label style="width:160px; margin-bottom:0;">
    <span class="small">Records per page</span>

    <select class="input" name="per_page">
        <?php foreach ($perPageOptions as $option): ?>
            <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>>
                <?= $option ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>