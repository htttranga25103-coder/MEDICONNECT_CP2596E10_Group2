<?php
?>
<form action = "<?php echo e($formAction); ?>" methond = "POST">
    <?php echo csrfField(); ?>
        <div class="form-group-custom">
            <label>Article title:</label>
            <input type = "text" name = "title" required maxlength = "255" value="<?php echo e($form['title']); ?>">
        </div>
        <div class="form-group-custom">
            <lable>Slug:</lable>
            <input type = "text" name = "slug" maxlength = "200" value = 
            "<?php echo e($form['slug']); ?>" placeholder = "Leave empty to create it from the title">
        </div>
        <div class="form-group-custom">
            <lable>Category Articles: </label>
            <select name = "category_id">
                <option value ="">No category</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?php echo e($cat['category_id']); ?>" <?php echo (string) $form['category_id'] === (string) $cat['category_id'] ? 'selected' : ''; ?>>
                        <?php echo e($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        </div>
         <div class="form-group-custom">
            <label>Short Summary:</label>
            <textarea name="summary" rows="3" maxlength="1000"><?php echo e($form['summary']); ?></textarea>
        </div>
        <div class="form-group-custom">
            <label>Detailed Content:</label>
            <textarea name="content" rows="10" required><?php echo e($form['content']); ?></textarea>
        </div>
        <div class="form-group-custom">
            <label>Thumbnail image path:</label>
            <input type="text" name="thumbnail_url" maxlength="255" value="<?php echo e($form['thumbnail_url']); ?>" placeholder="https://... or assets/images/file.jpg">
        </div>
        <button type="submit" name="<?php echo e($submitName); ?>" class="btn-submit"><?php echo e($submitLabel); ?></button>
             <?php if (!empty($cancelUrl)): ?>
        <a href="<?php echo e($cancelUrl); ?>" style="margin-left: 12px; color: #666;">Cancel</a>
        <?php endif; ?>
     </form>