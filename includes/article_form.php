<form action="<?php echo e($formAction); ?>" method="POST">
    <?php echo csrfField(); ?>
    <div class="form-grid">
        <div class="form-group col-span-2">
            <label class="form-label">Article title <span class="req">*</span></label>
            <input type="text" name="title" class="form-control" required maxlength="255" placeholder="Enter article title..." value="<?php echo e($form['title']); ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Slug</label>
            <input type="text" name="slug" class="form-control" maxlength="200" value="<?php echo e($form['slug']); ?>" placeholder="Leave empty to auto-generate">
        </div>

        <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select">
                <option value="">No category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo e($cat['category_id']); ?>" <?php echo (string)$form['category_id'] === (string)$cat['category_id'] ? 'selected' : ''; ?>>
                        <?php echo e($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group col-span-2">
            <label class="form-label">Thumbnail image path / URL</label>
            <input type="text" name="thumbnail_url" class="form-control" maxlength="255" value="<?php echo e($form['thumbnail_url']); ?>" placeholder="https://... or uploads/articles/file.jpg">
        </div>

        <div class="form-group col-span-2">
            <label class="form-label">Short Summary</label>
            <textarea name="summary" class="form-control" rows="2" maxlength="1000" placeholder="Brief overview of the article..."><?php echo e($form['summary']); ?></textarea>
        </div>

        <div class="form-group col-span-4">
            <label class="form-label">Detailed Content <span class="req">*</span></label>
            <textarea name="content" class="form-control" rows="10" required placeholder="Write the full medical article content here..."><?php echo e($form['content']); ?></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" name="<?php echo e($submitName); ?>" class="btn-mc btn-mc-teal"><?php echo e($submitLabel); ?></button>
        <?php if (!empty($cancelUrl)): ?>
            <a href="<?php echo e($cancelUrl); ?>" class="btn-mc btn-mc-outline-gray">Cancel</a>
        <?php endif; ?>
    </div>
</form>